@extends('layouts.shop')
@section('title', $product->name)
@section('content')
<nav class="sf-breadcrumb mb-3" aria-label="Breadcrumb"><a href="{{ route('katalog.index') }}">Katalog</a><x-icon name="chevron-right" :size="14" />@if($product->category)<a href="{{ route('katalog.index', ['category_id' => $product->category_id]) }}">{{ $product->category->name }}</a><x-icon name="chevron-right" :size="14" />@endif<span style="color:var(--sf-text);font-weight:600">{{ $product->name }}</span></nav>
<div class="sh-panel grid gap-6 p-4 md:p-6 md:grid-cols-2">
    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full rounded-2xl object-cover" style="aspect-ratio:1/1;background:rgba(115,129,152,.12)">
    <div class="space-y-3">
        <p class="text-sm" style="color:var(--sf-muted)">{{ $product->category->name ?? '' }} · {{ $product->code }}</p>
        <h1 class="text-2xl font-bold" style="letter-spacing:-.01em">{{ $product->name }}</h1>
        <div class="flex flex-wrap gap-3 text-sm" style="color:var(--sf-muted)"><span>Terjual <strong style="color:var(--sf-text)">{{ number_format($sold, 0, ',', '.') }}</strong></span><span>·</span><span>{{ $product->stock > 0 ? 'Stok tersedia: '.$product->stock.' '.$product->unit : 'Stok habis' }}</span></div>
        <div class="rounded-2xl px-4 py-3" style="background:rgba(23,105,224,.08)"><span class="text-3xl font-bold" style="color:var(--sf-primary)">{{ money($product->selling_price) }}</span> <span class="text-sm" style="color:var(--sf-muted)">/ {{ $product->unit }}</span></div>
        <p class="text-sm" style="color:var(--sf-muted)">{{ $product->description ?: 'Tidak ada deskripsi.' }}</p>
        <form method="POST" action="{{ route('keranjang.add', $product) }}" class="flex flex-wrap items-center gap-3 pt-2">
            @csrf
            <div class="sh-qty"><input type="number" name="quantity" value="1" min="1" max="{{ max(1, $product->stock) }}" aria-label="Jumlah"></div>
            <button @disabled($product->stock < 1) class="sf-btn sf-btn-primary" style="min-width:200px">Tambah ke Keranjang</button>
        </form>
    </div>
</div>
@if($related->isNotEmpty())
    <h2 class="font-bold mt-6 mb-3">Produk serupa</h2>
    <div class="sh-grid">@foreach($related as $product)@include('shop._card', ['product' => $product])@endforeach</div>
@endif
@endsection
