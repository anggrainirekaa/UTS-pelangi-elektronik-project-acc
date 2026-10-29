@extends('layouts.app')
@section('title', 'Mata Uang')
@section('content')
    <x-card title="Mata Uang" subtitle="Semua transaksi tersimpan dalam IDR. Mata uang lain hanya mengonversi TAMPILAN memakai kurs manual di bawah (bukan kurs real-time).">
        <x-slot:actions><a href="{{ route('mata-uang.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"><span>+</span> Tambah</a></x-slot:actions>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Kode</th><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Simbol</th><th class="px-6 py-3 text-right">Kurs (1 unit = … IDR)</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($currencies as $c)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium">{{ $c->code }} @if($c->is_base)<span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-indigo-50 text-indigo-700">Dasar</span>@endif</td>
                            <td class="px-6 py-4">{{ $c->name }}</td><td class="px-6 py-4">{{ $c->symbol }}</td>
                            <td class="px-6 py-4 text-right">{{ number_format($c->rate, 2, ',', '.') }}</td>
                            <td class="px-6 py-4">{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="px-6 py-4"><div class="flex justify-end gap-2">
                                <a href="{{ route('mata-uang.edit', $c) }}" class="px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-medium rounded-lg hover:bg-amber-100">Edit</a>
                                @unless($c->is_base)<form method="POST" action="{{ route('mata-uang.destroy', $c) }}" onsubmit="return confirm('Hapus mata uang ini?');">@csrf @method('DELETE')<button class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100">Hapus</button></form>@endunless
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
