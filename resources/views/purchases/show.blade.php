@extends('layouts.app')
@section('title', 'Detail Pembelian')
@section('breadcrumb')<x-breadcrumb :items="[['Pembelian', route('pembelian.index')], [$purchase->purchase_number, null]]" />@endsection
@section('content')
<div class="space-y-4 max-w-5xl">
    <x-card :title="$purchase->purchase_number" :subtitle="$purchase->purchased_at->format('d M Y H:i').' · dibuat oleh '.($purchase->user->name ?? '-')">
        <x-slot:actions>
            <x-status-badge :status="$purchase->status" />
            <button type="button" onclick="window.print()" class="no-loading sf-btn sf-btn-ghost sf-btn-sm sf-noprint"><x-icon name="printer" :size="16" /> Cetak</button>
            @if($purchase->status === 'draft')<form method="POST" action="{{ route('pembelian.submit', $purchase) }}" data-confirm="Ajukan PO ini ke owner untuk disetujui?" data-confirm-ok="Ajukan" data-confirm-title="Ajukan PO">@csrf<button class="sf-btn sf-btn-primary sf-btn-sm">Ajukan persetujuan</button></form>@endif
            @if($purchase->canReceive())<a href="{{ route('pembelian.receive.form', $purchase) }}" class="sf-btn sf-btn-success sf-btn-sm">Terima barang</a>@endif
        </x-slot:actions>
        <div class="sf-card-body grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div><p style="color:var(--sf-muted)">Supplier</p><p class="font-semibold">{{ $purchase->supplier->name ?? '-' }}</p></div>
            <div><p style="color:var(--sf-muted)">Gudang</p><p class="font-semibold">{{ $purchase->warehouse->name ?? '-' }}</p></div>
            <div><p style="color:var(--sf-muted)">Total pesanan</p><p class="font-semibold">{{ money($purchase->total) }}</p></div>
            <div><p style="color:var(--sf-muted)">Catatan</p><p class="font-semibold">{{ $purchase->note ?: '-' }}</p></div>
            @if($purchase->approver)<div><p style="color:var(--sf-muted)">Disetujui oleh</p><p class="font-semibold">{{ $purchase->approver->name }} · {{ $purchase->approved_at?->format('d M Y H:i') }}</p></div>@endif
        </div>
        @if($purchase->isCancelled() || $purchase->status === 'rejected')
            <div class="mx-5 mb-5 px-4 py-3 rounded-xl text-sm" style="background:rgba(217,66,66,.1);color:var(--sf-danger)">
                {{ $purchase->status === 'rejected' ? 'Ditolak' : 'Dibatalkan oleh '.($purchase->canceller->name ?? '-').' pada '.$purchase->cancelled_at?->format('d M Y H:i') }}. Alasan: {{ $purchase->cancel_reason ?: '-' }}
            </div>
        @endif
        <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>Barang</th><th class="sf-num">Dipesan</th><th class="sf-num">Diterima</th><th class="sf-num">Harga beli</th><th class="sf-num">Subtotal</th></tr></thead><tbody>
            @foreach($purchase->items as $item)
                <tr><td class="font-semibold">{{ $item->barang->name ?? '-' }}<br><span class="text-xs" style="color:var(--sf-muted)">{{ $item->barang->code ?? '' }}</span></td><td class="sf-num">{{ $item->quantity }} {{ $item->barang->unit ?? '' }}</td><td class="sf-num">{{ $item->received_quantity }}</td><td class="sf-num">{{ money($item->unit_cost) }}</td><td class="sf-num font-semibold">{{ money($item->subtotal) }}</td></tr>
            @endforeach
        </tbody><tfoot><tr><td colspan="4" class="sf-num font-semibold">Total</td><td class="sf-num font-bold">{{ money($purchase->total) }}</td></tr></tfoot></table></div>
    </x-card>

    @if($purchase->receipts->isNotEmpty())
        <x-card title="Riwayat penerimaan">
            @foreach($purchase->receipts as $r)
                <div class="sf-result" style="border-bottom:1px solid var(--sf-border)"><span class="font-semibold flex-1">{{ $r->receipt_number }} <span class="text-xs" style="color:var(--sf-muted)">{{ $r->received_at->format('d M Y H:i') }}</span></span><span class="sf-num font-semibold">{{ money($r->total) }}</span></div>
            @endforeach
        </x-card>
    @endif

    @can('approve-purchases')
        @if($purchase->status === 'pending')
        <x-card title="Keputusan owner" subtitle="Setelah disetujui, barang dapat diterima (sebagian atau penuh).">
            <div class="sf-card-body grid gap-4 md:grid-cols-2">
                <form method="POST" action="{{ route('pembelian.approve', $purchase) }}" data-confirm="Setujui PO ini?" data-confirm-ok="Setujui" data-confirm-title="Setujui PO">@csrf<button class="sf-btn sf-btn-success">Setujui PO</button></form>
                <form method="POST" action="{{ route('pembelian.reject', $purchase) }}" class="flex gap-2">@csrf<input name="reason" class="sf-input" placeholder="Alasan penolakan (wajib)" required maxlength="255"><button class="sf-btn sf-btn-danger">Tolak</button></form>
            </div>
        </x-card>
        @endif
    @endcan

    @can('cancel-transactions')
        @if(in_array($purchase->status, ['draft', 'pending', 'approved', 'partially_received', 'completed']))
        <x-card title="Batalkan pembelian" subtitle="Stok yang sudah diterima dikurangi kembali dan jurnal dibalik. Ditolak bila stok sudah terpakai.">
            <form method="POST" action="{{ route('pembelian.cancel', $purchase) }}" class="sf-card-body flex flex-col sm:flex-row gap-3" data-confirm="Pembelian akan dibatalkan dan tidak dapat dipulihkan." data-danger data-confirm-title="Batalkan pembelian" data-confirm-ok="Batalkan">
                @csrf
                <input type="text" name="reason" placeholder="Alasan pembatalan (wajib)" class="sf-input" maxlength="255" required>
                <button class="sf-btn sf-btn-danger">Batalkan</button>
            </form>
        </x-card>
        @endif
    @endcan
</div>
@endsection
