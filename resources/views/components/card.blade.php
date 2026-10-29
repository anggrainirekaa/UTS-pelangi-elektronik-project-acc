@props(['title' => null, 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'sf-card']) }}>
    @if($title || isset($actions))
        <div class="sf-card-header">
            <div>
                <h2 class="sf-card-title">{{ $title }}</h2>
                @if($subtitle)<p class="sf-card-sub">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    {{ $slot }}
</div>
