<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('barangs')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'barang_id']);
        });

        // Data lama: seluruh stok yang sudah tercatat dipindahkan ke "Gudang Utama".
        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'GDG-001',
            'name' => 'Gudang Utama',
            'address' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table('barangs')->where('stock', '>', 0)->get(['id', 'stock']) as $barang) {
            DB::table('warehouse_stocks')->insert([
                'warehouse_id' => $warehouseId,
                'barang_id' => $barang->id,
                'quantity' => $barang->stock,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('warehouses');
    }
};
