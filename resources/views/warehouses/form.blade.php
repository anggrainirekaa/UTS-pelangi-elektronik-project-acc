@extends('layouts.app')
@section('title', $warehouse->exists ? 'Edit Gudang' : 'Tambah Gudang')
@section('content')
    <x-card :title="$warehouse->exists ? 'Edit Gudang' : 'Tambah Gudang'" class="max-w-2xl">
        <form method="POST" action="{{ $warehouse->exists ? route('gudang.update', $warehouse) : route('gudang.store') }}" class="p-6 space-y-5">
            @csrf
            @if($warehouse->exists) @method('PUT') @endif
            <x-field name="code" label="Kode" :value="$warehouse->code" required />
            <x-field name="name" label="Nama Gudang" :value="$warehouse->name" required />
            <x-field name="address" label="Alamat" type="textarea" :value="$warehouse->address" />
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('is_default', $warehouse->is_default))> Jadikan gudang default
            </label>
            <div class="flex gap-3 pt-2">
                <button class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Simpan</button>
                <a href="{{ route('gudang.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
