<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batch/lot: hanya dipakai produk dengan tracks_expiry = true.
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 60);
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->unique(['barang_id', 'warehouse_id', 'batch_number', 'expiry_date'], 'stock_batches_unique_lot');
            $table->index(['barang_id', 'warehouse_id', 'expiry_date']);
        });

        // Kartu stok: setiap perubahan saldo tercatat (audit trail stok + sumber grafik pergerakan).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity');           // bertanda: + masuk, - keluar
            $table->integer('balance_after');      // saldo gudang setelah perubahan
            $table->integer('total_after');        // saldo total barang setelah perubahan
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['barang_id', 'occurred_at']);
            $table->index(['warehouse_id', 'occurred_at']);
            $table->index('type');
        });

        // Backfill: saldo yang sudah ada dianggap saldo awal agar kartu stok sesuai saldo.
        $now = now();
        $running = [];
        foreach (DB::table('warehouse_stocks')->where('quantity', '>', 0)->orderBy('id')->get() as $row) {
            $running[$row->barang_id] = ($running[$row->barang_id] ?? 0) + $row->quantity;
            DB::table('stock_movements')->insert([
                'barang_id' => $row->barang_id, 'warehouse_id' => $row->warehouse_id, 'type' => 'opening',
                'quantity' => $row->quantity, 'balance_after' => $row->quantity, 'total_after' => $running[$row->barang_id],
                'note' => 'Saldo awal', 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_batches');
    }
};
