<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul lama "Stok Masuk" (satu barang per transaksi) digantikan oleh Pembelian
 * (header + banyak item), dan "Stok Keluar" bertujuan Dijual digantikan oleh Penjualan.
 * Data lama dikonversi tanpa mengubah stok (stok sudah mencerminkan transaksi tersebut).
 * "Stok Keluar" untuk pemakaian internal / rusak tetap ada sebagai Penyesuaian Stok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_outs', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('barang_id')->constrained()->nullOnDelete();
        });

        $warehouseId = DB::table('warehouses')->where('is_default', true)->value('id');
        DB::table('stock_outs')->update(['warehouse_id' => $warehouseId]);

        if (Schema::hasTable('stock_ins') && DB::table('stock_ins')->exists()) {
            $fallbackSupplier = null;

            foreach (DB::table('stock_ins')->orderBy('id')->get() as $row) {
                $supplierId = $row->supplier_id;
                if (! $supplierId) {
                    $fallbackSupplier ??= DB::table('suppliers')->where('name', 'Tanpa Supplier')->value('id')
                        ?? DB::table('suppliers')->insertGetId([
                            'name' => 'Tanpa Supplier', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    $supplierId = $fallbackSupplier;
                }

                $purchasedAt = $row->date.' 00:00:00';
                $purchaseId = DB::table('purchases')->insertGetId([
                    'purchase_number' => 'TMP-'.uniqid('', true),
                    'supplier_id' => $supplierId,
                    'user_id' => $row->user_id,
                    'warehouse_id' => $warehouseId,
                    'total' => $row->quantity * $row->purchase_price,
                    'status' => 'completed',
                    'purchased_at' => $purchasedAt,
                    'note' => $row->note,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
                DB::table('purchases')->where('id', $purchaseId)->update([
                    'purchase_number' => 'PB-'.date('Ymd', strtotime($row->date)).'-'.str_pad((string) $purchaseId, 5, '0', STR_PAD_LEFT),
                ]);
                DB::table('purchase_items')->insert([
                    'purchase_id' => $purchaseId,
                    'barang_id' => $row->barang_id,
                    'quantity' => $row->quantity,
                    'unit_cost' => $row->purchase_price,
                    'subtotal' => $row->quantity * $row->purchase_price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $legacySales = DB::table('stock_outs')->where('purpose', 'sale')->orderBy('id')->get();
        foreach ($legacySales as $row) {
            $soldAt = $row->date.' 00:00:00';
            $saleId = DB::table('sales')->insertGetId([
                'sale_number' => 'TMP-'.uniqid('', true),
                'user_id' => $row->user_id,
                'warehouse_id' => $warehouseId,
                'channel' => 'pos',
                'total' => $row->quantity * $row->selling_price,
                'status' => 'completed',
                'payment_status' => 'paid',
                'paid_at' => $soldAt,
                'sold_at' => $soldAt,
                'completed_at' => $soldAt,
                'note' => $row->note,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            DB::table('sales')->where('id', $saleId)->update([
                'sale_number' => 'SL-'.date('Ymd', strtotime($row->date)).'-'.str_pad((string) $saleId, 5, '0', STR_PAD_LEFT),
            ]);
            DB::table('sale_items')->insert([
                'sale_id' => $saleId,
                'barang_id' => $row->barang_id,
                'quantity' => $row->quantity,
                'unit_price' => $row->selling_price,
                'unit_cost' => DB::table('barangs')->where('id', $row->barang_id)->value('purchase_price') ?? 0,
                'subtotal' => $row->quantity * $row->selling_price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('stock_outs')->where('purpose', 'sale')->delete();

        Schema::dropIfExists('stock_ins');
    }

    public function down(): void
    {
        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('barangs')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('purchase_price');
            $table->date('date');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('date');
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
