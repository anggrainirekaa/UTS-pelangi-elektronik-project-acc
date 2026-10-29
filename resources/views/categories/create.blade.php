@extends('layouts.app')

@section('title', 'Tambah Kategori')

@section('content')
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 max-w-2xl">
        <div class="p-6 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Tambah Kategori Baru</h2>
            <p class="text-sm text-slate-500">Isi form di bawah untuk menambahkan kategori.</p>
        </div>

        <form method="POST" action="{{ route('kategori.store') }}" class="p-6 space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">Nama Kategori</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="{{ old('name') }}"
                    class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-400 @enderror"
                    placeholder="Contoh: Elektronik"
                >
                @error('name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1.5">Deskripsi (Opsional)</label>
                <textarea
                    name="description"
                    id="description"
                    rows="3"
                    class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-400 @enderror"
                    placeholder="Deskripsi singkat tentang kategori ini..."
                >{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>


            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('is_active', true))>
                    Aktif
                </label>
                <p class="text-xs text-slate-500 mt-1">Data nonaktif tidak muncul di pilihan transaksi baru, tetapi riwayatnya tetap tersimpan.</p>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Simpan Kategori
                </button>
                <a href="{{ route('kategori.index') }}" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-200 transition">
                    Batal
                </a>
            </div>
        </form>
    </div>
@endsection