<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockOpname;
use App\Models\StockOut;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan akuntansi double-entry sederhana. Setiap transaksi bisnis menghasilkan jurnal
 * seimbang (total debit = total kredit). Pembatalan membuat jurnal pembalik, bukan menghapus.
 */
class JournalService
{
    /** Bagan akun standar. Dibuat otomatis jika belum ada. */
    public const ACCOUNTS = [
        'cash' => ['1101', 'Kas', 'asset'],
        'receivable' => ['1102', 'Piutang Usaha', 'asset'],
        'inventory' => ['1201', 'Persediaan Barang', 'asset'],
        'payable' => ['2101', 'Utang Usaha', 'liability'],
        'equity' => ['3101', 'Modal Pemilik', 'equity'],
        'revenue' => ['4101', 'Pendapatan Penjualan', 'revenue'],
        'cogs' => ['5101', 'Harga Pokok Penjualan', 'expense'],
        'expense' => ['5201', 'Beban Operasional', 'expense'],
        'loss' => ['5301', 'Beban Kerugian Persediaan', 'expense'],
    ];

    public function ensureAccounts(): void
    {
        foreach (self::ACCOUNTS as [$code, $name, $type]) {
            Account::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type]);
        }
    }

    public function postPurchase(Purchase $purchase): void
    {
        $this->post($purchase, 'Pembelian '.$purchase->purchase_number, $purchase->purchased_at->toDateString(), $purchase->user_id, [
            ['inventory', $purchase->total, 0],
            ['cash', 0, $purchase->total],
        ]);
    }

    /** Penerimaan barang (penuh/sebagian): persediaan bertambah sebesar nilai yang diterima. */
    public function postPurchaseReceipt(Purchase $purchase, PurchaseReceipt $receipt): void
    {
        $this->post($purchase, 'Penerimaan '.$purchase->purchase_number.' ('.$receipt->receipt_number.')', $receipt->received_at->toDateString(), $receipt->user_id, [
            ['inventory', $receipt->total, 0],
            ['cash', 0, $receipt->total],
        ]);
    }

    /** Retur penjualan: pendapatan berkurang, kas/piutang berkurang, persediaan kembali dan HPP berkurang. */
    public function postSaleReturn(Sale $sale, SaleReturn $return, int $cogs): void
    {
        $lines = [
            ['revenue', $return->refund_total, 0],
            [$sale->isPaid() ? 'cash' : 'receivable', 0, $return->refund_total],
        ];

        if ($cogs > 0) {
            $lines[] = ['inventory', $cogs, 0];
            $lines[] = ['cogs', 0, $cogs];
        }

        $this->post($sale, 'Retur '.$return->return_number.' atas '.$sale->sale_number, $return->returned_at->toDateString(), $return->user_id, $lines);
    }

    /** Selisih stock opname bernilai (harga pokok): negatif = kerugian, positif = koreksi persediaan. */
    public function postOpnameAdjustment(StockOpname $opname, int $signedAmount): void
    {
        if ($signedAmount === 0) {
            return;
        }

        $amount = abs($signedAmount);

        $this->post($opname, 'Selisih stock opname '.$opname->opname_number, now()->toDateString(), $opname->reviewed_by, $signedAmount < 0
            ? [['loss', $amount, 0], ['inventory', 0, $amount]]
            : [['inventory', $amount, 0], ['loss', 0, $amount]]);
    }

    public function postSale(Sale $sale): void
    {
        $sale->loadMissing('items');
        $date = ($sale->completed_at ?? $sale->sold_at)->toDateString();
        $cogs = (int) $sale->items->sum(fn ($item) => $item->quantity * $item->unit_cost);

        $lines = [
            [$sale->isPaid() ? 'cash' : 'receivable', $sale->total, 0],
            ['revenue', 0, $sale->total],
        ];

        if ($cogs > 0) {
            $lines[] = ['cogs', $cogs, 0];
            $lines[] = ['inventory', 0, $cogs];
        }

        $this->post($sale, 'Penjualan '.$sale->sale_number, $date, $sale->user_id, $lines);
    }

    /** Pelunasan penjualan yang sudah selesai: piutang menjadi kas. */
    public function postPayment(Sale $sale): void
    {
        // Jumlah piutang tersisa = total dikurangi retur yang sudah dibukukan.
        $net = max(0, $sale->total - (int) $sale->returns()->sum('refund_total'));

        $this->post($sale, 'Pelunasan '.$sale->sale_number, now()->toDateString(), $sale->user_id, [
            ['cash', $net, 0],
            ['receivable', 0, $net],
        ]);
    }

    public function postExpense(Expense $expense): void
    {
        $this->post($expense, 'Beban: '.$expense->title, $expense->expense_date->toDateString(), $expense->user_id, [
            ['expense', $expense->amount, 0],
            ['cash', 0, $expense->amount],
        ]);
    }

    /** Barang rusak / dipakai internal dicatat sebagai kerugian persediaan sebesar harga pokok. */
    public function postStockAdjustment(StockOut $stockOut, int $unitCost): void
    {
        $amount = $stockOut->quantity * $unitCost;

        if ($amount <= 0) {
            return;
        }

        $this->post($stockOut, 'Penyesuaian stok: '.$stockOut->purpose_label, $stockOut->date->toDateString(), $stockOut->user_id, [
            ['loss', $amount, 0],
            ['inventory', 0, $amount],
        ]);
    }

    public function postOpeningCapital(int $amount, ?User $user = null): JournalEntry
    {
        return $this->post(null, 'Setoran modal awal', now()->toDateString(), $user?->id, [
            ['cash', $amount, 0],
            ['equity', 0, $amount],
        ]);
    }

    /** Balik seluruh jurnal aktif milik sebuah transaksi (dipakai saat pembatalan). */
    public function reverse(Model $reference, ?int $userId = null): void
    {
        $entries = JournalEntry::with('lines')
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->whereNull('reversed_at')
            ->get();

        foreach ($entries as $entry) {
            $reversal = $this->createEntry(
                'Pembalikan: '.$entry->description,
                now()->toDateString(),
                $reference,
                $userId,
                $entry->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit])->all()
            );

            $entry->update(['reversed_at' => now()]);
            $reversal->update(['reversed_at' => now()]); // pembalik tidak dibalik lagi
        }
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: int}>  $lines  [kunci akun, debit, kredit]
     */
    private function post(?Model $reference, string $description, string $date, ?int $userId, array $lines): JournalEntry
    {
        $this->ensureAccounts();

        $resolved = collect($lines)
            ->map(fn ($l) => [
                'account_id' => Account::where('code', self::ACCOUNTS[$l[0]][0])->value('id'),
                'debit' => $l[1],
                'credit' => $l[2],
            ])
            ->all();

        return $this->createEntry($description, $date, $reference, $userId, $resolved);
    }

    /**
     * @param  array<int, array{account_id: int, debit: int, credit: int}>  $lines
     */
    private function createEntry(string $description, string $date, ?Model $reference, ?int $userId, array $lines): JournalEntry
    {
        $debit = array_sum(array_column($lines, 'debit'));
        $credit = array_sum(array_column($lines, 'credit'));

        if ($debit !== $credit) {
            throw new \LogicException("Jurnal tidak seimbang (debit {$debit} ≠ kredit {$credit}).");
        }

        return DB::transaction(function () use ($description, $date, $reference, $userId, $lines) {
            $entry = JournalEntry::create([
                'entry_number' => NumberGenerator::temporary(),
                'entry_date' => $date,
                'description' => $description,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $userId,
            ]);

            NumberGenerator::finalize($entry, 'JE', 'entry_number');

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            return $entry;
        });
    }
}
