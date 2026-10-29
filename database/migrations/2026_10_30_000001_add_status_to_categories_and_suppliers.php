<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Status aktif/nonaktif untuk kategori dan pemasok (data master tidak dihapus bila sudah dipakai transaksi). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'suppliers'] as $table) {
            if (! Schema::hasColumn($table, 'is_active')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('is_active')->default(true)->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['categories', 'suppliers'] as $table) {
            if (Schema::hasColumn($table, 'is_active')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex($table.'_is_active_index');
                    $t->dropColumn('is_active');
                });
            }
        }
    }
};
