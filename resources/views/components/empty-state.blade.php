@props(['icon' => 'box', 'title', 'message' => null])
<div {{ $attributes->merge(['class' => 'sf-empty']) }}>
    <div class="sf-empty-icon"><x-icon :name="$icon" :size="28" /></div>
    <h3>{{ $title }}</h3>
    @if($message)<p class="mt-1 text-sm max-w-md mx-auto">{{ $message }}</p>@endif
    @if(trim((string) $slot) !== '')<div class="mt-4 flex justify-center gap-2 flex-wrap">{{ $slot }}</div>@endif
</div>
