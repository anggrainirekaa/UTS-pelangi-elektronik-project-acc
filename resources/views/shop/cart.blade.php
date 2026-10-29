@extends('layouts.shop')
@section('title', 'Keranjang')
@section('content')
<h1 class="sf-page-title mb-4">Keranjang Belanja</h1>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
    <div class="sh-panel lg:col-span-2">
        @forelse($lines as $line)
            <div class="sh-row">
                <a href="{{ route('katalog.show', $line->product) }}"><img src="{{ $line->product->image_url }}" alt="" class="sh-thumb"></a>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('katalog.show', $line->product) }}" class="font-semibold text-sm" style="color:inherit;text-decoration:none">{{ $line->product->name }}</a>
                    <p class="text-xs" style="color:var(--sf-muted)">{{ money($line->product->selling_price) }} · Stok {{ $line->product->stock }}</p>
                    <form method="POST" action="{{ route('keranjang.update', $line->product) }}" class="flex items-center gap-2 mt-2">
                        @csrf @method('PATCH')
                        <div class="sh-qty"><input type="number" name="quantity" value="{{ $line->quantity }}" min="1" max="{{ $line->product->stock }}" aria-label="Jumlah {{ $line->product->name }}"></div>
                        <button class="sf-btn sf-btn-ghost sf-btn-sm">Ubah</button>
                    </form>
                </div>
                <div class="text-right">
                    <p class="font-bold" style="color:var(--sf-primary)">{{ money($line->subtotal) }}</p>
                    <form method="POST" action="{{ route('keranjang.remove', $line->product) }}" data-confirm="Hapus {{ $line->product->name }} dari keranjang?" data-confirm-title="Hapus produk" data-confirm-ok="Hapus" data-danger>@csrf @method('DELETE')<button class="sf-iconbtn" title="Hapus" aria-label="Hapus {{ $line->product->name }}" style="color:var(--sf-danger)"><x-icon name="trash" :size="18" /></button></form>
                </div>
            </div>
        @empty
            <x-empty-state icon="cart" title="Keranjang masih kosong" message="Temukan produk yang Anda butuhkan di katalog."><a href="{{ route('katalog.index') }}" class="sf-btn sf-btn-primary">Lihat katalog</a></x-empty-state>
        @endforelse
    </div>

    <div class="sh-panel sh-sticky">
        <div class="sf-card-header"><h2 class="sf-card-title">Ringkasan & Checkout</h2></div>
        <form method="POST" action="{{ route('keranjang.checkout') }}" class="sf-card-body space-y-4">
            @csrf
            <div class="flex justify-between items-baseline"><span style="color:var(--sf-muted)" class="text-sm">Total</span><span class="text-2xl font-bold" style="color:var(--sf-primary)">{{ money($total) }}</span></div>
            <x-field name="shipping_address" label="Alamat Pengiriman" type="textarea" :value="auth()->user()->address" required />
            <x-field name="note" label="Catatan (opsional)" />
            <p class="text-xs" style="color:var(--sf-muted)">Harga final dihitung ulang oleh server saat pesanan dibuat.</p>
            <button @disabled($lines->isEmpty()) class="sf-btn sf-btn-primary w-full">Buat Pesanan</button>
        </form>
    </div>
</div>
@endsection
