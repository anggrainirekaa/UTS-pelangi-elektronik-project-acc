<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-sales') ?? false;
    }

    /**
     * Harga tidak diterima dari browser; server mengambilnya dari tabel barangs.
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_PELANGGAN)],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'payment_method' => ['nullable', Rule::in(['cash', 'transfer_bank', 'qris', 'ewallet', 'kartu'])],
            'mark_paid' => ['sometimes', 'boolean'],
            'discount_type' => ['nullable', 'in:amount,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'request_key' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.barang_id' => ['required', 'integer', Rule::exists('barangs', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['mark_paid' => $this->boolean('mark_paid')]);
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Gudang wajib dipilih.',
            'customer_id.exists' => 'Pelanggan yang dipilih tidak valid.',
            'items.required' => 'Tambahkan minimal satu produk.',
            'items.min' => 'Tambahkan minimal satu produk.',
            'items.*.barang_id.required' => 'Pilih produk pada setiap baris.',
            'items.*.barang_id.exists' => 'Produk yang dipilih tidak valid.',
            'items.*.quantity.required' => 'Kuantitas wajib diisi.',
            'items.*.quantity.integer' => 'Kuantitas harus berupa angka bulat.',
            'items.*.quantity.min' => 'Kuantitas minimal 1.',
        ];
    }
}
