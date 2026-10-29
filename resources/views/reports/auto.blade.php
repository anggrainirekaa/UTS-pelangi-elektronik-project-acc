@extends('layouts.app')
@section('title', 'Laporan Otomatis')
@section('content')
<x-card title="Laporan {{ $periods[$period] }}" :subtitle="$report['start']->format('d M Y').' – '.$report['end']->format('d M Y').' · dibuat dari data saat ini; tidak dikirim ke luar.'">
    <x-slot:actions>
        <div class="sf-segment">@foreach($periods as $k => $l)<a href="{{ route('laporan.otomatis', ['period' => $k]) }}" class="{{ $period === $k ? 'on' : '' }}">{{ $l }}</a>@endforeach</div>
        <a class="sf-btn sf-btn-ghost sf-btn-sm" href="{{ route('laporan.otomatis.export', ['period' => $period, 'format' => 'csv']) }}">CSV</a>
        <a class="sf-btn sf-btn-ghost sf-btn-sm" href="{{ route('laporan.otomatis.export', ['period' => $period, 'format' => 'xlsx']) }}">Excel</a>
    </x-slot:actions>
    <div class="sf-card-body grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($report['summary'] as [$label, $value])
            <div class="sf-kpi"><div class="sf-kpi-label">{{ $label }}</div><div class="sf-kpi-value">{{ is_int($value) && str_contains($label, 'Nilai') || str_contains($label, 'Penjualan') ? money($value) : number_format($value, 0, ',', '.') }}</div></div>
        @endforeach
    </div>
</x-card>
<div class="grid gap-4 lg:grid-cols-2 mt-4">
    <x-card title="Produk terlaris">@forelse($report['top'] as $t)<div class="sf-result" style="border-bottom:1px solid var(--sf-border)"><span class="flex-1">{{ $t->name }}</span><span class="font-semibold">{{ $t->qty }} unit</span><span class="sf-num" style="color:var(--sf-muted)">{{ money($t->revenue) }}</span></div>@empty<x-empty-state icon="chart" title="Belum ada penjualan pada periode ini" />@endforelse</x-card>
    <x-card title="Indikator anomali">@forelse($report['anomalies'] as $a)<div class="sf-result" style="border-bottom:1px solid var(--sf-border)"><span class="sf-badge {{ $a->severity === 'high' ? 'bad' : 'warn' }}">{{ $a->severity === 'high' ? 'Tinggi' : 'Sedang' }}</span><span class="flex-1 text-sm">{{ $a->title }}</span></div>@empty<x-empty-state icon="shield" title="Tidak ada anomali terbuka" />@endforelse</x-card>
</div>
@endsection
