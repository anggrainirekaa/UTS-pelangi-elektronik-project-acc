@extends('layouts.app')
@section('title', 'Kedaluwarsa & Batch')
@section('content')
<x-card title="Batch mendekati / melewati kedaluwarsa" subtitle="Hanya produk yang ditandai memiliki kedaluwarsa. Penjualan memakai FEFO (kedaluwarsa terdekat keluar dulu); batch kedaluwarsa tidak dijual.">
    <x-slot:actions>
        <form method="GET" class="flex gap-2">
            <select name="show" class="sf-select" style="min-height:36px" onchange="this.form.submit()">
                <option value="attention" @selected($show === 'attention')>Perlu perhatian</option><option value="all" @selected($show === 'all')>Semua batch</option>
            </select>
            @if($show === 'attention')<select name="window" class="sf-select" style="min-height:36px" onchange="this.form.submit()" aria-label="Jendela peringatan">
                <option value="">Sesuai pengaturan produk</option>@foreach([7,14,30,60,90] as $d)<option value="{{ $d }}" @selected($window == $d)>≤ {{ $d }} hari</option>@endforeach</select>@endif
        </form>
    </x-slot:actions>
    @if($expiredValue > 0)<div class="mx-5 mt-4 px-4 py-3 rounded-xl text-sm" style="background:rgba(217,66,66,.1);color:var(--sf-danger)">Nilai persediaan kedaluwarsa (harga pokok): <strong>{{ money($expiredValue) }}</strong>. Keluarkan lewat Penyesuaian Stok (alasan “Kedaluwarsa”).</div>@endif
    @if($batches->isEmpty())
        <x-empty-state icon="calendar" title="Tidak ada batch yang perlu perhatian" message="Produk dengan kedaluwarsa akan tampil di sini saat mendekati tanggalnya." />
    @else
    <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>Produk</th><th>Batch</th><th>Gudang</th><th class="sf-num">Sisa</th><th>Kedaluwarsa</th><th>Status</th><th></th></tr></thead><tbody>
        @foreach($batches as $r)
            @php($b = $r['batch'])
            <tr><td class="font-semibold"><a href="{{ route('barang.show', $b->barang) }}">{{ $b->barang->name }}</a></td><td>{{ $b->batch_number ?: '-' }}</td><td>{{ $b->warehouse->name ?? '-' }}</td>
                <td class="sf-num">{{ $b->quantity }} {{ $b->barang->unit }}</td>
                <td>{{ $b->expiry_date?->translatedFormat('d M Y') }} <span class="text-xs" style="color:var(--sf-muted)">({{ $r['days'] < 0 ? abs($r['days']).' hari lalu' : $r['days'].' hari lagi' }})</span></td>
                <td><x-status-badge :status="$r['state']" /></td>
                <td class="text-right">@if($r['state'] === 'expired')<a class="sf-btn sf-btn-soft sf-btn-sm" href="{{ route('stok-keluar.create', ['barang_id' => $b->barang_id]) }}">Keluarkan</a>@endif</td></tr>
        @endforeach
    </tbody></table></div>
    @endif
</x-card>
@endsection
