@extends('layouts.app')

@section('title', 'Penyesuaian Stok')

@section('content')
    <x-card title="Penyesuaian Stok Keluar" subtitle="Barang keluar non-penjualan: pemakaian internal, rusak/hilang.">
        <x-slot:actions>
            <a href="{{ route('stok-keluar.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"><span>+</span> Catat Penyesuaian</a>
        </x-slot:actions>
        <div class="p-6 border-b border-slate-200">
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama atau kode barang..." class="flex-1 rounded-lg border-slate-300 text-sm">
                <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Cari</button>
                @if(request('search'))<a href="{{ route('stok-keluar.index') }}" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm rounded-lg">Reset</a>@endif
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr>
                    <th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Barang</th><th class="px-6 py-3">Gudang</th><th class="px-6 py-3">Alasan</th><th class="px-6 py-3 text-center">Jumlah</th><th class="px-6 py-3">Oleh</th><th class="px-6 py-3 text-right">Aksi</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($stockOuts as $stockOut)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-500">{{ $stockOut->date->format('d M Y') }}</td>
                            <td class="px-6 py-4"><div class="font-medium text-slate-800">{{ $stockOut->barang->name ?? '-' }}</div><div class="text-xs text-slate-400">{{ $stockOut->barang->code ?? '' }}</div></td>
                            <td class="px-6 py-4 text-slate-500">{{ $stockOut->warehouse->name ?? '-' }}</td>
                            <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ $stockOut->purpose_label }}</span></td>
                            <td class="px-6 py-4 text-center"><span class="px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700">-{{ $stockOut->quantity }} {{ $stockOut->barang->unit ?? '' }}</span></td>
                            <td class="px-6 py-4 text-slate-500">{{ $stockOut->user->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-right">
                                @can('cancel-transactions')
                                    <form method="POST" action="{{ route('stok-keluar.destroy', $stockOut) }}" onsubmit="return confirm('Hapus penyesuaian ini? Stok akan dikembalikan.');">
                                        @csrf @method('DELETE')
                                        <button class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100">Hapus</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-slate-400">Belum ada penyesuaian stok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($stockOuts->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $stockOuts->links() }}</div>@endif
    </x-card>
@endsection
