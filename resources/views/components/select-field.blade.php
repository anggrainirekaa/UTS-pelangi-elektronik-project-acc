@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="sf-label">{{ $label }}@if($required)<span style="color:var(--sf-danger)"> *</span>@endif</label>
    <select name="{{ $name }}" id="{{ $name }}" class="sf-select @error($name) is-invalid @enderror" @error($name) aria-invalid="true" @enderror>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) old($name, $value) === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @error($name)<p class="sf-error" role="alert">{{ $message }}</p>@enderror
</div>
