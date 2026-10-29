import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;
window.Chart = Chart;

/* ------------------------------------------------------------------ Tema terang/gelap */
window.sfTheme = {
    current() { return document.documentElement.classList.contains('dark') ? 'dark' : 'light'; },
    set(mode) {
        document.documentElement.classList.toggle('dark', mode === 'dark');
        try { localStorage.setItem('sf-theme', mode); } catch (e) { /* mode privat */ }
        window.dispatchEvent(new CustomEvent('sf-theme', { detail: mode }));
    },
    toggle() { this.set(this.current() === 'dark' ? 'light' : 'dark'); },
};

/* ------------------------------------------------------------------ Format angka */
const nf = new Intl.NumberFormat('id-ID');
window.sfFormat = {
    number: (v) => nf.format(Math.round(Number(v) || 0)),
    rupiah: (v) => 'Rp ' + nf.format(Math.round(Number(v) || 0)),
    compact: (v) => new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(Number(v) || 0),
};

/* ------------------------------------------------------------------ Toast & konfirmasi */
document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
        items: [],
        push(type, title, message = '', ttl = 5200) {
            const id = Date.now() + Math.random();
            this.items.push({ id, type, title, message });
            setTimeout(() => this.dismiss(id), ttl);
        },
        dismiss(id) { this.items = this.items.filter((t) => t.id !== id); },
    });

    Alpine.store('confirm', {
        open: false, opts: {}, _resolve: null,
        ask(opts) {
            this.opts = Object.assign({ title: 'Konfirmasi', message: '', ok: 'Ya, lanjutkan', cancel: 'Batal', danger: false }, opts);
            this.open = true;
            return new Promise((resolve) => { this._resolve = resolve; });
        },
        answer(value) { this.open = false; if (this._resolve) this._resolve(value); this._resolve = null; },
    });

    /* -------------------------------------------------------------- Pencarian global */
    Alpine.data('globalSearch', (url) => ({
        q: '', open: false, loading: false, groups: [], active: -1, timer: null,
        get flat() { return this.groups.flatMap((g) => g.items); },
        init() {
            window.addEventListener('keydown', (e) => {
                const tag = (e.target.tagName || '').toLowerCase();
                if (e.key === '/' && !['input', 'textarea', 'select'].includes(tag) && !e.target.isContentEditable) {
                    e.preventDefault();
                    this.$refs.input.focus();
                }
            });
        },
        onInput() {
            clearTimeout(this.timer);
            if (this.q.trim().length < 2) { this.groups = []; this.open = false; return; }
            this.loading = true; this.open = true;
            this.timer = setTimeout(() => this.fetchResults(), 180);
        },
        async fetchResults() {
            try {
                const res = await fetch(url + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                this.groups = data.groups || [];
                this.active = this.flat.length ? 0 : -1;
            } catch (e) { this.groups = []; }
            this.loading = false;
        },
        move(delta) {
            if (!this.flat.length) return;
            this.active = (this.active + delta + this.flat.length) % this.flat.length;
        },
        go() {
            const item = this.flat[this.active];
            if (item) window.location.href = item.url;
        },
        close() { this.open = false; this.active = -1; },
    }));

    /* -------------------------------------------------------------- Pemindai barcode kamera */
    Alpine.data('barcodeScanner', () => ({
        open: false, supported: 'BarcodeDetector' in window && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
        error: '', stream: null, raf: null,
        async start() {
            this.error = '';
            this.open = true;
            if (!this.supported) { this.error = 'Peramban ini belum mendukung pemindaian kamera. Gunakan scanner USB atau ketik kode secara manual.'; return; }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                const video = this.$refs.video;
                video.srcObject = this.stream;
                await video.play();
                const detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'qr_code'] });
                const tick = async () => {
                    if (!this.open) return;
                    try {
                        const codes = await detector.detect(video);
                        if (codes.length) {
                            this.$dispatch('barcode-scanned', { code: codes[0].rawValue });
                            this.stop();
                            return;
                        }
                    } catch (e) { /* frame belum siap */ }
                    this.raf = requestAnimationFrame(tick);
                };
                tick();
            } catch (e) {
                this.error = 'Kamera tidak dapat diakses. Periksa izin kamera pada peramban Anda.';
            }
        },
        stop() {
            this.open = false;
            if (this.raf) cancelAnimationFrame(this.raf);
            if (this.stream) this.stream.getTracks().forEach((t) => t.stop());
            this.stream = null;
        },
    }));

    /* -------------------------------------------------------------- Grafik dashboard */
    Alpine.data('dashboardCharts', (cfg) => ({
        preset: cfg.preset || '30d', from: cfg.from || '', to: cfg.to || '', granularity: cfg.granularity || 'auto',
        loading: true, empty: false, error: '', charts: {}, tables: { sales: [], movement: [], top: [] },
        init() {
            this.load();
            window.addEventListener('sf-theme', () => this.render());
        },
        setPreset(p) { this.preset = p; this.from = ''; this.to = ''; this.load(); },
        async load() {
            this.loading = true; this.error = '';
            const p = new URLSearchParams({ preset: this.preset, granularity: this.granularity });
            if (this.from) p.set('from', this.from);
            if (this.to) p.set('to', this.to);
            try {
                const res = await fetch(cfg.url + '?' + p.toString(), { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                this.data = await res.json();
                this.empty = !this.data.sales.values.some((v) => v) && !this.data.movement.in.some((v) => v) && !this.data.movement.out.some((v) => v);
                this.loading = false;
                this.$nextTick(() => { this.render(); this.syncTables(); });
            } catch (e) {
                this.loading = false;
                this.error = 'Grafik gagal dimuat. Coba muat ulang.';
            }
        },
        syncTables() {
            const d = this.data;
            this.tables.sales = d.sales.labels.map((l, i) => ({ label: l, value: d.sales.values[i], purchase: d.purchases.values[i] }));
            this.tables.movement = d.movement.labels.map((l, i) => ({ label: l, in: d.movement.in[i], out: d.movement.out[i] }));
            this.tables.top = d.top.map((t) => ({ name: t.name, qty: t.qty }));
        },
        render() {
            if (!this.data || this.empty) return;
            const css = getComputedStyle(document.documentElement);
            const muted = css.getPropertyValue('--sf-muted').trim();
            const grid = css.getPropertyValue('--sf-border').trim();
            const primary = css.getPropertyValue('--sf-primary').trim();
            const base = {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                animation: { duration: 220 },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: muted } } },
                scales: { x: { ticks: { color: muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: muted }, grid: { color: grid } } },
            };
            const draw = (key, canvas, config) => {
                if (!canvas) return;
                if (this.charts[key]) this.charts[key].destroy();
                this.charts[key] = new Chart(canvas, config);
            };

            const d = this.data;
            draw('sales', this.$refs.sales, {
                type: 'line',
                data: { labels: d.sales.labels, datasets: [{ label: 'Penjualan bersih (Rp)', data: d.sales.values, borderColor: '#3A86FF', backgroundColor: 'rgba(58,134,255,.14)', fill: true, tension: .35, pointRadius: d.sales.labels.length > 40 ? 0 : 3, borderWidth: 2.5 }, { label: 'Pembelian diterima (Rp)', data: d.purchases.values, borderColor: '#FF9F1C', backgroundColor: 'rgba(255,159,28,.10)', fill: true, tension: .35, pointRadius: d.sales.labels.length > 40 ? 0 : 3, borderWidth: 2.5 }] },
                options: Object.assign({}, base, { plugins: Object.assign({}, base.plugins, { tooltip: { callbacks: { label: (c) => ' ' + sfFormat.rupiah(c.parsed.y) } } }), scales: Object.assign({}, base.scales, { y: Object.assign({}, base.scales.y, { ticks: { color: muted, callback: (v) => sfFormat.compact(v) } }) }) }),
            });
            draw('movement', this.$refs.movement, {
                type: 'bar',
                data: { labels: d.movement.labels, datasets: [
                    { label: 'Barang masuk', data: d.movement.in, backgroundColor: '#2EC4B6', borderRadius: 6 },
                    { label: 'Barang keluar', data: d.movement.out, backgroundColor: '#FF4D6D', borderRadius: 6 },
                ] },
                options: Object.assign({}, base, { plugins: Object.assign({}, base.plugins, { tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': ' + sfFormat.number(c.parsed.y) + ' unit' } } }) }),
            });
            draw('top', this.$refs.top, {
                type: 'bar',
                data: { labels: d.top.map((t) => t.name), datasets: [{ label: 'Terjual (unit)', data: d.top.map((t) => t.qty), backgroundColor: ['#FF4D6D', '#FF9F1C', '#FFD23F', '#2EC4B6', '#3A86FF', '#8338EC'], borderRadius: 8 }] },
                options: Object.assign({}, base, { indexAxis: 'y', plugins: Object.assign({}, base.plugins, { legend: { display: false } }), scales: { x: Object.assign({}, base.scales.y), y: { ticks: { color: muted }, grid: { display: false } } } }),
            });
        },
    }));

    /* -------------------------------------------------------------- Grafik umum (halaman analitik) */
    Alpine.data('sfChart', (config) => ({
        chart: null,
        init() {
            this.draw();
            window.addEventListener('sf-theme', () => this.draw());
        },
        draw() {
            const css = getComputedStyle(document.documentElement);
            const muted = css.getPropertyValue('--sf-muted').trim();
            const grid = css.getPropertyValue('--sf-border').trim();
            if (this.chart) this.chart.destroy();
            const cfg = JSON.parse(JSON.stringify(config));
            const isPie = ['doughnut', 'pie'].includes(cfg.type);
            cfg.options = Object.assign({ responsive: true, maintainAspectRatio: false, animation: { duration: 220 }, interaction: { mode: 'index', intersect: false } }, cfg.options || {});
            cfg.options.plugins = Object.assign({ legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: muted } } }, cfg.options.plugins || {});
            if (!isPie) {
                cfg.options.scales = {
                    x: { ticks: { color: muted, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { color: muted, callback: (v) => (cfg.money ? sfFormat.compact(v) : sfFormat.number(v)) }, grid: { color: grid } },
                };
            }
            cfg.options.plugins.tooltip = { callbacks: { label: (c) => ' ' + (c.dataset.label ? c.dataset.label + ': ' : c.label + ': ') + (cfg.money ? sfFormat.rupiah(c.parsed.y ?? c.parsed) : sfFormat.number(c.parsed.y ?? c.parsed)) } };
            this.chart = new Chart(this.$refs.canvas, cfg);
        },
    }));
});

Alpine.start();

/* ------------------------------------------------------------------ Dialog konfirmasi untuk form */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.dataset.confirm && form.dataset.confirmed !== '1') {
        e.preventDefault();
        const submitter = e.submitter;
        Alpine.store('confirm').ask({
            title: form.dataset.confirmTitle || 'Konfirmasi',
            message: form.dataset.confirm,
            ok: form.dataset.confirmOk || 'Ya, lanjutkan',
            danger: form.dataset.danger !== undefined,
        }).then((ok) => {
            if (!ok) return;
            form.dataset.confirmed = '1';
            form.requestSubmit ? form.requestSubmit(submitter) : form.submit();
        });
        return;
    }

    // Umpan balik loading + cegah klik ganda (server juga idempoten lewat request_key).
    const btn = e.submitter || form.querySelector('button[type=submit]');
    if (btn && !btn.classList.contains('no-loading')) setTimeout(() => btn.classList.add('is-loading'), 0);
});

// Pulihkan tombol saat halaman diambil dari cache back/forward.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) document.querySelectorAll('.is-loading').forEach((b) => b.classList.remove('is-loading'));
});
