@extends('layouts.app')

@section('title', 'Penyesuaian Stok')

@section('content')
    <x-card title="Catat Penyesuaian Stok Keluar" subtitle="Untuk barang keluar non-penjualan (pemakaian internal, rusak/hilang). Penjualan dicatat lewat menu Penjualan." class="max-w-2xl">
        <form method="POST" action="{{ route('stok-keluar.store') }}" class="p-6 space-y-5">
            @csrf
            <x-select-field name="barang_id" label="Barang" placeholder="-- Pilih Barang --"
                :options="$barangs->mapWithKeys(fn ($b) => [$b->id => $b->name.' ('.$b->code.') — total stok '.$b->stock])" required />
            <x-select-field name="warehouse_id" label="Gudang" :options="$warehouses->pluck('name', 'id')" :value="$defaultWarehouse->id" required />
            <x-select-field name="purpose" label="Alasan" :options="['internal_use' => 'Pemakaian Internal', 'damaged' => 'Rusak/Hilang', 'other' => 'Lainnya']" value="damaged" required />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field name="quantity" label="Jumlah" type="number" min="1" value="1" required />
                <x-field name="date" label="Tanggal" type="date" :value="now()->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" required />
            </div>
            <x-field name="note" label="Catatan (opsional)" type="textarea" />
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Simpan & Kurangi Stok</button>
                <a href="{{ route('stok-keluar.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-200">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
