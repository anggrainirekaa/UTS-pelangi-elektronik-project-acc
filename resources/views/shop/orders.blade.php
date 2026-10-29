@extends('layouts.shop')
@section('title', 'Pesanan Saya')
@section('content')
@php($tabs = ['' => 'Semua', 'pending' => 'Menunggu', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'])
<h1 class="sf-page-title mb-4">Pesanan Saya</h1>
<nav class="sh-tabs" aria-label="Status pesanan">@foreach($tabs as $k => $l)<a href="{{ route('pesanan.index', $k ? ['status' => $k] : []) }}" class="{{ (string) request('status', '') === (string) $k ? 'on' : '' }}">{{ $l }}</a>@endforeach</nav>
<div class="sh-panel">
    @forelse($orders as $order)
        <a href="{{ route('pesanan.show', $order) }}" class="sh-row" style="color:inherit;text-decoration:none">
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm">{{ $order->sale_number }}</p>
                <p class="text-xs" style="color:var(--sf-muted)">{{ $order->sold_at->format('d M Y H:i') }} · {{ $order->items_count }} item</p>
                <div class="flex gap-1 mt-1"><x-status-badge :status="$order->status" /><x-status-badge :status="$order->payment_status" /></div>
            </div>
            <div class="text-right"><p class="font-bold" style="color:var(--sf-primary)">{{ money($order->total) }}</p><p class="text-xs" style="color:var(--sf-primary)">Detail</p></div>
        </a>
    @empty
        <x-empty-state icon="receipt" title="Belum ada pesanan" message="Pesanan yang Anda buat akan muncul di sini."><a href="{{ route('katalog.index') }}" class="sf-btn sf-btn-primary">Mulai belanja</a></x-empty-state>
    @endforelse
</div>
@if($orders->hasPages())<div class="mt-4">{{ $orders->links() }}</div>@endif
@endsection
