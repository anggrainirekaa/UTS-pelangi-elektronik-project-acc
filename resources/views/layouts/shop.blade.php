@php
    use App\Models\Category;

    $user = auth()->user();
    $cartCount = array_sum(session('cart', []));
    $unread = $user->unreadNotifications()->count();
    $latestNotes = $user->notifications()->latest()->limit(5)->get();
    $navCategories = Category::active()->whereNull("parent_id")->orderBy('name')->limit(12)->get(['id', 'name']);
    $flash = collect([['success', 'Berhasil', session('success')], ['error', 'Gagal', session('error')], ['warning', 'Perhatian', session('warning')]])->filter(fn ($f) => $f[2]);
    $title = trim($__env->yieldContent('title', 'Belanja'));
    $bottom = [
        ['dashboard', 'dashboard', 'dashboard', 'Beranda'], ['katalog.index', 'katalog.*', 'grid', 'Produk'],
        ['keranjang.index', 'keranjang.*', 'cart', 'Keranjang'], ['pesanan.index', 'pesanan.*', 'receipt', 'Pesanan'], ['profile.edit', 'profile.*', 'user', 'Akun'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#3A5BE8">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <title>{{ $title }} · {{ config('app.name', 'Pelangi Elektronik') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <script>
        try { var t = localStorage.getItem('sf-theme'); if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased sh-body" x-data="{ bell: false, profile: false, theme: sfTheme.current() }" @keydown.escape.window="bell = false; profile = false" @sf-theme.window="theme = $event.detail">

<a href="#konten" class="sr-only focus:not-sr-only sf-btn sf-btn-primary" style="position:fixed;top:8px;left:8px;z-index:200">Lompat ke konten</a>

<header class="sh-header">
    <div class="sh-wrap sh-header-row">
        <a href="{{ route('dashboard') }}" class="sh-logo"><x-logo :size="34" /><span class="hidden sm:inline">Pelangi Elektronik</span></a>

        <form method="GET" action="{{ route('katalog.index') }}" class="sh-search" role="search">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari produk, SKU, atau kategori…" aria-label="Cari produk" autocomplete="off">
            <button type="submit" aria-label="Cari"><x-icon name="search" :size="18" /></button>
        </form>

        <div class="sh-actions">
            <a href="{{ route('keranjang.index') }}" class="sh-icon" aria-label="Keranjang{{ $cartCount ? ', '.$cartCount.' item' : '' }}">
                <x-icon name="cart" :size="22" />@if($cartCount > 0)<span class="sf-dot">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>@endif
            </a>

            <div class="relative">
                <button type="button" class="sh-icon" @click="bell = !bell; profile = false" aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}" :aria-expanded="bell.toString()">
                    <x-icon name="bell" :size="22" />@if($unread > 0)<span class="sf-dot">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                </button>
                <div x-show="bell" x-cloak @click.outside="bell = false" class="sf-pop right-0 mt-2" style="width:min(360px,calc(100vw - 24px));color:var(--sf-text)">
                    <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid var(--sf-border)">
                        <p class="font-semibold text-sm">Notifikasi</p>
                        @if($unread > 0)<form method="POST" action="{{ route('notifikasi.read-all') }}">@csrf<button class="text-xs font-semibold" style="color:var(--sf-primary)">Tandai semua dibaca</button></form>@endif
                    </div>
                    @forelse($latestNotes as $n)
                        @php($d = $n->data)
                        <a href="{{ route('notifikasi.open', $n->id) }}" class="sf-result" style="align-items:flex-start">
                            <span class="mt-1.5 h-2 w-2 rounded-full shrink-0" style="background: {{ $n->read_at ? 'transparent' : 'var(--sf-primary)' }}"></span>
                            <div class="min-w-0"><p class="text-sm font-semibold">{{ $d['title'] ?? 'Notifikasi' }}</p><p class="text-xs" style="color:var(--sf-muted)">{{ $d['message'] ?? '' }}</p><p class="text-[11px] mt-0.5" style="color:var(--sf-muted)">{{ $n->created_at->diffForHumans() }}</p></div>
                        </a>
                    @empty
                        <p class="p-6 text-center text-sm" style="color:var(--sf-muted)">Belum ada notifikasi.</p>
                    @endforelse
                    <a href="{{ route('notifikasi.index') }}" class="block text-center text-sm font-semibold py-3" style="color:var(--sf-primary);border-top:1px solid var(--sf-border)">Lihat semua</a>
                </div>
            </div>

            <button type="button" class="sh-icon hidden sm:grid" @click="sfTheme.toggle()" :aria-label="theme === 'dark' ? 'Ganti ke tema terang' : 'Ganti ke tema gelap'">
                <span x-show="theme !== 'dark'"><x-icon name="moon" :size="20" /></span><span x-show="theme === 'dark'" x-cloak><x-icon name="sun" :size="20" /></span>
            </button>

            <div class="relative">
                <button type="button" class="sh-profile" @click="profile = !profile; bell = false" :aria-expanded="profile.toString()" aria-label="Menu akun">
                    <span class="sh-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><span class="hidden md:block text-sm font-semibold max-w-[110px] truncate">{{ $user->name }}</span>
                </button>
                <div x-show="profile" x-cloak @click.outside="profile = false" class="sf-pop right-0 mt-2 w-56 py-1" style="color:var(--sf-text)">
                    <a href="{{ route('profile.edit') }}" class="sf-result"><x-icon name="user" :size="18" /> Profil Saya</a>
                    <a href="{{ route('pesanan.index') }}" class="sf-result"><x-icon name="receipt" :size="18" /> Pesanan Saya</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="sf-result w-full text-left no-loading" style="color:var(--sf-danger)"><x-icon name="logout" :size="18" /> Keluar</button></form>
                </div>
            </div>
        </div>
    </div>

    @if($navCategories->isNotEmpty())
        <nav class="sh-wrap sh-chips" aria-label="Kategori">
            <a href="{{ route('katalog.index') }}" class="{{ ! request('category_id') && request()->routeIs('katalog.index') ? 'on' : '' }}">Semua</a>
            @foreach($navCategories as $c)<a href="{{ route('katalog.index', ['category_id' => $c->id]) }}" class="{{ (int) request('category_id') === $c->id ? 'on' : '' }}">{{ $c->name }}</a>@endforeach
        </nav>
    @endif
</header>

<main id="konten" class="sh-wrap sh-main sf-page" tabindex="-1">
    @if($errors->has('items'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm" role="alert" style="background:rgba(217,66,66,.1);color:var(--sf-danger);border:1px solid rgba(217,66,66,.3)">{{ $errors->first('items') }}</div>
    @endif
    @yield('content')
</main>

<footer class="sh-wrap sh-footer">© {{ date('Y') }} Pelangi Elektronik · Harga dan stok diperbarui langsung dari gudang.</footer>

<nav class="sf-bottom" aria-label="Navigasi cepat">
    @foreach($bottom as [$route, $pattern, $icon, $label])
        <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}" style="position:relative">
            <x-icon :name="$icon" :size="22" /> {{ $label }}
            @if($route === 'keranjang.index' && $cartCount > 0)<span class="sf-dot" style="top:2px;right:calc(50% - 22px)">{{ $cartCount }}</span>@endif
        </a>
    @endforeach
</nav>

<div class="sf-toasts" aria-live="polite" x-data>
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="sf-toast" :class="t.type" role="status">
            <div class="flex-1 min-w-0"><p class="sf-toast-title" x-text="t.title"></p><p class="sf-toast-msg" x-show="t.message" x-text="t.message"></p></div>
            <button type="button" @click="$store.toasts.dismiss(t.id)" aria-label="Tutup" class="sf-iconbtn" style="width:28px;height:28px"><x-icon name="x" :size="16" /></button>
        </div>
    </template>
</div>

<div x-data x-show="$store.confirm.open" x-cloak class="sf-overlay" role="dialog" aria-modal="true" @keydown.escape.window="$store.confirm.open && $store.confirm.answer(false)">
    <div class="sf-dialog" @click.outside="$store.confirm.answer(false)">
        <div class="sf-dialog-body"><p class="sf-dialog-title" x-text="$store.confirm.opts.title"></p><p class="sf-dialog-msg" x-text="$store.confirm.opts.message"></p></div>
        <div class="sf-dialog-actions">
            <button type="button" @click="$store.confirm.answer(false)" x-text="$store.confirm.opts.cancel"></button>
            <button type="button" :class="{ danger: $store.confirm.opts.danger }" @click="$store.confirm.answer(true)" x-text="$store.confirm.opts.ok"></button>
        </div>
    </div>
</div>

@stack('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        @foreach($flash as [$type, $label, $message])
            Alpine.store('toasts').push(@js($type), @js($label), @js($message));
        @endforeach
        @if($errors->any() && ! $errors->has('items'))
            Alpine.store('toasts').push('error', 'Periksa kembali isian', @js($errors->first()));
        @endif
    });
    if ('serviceWorker' in navigator) { navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {}); }
</script>
</body>
</html>
