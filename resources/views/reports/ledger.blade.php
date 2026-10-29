@extends('layouts.app')
@section('title', 'Buku Besar')
@section('content')
    <x-card title="Buku Besar" subtitle="Mutasi per akun.">
        @component('reports._period', ['start' => $start, 'end' => $end])
            @slot('extra')
                <div><label class="block text-xs text-slate-500 mb-1">Akun</label>
                    <select name="account_id" class="rounded-lg border-slate-300 text-sm">
                        <option value="">-- Pilih akun --</option>
                        @foreach($accounts as $a)<option value="{{ $a->id }}" @selected($account?->id === $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach
                    </select></div>
            @endslot
        @endcomponent
        @if($account)
            @php $sign = in_array($account->type, ['asset', 'expense']) ? 1 : -1; $running = $openingBalance; @endphp
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Tanggal</th><th class="px-6 py-3">No. Jurnal</th><th class="px-6 py-3">Keterangan</th><th class="px-6 py-3 text-right">Debit</th><th class="px-6 py-3 text-right">Kredit</th><th class="px-6 py-3 text-right">Saldo</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr class="bg-slate-50"><td colspan="5" class="px-6 py-3 font-medium">Saldo awal</td><td class="px-6 py-3 text-right font-medium">{{ money($openingBalance) }}</td></tr>
                    @foreach($lines as $l)
                        @php $running += $sign * ($l->debit - $l->credit); @endphp
                        <tr><td class="px-6 py-3">{{ $l->entry->entry_date->format('d M Y') }}</td><td class="px-6 py-3">{{ $l->entry->entry_number }}</td><td class="px-6 py-3">{{ $l->entry->description }}</td>
                            <td class="px-6 py-3 text-right">{{ $l->debit ? money($l->debit) : '' }}</td><td class="px-6 py-3 text-right">{{ $l->credit ? money($l->credit) : '' }}</td><td class="px-6 py-3 text-right">{{ money($running) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="p-8 text-center text-slate-400">Pilih akun untuk menampilkan mutasi.</p>
        @endif
    </x-card>
@endsection
