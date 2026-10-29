<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Penyesuaian skema untuk Pelangi Elektronik: kontak person pemasok dan nama kategori unik di level database. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('contact_person')->nullable()->after('phone');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('name', 'categories_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropUnique('categories_name_unique'));
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('contact_person'));
    }
};
