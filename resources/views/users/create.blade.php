@extends('layouts.app')
@section('title', 'Tambah Pengguna')
@section('content')
    <x-card title="Tambah Pengguna" class="max-w-3xl">
        <form method="POST" action="{{ route('pengguna.store') }}" class="p-6 space-y-5">
            @csrf
            @include('users._form')
            <div class="flex gap-3 pt-2">
                <button class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Simpan</button>
                <a href="{{ route('pengguna.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg">Batal</a>
            </div>
        </form>
    </x-card>
@endsection
