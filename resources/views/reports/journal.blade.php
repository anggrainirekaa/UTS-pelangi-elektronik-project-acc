@extends('layouts.app')
@section('title', 'Jurnal Umum')
@section('content')
    <x-card title="Jurnal Umum" subtitle="Setiap transaksi menghasilkan jurnal seimbang; pembatalan membuat jurnal pembalik.">
        @include('reports._period')
        <div class="divide-y divide-slate-100">
            @forelse($entries as $entry)
                <div class="p-6 text-sm">
                    <div class="flex justify-between"><span class="font-medium text-slate-800">{{ $entry->entry_number }} · {{ $entry->description }}</span><span class="text-slate-500">{{ $entry->entry_date->format('d M Y') }}</span></div>
                    <table class="w-full mt-2"><tbody>
                        @foreach($entry->lines as $line)
                            <tr><td class="py-1 {{ $line->credit ? 'pl-8' : '' }}">{{ $line->account->code }} {{ $line->account->name }}</td>
                                <td class="py-1 text-right w-40">{{ $line->debit ? money($line->debit) : '' }}</td><td class="py-1 text-right w-40">{{ $line->credit ? money($line->credit) : '' }}</td></tr>
                        @endforeach
                    </tbody></table>
                </div>
            @empty
                <p class="p-8 text-center text-slate-400">Tidak ada jurnal pada periode ini.</p>
            @endforelse
        </div>
        @if($entries->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $entries->links() }}</div>@endif
    </x-card>
@endsection
