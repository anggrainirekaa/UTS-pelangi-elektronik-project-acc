<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('total')->default(0);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        // Backfill: pembelian lama yang sudah selesai dianggap diterima penuh pada tanggal pembelian.
        foreach (\Illuminate\Support\Facades\DB::table('purchases')->where('status', 'completed')->get() as $purchase) {
            $receiptId = \Illuminate\Support\Facades\DB::table('purchase_receipts')->insertGetId([
                'receipt_number' => 'RC-'.str_pad((string) $purchase->id, 8, '0', STR_PAD_LEFT),
                'purchase_id' => $purchase->id, 'user_id' => $purchase->user_id,
                'received_at' => $purchase->purchased_at ?? $purchase->created_at, 'total' => $purchase->total,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach (\Illuminate\Support\Facades\DB::table('purchase_items')->where('purchase_id', $purchase->id)->get() as $item) {
                \Illuminate\Support\Facades\DB::table('purchase_receipt_items')->insert([
                    'purchase_receipt_id' => $receiptId, 'purchase_item_id' => $item->id, 'barang_id' => $item->barang_id,
                    'quantity' => $item->quantity, 'unit_cost' => $item->unit_cost, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 30)->unique();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('refund_total')->default(0);
            $table->string('reason', 500);
            $table->timestamp('returned_at');
            $table->string('request_key', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('refund_amount');
            $table->timestamps();
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number', 30)->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('user_id')->constrained();                 // penghitung
            $table->string('status', 20)->default('draft');             // draft|submitted|approved|rejected
            $table->string('note', 500)->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained();
            $table->integer('system_quantity');       // snapshot saat opname dibuat
            $table->integer('counted_quantity')->nullable();
            $table->integer('difference')->default(0);
            $table->string('reason', 500)->nullable();
            $table->integer('stock_before')->nullable(); // diisi saat disetujui
            $table->integer('stock_after')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'barang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
