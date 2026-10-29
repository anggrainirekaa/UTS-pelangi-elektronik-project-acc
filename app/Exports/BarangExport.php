<?php

namespace App\Exports;

use App\Models\Barang;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BarangExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(protected ?string $search = null, protected ?int $categoryId = null)
    {
    }

    /**
     * Ambil data barang sesuai filter yang aktif.
     */
    public function collection(): Collection
    {
        return Barang::query()
            ->with(['category', 'supplier'])
            ->when($this->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($this->categoryId, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Header kolom Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Kode Barang',
            'Nama Barang',
            'Kategori',
            'Supplier',
            'Satuan',
            'Harga Beli',
            'Harga Jual',
            'Stok',
            'Stok Minimum',
        ];
    }

    /**
     * Mapping tiap baris data ke format Excel.
     *
     * @return array<int, mixed>
     */
    public function map($barang): array
    {
        return [
            $barang->code,
            $barang->name,
            $barang->category->name ?? '-',
            $barang->supplier->name ?? '-',
            $barang->unit,
            $barang->purchase_price,
            $barang->selling_price,
            $barang->stock,
            $barang->minimum_stock,
        ];
    }

    /**
     * Styling sederhana untuk header.
     *
     * @return array<int, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}