@extends('layouts.app')
@section('title', 'Deteksi Anomali')
@section('content')
<x-card title="Temuan berbasis aturan" subtitle="Sistem hanya menandai untuk ditinjau manusia. Tidak ada transaksi yang diubah atau dihapus otomatis.">
    <x-slot:actions>
        <div class="sf-segment">@foreach(['open' => 'Terbuka', 'reviewed' => 'Ditinjau', 'dismissed' => 'Diabaikan'] as $k => $l)<a href="{{ route('anomali.index', ['status' => $k]) }}" class="{{ $status === $k ? 'on' : '' }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>@endforeach</div>
        <form method="POST" action="{{ route('anomali.scan') }}">@csrf<button class="sf-btn sf-btn-primary sf-btn-sm"><x-icon name="refresh" :size="16" /> Pindai sekarang</button></form>
    </x-slot:actions>
    @forelse($flags as $f)
        <div class="px-5 py-4" style="border-bottom:1px solid var(--sf-border)">
            <div class="flex flex-wrap items-center gap-2"><span class="sf-badge {{ $f->severity === 'high' ? 'bad' : 'warn' }}">{{ $f->severity === 'high' ? 'Tinggi' : 'Sedang' }}</span><span class="sf-badge mute">{{ $f->typeLabel() }}</span>
                <span class="text-xs" style="color:var(--sf-muted)">{{ $f->detected_at?->diffForHumans() }}</span></div>
            <p class="font-semibold mt-1">{{ $f->title }}</p><p class="text-sm" style="color:var(--sf-muted)">{{ $f->detail }}</p>
            @if($f->status !== 'open')<p class="text-xs mt-1">Oleh {{ $f->reviewer->name ?? '-' }} · {{ $f->review_note }}</p>
            @else
            <form method="POST" action="{{ route('anomali.review', $f) }}" class="mt-3 flex flex-col sm:flex-row gap-2">@csrf @method('PATCH')
                <input name="note" class="sf-input" placeholder="Catatan tinjauan (wajib)" required maxlength="500">
                <button name="status" value="reviewed" class="sf-btn sf-btn-soft sf-btn-sm">Sudah ditinjau</button>
                <button name="status" value="dismissed" class="sf-btn sf-btn-ghost sf-btn-sm">Abaikan</button></form>
            @endif
        </div>
    @empty
        <x-empty-state icon="shield" title="Tidak ada temuan" message="Pindai untuk memeriksa stok negatif, saldo tidak cocok, perubahan besar, dan transaksi berulang." />
    @endforelse
    @if($flags->hasPages())<div class="p-4">{{ $flags->links() }}</div>@endif
</x-card>
@endsection
