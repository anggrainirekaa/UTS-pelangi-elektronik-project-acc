@extends('layouts.shop')
@section('title', 'Katalog Produk')
@section('content')
@php($tabs = ['name' => 'Nama', 'newest' => 'Terbaru', 'popular' => 'Terlaris', 'price_asc' => 'Harga Terendah', 'price_desc' => 'Harga Tertinggi'])
@php($sort = request('sort', 'name'))
<div class="space-y-4">
    @if(! request()->hasAny(['search', 'category_id', 'sort', 'page']))
        <div class="sh-hero">
            <div><h2>Halo, {{ auth()->user()->name }}</h2><p>Pilih produk, masukkan ke keranjang, lalu pantau pesanan Anda.</p></div>
            <a href="{{ route('pesanan.index') }}" class="sf-btn" style="background:#fff;color:var(--sf-primary)">Pesanan Saya</a>
        </div>
    @endif

    @if(! request()->hasAny(['search', 'category_id', 'sort', 'page']) && $categories->isNotEmpty())
        <div class="sh-panel">
            <div class="sh-section-title"><span>Kategori</span></div>
            <div class="sh-cats">
                @foreach($categories as $i => $c)
                    <a href="{{ route('katalog.index', ['category_id' => $c->id]) }}"><i><x-icon :name="['tv','speaker','plug','home','cpu','box'][$i % 6]" :size="22" /></i>{{ $c->name }}</a>
                @endforeach
            </div>
        </div>
    @endif

    <nav class="sh-tabs" aria-label="Urutkan">
        @foreach($tabs as $k => $l)<a href="{{ route('katalog.index', array_merge(request()->except('page'), ['sort' => $k])) }}" class="{{ $sort === $k ? 'on' : '' }}">{{ $l }}</a>@endforeach
    </nav>

    @if(request('search'))<p class="text-sm" style="color:var(--sf-muted)">Hasil untuk “<strong style="color:var(--sf-text)">{{ request('search') }}</strong>” — {{ $products->total() }} produk. <a href="{{ route('katalog.index') }}" style="color:var(--sf-primary)">Reset</a></p>@endif

    @if($products->isEmpty())
        <div class="sh-panel"><x-empty-state icon="search" title="Produk tidak ditemukan" message="Coba kata kunci lain atau pilih kategori yang berbeda."><a href="{{ route('katalog.index') }}" class="sf-btn sf-btn-soft">Lihat semua produk</a></x-empty-state></div>
    @else
        <div class="sh-grid">@foreach($products as $product)@include('shop._card', ['product' => $product])@endforeach</div>
    @endif

    @if($products->hasPages())<div>{{ $products->links() }}</div>@endif
</div>
@endsection
