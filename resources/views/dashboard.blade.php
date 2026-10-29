@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $isMgmt = auth()->user()->isManagement();
    $n = fn ($v) => number_format($v, 0, ',', '.');
    $kpis = [
        ['Produk aktif', $n($stats['active_products']['value']), 'box', 'Jumlah produk berstatus aktif di katalog.', route('barang.index', ['status' => 'active']), $stats['active_products'], 'up'],
        ['Kategori aktif', $n($stats['categories']['value']), 'tag', 'Jumlah kategori produk berstatus aktif.', $isMgmt ? route('kategori.index') : null, null, 'up'],
        ['Supplier aktif', $n($stats['suppliers']['value']), 'truck', 'Jumlah pemasok berstatus aktif.', $isMgmt ? route('supplier.index') : null, null, 'up'],
        ['Total stok', $n($stats['total_stock']['value']), 'warehouse', 'Jumlah seluruh unit di semua gudang saat ini.', route('barang.index', ['sort' => 'stock_desc']), $stats['total_stock'], 'up'],
        ['Omzet penjualan', money($stats['sales']['value']), 'chart', 'Penjualan bersih (selesai, dikurangi retur) pada periode terpilih. Omzet bukan laba.', route('penjualan.index', ['status' => 'completed']), $stats['sales'], 'up'],
        ['Jumlah transaksi', $n($stats['transactions']['value']), 'receipt', 'Jumlah penjualan selesai pada periode. Rata-rata per transaksi: '.money($stats['average']['value']).'.', route('penjualan.index', ['status' => 'completed']), $stats['transactions'], 'up'],
        ['Total pembelian', money($stats['purchases']['value']), 'download', 'Nilai barang yang sudah diterima dari supplier pada periode ('.$stats['purchase_count']['value'].' pembelian).', route('pembelian.index'), $stats['purchases'], 'up'],
        ['Stok menipis', $n($stats['low_stock']['value']), 'alert', 'Produk dengan stok di bawah atau sama dengan stok minimum (belum habis).', route('stok-menipis.index'), null, 'down'],
    ];
    if ($isMgmt) {
        $kpis[] = ['Laba kotor (estimasi)', money($stats['gross_profit']['value']), 'wallet', 'Selisih harga jual dan harga pokok (harga beli saat transaksi) pada penjualan selesai. Belum dikurangi biaya operasional.', route('laporan.laba-rugi'), null, 'up'];
        $kpis[] = ['Nilai persediaan', money($stats['inventory_value']['value']), 'wallet', 'Saldo akun persediaan pada jurnal (stok × harga pokok).', route('analitik.index'), $stats['inventory_value'], 'up'];
    }
@endphp

{{-- Filter periode: 3 hari, 1 minggu, 1 bulan, 1 tahun, atau rentang tanggal kustom --}}
<form method="GET" action="{{ route('dashboard') }}" class="sf-card sf-card-body flex flex-wrap items-end gap-3 mb-5" aria-label="Filter periode dashboard">
    <div class="sf-segment" role="group" aria-label="Periode cepat">
        @foreach(['3d' => '3 hari', '7d' => '1 minggu', '30d' => '1 bulan', '12m' => '1 tahun'] as $k => $l)
            <a href="{{ route('dashboard', ['preset' => $k]) }}" class="{{ $period['preset'] === $k ? 'on' : '' }}" @if($period['preset'] === $k) aria-current="true" @endif>{{ $l }}</a>
        @endforeach
    </div>
    <div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="f-from">Dari tanggal</label><input id="f-from" type="date" name="from" value="{{ $period['from'] }}" class="sf-input" style="min-height:38px"></div>
    <div><label class="block text-xs mb-1" style="color:var(--sf-muted)" for="f-to">Sampai tanggal</label><input id="f-to" type="date" name="to" value="{{ $period['to'] }}" class="sf-input" style="min-height:38px"></div>
    <button class="sf-btn sf-btn-primary" type="submit">Terapkan</button>
    <p class="text-xs ml-auto" style="color:var(--sf-muted)">Periode: <strong>{{ $period['label'] }}</strong> · dibanding {{ $stats['days'] }} hari sebelumnya</p>
</form>

<div class="mb-5">
    <h1 class="sf-page-title">Halo, {{ explode(' ', auth()->user()->name)[0] }} 👋</h1>
    <p class="text-sm" style="color:var(--sf-muted)">Ringkasan operasional dari data nyata. {{ now()->translatedFormat('l, d F Y') }}.</p>
</div>

{{-- Pekerjaan menunggu --}}
@if(array_sum($pending) > 0)
    <div class="flex flex-wrap gap-2 mb-5">
        @if($pending['orders'])<a class="sf-badge warn" href="{{ route('penjualan.index', ['status' => 'pending']) }}">{{ $pending['orders'] }} pesanan online menunggu</a>@endif
        @if($pending['purchases'])<a class="sf-badge warn" href="{{ route('pembelian.index', ['status' => 'pending']) }}">{{ $pending['purchases'] }} PO menunggu persetujuan</a>@endif
        @if($pending['transfers'])<a class="sf-badge warn" href="{{ route('transfer-stok.index') }}">{{ $pending['transfers'] }} transfer menunggu persetujuan</a>@endif
        @if($pending['opnames'])<a class="sf-badge warn" href="{{ route('opname.index', ['status' => 'submitted']) }}">{{ $pending['opnames'] }} opname menunggu persetujuan</a>@endif
    </div>
