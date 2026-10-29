@extends('layouts.app')

@section('title', 'Penjualan')

@section('content')
    <x-card title="Daftar Penjualan" subtitle="Penjualan toko dan pesanan online pelanggan.">
        <x-slot:actions>
            <a href="{{ route('penjualan.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"><span>+</span> Penjualan Baru</a>
        </x-slot:actions>

        <div class="p-6 border-b border-slate-200">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-6 gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="No. penjualan / pelanggan" class="rounded-lg border-slate-300 text-sm sm:col-span-2">
                <select name="status" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua Status</option>
                    @foreach(['pending' => 'Menunggu', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'] as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach
                </select>
                <select name="channel" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua Kanal</option>
                    <option value="pos" @selected(request('channel') === 'pos')>Toko</option>
                    <option value="online" @selected(request('channel') === 'online')>Online</option>
                </select>
                <input type="date" name="start_date" value="{{ request('start_date') }}" class="rounded-lg border-slate-300 text-sm">
                <input type="date" name="end_date" value="{{ request('end_date') }}" class="rounded-lg border-slate-300 text-sm">
                <div class="sm:col-span-6 flex gap-2">
                    <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Terapkan</button>
                    @if(request()->query())<a href="{{ route('penjualan.index') }}" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm rounded-lg">Reset</a>@endif
                </div>
            </form>
            @error('end_date')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr>
                    <th class="px-6 py-3">No. Penjualan</th><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Pelanggan</th><th class="px-6 py-3">Kanal</th>
                    <th class="px-6 py-3 text-right">Total</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Bayar</th><th class="px-6 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sales as $sale)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $sale->sale_number }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $sale->sold_at->format('d M Y H:i') }}</td>
                            <td class="px-6 py-4">{{ $sale->customer->name ?? 'Umum' }}</td>
                            <td class="px-6 py-4"><x-status-badge :status="$sale->channel" /></td>
                            <td class="px-6 py-4 text-right font-medium">{{ money($sale->total) }}</td>
                            <td class="px-6 py-4"><x-status-badge :status="$sale->status" /></td>
                            <td class="px-6 py-4"><x-status-badge :status="$sale->payment_status" /></td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('penjualan.show', $sale) }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Belum ada penjualan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sales->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $sales->links() }}</div>@endif
    </x-card>
@endsection
