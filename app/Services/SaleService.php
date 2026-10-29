<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Barang;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        protected StockService $stock,
        protected JournalService $journal,
        protected WebhookDispatcher $webhooks,
        protected NotificationService $notifications,
    ) {
    }

    /**
     * Penjualan kasir oleh staff/admin: langsung selesai dan stok langsung berkurang.
     *
     * @param  array{customer_id?: int|null, warehouse_id?: int|null, payment_method?: string|null, mark_paid?: bool, note?: string|null, discount_type?: string|null, discount_value?: int|float|null, request_key?: string|null, items: array<int, array{barang_id: int, quantity: int}>}  $data
     *
     * @throws InsufficientStockException|ValidationException
     */
    public function createPos(User $user, array $data): Sale
    {
        return $this->create($user, $data, Sale::CHANNEL_POS, complete: true);
    }

    /**
     * Pesanan online oleh pelanggan: berstatus pending. Stok baru berkurang saat staff
     * memproses (complete) pesanan.
     *
     * @throws ValidationException
     */
    public function createOrder(User $customer, array $data): Sale
    {
        $data['customer_id'] = $customer->id;

        return $this->create($customer, $data, Sale::CHANNEL_ONLINE, complete: false);
    }

    private function create(User $creator, array $data, string $channel, bool $complete): Sale
    {
        $requestKey = $data['request_key'] ?? null;

        // Idempotensi: form yang terkirim dua kali (klik ganda/refresh) tidak membuat transaksi ganda.
        if ($requestKey && ($existing = $this->findByRequestKey($creator, $requestKey))) {
            return $existing;
        }

        $items = $this->mergeItems($data['items'] ?? []);

        try {
            $sale = DB::transaction(function () use ($creator, $data, $channel, $complete, $items, $requestKey) {
                // Kunci semua produk sekaligus (urut id) agar dua transaksi bersamaan tidak menjual stok yang sama.
                $products = Barang::whereIn('id', $items->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $warehouseId = $data['warehouse_id'] ?? Warehouse::default()->id;
                $sale = Sale::create([
                    'sale_number' => NumberGenerator::temporary(),
                    'user_id' => $creator->id,
                    'customer_id' => $data['customer_id'] ?? null,
                    'warehouse_id' => $warehouseId,
                    'channel' => $channel,
                    'subtotal' => 0,
                    'discount' => 0,
                    'total' => 0,
                    'status' => Sale::STATUS_PENDING,
                    'payment_method' => $data['payment_method'] ?? null,
                    'payment_status' => Sale::PAYMENT_UNPAID,
                    'sold_at' => now(),
                    'shipping_address' => $data['shipping_address'] ?? null,
                    'note' => $data['note'] ?? null,
                    'request_key' => $requestKey,
                ]);

                $subtotal = 0;

                foreach ($items as $barangId => $quantity) {
                    $product = $products->get($barangId);

                    if (! $product || ($channel === Sale::CHANNEL_ONLINE && ! $product->is_active)) {
                        throw ValidationException::withMessages(['items' => 'Salah satu produk tidak tersedia.']);
                    }

                    // Pesanan online belum mengurangi stok, tetapi tetap ditolak bila sudah tidak ada stok.
                    if (! $complete && $product->stock < $quantity) {
                        throw new InsufficientStockException($product->name, $product->stock, $quantity);
                    }

                    // Harga SELALU diambil dari database, bukan dari input browser.
                    $lineTotal = $product->selling_price * $quantity;

                    $sale->items()->create([
                        'barang_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $product->selling_price,
                        'unit_cost' => $product->purchase_price,
                        'subtotal' => $lineTotal,
                    ]);

                    $subtotal += $lineTotal;
                }

                $discount = $this->resolveDiscount($creator, $subtotal, $data, $channel);

                $sale->update(['subtotal' => $subtotal, 'discount' => $discount, 'total' => $subtotal - $discount]);
                NumberGenerator::finalize($sale, 'SL', 'sale_number');

                if ($complete) {
                    $this->finalizeCompletion($sale, $warehouseId, markPaid: (bool) ($data['mark_paid'] ?? false), actorId: $creator->id);
                }

                return $sale;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($requestKey && ($existing = $this->findByRequestKey($creator, $requestKey))) {
                return $existing; // permintaan paralel dengan kunci yang sama
            }

            throw $e;
        }

        AuditLogger::log('sale.created', "Penjualan {$sale->sale_number} dibuat (".$sale->channel.')', $sale, null, [
            'total' => $sale->total, 'discount' => $sale->discount, 'status' => $sale->status,
        ]);

        if ($sale->isCompleted()) {
            $this->afterCompleted($sale);
            $this->notifications->toUser($creator, 'sale_success', 'Penjualan berhasil', "{$sale->sale_number} senilai ".money($sale->total, base: true).' tersimpan.', route('penjualan.show', $sale), "sale-ok:{$sale->id}", 'success');
        } else {
            $this->notifications->toRoles(['owner', 'staff'], 'new_order', 'Pesanan baru', "{$sale->sale_number} menunggu diproses.", route('penjualan.show', $sale), "order:{$sale->id}");
        }

        return $sale->fresh(['items.barang', 'customer', 'user', 'warehouse']);
    }

    /**
     * Proses pesanan pending menjadi selesai. Stok divalidasi ulang dan dikurangi
     * dari gudang yang dipilih; gagal = seluruh proses di-rollback dan pesanan tetap pending.
     *
     * @throws BusinessRuleException|InsufficientStockException
     */
    public function complete(Sale $sale, User $actor, ?int $warehouseId = null): Sale
    {
        $sale = DB::transaction(function () use ($sale, $actor, $warehouseId) {
            $locked = Sale::with('items')->lockForUpdate()->findOrFail($sale->id);

            if (! $locked->isPending()) {
                throw new BusinessRuleException('Hanya pesanan berstatus menunggu yang dapat diproses.');
            }

            $this->finalizeCompletion($locked, $warehouseId ?? $locked->warehouse_id, markPaid: $locked->isPaid(), actorId: $actor->id);

            return $locked;
        });

        AuditLogger::log('sale.completed', "Pesanan {$sale->sale_number} diproses", $sale, ['status' => 'pending'], ['status' => 'completed']);
        $this->afterCompleted($sale);
        $this->notifications->toUser($sale->customer, 'order_processed', 'Pesanan diproses', "Pesanan {$sale->sale_number} sudah diproses.", route('pesanan.show', $sale), "order-done:{$sale->id}", 'success');

        return $sale;
    }

    /**
     * Batalkan transaksi. Pending: tidak ada stok yang dikembalikan (belum dikurangi).
     * Completed: stok dikembalikan ke batch & gudang asal, jurnal dibalik. Pembayaran menjadi refunded.
     * Transaksi yang sudah punya retur tidak dapat dibatalkan (lakukan retur untuk sisa barang).
     *
     * @throws BusinessRuleException
     */
    public function cancel(Sale $sale, User $actor, ?string $reason = null): Sale
    {
        $sale = DB::transaction(function () use ($sale, $actor, $reason) {
            $locked = Sale::with('items')->lockForUpdate()->findOrFail($sale->id);

            if ($locked->isCancelled()) {
                throw new BusinessRuleException('Transaksi ini sudah dibatalkan.');
            }

            if ($locked->returns()->exists()) {
                throw new BusinessRuleException('Transaksi ini sudah memiliki retur sehingga tidak dapat dibatalkan. Gunakan retur untuk barang yang tersisa.');
            }

            if ($locked->isCompleted()) {
                foreach ($locked->items->sortBy('barang_id') as $item) {
                    $allocations = $this->stock->outstandingAllocations($locked, $item->barang_id, 'out');

                    $this->stock->increase($item->barang_id, $locked->warehouse_id, $item->quantity, [
                        'type' => 'sale_cancel', 'reference' => $locked, 'user_id' => $actor->id, 'note' => 'Pembatalan '.$locked->sale_number,
                        'allocations' => $allocations,
                    ]);
                }

                $this->journal->reverse($locked, $actor->id);
            }

            $locked->update([
                'status' => Sale::STATUS_CANCELLED,
                'payment_status' => $locked->isPaid() ? Sale::PAYMENT_REFUNDED : $locked->payment_status,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);

            return $locked;
        });

        AuditLogger::log('sale.cancelled', "Penjualan {$sale->sale_number} dibatalkan: ".($reason ?: '-'), $sale, null, ['status' => 'cancelled', 'reason' => $reason]);
        $this->notifications->toUser($sale->customer, 'order_cancelled', 'Pesanan dibatalkan', "Pesanan {$sale->sale_number} dibatalkan.", route('pesanan.show', $sale), "order-cancel:{$sale->id}", 'warning');
        $this->webhooks->dispatch('sale.cancelled', $this->payload($sale));

        return $sale;
    }

    /**
     * Retur sebagian/seluruh barang dari penjualan yang sudah selesai.
     * Aturan: hanya penjualan selesai; jumlah retur kumulatif tidak boleh melebihi jumlah terjual;
     * staff dibatasi jendela waktu retur (admin tidak); nilai refund proporsional terhadap diskon.
     *
     * @param  array{reason: string, request_key?: string|null, items: array<int, array{sale_item_id: int, quantity: int}>}  $data
     *
     * @throws BusinessRuleException|ValidationException
     */
    public function returnItems(Sale $sale, User $actor, array $data): SaleReturn
    {
        $requestKey = $data['request_key'] ?? null;

        if ($requestKey && ($existing = SaleReturn::where('request_key', $requestKey)->where('sale_id', $sale->id)->first())) {
            return $existing;
        }

        $lines = collect($data['items'] ?? [])
            ->map(fn ($i) => ['sale_item_id' => (int) ($i['sale_item_id'] ?? 0), 'quantity' => (int) ($i['quantity'] ?? 0)])
            ->filter(fn ($i) => $i['quantity'] > 0)
            ->groupBy('sale_item_id')
            ->map(fn ($group, $id) => ['sale_item_id' => (int) $id, 'quantity' => $group->sum('quantity')])
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu barang yang diretur.']);
        }

        if (blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Alasan retur wajib diisi.']);
        }

        $return = DB::transaction(function () use ($sale, $actor, $data, $lines, $requestKey) {
            $locked = Sale::with('items')->lockForUpdate()->findOrFail($sale->id);

            if (! $locked->isCompleted()) {
                throw new BusinessRuleException('Retur hanya dapat dilakukan pada penjualan yang sudah selesai.');
            }

            $window = (int) config('stockflow.return_window_days');
            $completedAt = $locked->completed_at ?? $locked->sold_at;

            if (! $actor->isManagement() && $window > 0 && $completedAt->lt(now()->subDays($window))) {
                throw new BusinessRuleException("Batas waktu retur {$window} hari sudah terlewati. Hubungi owner untuk retur ini.");
            }

            $return = SaleReturn::create([
                'return_number' => NumberGenerator::temporary(),
                'sale_id' => $locked->id,
                'user_id' => $actor->id,
                'refund_total' => 0,
                'reason' => $data['reason'],
                'returned_at' => now(),
                'request_key' => $requestKey,
            ]);

            $refundTotal = 0;
            $cogs = 0;

            foreach ($lines->sortBy('sale_item_id') as $line) {
                /** @var SaleItem|null $item */
                $item = $locked->items->firstWhere('id', $line['sale_item_id']);

                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Barang yang diretur bukan bagian dari transaksi ini.']);
                }

                $alreadyReturned = (int) DB::table('sale_return_items')->where('sale_item_id', $item->id)->sum('quantity');
                $maxReturnable = $item->quantity - $alreadyReturned;

                if ($line['quantity'] > $maxReturnable) {
                    throw new BusinessRuleException("Retur {$item->barang?->name} melebihi jumlah yang bisa diretur (maksimal {$maxReturnable}).");
                }

                // Refund proporsional: diskon transaksi ikut dibagi rata sesuai nilai barang.
                $gross = $item->unit_price * $line['quantity'];
                $refund = $locked->subtotal > 0 ? (int) round($gross * $locked->total / $locked->subtotal) : 0;

                $return->items()->create([
                    'sale_item_id' => $item->id,
                    'barang_id' => $item->barang_id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'refund_amount' => $refund,
                ]);

                $allocations = $this->stock->slice($this->stock->outstandingAllocations($locked, $item->barang_id, 'out'), $line['quantity']);

                $this->stock->increase($item->barang_id, $locked->warehouse_id, $line['quantity'], [
                    'type' => 'sale_return', 'reference' => $locked, 'user_id' => $actor->id, 'note' => 'Retur atas '.$locked->sale_number,
                    'allocations' => $allocations,
                ]);

                $refundTotal += $refund;
                $cogs += $item->unit_cost * $line['quantity'];
            }

            $return->update(['refund_total' => $refundTotal]);
            NumberGenerator::finalize($return, 'RT', 'return_number');
            $this->journal->postSaleReturn($locked, $return->fresh(), $cogs);

            if ($locked->isPaid() && (int) $locked->returns()->sum('refund_total') >= $locked->total) {
                $locked->update(['payment_status' => Sale::PAYMENT_REFUNDED]);
            }

            return $return;
        });

        AuditLogger::log('sale.returned', "Retur {$return->return_number} atas {$sale->sale_number}: {$data['reason']}", $sale, null, ['refund_total' => $return->refund_total]);
        $this->notifications->toRoles(['owner'], 'sale_return', 'Retur penjualan', "{$return->return_number} atas {$sale->sale_number} senilai ".money($return->refund_total, base: true).'.', route('penjualan.show', $sale));
        $this->webhooks->dispatch('sale.returned', ['sale_number' => $sale->sale_number, 'return_number' => $return->return_number, 'refund_total' => $return->refund_total]);

        return $return;
    }

    /**
     * Tandai penjualan lunas (dipanggil oleh PaymentService / webhook pembayaran / kasir).
     *
     * @throws BusinessRuleException
     */
    public function markPaid(Sale $sale, string $method, ?string $reference = null): Sale
    {
        return DB::transaction(function () use ($sale, $method, $reference) {
            $locked = Sale::lockForUpdate()->findOrFail($sale->id);

            if ($locked->isCancelled()) {
                throw new BusinessRuleException('Transaksi yang dibatalkan tidak dapat dibayar.');
            }

            if ($locked->isPaid()) {
                throw new BusinessRuleException('Transaksi ini sudah lunas.');
            }

            $locked->update([
                'payment_method' => $method,
                'payment_status' => Sale::PAYMENT_PAID,
                'payment_reference' => $reference,
                'paid_at' => now(),
            ]);

            // Penjualan selesai yang belum lunas sudah membukukan piutang -> lunasi.
            // Pesanan pending belum dijurnal; jurnal dibuat saat pesanan diproses.
            if ($locked->isCompleted()) {
                $this->journal->postPayment($locked);
            }

            AuditLogger::log('sale.paid', "Pembayaran {$locked->sale_number} via {$method}", $locked, null, ['payment_status' => 'paid', 'method' => $method]);

            return $locked;
        });
    }

    /** Dipakai pembuatan POS dan proses pesanan: kurangi stok (FEFO), set status, jurnal. */
    private function finalizeCompletion(Sale $sale, int $warehouseId, bool $markPaid, int $actorId): void
    {
        foreach ($sale->items()->orderBy('barang_id')->get() as $item) {
            $this->stock->decrease($item->barang_id, $warehouseId, $item->quantity, [
                'type' => 'sale', 'reference' => $sale, 'user_id' => $actorId, 'note' => $sale->sale_number,
            ]);
        }

        $sale->update([
            'status' => Sale::STATUS_COMPLETED,
            'warehouse_id' => $warehouseId,
            'completed_at' => now(),
            'sold_at' => now(),
            'payment_status' => $markPaid ? Sale::PAYMENT_PAID : Sale::PAYMENT_UNPAID,
            'paid_at' => $markPaid ? ($sale->paid_at ?? now()) : null,
            'payment_method' => $sale->payment_method ?? ($markPaid ? 'cash' : null),
        ]);

        $this->journal->postSale($sale->fresh('items'));
    }

    private function afterCompleted(Sale $sale): void
    {
        $this->webhooks->dispatch('sale.completed', $this->payload($sale));

        $low = Barang::lowStock()->whereIn('id', $sale->items()->pluck('barang_id'))->get();
        foreach ($low as $barang) {
            $this->webhooks->dispatch('stock.low', [
                'sku' => $barang->code, 'name' => $barang->name,
                'stock' => $barang->stock, 'minimum_stock' => $barang->minimum_stock,
            ]);
        }
    }

    private function findByRequestKey(User $creator, string $key): ?Sale
    {
        return Sale::with(['items.barang', 'customer', 'user', 'warehouse'])
            ->where('request_key', $key)->where('user_id', $creator->id)->first();
    }

    /**
     * Diskon hanya untuk penjualan kasir. Nominal atau persen; tidak boleh melebihi subtotal.
     * Staff dibatasi persentase maksimum (config stockflow.max_staff_discount_percent).
     */
    private function resolveDiscount(User $actor, int $subtotal, array $data, string $channel): int
    {
        $value = (float) ($data['discount_value'] ?? 0);

        if ($channel !== Sale::CHANNEL_POS || $value <= 0) {
            return 0;
        }

        $type = ($data['discount_type'] ?? 'amount') === 'percent' ? 'percent' : 'amount';

        if ($type === 'percent' && $value > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Diskon persen tidak boleh lebih dari 100%.']);
        }

        $discount = $type === 'percent' ? (int) round($subtotal * $value / 100) : (int) round($value);

        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_value' => 'Diskon tidak boleh melebihi subtotal.']);
        }

        $max = (float) config('stockflow.max_staff_discount_percent');

        if (! $actor->isManagement() && $subtotal > 0 && $discount * 100 > $subtotal * $max) {
            throw ValidationException::withMessages(['discount_value' => "Diskon di atas {$max}% hanya dapat diberikan oleh owner."]);
        }

        return $discount;
    }

    /**
     * Gabungkan baris produk yang sama (jumlah dijumlahkan) agar pengecekan stok tidak
     * bisa dilewati dengan memecah satu produk menjadi beberapa baris.
     *
     * @return \Illuminate\Support\Collection<int, int> barang_id => quantity
     *
     * @throws ValidationException
     */
    private function mergeItems(array $rawItems): \Illuminate\Support\Collection
    {
        $merged = collect();

        foreach ($rawItems as $raw) {
            $quantity = (int) ($raw['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages(['items' => 'Kuantitas harus lebih dari 0.']);
            }

            $id = (int) ($raw['barang_id'] ?? 0);
            $merged[$id] = ($merged[$id] ?? 0) + $quantity;
        }

        if ($merged->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Tambahkan minimal satu produk.']);
        }

        return $merged->sortKeys();
    }

    private function payload(Sale $sale): array
    {
        $sale->loadMissing(['items.barang', 'customer']);

        return [
            'sale_number' => $sale->sale_number,
            'status' => $sale->status,
            'channel' => $sale->channel,
            'customer' => $sale->customer?->name,
            'total' => $sale->total,
            'payment_status' => $sale->payment_status,
            'items' => $sale->items->map(fn ($i) => [
                'sku' => $i->barang?->code,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
            ])->all(),
        ];
    }
}
