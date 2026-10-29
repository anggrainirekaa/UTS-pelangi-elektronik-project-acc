<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#3A5BE8">
    <title>{{ config('app.name', 'Pelangi Elektronik') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <script>try { var t = localStorage.getItem('sf-theme'); if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen grid lg:grid-cols-2">
        <section class="hidden lg:flex flex-col justify-between p-12 text-white" style="background: radial-gradient(900px 500px at 10% 0%, rgba(255,77,109,.55), transparent 60%), radial-gradient(900px 500px at 100% 30%, rgba(46,196,182,.45), transparent 60%), linear-gradient(160deg, #3A5BE8, #8338EC 60%, #12163D)">
            <div class="flex items-center gap-3 text-2xl font-bold tracking-tight">
                <x-logo :size="46" /> Pelangi Elektronik
            </div>
            <div>
                <h2 class="text-4xl font-bold leading-tight tracking-tight">Kelola stok, pembelian,<br>dan penjualan elektronik<br>dalam satu tempat.</h2>
                <p class="mt-4 text-lg" style="color:#CFE0F8">Pantau produk elektronik, pemasok, transaksi, dan laporan usaha dengan data yang selalu sesuai.</p>
            </div>
            <p class="text-sm" style="color:#9DB7DD">Data demo hanya untuk pembelajaran.</p>
        </section>

        <section class="flex items-center justify-center p-6 sm:p-10 pe-auth-bg">
            <div class="w-full max-w-md sf-page">
                <a href="/" class="lg:hidden flex items-center gap-2 mb-6 text-xl font-bold" style="color:var(--sf-navy)">
                    <x-logo :size="36" /> Pelangi Elektronik
                </a>
                <div class="sf-card p-6 sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </section>
    </div>
</body>
</html>
