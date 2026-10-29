@props(['items' => []])
{{-- items: [['Label', url|null], ...] --}}
<nav aria-label="Breadcrumb" class="sf-breadcrumb">
    <a href="{{ route('dashboard') }}">Beranda</a>
    @foreach($items as [$label, $url])
        <x-icon name="chevron-right" :size="14" />
        @if($url)<a href="{{ $url }}">{{ $label }}</a>@else<span aria-current="page" style="color: var(--sf-text); font-weight: 600">{{ $label }}</span>@endif
    @endforeach
</nav>
