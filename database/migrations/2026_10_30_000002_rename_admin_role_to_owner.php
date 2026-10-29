<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Hanya tiga role: owner, staff, pelanggan. Akun lama ber-role "admin" menjadi "owner". */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'owner']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'owner')->update(['role' => 'admin']);
    }
};
