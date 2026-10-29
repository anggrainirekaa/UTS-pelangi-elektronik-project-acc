@extends('layouts.app')
@section('title', $currency->exists ? 'Edit Mata Uang' : 'Tambah Mata Uang')
@section('content')
    <x-card :title="$currency->exists ? 'Edit '.$currency->code : 'Tambah Mata Uang'" class="max-w-2xl">
        <form method="POST" action="{{ $currency->exists ? route('mata-uang.update', $currency) : route('mata-uang.store') }}" class="p-6 space-y-5">
            @csrf
            @if($currency->exists) @method('PUT') @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-field name="code" label="Kode (3 huruf)" :value="$currency->code" maxlength="3" required :readonly="$currency->is_base" />
                <x-field name="name" label="Nama" :value="$currency->name" required />
                <x-field name="symbol" label="Simbol" :value="$currency->symbol" required />
                <x-field name="decimals" label="Desimal" type="number" min="0" max="4" :value="$currency->decimals" required />
                <x-field name="rate" label="Kurs terhadap IDR" type="number" step="0.000001" min="0.000001" :value="$currency->rate" :readonly="$currency->is_base" :hint="$currency->is_base ? 'Mata uang dasar selalu 1.' : '1 unit mata uang ini = berapa Rupiah.'" required />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('is_active', $currency->is_active))> Aktif</label>
            <div class="flex gap-3 pt-2">
                <button class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Simpan</button>
                <a href="{{ route('mata-uang.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
