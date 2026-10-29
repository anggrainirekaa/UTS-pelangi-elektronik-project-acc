@extends('layouts.app')
@section('title', 'Transfer Baru')
@section('content')
    <x-card title="Transfer Stok Baru" :subtitle="'Total stok tidak berubah. Transfer dari staff dengan jumlah ≥ '.config('stockflow.transfer_approval_threshold').' unit memerlukan persetujuan owner.'"  class="max-w-2xl">
        <form method="POST" action="{{ route('transfer-stok.store') }}" class="p-6 space-y-5">
            @csrf
            <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <x-select-field name="barang_id" label="Barang" placeholder="-- Pilih Barang --" :options="$barangs->mapWithKeys(fn ($b) => [$b->id => $b->name.' ('.$b->code.')'])" required />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-select-field name="from_warehouse_id" label="Gudang Asal" placeholder="-- Pilih --" :options="$warehouses->pluck('name', 'id')" required />
                <x-select-field name="to_warehouse_id" label="Gudang Tujuan" placeholder="-- Pilih --" :options="$warehouses->pluck('name', 'id')" required />
            </div>
            <x-field name="quantity" label="Jumlah" type="number" min="1" value="1" required />
            <x-field name="note" label="Catatan" />
            <div class="flex gap-3 pt-2">
                <button class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Transfer</button>
                <a href="{{ route('transfer-stok.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
