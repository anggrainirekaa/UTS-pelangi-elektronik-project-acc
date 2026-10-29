<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Penyesuaian stok keluar (non-penjualan). Penjualan dicatat lewat modul Penjualan.
 */
class StoreStockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-stock') ?? false;
    }

    public function rules(): array
    {
        return [
            'barang_id' => ['required', Rule::exists('barangs', 'id')->whereNull('deleted_at')],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['required', Rule::in(\App\Models\StockOut::PURPOSES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'barang_id.required' => 'Barang wajib dipilih.',
            'barang_id.exists' => 'Barang yang dipilih tidak valid.',
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'quantity.required' => 'Jumlah barang wajib diisi.',
            'quantity.min' => 'Jumlah barang minimal 1.',
            'date.required' => 'Tanggal wajib diisi.',
            'date.before_or_equal' => 'Tanggal tidak boleh lebih dari hari ini.',
            'purpose.required' => 'Alasan barang keluar wajib dipilih.',
            'purpose.in' => 'Alasan barang keluar tidak valid.',
        ];
    }
}
