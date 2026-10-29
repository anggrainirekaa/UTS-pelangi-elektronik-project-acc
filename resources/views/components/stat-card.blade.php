@props(['label', 'value', 'icon' => 'chart', 'tip' => null, 'href' => null, 'delta' => null, 'unit' => null])
@php
    // $delta: null (tanpa pembanding) atau ['pct' => float|null, 'dir' => 'up|down|flat|null', 'good' => 'up|down']
    $has = $delta && ($delta['dir'] ?? null);
    $good = $delta['good'] ?? 'up';
    $tone = $has ? ($delta['dir'] === 'flat' ? 'flat' : ($delta['dir'] === $good ? 'up' : 'down')) : 'none';
@endphp
<{{ $href ? 'a href="'.e($href).'"' : 'div' }} class="sf-kpi" @if($href) aria-label="{{ $label }}: lihat detail" @endif>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="sf-kpi-label">
                <span>{{ $label }}</span>
                @if($tip)<span class="sf-tip" tabindex="0" role="note" data-tip="{{ $tip }}" aria-label="{{ $tip }}">?</span>@endif
            </div>
            <div class="sf-kpi-value">{{ $value }}@if($unit)<span style="font-size:13px;font-weight:550;color:var(--sf-muted)"> {{ $unit }}</span>@endif</div>
        </div>
        <span class="sf-kpi-icon"><x-icon :name="$icon" :size="18" /></span>
    </div>
    @if($delta !== null)
        @if($has)
            <span class="sf-delta {{ $tone }}">
                @if($delta['dir'] === 'up')▲@elseif($delta['dir'] === 'down')▼@else•@endif
                {{ number_format($delta['pct'], 1, ',', '.') }}% <span style="font-weight:450;opacity:.8">vs periode lalu</span>
            </span>
        @else
            <span class="sf-delta none">Belum cukup data</span>
        @endif
    @endif
</{{ $href ? 'a' : 'div' }}>
