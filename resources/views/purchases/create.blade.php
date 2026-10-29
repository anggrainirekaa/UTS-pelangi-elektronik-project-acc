@extends('layouts.app')

@section('title', 'Buat Pembelian')

@section('content')
    @php
        $productData = $products->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'unit' => $p->unit, 'cost' => $p->purchase_price, 'stock' => $p->stock]);
        $rows = old('items') ?: ($prefillBarangId ? [['barang_id' => $prefillBarangId, 'quantity' => 10, 'unit_cost' => optional($products->firstWhere('id', $prefillBarangId))->purchase_price ?? 0]] : []);
    @endphp
    <x-card title="Buat Pembelian (Stok Masuk)" subtitle="Satu pembelian dapat berisi banyak barang. Stok bertambah setelah disimpan.">
        <form method="POST" action="{{ route('pembelian.store') }}" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-select-field name="supplier_id" label="Supplier" placeholder="-- Pilih Supplier --" :options="$suppliers->pluck('name', 'id')" required />
                <x-select-field name="warehouse_id" label="Gudang Tujuan" :options="$warehouses->pluck('name', 'id')" :value="$defaultWarehouse->id" required />
                <x-field name="note" label="Catatan" class="sm:col-span-2" />
            </div>

            <fieldset class="grid gap-3 sm:grid-cols-2">
                <legend class="sf-label">Cara pencatatan</legend>
                <label class="sf-card sf-card-body flex gap-3 cursor-pointer" style="padding:14px"><input type="radio" name="mode" value="draft" @checked(old('mode', 'draft') === 'draft')><span><strong>Draft PO (disarankan)</strong><br><span class="text-sm" style="color:var(--sf-muted)">Diajukan, disetujui owner, lalu barang diterima (boleh bertahap). Stok bertambah saat diterima.</span></span></label>
                <label class="sf-card sf-card-body flex gap-3 cursor-pointer" style="padding:14px"><input type="radio" name="mode" value="direct" @checked(old('mode') === 'direct')><span><strong>Terima langsung</strong><br><span class="text-sm" style="color:var(--sf-muted)">Barang sudah tiba; stok langsung bertambah. Produk berkedaluwarsa memerlukan Draft PO.</span></span></label>
            </fieldset>

            @include('partials.line-items', ['mode' => 'purchase', 'products' => $productData, 'rows' => $rows, 'defaultWarehouse' => $defaultWarehouse])

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Simpan</button>
                <a href="{{ route('pembelian.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-200">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