@endif

<div class="grid gap-4 grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 mb-4">
    @foreach($kpis as [$label, $value, $icon, $tip, $href, $card, $good])
        <x-stat-card :label="$label" :value="$value" :icon="$icon" :tip="$tip" :href="$href" :delta="$card ? ['pct' => $card['pct'], 'dir' => $card['dir'], 'good' => $good] : null" />
    @endforeach
</div>

{{-- Grafik prioritas --}}
<div x-data="dashboardCharts({ url: '{{ route('dashboard.data') }}', preset: '{{ $period['preset'] ?: '30d' }}', from: '{{ $period['from'] }}', to: '{{ $period['to'] }}' })" class="space-y-4 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="sf-card-title">Grafik utama</h2>
        <div class="flex flex-wrap items-center gap-2">
            <select x-model="granularity" @change="load()" class="sf-select" style="width:auto;min-height:36px" aria-label="Granularitas">
                <option value="auto">Otomatis</option><option value="day">Harian</option><option value="week">Mingguan</option><option value="month">Bulanan</option><option value="year">Tahunan</option>
            </select>
        </div>
    </div>

    <template x-if="loading"><div class="grid gap-4 lg:grid-cols-2"><div class="sf-skel" style="height:300px"></div><div class="sf-skel" style="height:300px"></div></div></template>
    <template x-if="error"><x-empty-state icon="alert" title="Grafik gagal dimuat" message="Periksa koneksi lalu coba lagi."><button class="sf-btn sf-btn-soft" @click="load()">Muat ulang</button></x-empty-state></template>
    <template x-if="!loading && !error && empty"><x-card><x-empty-state icon="chart" title="Belum ada data pada rentang ini" message="Grafik akan muncul setelah ada penjualan atau pergerakan stok." /></x-card></template>

    <div x-show="!loading && !error && !empty" x-cloak class="grid gap-4 lg:grid-cols-2">
        <x-card title="Tren penjualan & pembelian" subtitle="Penjualan bersih (dikurangi retur) dan nilai pembelian diterima. Klik legenda untuk menyembunyikan seri." class="lg:col-span-2">
            <div class="sf-card-body"><div style="height:280px"><canvas x-ref="sales" role="img" aria-label="Grafik garis penjualan"></canvas></div></div>
            <details class="px-5 pb-4 text-sm"><summary class="cursor-pointer font-semibold" style="color:var(--sf-primary)">Lihat tabel data</summary>
                <table class="sf-table mt-2"><thead><tr><th>Periode</th><th class="sf-num">Penjualan</th><th class="sf-num">Pembelian</th></tr></thead><tbody><template x-for="r in tables.sales" :key="r.label"><tr><td x-text="r.label"></td><td class="sf-num" x-text="sfFormat.rupiah(r.value)"></td><td class="sf-num" x-text="sfFormat.rupiah(r.purchase)"></td></tr></template></tbody></table></details>
        </x-card>
        <x-card title="Stok masuk vs keluar" subtitle="Transfer antar gudang tidak dihitung.">
            <div class="sf-card-body"><div style="height:260px"><canvas x-ref="movement" role="img" aria-label="Grafik batang stok masuk dan keluar"></canvas></div></div>
            <details class="px-5 pb-4 text-sm"><summary class="cursor-pointer font-semibold" style="color:var(--sf-primary)">Lihat tabel data</summary>
                <table class="sf-table mt-2"><thead><tr><th>Periode</th><th class="sf-num">Masuk</th><th class="sf-num">Keluar</th></tr></thead><tbody><template x-for="r in tables.movement" :key="r.label"><tr><td x-text="r.label"></td><td class="sf-num" x-text="sfFormat.number(r.in)"></td><td class="sf-num" x-text="sfFormat.number(r.out)"></td></tr></template></tbody></table></details>
        </x-card>
        <x-card title="Produk terlaris" subtitle="Berdasarkan unit terjual pada rentang terpilih.">
            <div class="sf-card-body"><div style="height:260px"><canvas x-ref="top" role="img" aria-label="Grafik batang produk terlaris"></canvas></div></div>
            <details class="px-5 pb-4 text-sm"><summary class="cursor-pointer font-semibold" style="color:var(--sf-primary)">Lihat tabel data</summary>
                <table class="sf-table mt-2"><thead><tr><th>Produk</th><th class="sf-num">Terjual</th></tr></thead><tbody><template x-for="r in tables.top" :key="r.name"><tr><td x-text="r.name"></td><td class="sf-num" x-text="sfFormat.number(r.qty)"></td></tr></template></tbody></table></details>
        </x-card>
    </div>
    <p class="text-sm"><a href="{{ route('analitik.index') }}" class="font-semibold" style="color:var(--sf-primary)">Buka analitik lanjutan →</a></p>
