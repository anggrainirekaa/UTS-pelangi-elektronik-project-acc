@extends('layouts.app')
@section('title', 'Pengeluaran')
@section('content')
    <x-card title="Pengeluaran Operasional" :subtitle="'Total tercatat: '.money($total).'. Setiap pengeluaran otomatis dijurnal (Dr Beban, Cr Kas).'">
        <x-slot:actions><a href="{{ route('pengeluaran.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"><span>+</span> Catat Pengeluaran</a></x-slot:actions>
        <div class="p-6 border-b border-slate-200">
            <form method="GET" class="flex gap-2"><input type="text" name="search" value="{{ request('search') }}" placeholder="Cari keterangan..." class="flex-1 rounded-lg border-slate-300 text-sm"><button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Cari</button></form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">Keterangan</th><th class="px-6 py-3 text-right">Jumlah</th><th class="px-6 py-3">Oleh</th><th class="px-6 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($expenses as $e)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-slate-500">{{ $e->expense_date->format('d M Y') }}</td>
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $e->title }}<div class="text-xs text-slate-400">{{ $e->note }}</div></td>
                            <td class="px-6 py-4 text-right">{{ money($e->amount) }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $e->user->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-right"><form method="POST" action="{{ route('pengeluaran.destroy', $e) }}" onsubmit="return confirm('Hapus pengeluaran ini? Jurnal akan dibalik.');">@csrf @method('DELETE')<button class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100">Hapus</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">Belum ada pengeluaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $expenses->links() }}</div>@endif
    </x-card>
@endsection
