@extends('layouts.app')

@section('title', 'Penjualan Baru')

@section('content')
    <x-card title="Penjualan Baru" subtitle="Harga diambil dari database. Stok berkurang setelah disimpan; stok tidak cukup akan ditolak.">
        <form method="POST" action="{{ route('penjualan.store') }}" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <x-select-field name="warehouse_id" label="Gudang Asal" :options="$warehouses->pluck('name', 'id')" :value="$defaultWarehouse->id" required />
                <x-select-field name="customer_id" label="Pelanggan (opsional)" placeholder="Pelanggan umum" :options="$customers->mapWithKeys(fn ($c) => [$c->id => $c->name.' — '.$c->email])" />
                <x-select-field name="payment_method" label="Metode Bayar" :options="['cash' => 'Tunai'] + $paymentMethods" value="cash" />
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="mark_paid" value="0">
                        <input type="checkbox" name="mark_paid" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('mark_paid', true))> Sudah dibayar (lunas)
                    </label>
                </div>
                <div class="sm:col-span-2 lg:col-span-4 grid grid-cols-2 gap-3 sm:max-w-md">
                    <div><label class="sf-label" for="discount_type">Diskon</label><select id="discount_type" name="discount_type" class="sf-select"><option value="amount" @selected(old('discount_type') === 'amount')>Rupiah (Rp)</option><option value="percent" @selected(old('discount_type') === 'percent')>Persen (%)</option></select></div>
                    <div><label class="sf-label" for="discount_value">Nilai diskon</label><input id="discount_value" name="discount_value" type="number" min="0" step="any" value="{{ old('discount_value', 0) }}" class="sf-input">@error('discount_value')<p class="sf-error">{{ $message }}</p>@enderror<p class="sf-help">Staff dibatasi maks. {{ config('stockflow.max_staff_discount_percent') }}%.</p></div>
                </div>
                <x-field name="note" label="Catatan" class="sm:col-span-2 lg:col-span-4" />
            </div>

            @include('partials.line-items', ['mode' => 'sale', 'products' => $productData, 'rows' => old('items', []), 'defaultWarehouse' => $defaultWarehouse])

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Simpan Penjualan</button>
                <a href="{{ route('penjualan.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-200">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
