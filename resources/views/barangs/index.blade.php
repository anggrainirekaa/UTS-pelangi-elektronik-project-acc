@extends('layouts.app')
@section('title', 'Produk')
@section('content')
@php
    $view = request('view') === 'grid' ? 'grid' : 'table';
    $owner = auth()->user()->isManagement();
@endphp
<x-card x-data="{ sel: [], all: false, toggle(ids) { this.sel = this.all ? ids.slice() : [] } }">
    <x-slot:actions>
        <div class="sf-segment" role="group" aria-label="Tampilan">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'table', 'page' => null]) }}" class="{{ $view === 'table' ? 'on' : '' }}"><x-icon name="list" :size="16" /> Tabel</a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'grid', 'page' => null]) }}" class="{{ $view === 'grid' ? 'on' : '' }}"><x-icon name="grid" :size="16" /> Grid</a>
        </div>
        <a href="{{ route('barang.export.excel', request()->query()) }}" class="sf-btn sf-btn-ghost sf-btn-sm">Excel</a>
        <a href="{{ route('barang.export.pdf', request()->query()) }}" class="sf-btn sf-btn-ghost sf-btn-sm">PDF</a>
        @can('manage-master')<a href="{{ route('barang.create') }}" class="sf-btn sf-btn-primary sf-btn-sm"><x-icon name="plus" :size="16" /> Tambah produk</a>@endcan
    </x-slot:actions>

    <form method="GET" action="{{ route('barang.index') }}" class="grid gap-2 p-5 sm:grid-cols-2 lg:grid-cols-6" style="border-bottom:1px solid var(--sf-border)">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="lg:col-span-2 relative" x-data="barcodeScanner()" @barcode-scanned="$refs.q.value = $event.detail.code; $refs.q.form.submit()">
            <input x-ref="q" type="search" name="search" value="{{ request('search') }}" class="sf-input" placeholder="Nama, SKU, barcode, kategori, supplier…" aria-label="Cari produk" style="padding-right:52px">
            <button type="button" class="sf-iconbtn" style="position:absolute;right:2px;top:0" @click="start()" aria-label="Scan barcode dengan kamera"><x-icon name="camera" :size="18" /></button>
            <div x-show="open" x-cloak class="sf-overlay" role="dialog" aria-modal="true" @keydown.escape.window="stop()"><div class="sf-card p-4" style="width:min(420px,100%)"><video x-ref="video" playsinline muted class="w-full rounded-xl bg-black" style="aspect-ratio:4/3"></video><p x-show="error" class="text-sm mt-2" style="color:var(--sf-danger)" x-text="error"></p><button type="button" class="sf-btn sf-btn-ghost w-full mt-3" @click="stop()">Tutup</button></div></div>
        </div>
        <select name="category_id" class="sf-select" aria-label="Kategori"><option value="">Semua kategori</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->full_name }}</option>@endforeach</select>
        <select name="supplier_id" class="sf-select" aria-label="Supplier"><option value="">Semua supplier</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>@endforeach</select>
        <select name="status" class="sf-select" aria-label="Status"><option value="">Semua status</option>@foreach(['active' => 'Aktif', 'inactive' => 'Nonaktif', 'low' => 'Stok menipis', 'out' => 'Stok habis'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
        <select name="sort" class="sf-select" aria-label="Urutkan"><option value="">Urutkan</option>@foreach(['name_asc' => 'Nama A-Z', 'name_desc' => 'Nama Z-A', 'stock_asc' => 'Stok terendah', 'stock_desc' => 'Stok tertinggi', 'price_asc' => 'Harga terendah', 'price_desc' => 'Harga tertinggi'] as $k => $l)<option value="{{ $k }}" @selected(request('sort') === $k)>{{ $l }}</option>@endforeach</select>
        <div class="flex gap-2 lg:col-span-6"><button class="sf-btn sf-btn-primary sf-btn-sm">Terapkan</button>@if(request()->hasAny(['search', 'category_id', 'supplier_id', 'status', 'sort']))<a href="{{ route('barang.index', ['view' => $view]) }}" class="sf-btn sf-btn-ghost sf-btn-sm">Reset</a>@endif</div>
    </form>

    @if($barangs->isEmpty())
        <x-empty-state icon="box" title="Produk tidak ditemukan" message="Ubah kata kunci atau filter, atau tambahkan produk baru.">@can('manage-master')<a href="{{ route('barang.create') }}" class="sf-btn sf-btn-primary">Tambah produk</a>@endcan</x-empty-state>
    @else
    <form method="POST" action="{{ route('barang.bulk') }}" id="bulk">@csrf
        @if($owner)
        <div x-show="sel.length" x-cloak class="flex flex-wrap items-center gap-2 px-5 py-3" style="background:rgba(23,105,224,.07)" role="region" aria-label="Aksi massal">
            <strong class="text-sm" x-text="sel.length + ' dipilih'"></strong>
            <button name="action" value="activate" class="sf-btn sf-btn-soft sf-btn-sm">Aktifkan</button>
            <button name="action" value="deactivate" class="sf-btn sf-btn-ghost sf-btn-sm">Nonaktifkan</button>
            <button name="action" value="delete" class="sf-btn sf-btn-danger sf-btn-sm" onclick="return confirm('Hapus produk terpilih? Produk yang masih memiliki stok dilewati.')">Hapus</button>
        </div>
        @endif

        @if($view === 'table')
        <div class="overflow-x-auto"><table class="sf-table"><thead><tr>
            @if($owner)<th style="width:44px"><input type="checkbox" x-model="all" @change="toggle(@js($barangs->pluck('id')->map(fn ($i) => (string) $i)->all()))" aria-label="Pilih semua"></th>@endif
            <th>Produk</th><th>Kategori</th><th>Supplier</th><th class="sf-num">Harga jual</th><th class="sf-num">Stok</th><th>Status</th><th></th></tr></thead><tbody>
            @foreach($barangs as $b)
                <tr>@if($owner)<td><input type="checkbox" name="ids[]" value="{{ $b->id }}" x-model="sel" aria-label="Pilih {{ $b->name }}"></td>@endif
                    <td><div class="flex items-center gap-3"><img src="{{ $b->image_url }}" alt="" class="h-10 w-10 rounded-xl object-cover" loading="lazy"><div class="min-w-0"><a href="{{ route('barang.show', $b) }}" class="font-semibold">{{ $b->name }}</a><div class="text-xs" style="color:var(--sf-muted)">{{ $b->code }}@if($b->barcode) · {{ $b->barcode }}@endif · {{ $b->unit }}</div></div></div></td>
                    <td>{{ $b->category->full_name ?? '-' }}</td><td>{{ $b->supplier->name ?? '-' }}</td><td class="sf-num">{{ money($b->selling_price) }}</td>
                    <td class="sf-num"><x-status-badge :status="$b->stockStatus()" /> <span class="font-semibold">{{ $b->stock }}</span></td>
                    <td>@if(! $b->is_active)<span class="sf-badge mute">Nonaktif</span>@else<span class="sf-badge info">Aktif</span>@endif</td>
                    <td class="text-right whitespace-nowrap"><a href="{{ route('barang.show', $b) }}" class="sf-btn sf-btn-ghost sf-btn-sm">Detail</a>@can('manage-master') <a href="{{ route('barang.edit', $b) }}" class="sf-btn sf-btn-soft sf-btn-sm">Edit</a>@endcan</td></tr>
            @endforeach
        </tbody></table></div>
        @else
        <div class="grid gap-4 p-5 grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
            @foreach($barangs as $b)
                <div class="sf-product">
                    @if($owner)<label class="absolute top-2 left-2 z-10 sf-card" style="padding:6px 8px;border-radius:10px"><input type="checkbox" name="ids[]" value="{{ $b->id }}" x-model="sel" aria-label="Pilih {{ $b->name }}"></label>@endif
                    <a href="{{ route('barang.show', $b) }}"><img src="{{ $b->image_url }}" alt="{{ $b->name }}" class="sf-product-img" loading="lazy"></a>
                    <div class="p-3 flex flex-col gap-1 flex-1">
                        <a href="{{ route('barang.show', $b) }}" class="font-semibold text-sm leading-snug">{{ $b->name }}</a>
                        <p class="text-xs" style="color:var(--sf-muted)">{{ $b->category->full_name ?? '-' }} · {{ $b->code }}</p>
                        <div class="mt-auto pt-2 flex items-center justify-between"><span class="font-bold text-sm">{{ money($b->selling_price) }}</span><x-status-badge :status="$b->stockStatus()" /></div>
                        <p class="text-xs" style="color:var(--sf-muted)">Stok {{ $b->stock }} {{ $b->unit }}@if(! $b->is_active) · Nonaktif @endif</p>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </form>
    @if($barangs->hasPages())<div class="p-4">{{ $barangs->links() }}</div>@endif
    @endif
</x-card>
@endsection
