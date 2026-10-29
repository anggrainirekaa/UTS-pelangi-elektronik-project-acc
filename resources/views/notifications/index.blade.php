@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
<x-card>
    <x-slot:actions>
        <div class="sf-segment"><a href="{{ route('notifikasi.index') }}" class="{{ $filter === 'all' ? 'on' : '' }}">Semua</a><a href="{{ route('notifikasi.index', ['filter' => 'unread']) }}" class="{{ $filter === 'unread' ? 'on' : '' }}">Belum dibaca ({{ $unread }})</a></div>
        @if($unread)<form method="POST" action="{{ route('notifikasi.read-all') }}">@csrf<button class="sf-btn sf-btn-ghost sf-btn-sm">Tandai semua dibaca</button></form>@endif
    </x-slot:actions>
    @forelse($notifications as $n)
        @php($d = $n->data)
        <a href="{{ route('notifikasi.open', $n->id) }}" class="sf-result" style="align-items:flex-start;border-bottom:1px solid var(--sf-border)">
            <span class="mt-2 h-2.5 w-2.5 rounded-full shrink-0" style="background: {{ $n->read_at ? 'transparent' : 'var(--sf-primary)' }}"></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">{{ $d['title'] ?? 'Notifikasi' }}
                    @if(($d['level'] ?? 'info') === 'warning')<span class="sf-badge warn ml-1">Perhatian</span>@elseif(($d['level'] ?? '') === 'danger')<span class="sf-badge bad ml-1">Penting</span>@endif</p>
                <p class="text-sm" style="color:var(--sf-muted)">{{ $d['message'] ?? '' }}</p>
                <p class="text-xs mt-1" style="color:var(--sf-muted)">{{ $n->created_at->translatedFormat('d M Y H:i') }} · {{ $n->created_at->diffForHumans() }}</p>
            </div>
        </a>
    @empty
        <x-empty-state icon="bell" title="Tidak ada notifikasi" message="Peringatan stok, persetujuan, dan kedaluwarsa akan muncul di sini." />
    @endforelse
    @if($notifications->hasPages())<div class="p-4">{{ $notifications->links() }}</div>@endif
</x-card>
@endsection
