@extends('layouts.app')
@section('title', $title)
@section('content')
@php $f = $filters; $has = fn ($k) => in_array($k, $fields, true); @endphp
<div class="space-y-5">
    <form method="GET" action="{{ route('laporan.data', $type) }}" class="sf-card sf-card-body sf-noprint grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
        @if($needsPeriod)
            <div class="sm:col-span-2 lg:col-span-4 sf-segment" role="group" aria-label="Periode cepat" style="width:fit-content">
                @foreach(['3d' => '3 hari', '7d' => '1 minggu', '30d' => '1 bulan', '12m' => '1 tahun'] as $k => $l)
                    <a href="{{ route('laporan.data', [$type, 'preset' => $k]) }}">{{ $l }}</a>
                @endforeach
            </div>
            <div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="start_date">Dari tanggal</label><input id="start_date" type="date" name="start_date" value="{{ $f['start_date'] }}" class="sf-input"></div>
            <div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="end_date">Sampai tanggal</label><input id="end_date" type="date" name="end_date" value="{{ $f['end_date'] }}" class="sf-input"></div>
        @endif
        @if($has('product'))<div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="product">Produk / SKU</label><input id="product" type="search" name="product" value="{{ $f['product'] ?? '' }}" class="sf-input" placeholder="Nama atau SKU"></div>@endif
        @if($has('category_id'))<div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="category_id">Kategori</label><select id="category_id" name="category_id" class="sf-select"><option value="">Semua</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(($f['category_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>@endif
        @if($has('supplier_id'))<div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="supplier_id">Supplier</label><select id="supplier_id" name="supplier_id" class="sf-select"><option value="">Semua</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(($f['supplier_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>@endif
        @if($has('user_id'))<div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="user_id">Staff</label><select id="user_id" name="user_id" class="sf-select"><option value="">Semua</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected(($f['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>@endif
        @if($has('status') && $statusOptions)<div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="status">Status</label><select id="status" name="status" class="sf-select"><option value="">Semua</option>@foreach($statusOptions as $k => $l)<option value="{{ $k }}" @selected(($f['status'] ?? '') === (string) $k)>{{ $l }}</option>@endforeach</select></div>@endif
        @if($has('move_type'))<div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="move_type">Jenis pergerakan</label><select id="move_type" name="move_type" class="sf-select"><option value="">Semua</option>@foreach($moveTypes as $k => $l)<option value="{{ $k }}" @selected(($f['move_type'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>@endif
        <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4">
            <button class="sf-btn sf-btn-primary" type="submit">Terapkan filter</button>
            <a class="sf-btn sf-btn-ghost" href="{{ route('laporan.data', $type) }}">Reset</a>
            <button type="button" onclick="window.print()" class="no-loading sf-btn sf-btn-ghost"><x-icon name="printer" :size="16" /> Cetak</button>
            <a class="sf-btn sf-btn-ghost" href="{{ route('laporan.data.csv', array_merge(['type' => $type], request()->query())) }}"><x-icon name="download" :size="16" /> Ekspor CSV</a>
        </div>
        @if($errors->any())<p class="sm:col-span-2 lg:col-span-4 text-sm" style="color:var(--sf-danger)">{{ $errors->first() }}</p>@endif
    </form>

    <div class="sf-print-only"><h2 style="font-size:16px;font-weight:700">{{ $title }}</h2><p style="font-size:12px">Periode: {{ $periodLabel }}</p></div>

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4">
        @foreach($summary as [$l, $val])
            <div class="sf-kpi"><div class="sf-kpi-label">{{ $l }}</div><div class="sf-kpi-value" style="font-size:20px">{{ $val }}</div></div>
        @endforeach
    </div>

    <div class="sf-card">
        <div class="sf-card-header"><div><div class="sf-card-title">{{ $title }}</div><div class="sf-card-sub">Periode: {{ $periodLabel }} · {{ count($rows) }} baris</div></div></div>
        <div class="overflow-x-auto">
            <table class="sf-table">
                <thead><tr>@foreach($columns as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($columns) }}"><x-empty-state icon="file" title="Tidak ada data" message="Ubah filter atau periode untuk melihat data lain. Periode tanpa transaksi tidak menimbulkan error." /></td></tr>
                    @endforelse
                </tbody>
                @if($rows && $totals)<tfoot><tr style="font-weight:700">@foreach($totals as $cell)<td>{{ $cell }}</td>@endforeach</tr></tfoot>@endif
            </table>
        </div>
        @if($truncated)<p class="px-5 py-3 text-xs" style="color:var(--sf-muted)">Hanya {{ count($rows) }} baris pertama yang ditampilkan. Persempit filter untuk melihat sisanya.</p>@endif
    </div>
</div>
@endsection
