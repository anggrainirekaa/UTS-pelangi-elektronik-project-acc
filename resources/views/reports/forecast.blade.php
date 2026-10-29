@extends('layouts.app')
@section('title', 'Prediksi Stok')
@section('content')
    <x-card title="Prediksi Kebutuhan Stok" subtitle="Statistik sederhana (regresi linear atas penjualan mingguan). Bukan AI/machine learning: hasil adalah estimasi, bukan jaminan.">
        <div class="p-6 border-b border-slate-200">
            <form method="GET" class="flex flex-col sm:flex-row gap-2 sm:items-center">
                <select name="history" class="rounded-lg border-slate-300 text-sm">@foreach([28, 56, 84, 168] as $d)<option value="{{ $d }}" @selected($history === $d)>Riwayat {{ $d }} hari</option>@endforeach</select>
                <select name="horizon" class="rounded-lg border-slate-300 text-sm">@foreach([14, 28, 56] as $d)<option value="{{ $d }}" @selected($horizon === $d)>Prediksi {{ $d }} hari</option>@endforeach</select>
                <label class="inline-flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="only_reorder" value="1" class="rounded border-slate-300" @checked(request()->boolean('only_reorder'))> Hanya yang perlu dibeli</label>
                <button class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg">Hitung</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr>
                    <th class="px-6 py-3">Barang</th><th class="px-6 py-3 text-center">Stok</th><th class="px-6 py-3 text-center">Terjual ({{ $history }}h)</th><th class="px-6 py-3 text-center">Prediksi ({{ $horizon }}h)</th>
                    <th class="px-6 py-3 text-center">Tren</th><th class="px-6 py-3 text-center">Sisa Hari</th><th class="px-6 py-3 text-center">Saran Beli</th><th class="px-6 py-3">Status</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $r)
                        @php $badge = ['kritis' => 'bg-red-50 text-red-700', 'waspada' => 'bg-amber-50 text-amber-700', 'aman' => 'bg-emerald-50 text-emerald-700'][$r['status']] ?? 'bg-slate-100 text-slate-600'; @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3 font-medium text-slate-800">{{ $r['barang']->name }}<div class="text-xs text-slate-400">{{ $r['barang']->code }}</div></td>
                            <td class="px-6 py-3 text-center">{{ $r['barang']->stock }}</td>
                            <td class="px-6 py-3 text-center">{{ $r['sold_history'] }}</td>
                            <td class="px-6 py-3 text-center">{{ $r['predicted_demand'] }}</td>
                            <td class="px-6 py-3 text-center">{{ $r['trend'] === 'naik' ? '📈' : ($r['trend'] === 'turun' ? '📉' : '➖') }} {{ $r['trend'] }}</td>
                            <td class="px-6 py-3 text-center">{{ $r['days_left'] ?? '∞' }}</td>
                            <td class="px-6 py-3 text-center font-semibold">{{ $r['reorder_qty'] }}</td>
                            <td class="px-6 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $badge }}">{{ $r['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-8 text-center text-slate-400">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
