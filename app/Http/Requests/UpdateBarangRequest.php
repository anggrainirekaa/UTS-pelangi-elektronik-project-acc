<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBarangRequest extends StoreBarangRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['code'] = ['required', 'string', 'max:50', Rule::unique('barangs', 'code')->ignore($this->route('barang'))];

        $rules['barcode'] = ['nullable', 'string', 'max:64', Rule::unique('barangs', 'barcode')->ignore($this->route('barang'))];

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'tracks_expiry' => $this->boolean('tracks_expiry'),
            'barcode' => $this->filled('barcode') ? trim($this->input('barcode')) : null,
            'code' => $this->filled('code') ? strtoupper(trim($this->input('code'))) : null,
        ]);
    }
}
