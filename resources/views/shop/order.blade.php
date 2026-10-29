@extends('layouts.shop')

@section('title', 'Detail Pesanan')

@section('content')
    <div class="space-y-6 max-w-4xl">
        <x-card :title="$sale->sale_number" :subtitle="$sale->sold_at->format('d M Y H:i')">
            <x-slot:actions>
                <x-status-badge :status="$sale->status" /><x-status-badge :status="$sale->payment_status" />
                <a href="{{ route('penjualan.invoice', $sale) }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">📄 Invoice PDF</a>
            </x-slot:actions>
            <div class="p-6 text-sm space-y-1">
                <p><span class="text-slate-500">Alamat:</span> {{ $sale->shipping_address }}</p>
                @if($sale->note)<p><span class="text-slate-500">Catatan:</span> {{ $sale->note }}</p>@endif
                @if($sale->isPending())<p class="text-amber-600">Pesanan Anda menunggu diproses oleh staff.</p>@endif
                @if($sale->isCancelled())<p class="text-red-600">Pesanan dibatalkan. {{ $sale->cancel_reason }}</p>@endif
                @if($sale->payment_reference)<p><span class="text-slate-500">Ref. pembayaran:</span> {{ $sale->payment_reference }}</p>@endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Produk</th><th class="px-6 py-3 text-center">Jumlah</th><th class="px-6 py-3 text-right">Harga</th><th class="px-6 py-3 text-right">Subtotal</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($sale->items as $item)
                            <tr><td class="px-6 py-3">{{ $item->barang->name ?? '-' }}</td><td class="px-6 py-3 text-center">{{ $item->quantity }}</td>
                                <td class="px-6 py-3 text-right">{{ money($item->unit_price) }}</td><td class="px-6 py-3 text-right font-medium">{{ money($item->subtotal) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="border-t"><td colspan="3" class="px-6 py-3 text-right font-semibold">Total</td><td class="px-6 py-3 text-right font-bold">{{ money($sale->total) }}</td></tr></tfoot>
                </table>
            </div>
        </x-card>

        @if(! $sale->isCancelled() && ! $sale->isPaid())
            <x-card title="Pembayaran (Simulasi)" subtitle="Tidak ada uang sungguhan. Kartu berakhiran 0000 akan ditolak untuk uji kegagalan.">
                <form method="POST" action="{{ route('pesanan.pay', $sale) }}" class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-3" x-data="{ method: 'qris' }">
                    @csrf
                    <select name="payment_method" x-model="method" class="rounded-lg border-slate-300 text-sm">
                        @foreach($paymentMethods as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                    <input x-show="method === 'kartu'" x-cloak type="text" name="card_number" placeholder="Nomor kartu (simulasi)" class="rounded-lg border-slate-300 text-sm" maxlength="25">
                    <button class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Bayar {{ money($sale->total) }}</button>
                </form>
            </x-card>
        @endif

        @if($sale->canBeCancelledBy(auth()->user()))
            <form method="POST" action="{{ route('pesanan.cancel', $sale) }}" data-confirm="Batalkan pesanan ini? Tindakan ini tidak dapat diurungkan." data-confirm-title="Batalkan pesanan" data-confirm-ok="Ya, batalkan" data-danger>
                @csrf
                <button class="px-5 py-2 bg-red-50 text-red-700 text-sm font-medium rounded-lg hover:bg-red-100">Batalkan Pesanan</button>
            </form>
        @endif
        <a href="{{ route('pesanan.index') }}" class="text-sm text-indigo-600 hover:underline">← Kembali ke pesanan</a>
    </div>
@endsection
