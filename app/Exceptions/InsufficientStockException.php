<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(string $barangName, int $available, int $requested)
    {
        parent::__construct(
            "Stok tidak mencukupi untuk barang \"{$barangName}\". Stok tersedia: {$available}, diminta: {$requested}."
        );
    }
}