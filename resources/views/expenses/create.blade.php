@extends('layouts.app')
@section('title', 'Catat Pengeluaran')
@section('content')
    <x-card title="Catat Pengeluaran" class="max-w-2xl">
        <form method="POST" action="{{ route('pengeluaran.store') }}" class="p-6 space-y-5">
            @csrf
            <x-field name="title" label="Keterangan" required />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field name="amount" label="Jumlah (Rp)" type="number" min="1" required />
                <x-field name="expense_date" label="Tanggal" type="date" :value="now()->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" required />
            </div>
            <x-field name="note" label="Catatan" type="textarea" />
            <div class="flex gap-3 pt-2">
                <button class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Simpan</button>
                <a href="{{ route('pengeluaran.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
