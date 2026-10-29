{{--
    Baris item dinamis (Alpine). Parameter:
      $mode        'sale' | 'purchase'
      $products    array: id, code, name, unit, price (jual) / cost (beli), stocks {warehouse_id: qty}, stock
      $rows        baris awal (old input / prefill)
    Harga penjualan hanya ditampilkan untuk informasi; server selalu mengambil harga dari database.
--}}
@php
    $config = [
        'mode' => $mode,
        'products' => $products,
        'rows' => $rows,
        'warehouse' => (string) old('warehouse_id', $defaultWarehouse->id ?? ''),
    ];
@endphp
<div x-data="lineItems(@js($config))" x-init="document.getElementById('warehouse_id')?.addEventListener('change', e => warehouse = e.target.value)" @barcode-scanned.window="addByCode($event.detail.code)" class="space-y-3">
    @if($mode === 'sale')
    <div class="flex flex-col sm:flex-row gap-2" x-data="barcodeScanner()">
        <div class="relative flex-1">
            <x-icon name="barcode" :size="18" class="sf-search-icon" />
            <input type="text" x-model="scanQuery" @keydown.enter.prevent="addByCode(scanQuery); scanQuery = ''" class="sf-input" style="padding-left:42px" placeholder="Scan / ketik barcode atau SKU lalu Enter" autocomplete="off" aria-label="Scan barcode atau SKU">
        </div>
        <button type="button" class="sf-btn sf-btn-ghost" @click="start()"><x-icon name="camera" :size="18" /> Scan kamera</button>
        <div x-show="open" x-cloak class="sf-overlay" role="dialog" aria-modal="true" @keydown.escape.window="stop()">
            <div class="sf-card p-4" style="width:min(420px,100%)">
                <video x-ref="video" playsinline muted class="w-full rounded-xl bg-black" style="aspect-ratio:4/3"></video>
                <p x-show="error" class="text-sm mt-2" style="color:var(--sf-danger)" x-text="error"></p>
                <button type="button" class="sf-btn sf-btn-ghost w-full mt-3" @click="stop()">Tutup</button>
            </div>
        </div>
    </div>
    <p x-show="scanMsg" x-text="scanMsg" class="text-sm" style="color:var(--sf-muted)" role="status"></p>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 text-slate-600 uppercase text-xs"><tr>
                <th class="px-3 py-2 min-w-56">Produk</th>
                <th class="px-3 py-2 text-center">{{ $mode === 'sale' ? 'Tersedia' : 'Stok Kini' }}</th>
                <th class="px-3 py-2 text-right w-36">{{ $mode === 'sale' ? 'Harga Jual' : 'Harga Beli (Rp)' }}</th>
                <th class="px-3 py-2 text-center w-28">Jumlah</th>
                <th class="px-3 py-2 text-right">Subtotal</th>
                <th class="px-3 py-2"></th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                <template x-for="(row, i) in rows" :key="row.key">
                    <tr>
                        <td class="px-3 py-2">
                            <select :name="`items[${i}][barang_id]`" x-model="row.barang_id" @change="pick(row)" class="w-full rounded-lg border-slate-300 text-sm" required>
                                <option value="">-- Pilih produk --</option>
                                <template x-for="p in products" :key="p.id">
                                    <option :value="p.id" x-text="`${p.name} (${p.code})`" :selected="String(p.id) === String(row.barang_id)"></option>
                                </template>
                            </select>
                        </td>
                        <td class="px-3 py-2 text-center text-slate-500" x-text="stockOf(row)"></td>
                        <td class="px-3 py-2 text-right">
                            @if($mode === 'sale')
                                <span x-text="rupiah(unitOf(row))"></span>
                            @else
                                <input type="number" min="0" :name="`items[${i}][unit_cost]`" x-model.number="row.unit_cost" class="w-full rounded-lg border-slate-300 text-sm text-right" required>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <input type="number" min="1" :name="`items[${i}][quantity]`" x-model.number="row.quantity" class="w-full rounded-lg border-slate-300 text-sm text-center" required>
                        </td>
                        <td class="px-3 py-2 text-right font-medium" x-text="rupiah(unitOf(row) * (row.quantity || 0))"></td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" @click="remove(i)" x-show="rows.length > 1" class="text-red-500 hover:text-red-700" title="Hapus baris">✕</button>
                        </td>
                    </tr>
                </template>
            </tbody>
            <tfoot>
                <tr class="border-t border-slate-200">
                    <td colspan="4" class="px-3 py-3 text-right font-semibold text-slate-700">Total{{ $mode === 'sale' ? ' (dihitung ulang oleh server)' : '' }}</td>
                    <td class="px-3 py-3 text-right text-lg font-bold text-slate-800" x-text="rupiah(total())"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <button type="button" @click="add()" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-200">+ Tambah Baris</button>
    @foreach($errors->getMessages() as $key => $messages)
        @if(str_starts_with($key, 'items.'))
            <p class="text-sm text-red-600">{{ $messages[0] }}</p>
        @endif
    @endforeach
</div>

@once
<script>
    window.lineItems = function (cfg) {
        let counter = 0;
        const newRow = (r = {}) => ({
            key: ++counter,
            barang_id: r.barang_id ?? '',
            quantity: r.quantity ?? 1,
            unit_cost: r.unit_cost ?? 0,
        });

        return {
            mode: cfg.mode,
            products: cfg.products,
            warehouse: cfg.warehouse,
            rows: (cfg.rows && cfg.rows.length ? cfg.rows : [{}]).map(newRow),
            find(row) { return this.products.find(p => String(p.id) === String(row.barang_id)); },
            pick(row) {
                const p = this.find(row);
                if (p && this.mode === 'purchase' && !row.unit_cost) row.unit_cost = p.cost;
                if (p && this.mode === 'purchase') row.unit_cost = p.cost;
            },
            stockOf(row) {
                const p = this.find(row);
                if (!p) return '-';
                return this.mode === 'sale' ? ((p.stocks || {})[this.warehouse] ?? 0) + ' ' + p.unit : p.stock + ' ' + p.unit;
            },
            unitOf(row) {
                const p = this.find(row);
                if (!p) return 0;
                return this.mode === 'sale' ? p.price : (Number(row.unit_cost) || 0);
            },
            total() { return this.rows.reduce((sum, r) => sum + this.unitOf(r) * (Number(r.quantity) || 0), 0); },
            scanQuery: '', scanMsg: '',
            addByCode(code) {
                const c = String(code || '').trim().toLowerCase();
                if (!c) return;
                const p = this.products.find(x => (x.barcode && String(x.barcode).toLowerCase() === c) || String(x.code).toLowerCase() === c);
                if (!p) { this.scanMsg = 'Produk dengan kode “' + code + '” tidak ditemukan.'; return; }
                const existing = this.rows.find(r => String(r.barang_id) === String(p.id));
                if (existing) { existing.quantity = (Number(existing.quantity) || 0) + 1; }
                else {
                    const empty = this.rows.find(r => !r.barang_id);
                    if (empty) { empty.barang_id = p.id; empty.quantity = 1; this.pick(empty); }
                    else { const r = newRow({ barang_id: p.id, quantity: 1 }); this.rows.push(r); this.pick(r); }
                }
                this.scanMsg = p.name + ' ditambahkan.';
            },
            add() { this.rows.push(newRow()); },
            remove(i) { this.rows.splice(i, 1); },
            rupiah(n) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0); },
        };
    };
</script>
@endonce
