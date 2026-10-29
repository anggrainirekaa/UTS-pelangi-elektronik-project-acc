<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Transfer stok antar gudang.
 *  - Admin, atau staff dengan jumlah di bawah ambang (config stockflow.transfer_approval_threshold): langsung selesai.
 *  - Staff dengan jumlah >= ambang: berstatus pending sampai admin menyetujui. Stok BELUM berubah saat pending.
 * Stok hanya dipindahkan satu kali: eksekusi mengunci baris transfer dan menolak bila status bukan pending.
 */
class StockTransferService
{
    public function __construct(protected StockService $stock, protected NotificationService $notifications)
    {
    }

    /**
     * @param  array{from_warehouse_id: int, to_warehouse_id: int, barang_id: int, quantity: int, note?: string|null, request_key?: string|null}  $data
     *
     * @throws BusinessRuleException|\App\Exceptions\InsufficientStockException
     */
    public function transfer(User $user, array $data): StockTransfer
    {
        return $this->request($user, $data);
    }

    public function request(User $user, array $data): StockTransfer
    {
        if ((int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']) {
            throw new BusinessRuleException('Gudang asal dan tujuan tidak boleh sama.');
        }

        $requestKey = $data['request_key'] ?? null;

        if ($requestKey && ($existing = StockTransfer::where('request_key', $requestKey)->where('user_id', $user->id)->first())) {
            return $existing;
        }

        $needsApproval = ! $user->isManagement() && (int) $data['quantity'] >= (int) config('stockflow.transfer_approval_threshold');

        try {
            $transfer = DB::transaction(function () use ($user, $data, $needsApproval, $requestKey) {
                $transfer = StockTransfer::create([
                    'transfer_number' => NumberGenerator::temporary(),
                    'from_warehouse_id' => $data['from_warehouse_id'],
                    'to_warehouse_id' => $data['to_warehouse_id'],
                    'barang_id' => $data['barang_id'],
                    'user_id' => $user->id,
                    'quantity' => $data['quantity'],
                    'status' => StockTransfer::STATUS_PENDING,
                    'note' => $data['note'] ?? null,
                    'request_key' => $requestKey,
                ]);

                NumberGenerator::finalize($transfer, 'TF', 'transfer_number');

                if (! $needsApproval) {
                    $this->execute($transfer, $user, approvedBy: $user->id);
                }

                return $transfer;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($requestKey && ($existing = StockTransfer::where('request_key', $requestKey)->where('user_id', $user->id)->first())) {
                return $existing;
            }

            throw $e;
        }

        $transfer->load(['barang', 'fromWarehouse', 'toWarehouse']);

        AuditLogger::log('transfer.requested', "Transfer {$transfer->transfer_number}: {$transfer->quantity} x {$transfer->barang->name} ({$transfer->fromWarehouse->name} → {$transfer->toWarehouse->name}) — {$transfer->statusLabel()}", $transfer);

        if ($transfer->isPending()) {
            $this->notifications->toRoles(['owner'], 'transfer_pending', 'Transfer menunggu persetujuan', "{$transfer->transfer_number}: {$transfer->quantity} {$transfer->barang->unit} {$transfer->barang->name}.", route('transfer-stok.index'), "tf-pending:{$transfer->id}", 'warning');
        } else {
            $this->notifications->toRoles(['owner'], 'transfer_done', 'Transfer stok selesai', "{$transfer->transfer_number}: {$transfer->quantity} {$transfer->barang->unit} {$transfer->barang->name} dipindahkan.", route('transfer-stok.index'), "tf-done:{$transfer->id}", 'info');
        }

        return $transfer;
    }

    public function approve(StockTransfer $transfer, User $admin): StockTransfer
    {
        $transfer = DB::transaction(function () use ($transfer, $admin) {
            $locked = StockTransfer::lockForUpdate()->findOrFail($transfer->id);

            if (! $locked->isPending()) {
                throw new BusinessRuleException('Transfer ini sudah diproses sebelumnya.');
            }

            $this->execute($locked, $admin, approvedBy: $admin->id);

            return $locked;
        });

        AuditLogger::log('transfer.approved', "Transfer {$transfer->transfer_number} disetujui dan dieksekusi", $transfer);
        $this->notifications->resolve("tf-pending:{$transfer->id}");
        $this->notifications->toUser($transfer->user, 'transfer_approved', 'Transfer disetujui', "{$transfer->transfer_number} disetujui dan stok sudah dipindahkan.", route('transfer-stok.index'), "tf-approved:{$transfer->id}", 'success');

        return $transfer;
    }

    public function reject(StockTransfer $transfer, User $admin, string $reason): StockTransfer
    {
        return $this->close($transfer, $admin, StockTransfer::STATUS_REJECTED, $reason, 'transfer.rejected', 'Transfer ditolak');
    }

    /** Pemohon (atau admin) membatalkan permintaan yang masih pending. */
    public function cancel(StockTransfer $transfer, User $actor, string $reason = 'Dibatalkan pemohon'): StockTransfer
    {
        return $this->close($transfer, $actor, StockTransfer::STATUS_CANCELLED, $reason, 'transfer.cancelled', 'Transfer dibatalkan');
    }

    private function close(StockTransfer $transfer, User $actor, string $status, string $reason, string $event, string $title): StockTransfer
    {
        $transfer = DB::transaction(function () use ($transfer, $actor, $status, $reason) {
            $locked = StockTransfer::lockForUpdate()->findOrFail($transfer->id);

            if (! $locked->isPending()) {
                throw new BusinessRuleException('Transfer ini sudah diproses sebelumnya.');
            }

            $locked->update(['status' => $status, 'approved_by' => $actor->id, 'approved_at' => now(), 'reject_reason' => $reason]);

            return $locked;
        });

        AuditLogger::log($event, "Transfer {$transfer->transfer_number}: {$reason}", $transfer);
        $this->notifications->resolve("tf-pending:{$transfer->id}");

        if ($transfer->user_id !== $actor->id) {
            $this->notifications->toUser($transfer->user, 'transfer_closed', $title, "{$transfer->transfer_number}: {$reason}", route('transfer-stok.index'), "tf-closed:{$transfer->id}", 'danger');
        }

        return $transfer;
    }

    /** Pindahkan stok (batch ikut berpindah). Dipanggil dalam transaksi dengan baris transfer terkunci. */
    private function execute(StockTransfer $transfer, User $actor, int $approvedBy): void
    {
        $allocations = $this->stock->decrease($transfer->barang_id, $transfer->from_warehouse_id, $transfer->quantity, [
            'type' => 'transfer_out', 'reference' => $transfer, 'user_id' => $actor->id, 'note' => $transfer->transfer_number,
        ]);

        $this->stock->increase($transfer->barang_id, $transfer->to_warehouse_id, $transfer->quantity, [
            'type' => 'transfer_in', 'reference' => $transfer, 'user_id' => $actor->id, 'note' => $transfer->transfer_number,
            'lots' => $this->stock->lotsFromAllocations($allocations),
        ]);

        $transfer->update([
            'status' => StockTransfer::STATUS_COMPLETED,
            'transferred_at' => now(),
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }
}
