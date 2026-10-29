@extends('layouts.app')
@section('title', 'Analitik')
@section('content')
@php
    $line = fn ($labels, $values, $label) => ['type' => 'line', 'money' => true, 'data' => ['labels' => $labels, 'datasets' => [['label' => $label, 'data' => $values, 'borderColor' => '#1769E0', 'backgroundColor' => 'rgba(23,105,224,.12)', 'fill' => true, 'tension' => .35, 'borderWidth' => 2.5, 'pointRadius' => count($labels) > 40 ? 0 : 3]]]];
    $inout = fn ($m) => ['type' => 'bar', 'data' => ['labels' => $m['labels'], 'datasets' => [['label' => 'Masuk', 'data' => $m['in'], 'backgroundColor' => '#159A68', 'borderRadius' => 6], ['label' => 'Keluar', 'data' => $m['out'], 'backgroundColor' => '#3987F7', 'borderRadius' => 6]]]];
    $palette = ['#1769E0', '#3987F7', '#5E9DF8', '#159A68', '#E7A323', '#8FBBF9', '#12315A', '#D94242'];
@endphp
<form method="GET" class="sf-card sf-card-body grid gap-3 sm:grid-cols-6 mb-5" style="padding:16px">
    <select name="preset" class="sf-select" aria-label="Rentang cepat"><option value="">Rentang cepat</option>@foreach(['3d' => '3 hari', '7d' => '1 minggu', '30d' => '1 bulan', '12m' => '1 tahun'] as $k => $l)<option value="{{ $k }}" @selected(($input['preset'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
    <input type="date" name="from" value="{{ $input['from'] ?? '' }}" class="sf-input" aria-label="Dari"><input type="date" name="to" value="{{ $input['to'] ?? '' }}" class="sf-input" aria-label="Sampai">
    <select name="granularity" class="sf-select" aria-label="Granularitas"><option value="auto">Otomatis</option>@foreach($granularities as $k => $l)<option value="{{ $k }}" @selected(($input['granularity'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
    <select name="warehouse_id" class="sf-select" aria-label="Gudang (pergerakan)"><option value="">Semua gudang</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(($input['warehouse_id'] ?? '') == $w->id)>{{ $w->name }}</option>@endforeach</select>
    <div class="flex gap-2"><button class="sf-btn sf-btn-primary flex-1">Terapkan</button><a class="sf-btn sf-btn-ghost" href="{{ route('analitik.export', array_merge(request()->query(), ['format' => 'csv'])) }}" title="Ekspor CSV">CSV</a></div>
</form>
<p class="text-sm mb-4" style="color:var(--sf-muted)">Periode {{ $start->format('d M Y') }} – {{ $end->format('d M Y') }} · {{ $granularities[$granularity] }}</p>

<div class="grid gap-4 lg:grid-cols-2">
    @foreach([
        ['Penjualan bersih', $line($sales['labels'], $sales['values'], 'Penjualan (Rp)'), $sales['labels'], [['Penjualan', $sales['values'], true]]],
        ['Stok masuk vs keluar', $inout($movement), $movement['labels'], [['Masuk', $movement['in'], false], ['Keluar', $movement['out'], false]]],
        ['Nilai persediaan', $line($inventory['labels'], $inventory['values'], 'Nilai persediaan (Rp)'), $inventory['labels'], [['Nilai', $inventory['values'], true]]],
    ] as [$title, $cfg, $labels, $cols])
        <x-card :title="$title">
            @if(! collect($cols)->flatMap(fn ($c) => $c[1])->filter()->count())
                <x-empty-state icon="chart" title="Belum ada data" message="Tidak ada data pada rentang ini." />
            @else
            <div class="sf-card-body" x-data="sfChart(@js($cfg))"><div style="height:260px"><canvas x-ref="canvas" role="img" aria-label="{{ $title }}"></canvas></div></div>
            <details class="px-5 pb-4 text-sm"><summary class="cursor-pointer font-semibold" style="color:var(--sf-primary)">Tabel data</summary>
                <div class="overflow-x-auto max-h-64"><table class="sf-table mt-2"><thead><tr><th>Periode</th>@foreach($cols as $c)<th class="sf-num">{{ $c[0] }}</th>@endforeach</tr></thead><tbody>
                    @foreach($labels as $i => $l)<tr><td>{{ $l }}</td>@foreach($cols as $c)<td class="sf-num">{{ $c[2] ? money($c[1][$i] ?? 0) : number_format($c[1][$i] ?? 0, 0, ',', '.') }}</td>@endforeach</tr>@endforeach
                </tbody></table></div></details>
            @endif
        </x-card>
    @endforeach

    <x-card title="Komposisi kategori" subtitle="Nilai persediaan per kategori (harga pokok).">
        @if($categories->isEmpty())<x-empty-state icon="tag" title="Belum ada kategori berisi produk" />@else
        <div class="sf-card-body" x-data="sfChart(@js(['type' => 'doughnut', 'money' => true, 'data' => ['labels' => $categories->pluck('name'), 'datasets' => [['data' => $categories->pluck('value'), 'backgroundColor' => array_slice(array_pad($palette, $categories->count(), '#9BA9BF'), 0, $categories->count()), 'borderWidth' => 0]]]]))"><div style="height:260px"><canvas x-ref="canvas" role="img" aria-label="Komposisi kategori"></canvas></div></div>
        <details class="px-5 pb-4 text-sm"><summary class="cursor-pointer font-semibold" style="color:var(--sf-primary)">Tabel data</summary><table class="sf-table mt-2"><thead><tr><th>Kategori</th><th class="sf-num">Produk</th><th class="sf-num">Unit</th><th class="sf-num">Nilai</th></tr></thead><tbody>@foreach($categories as $c)<tr><td>{{ $c['name'] }}</td><td class="sf-num">{{ $c['products'] }}</td><td class="sf-num">{{ number_format($c['units'], 0, ',', '.') }}</td><td class="sf-num">{{ money($c['value']) }}</td></tr>@endforeach</tbody></table></details>@endif
    </x-card>

    <x-card title="Pergerakan per gudang">
        @if(! array_sum($warehouseChart['in']) && ! array_sum($warehouseChart['out']))<x-empty-state icon="warehouse" title="Belum ada pergerakan" />@else
        <div class="sf-card-body" x-data="sfChart(@js($inout($warehouseChart)))"><div style="height:260px"><canvas x-ref="canvas" role="img" aria-label="Pergerakan per gudang"></canvas></div></div>@endif
        <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>Gudang</th><th class="sf-num">Unit</th><th class="sf-num">Nilai</th><th class="sf-num">Masuk</th><th class="sf-num">Keluar</th></tr></thead><tbody>@foreach($warehousePerf as $w)<tr><td>{{ $w['warehouse']->name }}</td><td class="sf-num">{{ number_format($w['units'], 0, ',', '.') }}</td><td class="sf-num">{{ money($w['value']) }}</td><td class="sf-num">{{ $w['in'] }}</td><td class="sf-num">{{ $w['out'] }}</td></tr>@endforeach</tbody></table></div>
    </x-card>

    <x-card title="Produk terlaris">
        @if($top->isEmpty())<x-empty-state icon="box" title="Belum ada penjualan" />@else
        <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>#</th><th>Produk</th><th class="sf-num">Terjual</th><th class="sf-num">Pendapatan</th></tr></thead><tbody>@foreach($top as $i => $t)<tr><td>{{ $i + 1 }}</td><td>{{ $t->name }}</td><td class="sf-num">{{ $t->qty }}</td><td class="sf-num">{{ money($t->revenue) }}</td></tr>@endforeach</tbody></table></div>@endif
    </x-card>

    <x-card title="Performa supplier" subtitle="Fill rate = diterima ÷ dipesan. Lead time dari persetujuan ke penerimaan terakhir.">
        @if($suppliers->isEmpty())<x-empty-state icon="truck" title="Belum ada pesanan pada rentang ini" />@else
        <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>Supplier</th><th class="sf-num">PO</th><th class="sf-num">Nilai diterima</th><th class="sf-num">Fill rate</th><th class="sf-num">Lead time</th></tr></thead><tbody>@foreach($suppliers as $s)<tr><td>{{ $s['supplier'] }}</td><td class="sf-num">{{ $s['orders'] }}</td><td class="sf-num">{{ money($s['value']) }}</td><td class="sf-num">{{ $s['fill_rate'] !== null ? number_format($s['fill_rate'], 1, ',', '.').'%' : 'Belum cukup data' }}</td><td class="sf-num">{{ $s['avg_lead_days'] !== null ? $s['avg_lead_days'].' hr' : '—' }}@if($s['promised_lead_days'])<span class="text-xs" style="color:var(--sf-muted)"> / janji {{ $s['promised_lead_days'] }}</span>@endif</td></tr>@endforeach</tbody></table></div>@endif
    </x-card>
</div>
@endsection
