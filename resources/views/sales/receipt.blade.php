@extends('layouts.app')
@section('title', 'Struk '.$sale->sale_number)
@section('content')
<div class="max-w-sm mx-auto">
    <div class="sf-noprint flex gap-2 mb-4"><button type="button" onclick="window.print()" class="sf-btn sf-btn-primary flex-1"><x-icon name="printer" :size="18" /> Cetak</button><a href="{{ route('penjualan.show', $sale) }}" class="sf-btn sf-btn-ghost">Kembali</a></div>
    <div class="sf-card sf-card-body text-sm" style="font-family:ui-monospace,Menlo,monospace">
        <p class="text-center font-bold text-base">{{ config('app.name', 'Pelangi Elektronik') }}</p>
        <p class="text-center text-xs" style="color:var(--sf-muted)">{{ $sale->warehouse->name ?? '' }}</p>
        <hr class="my-3" style="border-color:var(--sf-border)">
        <p>No: {{ $sale->sale_number }}</p><p>Tgl: {{ $sale->sold_at->format('d/m/Y H:i') }}</p><p>Staff: {{ $sale->user->name ?? '-' }}</p><p>Pelanggan: {{ $sale->customer->name ?? 'Umum' }}</p>
        <hr class="my-3" style="border-color:var(--sf-border)">
        @foreach($sale->items as $item)
            <p>{{ $item->barang->name ?? '-' }}</p><p class="flex justify-between"><span>{{ $item->quantity }} × {{ money($item->unit_price) }}</span><span>{{ money($item->subtotal) }}</span></p>
        @endforeach
        <hr class="my-3" style="border-color:var(--sf-border)">
        @if($sale->discount > 0)<p class="flex justify-between"><span>Subtotal</span><span>{{ money($sale->subtotal) }}</span></p><p class="flex justify-between"><span>Diskon</span><span>− {{ money($sale->discount) }}</span></p>@endif
        <p class="flex justify-between font-bold text-base"><span>TOTAL</span><span>{{ money($sale->total) }}</span></p>
        <p class="flex justify-between"><span>Bayar</span><span>{{ $sale->payment_status === 'paid' ? ucfirst(str_replace('_', ' ', $sale->payment_method ?? '')) : 'Belum lunas' }}</span></p>
        <p class="text-center text-xs mt-4" style="color:var(--sf-muted)">Terima kasih</p>
    </div>
</div>
@endsection
