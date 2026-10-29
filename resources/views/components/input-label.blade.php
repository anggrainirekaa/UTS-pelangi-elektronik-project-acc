@props(['value'])

<label {{ $attributes->merge(['class' => 'sf-label']) }}>
    {{ $value ?? $slot }}
</label>
