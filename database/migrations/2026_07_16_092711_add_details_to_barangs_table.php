<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            $table->string('code')->unique()->after('id');
            $table->string('unit')->default('pcs')->after('name');
            $table->unsignedBigInteger('purchase_price')->default(0)->after('unit');
            $table->unsignedBigInteger('selling_price')->default(0)->after('purchase_price');
            $table->unsignedInteger('stock')->default(0)->after('selling_price');
            $table->unsignedInteger('minimum_stock')->default(5)->after('stock');
            $table->string('image')->nullable()->after('minimum_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('barangs', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'unit',
                'purchase_price',
                'selling_price',
                'stock',
                'minimum_stock',
                'image',
            ]);
        });
    }
};