@extends('layouts.app')
@section('title', 'Retur Penjualan')
@section('breadcrumb')<x-breadcrumb :items="[['Penjualan', route('penjualan.index')], [$sale->sale_number, route('penjualan.show', $sale)], ['Retur', null]]" />@endsection
@section('content')
<form method="POST" action="{{ route('penjualan.return', $sale) }}" class="max-w-4xl space-y-4">
    @csrf
    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <x-card :title="'Retur atas '.$sale->sale_number" subtitle="Aturan: hanya penjualan selesai; jumlah retur kumulatif tidak boleh melebihi jumlah terjual; nilai refund proporsional terhadap diskon.">
        @if($windowDays > 0 && ! auth()->user()->isManagement())<p class="px-5 pt-4 text-sm" style="color:var(--sf-muted)">Batas waktu retur oleh staff: {{ $windowDays }} hari sejak transaksi selesai. Admin tidak dibatasi.</p>@endif
        <div class="overflow-x-auto"><table class="sf-table mt-3"><thead><tr><th>Produk</th><th class="sf-num">Terjual</th><th class="sf-num">Sudah diretur</th><th class="sf-num">Harga</th><th>Jumlah retur</th></tr></thead><tbody>
            @foreach($sale->items as $i => $item)
                @php($max = $item->quantity - (int) ($returned[$item->id] ?? 0))
                <tr><td class="font-semibold">{{ $item->barang->name ?? '-' }}<input type="hidden" name="items[{{ $i }}][sale_item_id]" value="{{ $item->id }}"></td><td class="sf-num">{{ $item->quantity }}</td><td class="sf-num">{{ $item->quantity - $max }}</td><td class="sf-num">{{ money($item->unit_price) }}</td>
                    <td><input type="number" min="0" max="{{ $max }}" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", 0) }}" class="sf-input" style="width:110px" @disabled($max <= 0) aria-label="Jumlah retur {{ $item->barang->name ?? '' }}"></td></tr>
            @endforeach
        </tbody></table></div>
        <div class="sf-card-body"><label class="sf-label" for="reason">Alasan retur</label><input id="reason" name="reason" class="sf-input" maxlength="255" required value="{{ old('reason') }}">@error('reason')<p class="sf-error">{{ $message }}</p>@enderror @error('items')<p class="sf-error">{{ $message }}</p>@enderror</div>
    </x-card>
    <div class="flex gap-2"><button class="sf-btn sf-btn-primary" data-x>Proses retur</button><a href="{{ route('penjualan.show', $sale) }}" class="sf-btn sf-btn-ghost">Batal</a></div>
</form>
@endsection
