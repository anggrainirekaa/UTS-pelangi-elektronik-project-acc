@extends('layouts.app')
@section('title', 'Laporan Penjualan')
@section('content')
    <div class="space-y-6">
        <x-card title="Laporan Penjualan" subtitle="Hanya transaksi berstatus selesai.">
            @include('reports._period')
            <div class="p-6 grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div class="rounded-lg bg-slate-50 p-4"><p class="text-slate-500">Transaksi</p><p class="text-xl font-bold">{{ $summary['transactions'] }}</p></div>
                <div class="rounded-lg bg-slate-50 p-4"><p class="text-slate-500">Pendapatan</p><p class="text-xl font-bold">{{ money($summary['revenue']) }}</p></div>
                <div class="rounded-lg bg-emerald-50 p-4"><p class="text-slate-500">Sudah Dibayar</p><p class="text-xl font-bold text-emerald-700">{{ money($summary['paid']) }}</p></div>
                <div class="rounded-lg bg-amber-50 p-4"><p class="text-slate-500">Piutang</p><p class="text-xl font-bold text-amber-700">{{ money($summary['unpaid']) }}</p></div>
            </div>
        </x-card>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Per Hari">
                <table class="w-full text-sm text-left"><thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3 text-center">Transaksi</th><th class="px-6 py-3 text-right">Pendapatan</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($perDay as $d)<tr><td class="px-6 py-3">{{ \Illuminate\Support\Carbon::parse($d->day)->format('d M Y') }}</td><td class="px-6 py-3 text-center">{{ $d->transactions }}</td><td class="px-6 py-3 text-right">{{ money($d->revenue) }}</td></tr>
                        @empty<tr><td colspan="3" class="px-6 py-8 text-center text-slate-400">Tidak ada data.</td></tr>@endforelse
                    </tbody></table>
            </x-card>
            <x-card title="10 Produk Teratas">
                <table class="w-full text-sm text-left"><thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Produk</th><th class="px-6 py-3 text-center">Terjual</th><th class="px-6 py-3 text-right">Pendapatan</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($topProducts as $p)<tr><td class="px-6 py-3">{{ $p->name }}<div class="text-xs text-slate-400">{{ $p->code }}</div></td><td class="px-6 py-3 text-center">{{ $p->qty }}</td><td class="px-6 py-3 text-right">{{ money($p->revenue) }}</td></tr>
                        @empty<tr><td colspan="3" class="px-6 py-8 text-center text-slate-400">Tidak ada data.</td></tr>@endforelse
                    </tbody></table>
            </x-card>
        </div>
    </div>
@endsection
