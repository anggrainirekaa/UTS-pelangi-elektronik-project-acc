@extends('layouts.app')

@section('title', 'Detail Penjualan')

@section('content')
    <div class="space-y-6 max-w-4xl">
        <x-card :title="$sale->sale_number" :subtitle="$sale->sold_at->format('d M Y H:i').' · dibuat oleh '.($sale->user->name ?? '-')">
            <x-slot:actions>
                <x-status-badge :status="$sale->channel" /><x-status-badge :status="$sale->status" /><x-status-badge :status="$sale->payment_status" />
                @if(! $sale->isCancelled())<a href="{{ route('penjualan.receipt', $sale) }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">🧾 Struk</a>@endif
                @if($sale->isCompleted())<a href="{{ route('penjualan.return.form', $sale) }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">↩ Retur</a>@endif
                <a href="{{ route('penjualan.invoice', $sale) }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">📄 Invoice PDF</a>
            </x-slot:actions>
            <div class="p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><p class="text-slate-500">Pelanggan</p><p class="font-medium">{{ $sale->customer->name ?? 'Umum' }}</p></div>
                <div><p class="text-slate-500">Gudang</p><p class="font-medium">{{ $sale->warehouse->name ?? '-' }}</p></div>
                <div><p class="text-slate-500">Metode Bayar</p><p class="font-medium">{{ $sale->payment_method ? ($paymentMethods[$sale->payment_method] ?? ucfirst(str_replace('_', ' ', $sale->payment_method))) : '-' }}</p></div>
                <div><p class="text-slate-500">Ref. Pembayaran</p><p class="font-medium">{{ $sale->payment_reference ?: '-' }}</p></div>
                @if($sale->shipping_address)<div class="col-span-2 sm:col-span-4"><p class="text-slate-500">Alamat Pengiriman</p><p class="font-medium">{{ $sale->shipping_address }}</p></div>@endif
                @if($sale->note)<div class="col-span-2 sm:col-span-4"><p class="text-slate-500">Catatan</p><p class="font-medium">{{ $sale->note }}</p></div>@endif
            </div>
            @if($sale->isCancelled())
                <div class="mx-6 mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-700 text-sm">
                    Dibatalkan oleh {{ $sale->canceller->name ?? '-' }} pada {{ $sale->cancelled_at?->format('d M Y H:i') }}. Alasan: {{ $sale->cancel_reason ?: '-' }}
                </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr>
                        <th class="px-6 py-3">Produk</th><th class="px-6 py-3 text-center">Jumlah</th><th class="px-6 py-3 text-right">Harga</th><th class="px-6 py-3 text-right">Subtotal</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($sale->items as $item)
                            <tr><td class="px-6 py-3">{{ $item->barang->name ?? '-' }}<div class="text-xs text-slate-400">{{ $item->barang->code ?? '' }}</div></td>
                                <td class="px-6 py-3 text-center">{{ $item->quantity }} {{ $item->barang->unit ?? '' }}</td>
                                <td class="px-6 py-3 text-right">{{ money($item->unit_price) }}</td>
                                <td class="px-6 py-3 text-right font-medium">{{ money($item->subtotal) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                    @if($sale->discount > 0)<tr><td colspan="3" class="px-6 py-2 text-right text-slate-500">Subtotal</td><td class="px-6 py-2 text-right">{{ money($sale->subtotal) }}</td></tr><tr><td colspan="3" class="px-6 py-2 text-right text-slate-500">Diskon</td><td class="px-6 py-2 text-right">− {{ money($sale->discount) }}</td></tr>@endif
                    <tr class="border-t"><td colspan="3" class="px-6 py-3 text-right font-semibold">Total</td><td class="px-6 py-3 text-right font-bold">{{ money($sale->total) }}</td></tr></tfoot>
                </table>
            </div>
        </x-card>

        @if($sale->isPending())
            @can('manage-sales')
                <x-card title="Proses Pesanan" subtitle="Stok divalidasi ulang dan dikurangi dari gudang yang dipilih. Jika stok tidak cukup, pesanan tetap menunggu.">
                    <form method="POST" action="{{ route('penjualan.complete', $sale) }}" class="p-6 flex flex-col sm:flex-row gap-3">
                        @csrf
                        <select name="warehouse_id" class="rounded-lg border-slate-300 text-sm">
                            @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected($w->id === $sale->warehouse_id)>{{ $w->name }}</option>@endforeach
                        </select>
                        <button class="px-5 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">Proses & Kurangi Stok</button>
                    </form>
                </x-card>
            @endcan
        @endif

        @if(! $sale->isCancelled() && ! $sale->isPaid())
            @can('manage-sales')
                <x-card title="Catat Pembayaran">
                    <form method="POST" action="{{ route('penjualan.pay', $sale) }}" class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @csrf
                        <select name="payment_method" class="rounded-lg border-slate-300 text-sm">
                            @foreach(['cash' => 'Tunai'] + $paymentMethods as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select>
                        <input type="text" name="payment_reference" placeholder="Referensi (opsional)" class="rounded-lg border-slate-300 text-sm" maxlength="100">
                        <button class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Tandai Lunas</button>
                    </form>
                </x-card>
            @endcan
        @endif

        @if($sale->canBeCancelledBy(auth()->user()))
            <x-card :title="$sale->isPending() ? 'Tolak / Batalkan Pesanan' : 'Batalkan Transaksi'"
                    :subtitle="$sale->isCompleted() ? 'Stok dikembalikan ke gudang asal, jurnal dibalik, dan pembayaran ditandai dikembalikan.' : 'Stok belum dikurangi sehingga tidak ada perubahan stok.'">
                <form method="POST" action="{{ route('penjualan.cancel', $sale) }}" class="p-6 flex flex-col sm:flex-row gap-3" onsubmit="return confirm('Yakin membatalkan transaksi ini?');">
                    @csrf
                    <input type="text" name="reason" placeholder="Alasan (opsional)" class="flex-1 rounded-lg border-slate-300 text-sm" maxlength="255">
                    <button class="px-5 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700">Batalkan</button>
                </form>
            </x-card>
        @endif
        @if($sale->returns->isNotEmpty())
            <x-card title="Riwayat retur">@foreach($sale->returns as $r)<div class="sf-result" style="border-bottom:1px solid var(--sf-border)"><span class="flex-1"><strong>{{ $r->return_number }}</strong> · {{ $r->returned_at->format('d M Y H:i') }}<br><span class="text-xs" style="color:var(--sf-muted)">{{ $r->reason }}</span></span><span class="font-semibold">{{ money($r->refund_total) }}</span></div>@endforeach</x-card>
        @endif
        <a href="{{ route('penjualan.index') }}" class="text-sm text-indigo-600 hover:underline">← Kembali ke daftar</a>
    </div>
@endsection
