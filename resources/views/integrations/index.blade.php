@extends('layouts.app')
@section('title', 'Integrasi & API')
@section('content')
    <div class="space-y-6">
        @if(session('new_secret'))
            <div class="px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">Secret webhook (salin sekarang, tidak ditampilkan lagi): <code class="font-mono break-all">{{ session('new_secret') }}</code></div>
        @endif
        @if(session('new_token'))
            <div class="px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">API key baru (salin sekarang, tidak ditampilkan lagi): <code class="font-mono break-all">{{ session('new_token') }}</code></div>
        @endif

        <x-card title="Webhook Keluar (ERP)" subtitle="Event dikirim via HTTP POST JSON dengan header X-StockFlow-Signature (HMAC-SHA256 dari body memakai secret).">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Nama</th><th class="px-6 py-3">URL</th><th class="px-6 py-3">Event</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($endpoints as $e)
                            <tr>
                                <td class="px-6 py-3 font-medium">{{ $e->name }}</td>
                                <td class="px-6 py-3 text-slate-500 break-all">{{ $e->url }}</td>
                                <td class="px-6 py-3 text-xs">{{ implode(', ', $e->events) }}</td>
                                <td class="px-6 py-3">{{ $e->is_active ? 'Aktif' : 'Nonaktif' }} <span class="text-xs text-slate-400">({{ $e->deliveries_count }} kiriman)</span></td>
                                <td class="px-6 py-3"><div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('integrasi.webhook.test', $e) }}">@csrf<button class="px-3 py-1.5 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200">Tes</button></form>
                                    <form method="POST" action="{{ route('integrasi.webhook.destroy', $e) }}" onsubmit="return confirm('Hapus webhook ini?');">@csrf @method('DELETE')<button class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100">Hapus</button></form>
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-slate-400">Belum ada webhook.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('integrasi.webhook.store') }}" class="p-6 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @csrf
                <x-field name="name" label="Nama" required />
                <x-field name="url" label="URL (http/https)" type="url" required />
                <div class="sm:col-span-2">
                    <p class="text-sm font-medium text-slate-700 mb-1.5">Event</p>
                    <div class="flex flex-wrap gap-4 text-sm">
                        @foreach($events as $key => $label)<label class="inline-flex items-center gap-2"><input type="checkbox" name="events[]" value="{{ $key }}" class="rounded border-slate-300" @checked(in_array($key, old('events', [])))> {{ $label }}</label>@endforeach
                    </div>
                    @error('events')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <input type="hidden" name="is_active" value="1">
                <div><button class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Tambah Webhook</button></div>
            </form>
        </x-card>

        <x-card title="Log Pengiriman Terakhir">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Waktu</th><th class="px-6 py-3">Endpoint</th><th class="px-6 py-3">Event</th><th class="px-6 py-3">Hasil</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($deliveries as $d)
                        <tr><td class="px-6 py-3 text-slate-500">{{ $d->created_at->format('d M Y H:i:s') }}</td><td class="px-6 py-3">{{ $d->endpoint->name ?? '-' }}</td><td class="px-6 py-3">{{ $d->event }}</td>
                            <td class="px-6 py-3">{!! $d->succeeded ? '<span class="text-emerald-600">✔ '.$d->response_status.'</span>' : '<span class="text-red-600">✖ '.e($d->response_status ?? $d->error ?? 'gagal').'</span>' !!}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-6 text-center text-slate-400">Belum ada pengiriman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card title="API Key (Mobile & ERP)" subtitle="Aplikasi mobile memakai POST /api/v1/login. Integrasi ERP memakai API key tipe erp pada /api/erp/v1/* (header Authorization: Bearer <key>).">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Tipe</th><th class="px-6 py-3">Pemilik</th><th class="px-6 py-3">Terakhir dipakai</th><th class="px-6 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tokens as $t)
                        <tr><td class="px-6 py-3">{{ $t->name }}</td><td class="px-6 py-3">{{ $t->type }}</td><td class="px-6 py-3">{{ $t->user->name ?? '-' }}</td><td class="px-6 py-3 text-slate-500">{{ $t->last_used_at?->diffForHumans() ?? '-' }}</td>
                            <td class="px-6 py-3 text-right"><form method="POST" action="{{ route('integrasi.token.destroy', $t) }}" onsubmit="return confirm('Cabut API key ini?');">@csrf @method('DELETE')<button class="px-3 py-1.5 bg-red-50 text-red-700 text-xs font-medium rounded-lg hover:bg-red-100">Cabut</button></form></td></tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-6 text-center text-slate-400">Belum ada API key.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <form method="POST" action="{{ route('integrasi.token.store') }}" class="p-6 border-t border-slate-200 flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <x-field name="name" label="Nama key" class="flex-1" required />
                <x-select-field name="type" label="Tipe" :options="['erp' => 'ERP (baca-saja)', 'mobile' => 'Mobile']" value="erp" />
                <button class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">Buat API Key</button>
            </form>
        </x-card>
    </div>
@endsection
