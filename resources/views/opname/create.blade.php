@extends('layouts.app')
@section('title', 'Opname Baru')
@section('breadcrumb')<x-breadcrumb :items="[['Stock Opname', route('opname.index')], ['Baru', null]]" />@endsection
@section('content')
<form method="POST" action="{{ route('opname.store') }}" class="max-w-2xl">
    @csrf
    <x-card title="Mulai opname" subtitle="Sistem mengambil snapshot stok saat ini sebagai pembanding.">
        <div class="sf-card-body space-y-4">
            <div><label class="sf-label" for="warehouse_id">Gudang</label>
                <select id="warehouse_id" name="warehouse_id" class="sf-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected(old('warehouse_id', $defaultWarehouse->id) == $w->id)>{{ $w->name }}</option>@endforeach</select>
                @error('warehouse_id')<p class="sf-error">{{ $message }}</p>@enderror</div>
            <div><label class="sf-label" for="barang_ids">Produk yang dihitung <span class="sf-help" style="display:inline">(kosong = semua yang ada di gudang)</span></label>
                <select id="barang_ids" name="barang_ids[]" class="sf-select" multiple size="8">@foreach($barangs as $b)<option value="{{ $b->id }}">{{ $b->code }} — {{ $b->name }}</option>@endforeach</select></div>
            <div><label class="sf-label" for="note">Catatan</label><input id="note" name="note" class="sf-input" maxlength="500" value="{{ old('note') }}"></div>
            <button class="sf-btn sf-btn-primary">Buat opname</button>
        </div>
    </x-card>
</form>
@endsection
