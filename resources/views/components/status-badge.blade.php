@props(['status'])
@php
    // [label, kelas badge]
    $map = [
        'pending' => ['Menunggu', 'warn'], 'completed' => ['Selesai', 'ok'], 'cancelled' => ['Dibatalkan', 'bad'],
        'paid' => ['Lunas', 'ok'], 'unpaid' => ['Belum Lunas', 'warn'], 'refunded' => ['Dikembalikan', 'mute'],
        'pos' => ['Toko', 'info'], 'online' => ['Online', 'info'],
        'draft' => ['Draft', 'mute'], 'approved' => ['Disetujui', 'info'], 'partially_received' => ['Diterima sebagian', 'warn'],
        'submitted' => ['Menunggu persetujuan', 'warn'], 'rejected' => ['Ditolak', 'bad'],
        'open' => ['Perlu ditinjau', 'bad'], 'reviewed' => ['Ditinjau', 'ok'], 'dismissed' => ['Diabaikan', 'mute'],
        'ok' => ['Aman', 'ok'], 'low' => ['Menipis', 'warn'], 'out' => ['Habis', 'bad'],
        'expired' => ['Kedaluwarsa', 'bad'], 'critical' => ['Segera', 'bad'], 'warning' => ['Mendekati', 'warn'],
    ];
    [$label, $tone] = $map[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'mute'];
@endphp
<span {{ $attributes->merge(['class' => "sf-badge {$tone}"]) }}>{{ $label }}</span>
