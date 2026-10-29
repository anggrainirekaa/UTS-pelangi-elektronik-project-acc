@extends('layouts.app')
@section('title', 'Rekomendasi Restock')
@section('content')
<x-card class="mb-4"><div class="sf-card-body text-sm" style="color:var(--sf-muted)">
    Rekomendasi dihitung dari penjualan nyata {{ $config['history_days'] }} hari terakhir (dikurangi retur): target = stok minimum + rata-rata harian × (lead time supplier + {{ $config['coverage_days'] }} hari cadangan). Pesanan berjalan dikurangkan. Tombol di bawah hanya membuat <strong>draft PO</strong>; harus diajukan dan disetujui owner sebelum barang dapat diterima.
</div></x-card>

@forelse($groups as $key => $rows)
    @php($supplier = $rows->first()['supplier'])
    <form method="POST" action="{{ route('restock.drafts') }}" class="mb-4" data-confirm="Buat draft PO untuk produk yang dicentang? Draft belum memesan ke supplier." data-confirm-title="Buat draft PO" data-confirm-ok="Buat draft">
        @csrf
        @if($supplier)<input type="hidden" name="supplier_id" value="{{ $supplier->id }}">@endif
        <x-card :title="$supplier->name ?? 'Tanpa supplier'" :subtitle="$rows->count().' produk'.($supplier ? ' · lead time '.($supplier->lead_time_days ?? 3).' hari' : ' · tetapkan supplier pada produk agar dapat dibuatkan PO')">
            <x-slot:actions>@if($supplier)<button class="sf-btn sf-btn-primary sf-btn-sm">Buat draft PO</button>@endif</x-slot:actions>
            <div class="overflow-x-auto"><table class="sf-table"><thead><tr>@if($supplier)<th></th>@endif<th>Produk</th><th class="sf-num">Stok</th><th class="sf-num">Rata-rata/hari</th><th class="sf-num">Sisa hari</th><th class="sf-num">Disarankan</th><th>Alasan</th></tr></thead><tbody>
                @foreach($rows as $r)
                    <tr>@if($supplier)<td><input type="checkbox" name="barang_ids[]" value="{{ $r['barang']->id }}" checked aria-label="Pilih {{ $r['barang']->name }}"></td>@endif
                        <td class="font-semibold"><a href="{{ route('barang.show', $r['barang']) }}">{{ $r['barang']->name }}</a><br><x-status-badge :status="['habis' => 'out', 'menipis' => 'low', 'segera' => 'warning', 'normal' => 'ok'][$r['urgency']]" /></td>
                        <td class="sf-num">{{ $r['barang']->stock }}</td><td class="sf-num">{{ number_format($r['avg_daily'], 2, ',', '.') }}</td>
                        <td class="sf-num">{{ $r['days_left'] ?? '—' }}</td><td class="sf-num font-bold">{{ $r['recommended'] }} {{ $r['barang']->unit }}</td>
                        <td class="text-xs max-w-md" style="color:var(--sf-muted)">{{ $r['reason'] }}</td></tr>
                @endforeach
            </tbody></table></div>
        </x-card>
    </form>
@empty
    <x-card><x-empty-state icon="check" title="Tidak ada rekomendasi" message="Semua stok masih cukup untuk kebutuhan yang diperkirakan." /></x-card>
@endforelse
@endsection
