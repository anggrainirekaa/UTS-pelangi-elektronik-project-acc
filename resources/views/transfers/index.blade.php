@extends('layouts.app')
@section('title', 'Transfer Gudang')
@section('content')
<x-card title="Transfer stok antar gudang" subtitle="Tidak ada pemotongan ganda: stok keluar dari gudang asal dan masuk ke gudang tujuan dalam satu transaksi.">
    <x-slot:actions><a href="{{ route('transfer-stok.create') }}" class="sf-btn sf-btn-primary sf-btn-sm"><x-icon name="plus" :size="16" /> Transfer baru</a></x-slot:actions>
    @if($transfers->isEmpty())
        <x-empty-state icon="repeat" title="Belum ada transfer" message="Pindahkan stok antar gudang dengan riwayat dan persetujuan yang jelas." />
    @else
    <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>No.</th><th>Tanggal</th><th>Barang</th><th>Dari → Ke</th><th class="sf-num">Jumlah</th><th>Status</th><th>Oleh</th><th></th></tr></thead><tbody>
        @foreach($transfers as $t)
            <tr><td class="font-semibold">{{ $t->transfer_number }}</td><td>{{ ($t->transferred_at ?? $t->created_at)->format('d M Y H:i') }}</td><td>{{ $t->barang->name ?? '-' }}</td>
                <td>{{ $t->fromWarehouse->name ?? '-' }} → {{ $t->toWarehouse->name ?? '-' }}</td><td class="sf-num">{{ $t->quantity }}</td>
                <td><x-status-badge :status="$t->status" />@if($t->reject_reason)<div class="text-xs" style="color:var(--sf-muted)">{{ $t->reject_reason }}</div>@endif</td><td>{{ $t->user->name ?? '-' }}</td>
                <td class="text-right whitespace-nowrap">
                    @if($t->isPending())
                        @can('approve-transfers')
                            <form method="POST" action="{{ route('transfer-stok.approve', $t) }}" class="inline" data-confirm="Setujui dan pindahkan stok sekarang?" data-confirm-title="Setujui transfer" data-confirm-ok="Setujui">@csrf<button class="sf-btn sf-btn-success sf-btn-sm">Setujui</button></form>
                            <form method="POST" action="{{ route('transfer-stok.reject', $t) }}" class="inline-flex gap-1">@csrf<input name="reason" class="sf-input" style="min-height:36px;width:150px" placeholder="Alasan tolak" required><button class="sf-btn sf-btn-danger sf-btn-sm">Tolak</button></form>
                        @endcan
                        @if(auth()->user()->isManagement() || $t->user_id === auth()->id())
                            <form method="POST" action="{{ route('transfer-stok.cancel', $t) }}" class="inline" data-confirm="Batalkan permintaan transfer ini?" data-danger data-confirm-ok="Batalkan">@csrf<button class="sf-btn sf-btn-ghost sf-btn-sm">Batalkan</button></form>
                        @endif
                    @endif
                </td></tr>
        @endforeach
    </tbody></table></div>
    @if($transfers->hasPages())<div class="p-4">{{ $transfers->links() }}</div>@endif
    @endif
</x-card>
@endsection
