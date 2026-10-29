<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->unique()->after('code');
            $table->boolean('tracks_expiry')->default(false)->after('is_active');
            $table->unsignedSmallInteger('expiry_warning_days')->default(30)->after('tracks_expiry');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_time_days')->default(3)->after('address');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal')->default(0)->after('channel');
            $table->unsignedBigInteger('discount')->default(0)->after('subtotal');
            $table->string('request_key', 64)->nullable()->unique();
        });
        DB::table('sales')->update(['subtotal' => DB::raw('total')]);

        Schema::table('purchases', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_key', 64)->nullable()->unique();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->unsignedInteger('received_quantity')->default(0)->after('quantity');
        });
        // Pembelian lama langsung diterima penuh.
        DB::table('purchase_items')->update(['received_quantity' => DB::raw('quantity')]);

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('quantity');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->string('request_key', 64)->nullable()->unique();
        });

        // transferred_at wajib terisi hanya saat transfer selesai.
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->timestamp('transferred_at')->nullable()->change();
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->string('request_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        // Migrasi maju saja (data produksi); rollback dilakukan lewat migrate:fresh di lingkungan pengembangan.
    }
};
