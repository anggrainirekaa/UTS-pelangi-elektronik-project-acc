@extends('layouts.app')
@section('title', 'Stock Opname')
@section('content')
<x-card title="Riwayat opname" subtitle="Hitung fisik → ajukan → owner menyetujui. Stok hanya berubah setelah disetujui.">
    <x-slot:actions>
        <form method="GET"><select name="status" class="sf-select" style="min-height:36px" onchange="this.form.submit()"><option value="">Semua status</option>@foreach(['draft' => 'Draft', 'submitted' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></form>
        <a href="{{ route('opname.create') }}" class="sf-btn sf-btn-primary sf-btn-sm"><x-icon name="plus" :size="16" /> Opname baru</a>
    </x-slot:actions>
    @if($opnames->isEmpty())
        <x-empty-state icon="clipboard" title="Belum ada opname" message="Mulai hitung fisik untuk mencocokkan stok sistem dengan stok di gudang."><a href="{{ route('opname.create') }}" class="sf-btn sf-btn-primary">Mulai opname</a></x-empty-state>
    @else
    <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>No.</th><th>Gudang</th><th class="sf-num">Item</th><th>Status</th><th>Dibuat oleh</th><th>Tanggal</th><th></th></tr></thead><tbody>
        @foreach($opnames as $o)
            <tr><td class="font-semibold">{{ $o->opname_number }}</td><td>{{ $o->warehouse->name }}</td><td class="sf-num">{{ $o->items_count }}</td><td><x-status-badge :status="$o->status" /></td><td>{{ $o->user->name ?? '-' }}</td><td>{{ $o->created_at->format('d M Y H:i') }}</td><td class="text-right"><a class="sf-btn sf-btn-ghost sf-btn-sm" href="{{ route('opname.show', $o) }}">Buka</a></td></tr>
        @endforeach
    </tbody></table></div>
    @if($opnames->hasPages())<div class="p-4">{{ $opnames->links() }}</div>@endif
    @endif
</x-card>
@endsection
