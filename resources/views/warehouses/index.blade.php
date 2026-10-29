@extends('layouts.app')
@section('title', 'Gudang')
@section('content')
    <x-card title="Gudang" subtitle="Stok disimpan per gudang; total stok barang adalah jumlah seluruh gudang.">
        <x-slot:actions><a href="{{ route('gudang.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"><span>+</span> Tambah Gudang</a></x-slot:actions>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Kode</th><th class="px-6 py-3">Nama</th><th class="px-6 py-3 text-center">Jenis Barang</th><th class="px-6 py-3 text-center">Total Unit</th><th class="px-6 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($warehouses as $w)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">{{ $w->code }}</td>
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $w->name }} @if($w->is_default)<span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700">Default</span>@endif<div class="text-xs text-slate-400">{{ $w->address }}</div></td>
                            <td class="px-6 py-4 text-center">{{ $w->product_count }}</td>
                            <td class="px-6 py-4 text-center">{{ (int) $w->total_quantity }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('gudang.show', $w) }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">Stok</a>
                                    <a href="{{ route('gudang.edit', $w) }}" class="px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg hover:bg-amber-100">Edit</a>
                                    <form method="POST" action="{{ route('gudang.destroy', $w) }}" onsubmit="return confirm('Hapus gudang ini?');">@csrf @method('DELETE')<button class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100">Hapus</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
