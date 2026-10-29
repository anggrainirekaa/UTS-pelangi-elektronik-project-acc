@extends('layouts.app')
@section('title', 'Terima Barang')
@section('breadcrumb')<x-breadcrumb :items="[['Pembelian', route('pembelian.index')], [$purchase->purchase_number, route('pembelian.show', $purchase)], ['Terima barang', null]]" />@endsection
@section('content')
<form method="POST" action="{{ route('pembelian.receive', $purchase) }}" class="max-w-5xl space-y-4">
    @csrf
    <x-card :title="'Penerimaan '.$purchase->purchase_number" :subtitle="$purchase->supplier->name.' → '.$purchase->warehouse->name.'. Isi jumlah yang benar-benar diterima; kosongkan bila belum datang.'">
        <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>Barang</th><th class="sf-num">Sisa</th><th>Diterima</th><th>Batch</th><th>Kedaluwarsa</th></tr></thead><tbody>
            @foreach($purchase->items as $i => $item)
                @php($rem = $item->remaining())
                @continue($rem <= 0)
                <tr><td class="font-semibold">{{ $item->barang->name }}<input type="hidden" name="lines[{{ $i }}][purchase_item_id]" value="{{ $item->id }}"><br><span class="text-xs" style="color:var(--sf-muted)">{{ $item->barang->code }}</span></td>
                    <td class="sf-num">{{ $rem }} {{ $item->barang->unit }}</td>
                    <td><input type="number" min="0" max="{{ $rem }}" name="lines[{{ $i }}][quantity]" value="{{ old("lines.$i.quantity", $rem) }}" class="sf-input" style="width:110px" aria-label="Jumlah diterima {{ $item->barang->name }}"></td>
                    <td>@if($item->barang->tracks_expiry)<input name="lines[{{ $i }}][batch_number]" value="{{ old("lines.$i.batch_number") }}" class="sf-input" style="width:150px" placeholder="No. batch" maxlength="60">@else<span style="color:var(--sf-muted)">—</span>@endif</td>
                    <td>@if($item->barang->tracks_expiry)<input type="date" name="lines[{{ $i }}][expiry_date]" value="{{ old("lines.$i.expiry_date") }}" class="sf-input" style="width:170px" min="{{ today()->toDateString() }}" required>@else<span style="color:var(--sf-muted)">Tidak dilacak</span>@endif</td></tr>
            @endforeach
        </tbody></table></div>
        <div class="sf-card-body"><label class="sf-label" for="note">Catatan penerimaan</label><input id="note" name="note" class="sf-input" maxlength="500" value="{{ old('note') }}"></div>
    </x-card>
    <div class="flex gap-2"><button class="sf-btn sf-btn-success">Catat penerimaan</button><a href="{{ route('pembelian.show', $purchase) }}" class="sf-btn sf-btn-ghost">Kembali</a></div>
</form>
@endsection
