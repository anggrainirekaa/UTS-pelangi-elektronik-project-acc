@extends('layouts.app')
@section('title', 'Laba Rugi')
@section('content')
    <x-card title="Laporan Laba Rugi" subtitle="Dibaca dari jurnal. Pendapatan − HPP − Beban." class="max-w-3xl">
        @include('reports._period')
        <div class="p-6 text-sm space-y-1">
            <p class="font-semibold text-slate-700">Pendapatan</p>
            @forelse($revenue as $r)<div class="flex justify-between pl-4"><span>{{ $r['code'] }} {{ $r['name'] }}</span><span>{{ money($r['balance']) }}</span></div>@empty<p class="pl-4 text-slate-400">-</p>@endforelse
            <div class="flex justify-between font-semibold border-t pt-1"><span>Total Pendapatan</span><span>{{ money($totalRevenue) }}</span></div>

            <p class="font-semibold text-slate-700 pt-3">Harga Pokok Penjualan</p>
            <div class="flex justify-between pl-4"><span>HPP</span><span>({{ money($totalCogs) }})</span></div>
            <div class="flex justify-between font-semibold border-t pt-1"><span>Laba Kotor</span><span>{{ money($grossProfit) }}</span></div>

            <p class="font-semibold text-slate-700 pt-3">Beban</p>
            @forelse($expenses as $e)<div class="flex justify-between pl-4"><span>{{ $e['code'] }} {{ $e['name'] }}</span><span>({{ money($e['balance']) }})</span></div>@empty<p class="pl-4 text-slate-400">-</p>@endforelse
            <div class="flex justify-between font-semibold border-t pt-1"><span>Total Beban</span><span>({{ money($totalExpenses) }})</span></div>

            <div class="flex justify-between text-lg font-bold border-t-2 border-slate-300 pt-3 mt-3 {{ $netProfit >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                <span>{{ $netProfit >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}</span><span>{{ money($netProfit) }}</span>
            </div>
        </div>
    </x-card>
@endsection
