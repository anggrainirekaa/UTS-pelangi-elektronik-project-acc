@extends('layouts.app')

@section('title', 'Tambah Barang')

@section('content')
    <x-card title="Tambah Barang" class="max-w-3xl">
        <form method="POST" action="{{ route('barang.store') }}" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf
            @include('barangs._form')
            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Simpan</button>
                <a href="{{ route('barang.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-200">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
