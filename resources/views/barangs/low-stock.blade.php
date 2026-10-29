@extends('layouts.app')

@section('title', 'Stok Menipis')

@section('content')
    <x-card title="Daftar Stok Menipis" subtitle="Barang dengan stok kurang dari atau sama dengan stok minimum.">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr>
                    <th class="px-6 py-3">Barang</th><th class="px-6 py-3">Supplier</th><th class="px-6 py-3 text-center">Stok</th><th class="px-6 py-3 text-center">Minimum</th><th class="px-6 py-3 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($barangs as $barang)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4"><a href="{{ route('barang.show', $barang) }}" class="font-medium text-slate-800 hover:underline">{{ $barang->name }}</a><div class="text-xs text-slate-400">{{ $barang->code }} · {{ $barang->category->name ?? '-' }}</div></td>
                            <td class="px-6 py-4 text-slate-500">{{ $barang->supplier->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-center"><span class="px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700">{{ $barang->stock }} {{ $barang->unit }}</span></td>
                            <td class="px-6 py-4 text-center text-slate-500">{{ $barang->minimum_stock }}</td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('pembelian.create', ['barang_id' => $barang->id]) }}" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-100">Buat Pembelian</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">Semua stok aman. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($barangs->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $barangs->links() }}</div>@endif
    </x-card>
@endsection
