@extends('layouts.app')
@section('title', $opname->opname_number)
@section('breadcrumb')<x-breadcrumb :items="[['Stock Opname', route('opname.index')], [$opname->opname_number, null]]" />@endsection
@section('content')
@php($editable = $opname->status === 'draft')
<div class="space-y-4">
<x-card :title="$opname->opname_number" :subtitle="$opname->warehouse->name.' · dibuat '.$opname->user->name.' · '.$opname->created_at->format('d M Y H:i')">
    <x-slot:actions><x-status-badge :status="$opname->status" /><button type="button" onclick="window.print()" class="no-loading sf-btn sf-btn-ghost sf-btn-sm sf-noprint"><x-icon name="printer" :size="16" /> Cetak</button></x-slot:actions>
    @if($opname->note)<p class="px-5 pt-4 text-sm">Catatan: {{ $opname->note }}</p>@endif
    @if($opname->reviewer)<p class="px-5 pt-3 text-sm" style="color:var(--sf-muted)">Ditinjau oleh {{ $opname->reviewer->name }} · {{ $opname->reviewed_at?->format('d M Y H:i') }}@if($opname->review_note) — {{ $opname->review_note }}@endif</p>@endif

    <form method="POST" action="{{ route('opname.counts', $opname) }}">
        @csrf @method('PUT')
        <div class="overflow-x-auto"><table class="sf-table mt-3"><thead><tr><th>Produk</th><th class="sf-num">Stok sistem</th><th class="sf-num">Hitung fisik</th><th class="sf-num">Selisih</th><th>Alasan selisih</th>@if($opname->status === 'approved')<th class="sf-num">Sebelum → sesudah</th>@endif</tr></thead><tbody>
            @foreach($opname->items as $i)
                <tr><td class="font-semibold">{{ $i->barang->name }}<br><span class="text-xs" style="color:var(--sf-muted)">{{ $i->barang->code }}</span></td>
                    <td class="sf-num">{{ $i->system_quantity }}</td>
                    <td class="sf-num">@if($editable)<input type="number" min="0" name="counts[{{ $i->barang_id }}][counted]" value="{{ old("counts.{$i->barang_id}.counted", $i->counted_quantity) }}" class="sf-input" style="width:110px;text-align:right" aria-label="Hitung fisik {{ $i->barang->name }}">@else{{ $i->counted_quantity ?? '—' }}@endif</td>
                    <td class="sf-num font-semibold" style="color:{{ ($i->difference ?? 0) < 0 ? 'var(--sf-danger)' : (($i->difference ?? 0) > 0 ? 'var(--sf-success)' : 'inherit') }}">{{ $i->counted_quantity === null ? '—' : ($i->difference > 0 ? '+' : '').$i->difference }}</td>
                    <td>@if($editable)<input name="counts[{{ $i->barang_id }}][reason]" value="{{ old("counts.{$i->barang_id}.reason", $i->reason) }}" class="sf-input" maxlength="255" placeholder="Wajib bila ada selisih" aria-label="Alasan {{ $i->barang->name }}">@else{{ $i->reason ?: '—' }}@endif</td>
                    @if($opname->status === 'approved')<td class="sf-num">{{ $i->stock_before }} → {{ $i->stock_after }}</td>@endif</tr>
            @endforeach
        </tbody></table></div>
        @if($editable)<div class="p-5 flex gap-2"><button class="sf-btn sf-btn-ghost">Simpan hitungan</button></div>@endif
    </form>

    @if($editable)
        <form method="POST" action="{{ route('opname.submit', $opname) }}" class="px-5 pb-5" data-confirm="Ajukan opname ini ke owner? Hitungan tidak dapat diubah setelah diajukan." data-confirm-title="Ajukan opname" data-confirm-ok="Ajukan">@csrf<button class="sf-btn sf-btn-primary">Ajukan untuk persetujuan</button></form>
    @endif
</x-card>

@if($opname->status === 'submitted')
    @can('approve-opname')
    <x-card title="Keputusan owner" subtitle="Menyetujui akan menyesuaikan stok berdasarkan selisih terhadap snapshot dan mencatat jurnal.">
        <div class="sf-card-body grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('opname.approve', $opname) }}" class="space-y-2" data-confirm="Stok akan disesuaikan sesuai selisih. Lanjutkan?" data-confirm-title="Setujui opname" data-confirm-ok="Setujui">@csrf
                <input name="note" class="sf-input" placeholder="Catatan (opsional)" maxlength="500"><button class="sf-btn sf-btn-success">Setujui & sesuaikan stok</button></form>
            <form method="POST" action="{{ route('opname.reject', $opname) }}" class="space-y-2">@csrf
                <input name="note" class="sf-input" placeholder="Alasan penolakan (wajib)" maxlength="500" required><button class="sf-btn sf-btn-danger">Tolak</button></form>
        </div>
    </x-card>
    @else
        <x-card><div class="sf-card-body text-sm" style="color:var(--sf-muted)">Menunggu persetujuan owner. Hanya owner yang dapat menyesuaikan stok.</div></x-card>
    @endcan
@endif
</div>
@endsection
