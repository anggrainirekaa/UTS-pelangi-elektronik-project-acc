<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrayExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function __construct(protected array $headings, protected array $rows)
    {
    }

    public function array(): array
    {
        // Nilai teks yang diawali = + - @ diberi tanda kutip agar tidak dieksekusi sebagai formula di Excel/Sheets.
        return array_map(fn ($row) => array_map(function ($v) {
            if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$v;
            }

            return $v === null ? '' : (is_int($v) || is_float($v) ? (string) $v : $v);
        }, $row), $this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
