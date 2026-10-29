@extends('layouts.app')

@section('title', 'Supplier')

@section('content')
    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
        {{-- Header --}}
        <div class="p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">Daftar Supplier</h2>
                <p class="text-sm text-slate-500">Kelola data supplier barang inventaris Anda.</p>
            </div>
            <a href="{{ route('supplier.create') }}"
               class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                <span>+</span> Tambah Supplier
            </a>
        </div>

        {{-- Search --}}
        <div class="p-6 border-b border-slate-200">
            <form method="GET" action="{{ route('supplier.index') }}" class="flex gap-2">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari nama atau email supplier..."
                    class="flex-1 rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <select name="status" class="rounded-lg border-slate-300 text-sm" aria-label="Status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-900 transition">
                    Cari
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('supplier.index') }}" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-200 transition">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">Nama Supplier</th>
                        <th class="px-6 py-3">Kontak</th>
                        <th class="px-6 py-3">Alamat</th>
                        <th class="px-6 py-3 text-center">Jumlah Barang</th>
                        <th class="px-6 py-3 text-center">Status</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($suppliers as $supplier)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-800"><a href="{{ route('supplier.show', $supplier) }}" class="hover:underline">{{ $supplier->name }}</a></td>
                            <td class="px-6 py-4 text-slate-500">
                                <div>{{ $supplier->phone ?? '-' }}</div>@if($supplier->contact_person)<div class="text-xs text-slate-400">CP: {{ $supplier->contact_person }}</div>@endif
                                <div class="text-xs text-slate-400">{{ $supplier->email ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 max-w-xs truncate">{{ $supplier->address ?? '-' }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                    {{ $supplier->barangs_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center"><span class="sf-badge {{ $supplier->is_active ? 'ok' : 'mute' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('supplier.edit', $supplier) }}"
                                       class="px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg hover:bg-amber-100 transition">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('supplier.destroy', $supplier) }}"
                                          onsubmit="return confirm('Yakin ingin menghapus supplier ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100 transition">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                                Belum ada data supplier.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($suppliers->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
@endsection