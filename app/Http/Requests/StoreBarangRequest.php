<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-master') ?? false;
    }

    /**
     * Stok tidak diinput di sini: stok hanya berubah lewat transaksi (pembelian, penjualan, penyesuaian)
     * agar riwayat dan jurnal selalu konsisten dengan jumlah stok.
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'code' => ['nullable', 'string', 'max:50', 'unique:barangs,code'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:barangs,barcode'],
            'tracks_expiry' => ['sometimes', 'boolean'],
            'expiry_warning_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:50'],
            'purchase_price' => ['required', 'integer', 'min:0'],
            'selling_price' => ['required', 'integer', 'min:0', 'gte:purchase_price'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'tracks_expiry' => $this->boolean('tracks_expiry'),
            'barcode' => $this->filled('barcode') ? trim($this->input('barcode')) : null,
            'code' => $this->filled('code') ? strtoupper(trim($this->input('code'))) : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak valid.',
            'barcode.unique' => 'Barcode sudah digunakan produk lain.',
            'code.unique' => 'SKU/kode barang sudah digunakan.',
            'name.required' => 'Nama barang wajib diisi.',
            'unit.required' => 'Satuan barang wajib diisi.',
            'purchase_price.required' => 'Harga beli wajib diisi.',
            'purchase_price.integer' => 'Harga beli harus berupa angka.',
            'purchase_price.min' => 'Harga beli tidak boleh negatif.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'selling_price.min' => 'Harga jual tidak boleh negatif.',
            'selling_price.gte' => 'Harga jual tidak boleh lebih kecil dari harga beli.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar harus JPG, JPEG, atau PNG.',
            'image.max' => 'Ukuran gambar maksimal 2MB.',
        ];
    }
}
