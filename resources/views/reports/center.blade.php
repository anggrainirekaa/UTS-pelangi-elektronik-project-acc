@extends('layouts.app')
@section('title', 'Pusat Laporan')
@section('content')
@php
    $desc = [
        'penjualan' => ['Daftar transaksi penjualan dengan filter tanggal, staff, status, kategori, dan produk.', 'receipt'],
        'pembelian' => ['Daftar pembelian ke pemasok dengan filter tanggal, supplier, status, dan produk.', 'download'],
        'stok' => ['Posisi stok setiap produk beserta nilai persediaan.', 'box'],
        'stok-rendah' => ['Produk yang stoknya sudah di bawah atau sama dengan minimum.', 'alert'],
        'pergerakan-stok' => ['Kartu stok: semua barang masuk dan keluar beserta saldonya.', 'list'],
        'produk-terlaris' => ['Peringkat produk berdasarkan unit terjual, omzet, dan laba kotor.', 'chart'],
        'pendapatan' => ['Ringkasan omzet harian, rata-rata transaksi, piutang, dan laba kotor.', 'wallet'],
    ];
@endphp
<p class="text-sm mb-5" style="color:var(--sf-muted)">Pilih laporan. Setiap laporan bisa difilter, dicetak (kop Pelangi Elektronik), dan diekspor ke CSV.</p>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($types as $key => $label)
        <a href="{{ route('laporan.data', $key) }}" class="sf-kpi">
            <div class="sf-kpi-label"><span class="sf-kpi-icon"><x-icon :name="$desc[$key][1]" :size="18" /></span>{{ $label }}</div>
            <p class="text-sm mt-3" style="color:var(--sf-muted)">{{ $desc[$key][0] }}</p>
        </a>
    @endforeach
    <a href="{{ route('laporan.laba-rugi') }}" class="sf-kpi"><div class="sf-kpi-label"><span class="sf-kpi-icon"><x-icon name="wallet" :size="18" /></span>Laba Rugi</div><p class="text-sm mt-3" style="color:var(--sf-muted)">Pendapatan, harga pokok, dan beban operasional dari jurnal.</p></a>
</div>
@endsection
