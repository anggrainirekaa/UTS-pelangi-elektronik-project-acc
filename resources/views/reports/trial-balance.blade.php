@extends('layouts.app')
@section('title', 'Neraca Saldo')
@section('content')
    <x-card title="Neraca Saldo" :subtitle="'Per '.$end->format('d M Y').'. Total debit harus sama dengan total kredit.'" class="max-w-4xl">
        <form method="GET" class="p-6 border-b border-slate-200 flex gap-2 items-end">
            <div><label class="block text-xs text-slate-500 mb-1">Per tanggal</label><input type="date" name="end_date" value="{{ $end->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></div>
            <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Terapkan</button>
        </form>
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Kode</th><th class="px-6 py-3">Akun</th><th class="px-6 py-3 text-right">Debit</th><th class="px-6 py-3 text-right">Kredit</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $r)<tr><td class="px-6 py-3">{{ $r['code'] }}</td><td class="px-6 py-3">{{ $r['name'] }}</td><td class="px-6 py-3 text-right">{{ money($r['debit']) }}</td><td class="px-6 py-3 text-right">{{ money($r['credit']) }}</td></tr>
                @empty<tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">Belum ada jurnal.</td></tr>@endforelse
            </tbody>
            <tfoot><tr class="border-t-2 font-bold"><td colspan="2" class="px-6 py-3 text-right">Total</td><td class="px-6 py-3 text-right">{{ money($totalDebit) }}</td><td class="px-6 py-3 text-right">{{ money($totalCredit) }}</td></tr>
                <tr><td colspan="4" class="px-6 pb-4 text-right text-xs {{ $totalDebit === $totalCredit ? 'text-emerald-600' : 'text-red-600' }}">{{ $totalDebit === $totalCredit ? '✔ Seimbang' : '✖ Tidak seimbang' }}</td></tr></tfoot>
        </table>
    </x-card>
@endsection
