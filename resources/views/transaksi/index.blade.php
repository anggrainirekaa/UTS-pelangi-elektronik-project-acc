@extends('layouts.app')

@section('title', 'Riwayat Transaksi')

@section('content')
    @php $types = ['purchase' => ['Pembelian', 'bg-emerald-50 text-emerald-700'], 'sale' => ['Penjualan', 'bg-indigo-50 text-indigo-700'], 'adjustment' => ['Penyesuaian', 'bg-amber-50 text-amber-700'], 'transfer' => ['Transfer', 'bg-sky-50 text-sky-700']]; @endphp
    <x-card title="Riwayat Transaksi" subtitle="Gabungan pembelian, penjualan, penyesuaian, dan transfer sesuai hak akses Anda.">
        <div class="p-6 border-b border-slate-200">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                <select name="type" class="rounded-lg border-slate-300 text-sm">
                    <option value="all">Semua Jenis</option>
                    @foreach($types as $v => [$l])<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>@endforeach
                </select>
                <input type="date" name="start_date" value="{{ request('start_date') }}" class="rounded-lg border-slate-300 text-sm">
                <input type="date" name="end_date" value="{{ request('end_date') }}" class="rounded-lg border-slate-300 text-sm">
                <div class="flex gap-2"><button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Terapkan</button>
                    @if(request()->query())<a href="{{ route('transaksi.index') }}" class="px-4 py-2 bg-slate-100 text-slate-600 text-sm rounded-lg">Reset</a>@endif</div>
            </form>
            @error('end_date')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr>
                    <th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Jenis</th><th class="px-6 py-3">No.</th><th class="px-6 py-3">Pihak / Keterangan</th><th class="px-6 py-3 text-center">Item</th><th class="px-6 py-3 text-right">Nilai</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Oleh</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $trx)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-500">{{ $trx->date?->format('d M Y H:i') }}</td>
                            <td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $types[$trx->type][1] }}">{{ $types[$trx->type][0] }}</span></td>
                            <td class="px-6 py-4"><a href="{{ $trx->url }}" class="font-medium text-indigo-600 hover:underline">{{ $trx->number }}</a></td>
                            <td class="px-6 py-4">{{ $trx->party }}</td>
                            <td class="px-6 py-4 text-center">{{ $trx->items }}</td>
                            <td class="px-6 py-4 text-right">{{ $trx->amount !== null ? money($trx->amount) : '-' }}</td>
                            <td class="px-6 py-4"><x-status-badge :status="$trx->status" /></td>
                            <td class="px-6 py-4 text-slate-500">{{ $trx->user->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Belum ada transaksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($transactions->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $transactions->links() }}</div>@endif
    </x-card>
@endsection
