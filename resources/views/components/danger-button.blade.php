<button {{ $attributes->merge(['type' => 'submit', 'class' => 'sf-btn sf-btn-danger']) }}>
    {{ $slot }}
</button>
