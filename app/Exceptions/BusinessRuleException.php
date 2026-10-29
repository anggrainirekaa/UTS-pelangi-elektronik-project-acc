<?php

namespace App\Exceptions;

use Exception;

/**
 * Pelanggaran aturan bisnis (mis. membatalkan transaksi yang sudah dibatalkan).
 * Pesan ditampilkan langsung ke pengguna.
 */
class BusinessRuleException extends Exception
{
}
