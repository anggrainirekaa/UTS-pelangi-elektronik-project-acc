@extends('layouts.app')

@section('title', 'Pembelian')

@section('content')
    <x-card title="Daftar Pembelian" :subtitle="auth()->user()->isManagement() ? 'Semua pembelian.' : 'Pembelian yang Anda buat.'">
        <x-slot:actions>
            <a href="{{ route('pembelian.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"><span>+</span> Buat Pembelian</a>
        </x-slot:actions>

        <div class="p-6 border-b border-slate-200">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-5 gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="No. pembelian / supplier" class="rounded-lg border-slate-300 text-sm sm:col-span-2">
                <select name="status" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Semua Status</option>
                    @foreach(['draft' => 'Draft', 'pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'partially_received' => 'Diterima sebagian', 'completed' => 'Selesai', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
                </select>
                <input type="date" name="start_date" value="{{ request('start_date') }}" class="rounded-lg border-slate-300 text-sm">
                <input type="date" name="end_date" value="{{ request('end_date') }}" class="rounded-lg border-slate-300 text-sm">
                <div class="sm:col-span-5 flex gap-2">
                    <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Terapkan</button>
                    @if(request()->query())<a href="{{ route('pembelian.index') }}" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm rounded-lg">Reset</a>@endif
                </div>
            </form>
            @error('end_date')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr>
                    <th class="px-6 py-3">No. Pembelian</th><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Supplier</th><th class="px-6 py-3">Gudang</th>
                    <th class="px-6 py-3 text-center">Item</th><th class="px-6 py-3 text-right">Total</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Oleh</th><th class="px-6 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($purchases as $purchase)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $purchase->purchase_number }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $purchase->purchased_at->format('d M Y H:i') }}</td>
                            <td class="px-6 py-4">{{ $purchase->supplier->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $purchase->warehouse->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-center">{{ $purchase->items_count }}</td>
                            <td class="px-6 py-4 text-right font-medium">{{ money($purchase->total) }}</td>
                            <td class="px-6 py-4"><x-status-badge :status="$purchase->status" /></td>
                            <td class="px-6 py-4 text-slate-500">{{ $purchase->user->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('pembelian.show', $purchase) }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-8 text-center text-slate-400">Belum ada pembelian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchases->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $purchases->links() }}</div>@endif
    </x-card>
@endsection
