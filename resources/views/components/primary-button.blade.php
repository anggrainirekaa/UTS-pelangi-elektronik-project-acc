<button {{ $attributes->merge(['type' => 'submit', 'class' => 'sf-btn sf-btn-primary']) }}>
    {{ $slot }}
</button>
