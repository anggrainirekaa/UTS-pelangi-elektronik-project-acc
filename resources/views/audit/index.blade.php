@extends('layouts.app')
@section('title', 'Audit Trail')
@section('content')
<x-card title="Catatan perubahan" subtitle="Siapa, kapan, apa — termasuk nilai sebelum dan sesudah. Tidak dapat diubah dari aplikasi.">
    <form method="GET" class="grid gap-2 p-5 sm:grid-cols-5" style="border-bottom:1px solid var(--sf-border)">
        <select name="user_id" class="sf-select"><option value="">Semua pengguna</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select>
        <input name="event" value="{{ request('event') }}" class="sf-input" placeholder="Peristiwa (mis. sale)">
        <input type="date" name="start_date" value="{{ request('start_date') }}" class="sf-input" aria-label="Dari tanggal"><input type="date" name="end_date" value="{{ request('end_date') }}" class="sf-input" aria-label="Sampai tanggal">
        <button class="sf-btn sf-btn-primary">Terapkan</button>
    </form>
    @if($logs->isEmpty())<x-empty-state icon="eye" title="Belum ada catatan" />@else
    <div class="overflow-x-auto"><table class="sf-table"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Peristiwa</th><th>Keterangan</th><th>Perubahan</th></tr></thead><tbody>
        @foreach($logs as $l)
            <tr><td class="whitespace-nowrap">{{ $l->created_at->format('d M Y H:i') }}</td><td>{{ $l->user->name ?? 'Sistem' }}</td><td><span class="sf-badge mute">{{ $l->event }}</span></td><td>{{ $l->description }}</td>
                <td class="text-xs">@if($l->old_values || $l->new_values)<details><summary class="cursor-pointer" style="color:var(--sf-primary)">Lihat</summary><pre class="mt-1 whitespace-pre-wrap">Sebelum: {{ json_encode($l->old_values, JSON_UNESCAPED_UNICODE) }}
Sesudah: {{ json_encode($l->new_values, JSON_UNESCAPED_UNICODE) }}</pre></details>@else — @endif</td></tr>
        @endforeach
    </tbody></table></div>@if($logs->hasPages())<div class="p-4">{{ $logs->links() }}</div>@endif @endif
</x-card>
@endsection