</div>

<div class="grid gap-4 lg:grid-cols-2 mb-6">
    <x-card title="Transaksi terbaru" subtitle="Penjualan dan pembelian terakhir.">
        <x-slot:actions><a href="{{ route('transaksi.index') }}" class="text-sm font-semibold" style="color:var(--sf-primary)">Semua</a></x-slot:actions>
        @forelse($recent as $t)
            <a href="{{ $t['url'] }}" class="sf-result" style="border-bottom:1px solid var(--sf-border)">
                <span class="sf-kpi-icon"><x-icon :name="$t['kind'] === 'Penjualan' ? 'wallet' : 'download'" :size="16" /></span>
                <div class="min-w-0 flex-1"><p class="text-sm font-semibold truncate">{{ $t['number'] }} · {{ $t['kind'] }}</p><p class="text-xs truncate" style="color:var(--sf-muted)">{{ $t['who'] }} · {{ $t['at']?->diffForHumans() }}</p></div>
                <div class="text-right"><p class="text-sm font-semibold sf-num">{{ money($t['amount']) }}</p><x-status-badge :status="$t['status']" /></div>
            </a>
        @empty
            <x-empty-state icon="receipt" title="Belum ada transaksi" message="Transaksi akan tampil di sini setelah ada penjualan atau pembelian." />
        @endforelse
    </x-card>

    <x-card title="Performa gudang" subtitle="Stok, nilai, dan pergerakan 30 hari.">
        <x-slot:actions><a href="{{ route('gudang.index') }}" class="text-sm font-semibold" style="color:var(--sf-primary)">Kelola</a></x-slot:actions>
        <div class="overflow-x-auto">
        <table class="sf-table"><thead><tr><th>Gudang</th><th class="sf-num">Unit</th><th class="sf-num">Nilai</th><th class="sf-num">Masuk</th><th class="sf-num">Keluar</th></tr></thead><tbody>
            @forelse($warehouses as $w)
                <tr><td class="font-semibold">{{ $w['warehouse']->name }}@if($w['low'])<span class="sf-badge warn ml-1">{{ $w['low'] }} menipis</span>@endif</td><td class="sf-num">{{ number_format($w['units'], 0, ',', '.') }}</td><td class="sf-num">{{ money($w['value']) }}</td><td class="sf-num">{{ number_format($w['in'], 0, ',', '.') }}</td><td class="sf-num">{{ number_format($w['out'], 0, ',', '.') }}</td></tr>
            @empty
                <tr><td colspan="5"><x-empty-state icon="warehouse" title="Belum ada gudang" /></td></tr>
            @endforelse
        </tbody></table></div>
    </x-card>

    <x-card title="Perlu restock" subtitle="Produk dengan stok di bawah minimum.">
        <x-slot:actions><a href="{{ route('restock.index') }}" class="text-sm font-semibold" style="color:var(--sf-primary)">Rekomendasi</a></x-slot:actions>
        @forelse($lowStock as $b)
            <a href="{{ route('barang.show', $b) }}" class="sf-result" style="border-bottom:1px solid var(--sf-border)">
                <div class="min-w-0 flex-1"><p class="text-sm font-semibold truncate">{{ $b->name }}</p><p class="text-xs" style="color:var(--sf-muted)">{{ $b->category->name ?? '-' }}</p></div>
                <span class="sf-badge {{ $b->stock <= 0 ? 'bad' : 'warn' }}">{{ $b->stock }} / min {{ $b->minimum_stock }}</span>
            </a>
        @empty
            <x-empty-state icon="check" title="Semua stok aman" message="Tidak ada produk di bawah stok minimum." />
        @endforelse
    </x-card>

    @if(auth()->user()->isManagement())
    <x-card title="Aktivitas pengguna" subtitle="Dari audit trail.">
        <x-slot:actions><a href="{{ route('audit.index') }}" class="text-sm font-semibold" style="color:var(--sf-primary)">Audit</a></x-slot:actions>
        @forelse($activity as $a)
            <div class="sf-result" style="border-bottom:1px solid var(--sf-border)">
                <span class="grid place-items-center h-8 w-8 rounded-full text-xs font-bold text-white shrink-0" style="background:var(--sf-accent)">{{ mb_strtoupper(mb_substr($a->user?->name ?? 'S', 0, 1)) }}</span>
                <div class="min-w-0"><p class="text-sm truncate">{{ $a->description }}</p><p class="text-xs" style="color:var(--sf-muted)">{{ $a->user?->name ?? 'Sistem' }} · {{ $a->created_at->diffForHumans() }}</p></div>
            </div>
        @empty
            <x-empty-state icon="eye" title="Belum ada aktivitas" />
        @endforelse
    </x-card>
    @endif
</div>
@endsection
