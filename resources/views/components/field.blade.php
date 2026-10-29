@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="sf-label">{{ $label }}@if($required)<span style="color:var(--sf-danger)"> *</span>@endif</label>
    @if($type === 'textarea')
        <textarea name="{{ $name }}" id="{{ $name }}" rows="3" class="sf-textarea @error($name) is-invalid @enderror" @error($name) aria-invalid="true" @enderror>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
            @if($type !== 'password') value="{{ old($name, $value) }}" @endif
            {{ $attributes->except('class') }}
            class="sf-input @error($name) is-invalid @enderror" @error($name) aria-invalid="true" @enderror>
    @endif
    @if($hint)<p class="sf-help">{{ $hint }}</p>@endif
    @error($name)<p class="sf-error" role="alert">{{ $message }}</p>@enderror
</div>
