@extends('layouts.app')

@section('title', 'Detail Barang')

@section('content')
    <div class="space-y-6">
        <x-card :title="$barang->name" :subtitle="$barang->code.' · '.($barang->category->name ?? '-')">
            <x-slot:actions>
                @can('manage-master')
                    <a href="{{ route('barang.edit', $barang) }}" class="px-4 py-2 bg-amber-50 text-amber-700 text-sm font-medium rounded-lg hover:bg-amber-100">Edit</a>
                @endcan
                @can('manage-purchases')
                    <a href="{{ route('pembelian.create', ['barang_id' => $barang->id]) }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">+ Beli Stok</a>
                @endcan
            </x-slot:actions>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                <img src="{{ $barang->image_url }}" alt="{{ $barang->name }}" class="w-full max-w-xs rounded-xl border border-slate-200 object-cover">
                <dl class="md:col-span-2 grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-slate-500">Harga Beli</dt><dd class="font-medium">{{ money($barang->purchase_price) }}</dd></div>
                    <div><dt class="text-slate-500">Harga Jual</dt><dd class="font-medium">{{ money($barang->selling_price) }}</dd></div>
                    <div><dt class="text-slate-500">Stok Total</dt><dd><span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $barang->isLowStock() ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $barang->stock }} {{ $barang->unit }}</span></dd></div>
                    <div><dt class="text-slate-500">Stok Minimum</dt><dd class="font-medium">{{ $barang->minimum_stock }} {{ $barang->unit }}</dd></div>
                    <div><dt class="text-slate-500">Supplier</dt><dd class="font-medium">{{ $barang->supplier->name ?? '-' }}</dd></div>
                    <div><dt class="text-slate-500">Katalog</dt><dd class="font-medium">{{ $barang->is_active ? 'Tampil' : 'Disembunyikan' }}</dd></div>
                    <div class="col-span-2"><dt class="text-slate-500">Deskripsi</dt><dd>{{ $barang->description ?: '-' }}</dd></div>
                    <div class="col-span-2">
                        <dt class="text-slate-500 mb-1">Stok per Gudang</dt>
                        <dd class="flex flex-wrap gap-2">
                            @forelse($warehouseStocks as $ws)
                                <span class="px-3 py-1 bg-slate-100 rounded-lg">{{ $ws->warehouse->name }}: <strong>{{ $ws->quantity }}</strong></span>
                            @empty
                                <span class="text-slate-400">Belum ada stok.</span>
                            @endforelse
                        </dd>
                    </div>
                </dl>
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Penjualan Terakhir">
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse($recentSales as $item)
                        <li class="px-6 py-3 flex justify-between"><span>{{ $item->sale->sale_number }} · {{ $item->quantity }} {{ $barang->unit }}</span><span class="text-slate-500">{{ $item->sale->sold_at->format('d M Y') }}</span></li>
                    @empty
                        <li class="px-6 py-6 text-center text-slate-400">Belum ada penjualan.</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="Pembelian Terakhir">
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse($recentPurchases as $item)
                        <li class="px-6 py-3 flex justify-between"><span>{{ $item->purchase->purchase_number }} · {{ $item->quantity }} {{ $barang->unit }}</span><span class="text-slate-500">{{ $item->purchase->supplier->name ?? '' }}</span></li>
                    @empty
                        <li class="px-6 py-6 text-center text-slate-400">Belum ada pembelian.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @if($barang->tracks_expiry)
            <x-card title="Batch & kedaluwarsa (FEFO)" subtitle="Batch terdekat kedaluwarsa dijual lebih dulu.">
                @forelse($batches as $bt)
                    <div class="sf-result" style="border-bottom:1px solid var(--sf-border)"><span class="flex-1 text-sm">{{ $bt->batch_number ?: 'Tanpa nomor' }} · {{ $bt->warehouse->name ?? '-' }}<br><span class="text-xs" style="color:var(--sf-muted)">Kedaluwarsa {{ $bt->expiry_date?->translatedFormat('d M Y') ?? '-' }}</span></span><span class="font-semibold">{{ $bt->quantity }}</span>@if($bt->isExpired())<x-status-badge status="expired" />@endif</div>
                @empty
                    <p class="px-6 py-6 text-center text-slate-400 text-sm">Tidak ada batch bersisa.</p>
                @endforelse
            </x-card>
            @endif
            <x-card title="Riwayat harga">
                @forelse($priceHistory as $h)
                    <div class="px-6 py-3 text-sm" style="border-bottom:1px solid var(--sf-border)"><div class="flex justify-between"><span>{{ $h->created_at->format('d M Y H:i') }} · {{ $h->source }}</span><span style="color:var(--sf-muted)">{{ $h->user->name ?? 'Sistem' }}</span></div>
                        <div style="color:var(--sf-muted)">Beli: {{ $h->old_purchase_price !== null ? money($h->old_purchase_price).' → ' : '' }}{{ money($h->new_purchase_price) }} · Jual: {{ $h->old_selling_price !== null ? money($h->old_selling_price).' → ' : '' }}{{ money($h->new_selling_price) }}</div></div>
                @empty
                    <p class="px-6 py-6 text-center text-slate-400 text-sm">Belum ada riwayat harga.</p>
                @endforelse
            </x-card>
            <x-card title="Kartu stok (15 terakhir)" class="lg:col-span-2">
                <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>Waktu</th><th>Jenis</th><th class="sf-num">Jumlah</th><th class="sf-num">Saldo gudang</th><th>Catatan</th></tr></thead><tbody>
                    @forelse($movements as $m)
                        <tr><td>{{ $m->occurred_at->format('d M Y H:i') }}</td><td><span class="sf-badge mute">{{ str_replace('_', ' ', $m->type) }}</span></td><td class="sf-num font-semibold" style="color:{{ $m->quantity < 0 ? 'var(--sf-danger)' : 'var(--sf-success)' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td><td class="sf-num">{{ $m->balance_after }}</td><td>{{ $m->note }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-400">Belum ada pergerakan.</td></tr>
                    @endforelse
                </tbody></table></div>
            </x-card>
        </div>
    </div>
@endsection
