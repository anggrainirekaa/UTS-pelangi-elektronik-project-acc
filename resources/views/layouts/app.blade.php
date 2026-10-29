@php
    use App\Models\{AnomalyFlag, Purchase, StockOpname, StockTransfer, Barang};

    $user = auth()->user();
    $cartCount = $user->isPelanggan() ? array_sum(session('cart', [])) : 0;

    // Penanda jumlah pekerjaan yang menunggu (hanya untuk yang berwenang).
    $badge = [];
    if ($user->isManagement()) {
        $badge['pembelian.index'] = Purchase::where('status', Purchase::STATUS_PENDING)->count();
        $badge['transfer-stok.index'] = StockTransfer::where('status', StockTransfer::STATUS_PENDING)->count();
        $badge['opname.index'] = StockOpname::where('status', StockOpname::STATUS_SUBMITTED)->count();
        $badge['anomali.index'] = AnomalyFlag::where('status', 'open')->count();
    }
    if ($user->isInternal()) {
        $badge['stok-menipis.index'] = Barang::active()->lowStock()->count();
    }
    if ($user->isPelanggan()) {
        $badge['keranjang.index'] = $cartCount;
    }

    // [route, pola aktif, ikon, label]
    $menu = [['label' => null, 'items' => [['dashboard', 'dashboard', 'dashboard', 'Dashboard']]]];

    if ($user->isPelanggan()) {
        $menu[] = ['label' => 'Belanja', 'items' => [
            ['katalog.index', 'katalog.*', 'grid', 'Katalog Produk'],
            ['keranjang.index', 'keranjang.*', 'cart', 'Keranjang'],
            ['pesanan.index', 'pesanan.*', 'receipt', 'Pesanan Saya'],
        ]];
    }

    if ($user->isInternal()) {
        $menu[] = ['label' => 'Transaksi', 'items' => [
            ['penjualan.index', 'penjualan.*', 'wallet', 'Penjualan'],
            ['pembelian.index', 'pembelian.*', 'download', 'Pembelian'],
        ]];
        $menu[] = ['label' => 'Gudang & Stok', 'items' => [
            ['barang.index', 'barang.*', 'box', 'Produk'],
            ['stok-menipis.index', 'stok-menipis.*', 'alert', 'Stok Menipis'],
            ['kedaluwarsa.index', 'kedaluwarsa.*', 'calendar', 'Kedaluwarsa'],
            ['transfer-stok.index', 'transfer-stok.*', 'repeat', 'Transfer Gudang'],
            ['opname.index', 'opname.*', 'clipboard', 'Stock Opname'],
            ['stok-keluar.index', 'stok-keluar.*', 'upload', 'Penyesuaian Stok'],
            ['transaksi.index', 'transaksi.*', 'clock', 'Riwayat Transaksi'],
        ]];
        $smart = [
            ['restock.index', 'restock.*', 'sparkle', 'Rekomendasi Restock'],
            ['prediksi.index', 'prediksi.*', 'chart', 'Prediksi Stok'],
            ['analitik.index', 'analitik.*', 'chart', 'Analitik'],
        ];
        if ($user->isManagement()) {
            $smart[] = ['anomali.index', 'anomali.*', 'shield', 'Deteksi Anomali'];
            $smart[] = ['laporan.otomatis', 'laporan.otomatis*', 'file', 'Laporan Otomatis'];
        }
        $menu[] = ['label' => 'Fitur Pintar', 'items' => $smart];
    }

    if ($user->isManagement()) {
        $menu[] = ['label' => 'Data Master', 'items' => [
            ['kategori.index', 'kategori.*', 'tag', 'Kategori'],
            ['supplier.index', 'supplier.*', 'truck', 'Supplier'],
            ['gudang.index', 'gudang.*', 'warehouse', 'Gudang'],
                    ]];
        $menu[] = ['label' => 'Laporan & Akuntansi', 'items' => [
            ['laporan.index', 'laporan.index', 'file', 'Pusat Laporan'],
            ['laporan.penjualan', 'laporan.penjualan', 'chart', 'Laporan Penjualan'],
            ['laporan.laba-rugi', 'laporan.laba-rugi', 'wallet', 'Laba Rugi'],
            ['laporan.neraca-saldo', 'laporan.neraca-saldo', 'list', 'Neraca Saldo'],
            ['laporan.buku-besar', 'laporan.buku-besar', 'file', 'Buku Besar'],
            ['laporan.jurnal', 'laporan.jurnal', 'receipt', 'Jurnal Umum'],
            ['pengeluaran.index', 'pengeluaran.*', 'wallet', 'Pengeluaran'],
        ]];
        $menu[] = ['label' => 'Pengaturan', 'items' => [
            ['audit.index', 'audit.*', 'eye', 'Audit Trail'],
        ]];
        if ($user->isAdmin()) {
            $menu[] = ['label' => 'Sistem', 'items' => [
                ['pengguna.index', 'pengguna.*', 'users', 'Pengguna & Role'],
                ['mata-uang.index', 'mata-uang.*', 'settings', 'Mata Uang'],
                ['integrasi.index', 'integrasi.*', 'link', 'Integrasi & API'],
            ]];
        }
    }

    // Navigasi bawah (mobile): 4 tujuan utama + Menu.
    $bottom = $user->isPelanggan()
        ? [['dashboard', 'dashboard', 'dashboard', 'Beranda'], ['katalog.index', 'katalog.*', 'grid', 'Katalog'], ['keranjang.index', 'keranjang.*', 'cart', 'Keranjang'], ['pesanan.index', 'pesanan.*', 'receipt', 'Pesanan']]
        : [['dashboard', 'dashboard', 'dashboard', 'Beranda'], ['penjualan.index', 'penjualan.*', 'wallet', 'Penjualan'], ['barang.index', 'barang.*', 'box', 'Produk'], ['pembelian.index', 'pembelian.*', 'download', 'Pembelian']];

    $currencies = \App\Models\Currency::where('is_active', true)->orderByDesc('is_base')->orderBy('code')->get(['code']);
    $currentCurrency = app(\App\Services\CurrencyService::class)->current()->code;

    $unread = $user->unreadNotifications()->count();
    $latestNotes = $user->notifications()->latest()->limit(6)->get();
    $flash = collect([['success', 'Berhasil', session('success')], ['error', 'Gagal', session('error')], ['warning', 'Perhatian', session('warning')]])->filter(fn ($f) => $f[2]);
    $title = trim($__env->yieldContent('title', 'Dashboard'));
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

    {{-- Terapkan tema sebelum render agar tidak berkedip. --}}
    <script>
        try { var t = localStorage.getItem('sf-theme'); if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased" x-data="{ nav: false, bell: false, profile: false, theme: sfTheme.current() }" @keydown.escape.window="nav = false; bell = false; profile = false"
      @sf-theme.window="theme = $event.detail">

<a href="#konten" class="sr-only focus:not-sr-only sf-btn sf-btn-primary" style="position:fixed;top:8px;left:8px;z-index:200">Lompat ke konten</a>

<div class="sf-shell">

    {{-- SIDEBAR --}}
    <div x-show="nav" x-cloak x-transition.opacity @click="nav = false" class="fixed inset-0 z-[60] bg-black/50 lg:hidden"></div>

    <aside class="sf-sidebar" :class="{ 'open': nav }" aria-label="Menu utama">
        <a href="{{ route('dashboard') }}" class="sf-brand">
            <x-logo :size="34" /> <span class="leading-tight">Pelangi<br><small style="font-size:11px;font-weight:600;letter-spacing:.14em;opacity:.8">ELECTRONIC</small></span>
        </a>

        <nav class="pb-6">
            @foreach($menu as $group)
                @if($group['label'])<p class="sf-nav-label">{{ $group['label'] }}</p>@endif
                @foreach($group['items'] as [$route, $pattern, $icon, $label])
                    <a href="{{ route($route) }}" class="sf-nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif>
                        <x-icon :name="$icon" :size="19" /> <span>{{ $label }}</span>
                        @if(($badge[$route] ?? 0) > 0)<span class="sf-nav-badge" aria-label="{{ $badge[$route] }} item">{{ $badge[$route] > 99 ? '99+' : $badge[$route] }}</span>@endif
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>

    {{-- KONTEN --}}
    <div class="sf-main">

        <header class="sf-header">
            <button type="button" class="sf-iconbtn lg:hidden" @click="nav = true" aria-label="Buka menu"><x-icon name="menu" /></button>

            <div class="sf-search" x-data="globalSearch('{{ route('pencarian') }}')" @click.outside="close()">
                <x-icon name="search" :size="18" class="sf-search-icon" />
                <input x-ref="input" x-model="q" @input="onInput()" @focus="if (q.length >= 2) open = true"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()" @keydown.escape="close()"
                       type="search" class="sf-input" autocomplete="off" role="combobox" aria-expanded="false" :aria-expanded="open.toString()" aria-label="Cari"
                       placeholder="{{ $user->isPelanggan() ? 'Cari produk…' : 'Cari produk, SKU, barcode, kategori, supplier, transaksi…' }}">
                <span class="sf-kbd hidden sm:block" aria-hidden="true">/</span>

                <div x-show="open" x-cloak class="sf-pop left-0 right-0 mt-2" style="max-height: 70vh; overflow-y: auto" role="listbox">
                    <template x-if="loading"><div class="p-3 space-y-2"><div class="sf-skel h-10"></div><div class="sf-skel h-10"></div><div class="sf-skel h-10"></div></div></template>
                    <template x-if="!loading && !groups.length"><p class="p-4 text-sm" style="color: var(--sf-muted)">Tidak ada hasil untuk “<span x-text="q"></span>”.</p></template>
                    <template x-for="g in groups" :key="g.title">
                        <div>
                            <p class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider" style="color: var(--sf-muted)" x-text="g.title"></p>
                            <template x-for="item in g.items" :key="item.url">
                                <a :href="item.url" class="sf-result" :class="{ 'is-active': flat[active] === item }" role="option">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold truncate" x-text="item.title"></p>
                                        <p class="text-xs truncate" style="color: var(--sf-muted)" x-text="item.subtitle"></p>
                                    </div>
                                    <span class="sf-badge mute" x-show="item.badge" x-text="item.badge"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center gap-1 ml-auto">
                <form method="POST" action="{{ route('mata-uang.select') }}" class="hidden md:block">
                    @csrf
                    <select name="code" onchange="this.form.submit()" title="Mata uang tampilan" aria-label="Mata uang tampilan" class="sf-select" style="min-height:36px;padding:4px 28px 4px 10px;width:auto;font-size:13px">
                        @foreach($currencies as $c)<option value="{{ $c->code }}" @selected($c->code === $currentCurrency)>{{ $c->code }}</option>@endforeach
                    </select>
                </form>

                <button type="button" class="sf-iconbtn" @click="sfTheme.toggle()" :aria-label="theme === 'dark' ? 'Ganti ke tema terang' : 'Ganti ke tema gelap'">
                    <span x-show="theme !== 'dark'"><x-icon name="moon" /></span><span x-show="theme === 'dark'" x-cloak><x-icon name="sun" /></span>
                </button>

                {{-- Notifikasi --}}
                <div class="relative">
                    <button type="button" class="sf-iconbtn" @click="bell = !bell; profile = false" aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}" :aria-expanded="bell.toString()">
                        <x-icon name="bell" />
                        @if($unread > 0)<span class="sf-dot">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                    </button>
                    <div x-show="bell" x-cloak @click.outside="bell = false" class="sf-pop right-0 mt-2" style="width: min(380px, calc(100vw - 24px))">
                        <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid var(--sf-border)">
                            <p class="font-semibold text-sm">Notifikasi</p>
                            @if($unread > 0)
                                <form method="POST" action="{{ route('notifikasi.read-all') }}">@csrf<button class="text-xs font-semibold" style="color:var(--sf-primary)">Tandai semua dibaca</button></form>
                            @endif
                        </div>
                        @forelse($latestNotes as $n)
                            @php($d = $n->data)
                            <a href="{{ route('notifikasi.open', $n->id) }}" class="sf-result" style="align-items:flex-start">
                                <span class="mt-1.5 h-2 w-2 rounded-full shrink-0" style="background: {{ $n->read_at ? 'transparent' : 'var(--sf-primary)' }}"></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">{{ $d['title'] ?? 'Notifikasi' }}</p>
                                    <p class="text-xs" style="color:var(--sf-muted)">{{ $d['message'] ?? '' }}</p>
                                    <p class="text-[11px] mt-0.5" style="color:var(--sf-muted)">{{ $n->created_at->diffForHumans() }}</p>
                                </div>
                            </a>
                        @empty
                            <p class="p-6 text-center text-sm" style="color:var(--sf-muted)">Belum ada notifikasi.</p>
                        @endforelse
                        <a href="{{ route('notifikasi.index') }}" class="block text-center text-sm font-semibold py-3" style="color:var(--sf-primary);border-top:1px solid var(--sf-border)">Lihat semua</a>
                    </div>
                </div>

                {{-- Profil --}}
                <div class="relative">
                    <button type="button" class="sf-iconbtn" style="width:auto;padding:0 8px;gap:8px" @click="profile = !profile; bell = false" :aria-expanded="profile.toString()" aria-label="Menu profil">
                        <span class="grid place-items-center h-8 w-8 rounded-full text-sm font-bold text-white" style="background:linear-gradient(135deg,#3987F7,#1769E0)">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="hidden sm:block text-left leading-tight"><span class="block text-sm font-semibold max-w-[140px] truncate">{{ $user->name }}</span><span class="block text-[11px]" style="color:var(--sf-muted)">{{ $user->roleLabel() }}</span></span>
                    </button>
                    <div x-show="profile" x-cloak @click.outside="profile = false" class="sf-pop right-0 mt-2 w-56 py-1">
                        <a href="{{ route('profile.edit') }}" class="sf-result"><x-icon name="user" :size="18" /> Profil Saya</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button type="submit" class="sf-result w-full text-left no-loading" style="color:var(--sf-danger)"><x-icon name="logout" :size="18" /> Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="konten" class="sf-content sf-page" tabindex="-1">
            <div class="sf-print-head"><strong>Pelangi Elektronik</strong> — {{ $title }}<br><span>Dicetak {{ now()->format('d M Y H:i') }} oleh {{ $user->name }} ({{ $user->roleLabel() }})</span></div>
            @hasSection('breadcrumb')
                @yield('breadcrumb')
            @elseif($title !== 'Dashboard')
                <x-breadcrumb :items="[[$title, null]]" />
            @endif

            @if($title !== 'Dashboard')
                <h1 class="sf-page-title mb-4">{{ $title }}</h1>
            @endif

            @if($errors->has('items'))
                <div class="mb-4 px-4 py-3 rounded-xl text-sm" role="alert" style="background:rgba(217,66,66,.1);color:var(--sf-danger);border:1px solid rgba(217,66,66,.3)">{{ $errors->first('items') }}</div>
            @endif

            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
</div>

{{-- Navigasi bawah (mobile) --}}
<nav class="sf-bottom" aria-label="Navigasi cepat">
    @foreach($bottom as [$route, $pattern, $icon, $label])
        <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}" style="position:relative">
            <x-icon :name="$icon" :size="22" /> {{ $label }}
            @if($route === 'keranjang.index' && $cartCount > 0)<span class="sf-dot" style="top:2px;right:calc(50% - 22px)">{{ $cartCount }}</span>@endif
        </a>
    @endforeach
    <button type="button" @click="nav = true"><x-icon name="menu" :size="22" /> Menu</button>
</nav>

{{-- Toast --}}
<div class="sf-toasts" aria-live="polite" x-data>
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="sf-toast" :class="t.type" role="status">
            <div class="flex-1 min-w-0"><p class="sf-toast-title" x-text="t.title"></p><p class="sf-toast-msg" x-show="t.message" x-text="t.message"></p></div>
            <button type="button" @click="$store.toasts.dismiss(t.id)" aria-label="Tutup" class="sf-iconbtn" style="width:28px;height:28px"><x-icon name="x" :size="16" /></button>
        </div>
    </template>
</div>

{{-- Dialog konfirmasi ala iOS --}}
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
