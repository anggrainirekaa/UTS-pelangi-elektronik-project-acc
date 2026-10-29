@extends('layouts.app')

@section('title', 'Detail Supplier')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="sf-card lg:col-span-1">
            <div class="sf-card-header">
                <div>
                    <div class="sf-card-title">{{ $supplier->name }}</div>
                    <div class="sf-card-sub">Data pemasok</div>
                </div>
                <span class="sf-badge {{ $supplier->is_active ? 'ok' : 'mute' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            </div>
            <dl class="sf-card-body space-y-3 text-sm">
                <div><dt class="text-xs" style="color:var(--sf-muted)">Kontak person</dt><dd>{{ $supplier->contact_person ?: '-' }}</dd></div>
                <div><dt class="text-xs" style="color:var(--sf-muted)">Telepon</dt><dd>{{ $supplier->phone ?: '-' }}</dd></div>
                <div><dt class="text-xs" style="color:var(--sf-muted)">Email</dt><dd>{{ $supplier->email ?: '-' }}</dd></div>
                <div><dt class="text-xs" style="color:var(--sf-muted)">Alamat</dt><dd>{{ $supplier->address ?: '-' }}</dd></div>
                <div><dt class="text-xs" style="color:var(--sf-muted)">Jumlah produk / pembelian</dt><dd>{{ $supplier->barangs_count }} produk · {{ $supplier->purchases_count }} pembelian</dd></div>
                <div class="flex gap-2 pt-2">
                    <a href="{{ route('supplier.edit', $supplier) }}" class="sf-btn sf-btn-primary">Ubah</a>
                    <a href="{{ route('supplier.index') }}" class="sf-btn sf-btn-ghost">Kembali</a>
                </div>
            </dl>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="sf-card">
                <div class="sf-card-header"><div class="sf-card-title">Produk dipasok</div></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">SKU</th><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Kategori</th><th class="px-6 py-3 text-right">Stok</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($products as $p)
                            <tr><td class="px-6 py-3">{{ $p->code }}</td><td class="px-6 py-3"><a href="{{ route('barang.show', $p) }}" class="hover:underline">{{ $p->name }}</a></td><td class="px-6 py-3">{{ $p->category?->name }}</td><td class="px-6 py-3 text-right">{{ $p->stock }} {{ $p->unit }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">Belum ada produk dari pemasok ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sf-card">
                <div class="sf-card-header"><div class="sf-card-title">Riwayat pembelian terbaru</div></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Nomor</th><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($purchases as $po)
                            <tr><td class="px-6 py-3"><a href="{{ route('pembelian.show', $po) }}" class="hover:underline">{{ $po->purchase_number }}</a></td><td class="px-6 py-3">{{ $po->purchased_at?->format('d/m/Y') }}</td><td class="px-6 py-3"><span class="sf-badge info">{{ $po->statusLabel() }}</span></td><td class="px-6 py-3 text-right">{{ money($po->total, base: true) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">Belum ada pembelian.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
