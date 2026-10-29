@php($soldQty = (int) ($product->sold_qty ?? 0))
<div class="sh-card">
    <a href="{{ route('katalog.show', $product) }}" aria-label="{{ $product->name }}">
        <div class="sh-card-img">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
            @if($product->stock < 1)<span class="sh-soldout">Stok habis</span>@elseif($product->stock <= $product->minimum_stock)<span class="sh-tag">Sisa {{ $product->stock }}</span>@endif
        </div>
    </a>
    <div class="sh-card-body">
        <a href="{{ route('katalog.show', $product) }}" class="sh-name" style="color:inherit;text-decoration:none">{{ $product->name }}</a>
        <p class="sh-price">{{ money($product->selling_price) }} <small>/ {{ $product->unit }}</small></p>
        <div class="sh-meta"><span>{{ $product->category->name ?? '' }}</span><span>{{ $soldQty > 0 ? 'Terjual '.number_format($soldQty, 0, ',', '.') : 'Stok '.$product->stock }}</span></div>
    </div>
    <form method="POST" action="{{ route('keranjang.add', $product) }}" class="sh-add">
        @csrf
        <button type="submit" @disabled($product->stock < 1) class="sf-btn sf-btn-primary w-full" style="min-height:40px">+ Keranjang</button>
    </form>
</div>
