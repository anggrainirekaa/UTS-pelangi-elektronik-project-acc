<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 8);
            $table->unsignedTinyInteger('decimals')->default(0);
            $table->decimal('rate', 18, 6)->default(1); // nilai 1 unit mata uang ini dalam IDR
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Kurs awal hanya contoh; admin dapat mengubahnya di menu Mata Uang.
        $now = now();
        DB::table('currencies')->insert([
            ['code' => 'IDR', 'name' => 'Rupiah Indonesia', 'symbol' => 'Rp', 'decimals' => 0, 'rate' => 1, 'is_base' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2, 'rate' => 16000, 'is_base' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimals' => 2, 'rate' => 12000, 'is_base' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MYR', 'name' => 'Ringgit Malaysia', 'symbol' => 'RM', 'decimals' => 2, 'rate' => 3600, 'is_base' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimals' => 2, 'rate' => 17500, 'is_base' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
