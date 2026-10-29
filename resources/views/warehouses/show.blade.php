@extends('layouts.app')
@section('title', 'Stok Gudang')
@section('content')
    <x-card :title="$warehouse->name" :subtitle="$warehouse->code.' · '.$warehouse->address">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Barang</th><th class="px-6 py-3 text-center">Stok di Gudang</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($stocks as $s)
                        <tr><td class="px-6 py-3">{{ $s->barang->name ?? '-' }}<div class="text-xs text-slate-400">{{ $s->barang->code ?? '' }}</div></td><td class="px-6 py-3 text-center font-medium">{{ $s->quantity }} {{ $s->barang->unit ?? '' }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="px-6 py-8 text-center text-slate-400">Gudang ini belum memiliki stok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($stocks->hasPages())<div class="px-6 py-4 border-t border-slate-200">{{ $stocks->links() }}</div>@endif
    </x-card>
    <a href="{{ route('gudang.index') }}" class="inline-block mt-4 text-sm text-indigo-600 hover:underline">← Kembali</a>
@endsection
