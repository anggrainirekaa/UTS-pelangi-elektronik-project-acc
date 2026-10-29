@extends('layouts.shop')
@section('title', 'Dashboard')
@section('content')
<div class="space-y-4">
    <div class="sh-hero">
        <div><h2>Halo, {{ auth()->user()->name }}</h2><p>Belanja kebutuhan Anda dan pantau status pesanan di sini.</p></div>
        <a href="{{ route('katalog.index') }}" class="sf-btn" style="background:#fff;color:var(--sf-primary)">Mulai Belanja</a>
    </div>

    <div class="sh-panel">
        <div class="sh-status">
            @foreach([['Menunggu', $orderCounts['pending'], 'pending'], ['Selesai', $orderCounts['completed'], 'completed'], ['Dibatalkan', $orderCounts['cancelled'], 'cancelled']] as [$label, $count, $st])
                <a href="{{ route('pesanan.index', ['status' => $st]) }}"><b>{{ $count }}</b><span>Pesanan {{ $label }}</span></a>
            @endforeach
        </div>
        <div class="px-5 pb-4 text-sm" style="color:var(--sf-muted)">Total belanja selesai: <strong style="color:var(--sf-text)">{{ money($totalSpent) }}</strong></div>
    </div>

    <x-card title="Pesanan Terbaru">
        <x-slot:actions><a href="{{ route('pesanan.index') }}" class="text-sm font-semibold" style="color:var(--sf-primary)">Semua pesanan</a></x-slot:actions>
        @forelse($recentOrders as $order)
            <a href="{{ route('pesanan.show', $order) }}" class="sh-row" style="color:inherit;text-decoration:none">
                <div class="flex-1 min-w-0"><p class="font-semibold text-sm">{{ $order->sale_number }}</p><p class="text-xs" style="color:var(--sf-muted)">{{ $order->sold_at->format('d M Y H:i') }}</p></div>
                <div class="flex gap-1"><x-status-badge :status="$order->status" /><x-status-badge :status="$order->payment_status" /></div>
                <p class="font-bold text-sm" style="color:var(--sf-primary)">{{ money($order->total) }}</p>
            </a>
        @empty
            <x-empty-state icon="receipt" title="Belum ada pesanan" message="Mulai belanja untuk membuat pesanan pertama Anda." />
        @endforelse
    </x-card>
</div>
@endsection
