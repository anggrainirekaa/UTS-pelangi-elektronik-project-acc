@props(['size' => 36])
{{-- Logo Pelangi Elektronik: busur pelangi (sinyal) dan petir pada latar gelap. --}}
<svg {{ $attributes->merge(['class' => 'pe-logo']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" role="img" aria-label="Logo Pelangi Elektronik" xmlns="http://www.w3.org/2000/svg">
    <defs><linearGradient id="pe-bg-{{ $size }}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2B3A8F"/><stop offset="1" stop-color="#12163D"/></linearGradient></defs>
    <rect width="64" height="64" rx="16" fill="url(#pe-bg-{{ $size }})"/>
    <path d="M8 38a24 24 0 0 1 48 0" fill="none" stroke="#FF4D6D" stroke-width="3.4" stroke-linecap="round"/>
    <path d="M13 38a19 19 0 0 1 38 0" fill="none" stroke="#FF9F1C" stroke-width="3.4" stroke-linecap="round"/>
    <path d="M18 38a14 14 0 0 1 28 0" fill="none" stroke="#2EC4B6" stroke-width="3.4" stroke-linecap="round"/>
    <path d="M23 38a9 9 0 0 1 18 0" fill="none" stroke="#3A86FF" stroke-width="3.4" stroke-linecap="round"/>
    <path d="M33.5 30 26 44h5.5L29 56l12-16h-6z" fill="#FFD23F"/>
</svg>
