@php $b = $barang ?? null; @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-field name="name" label="Nama Barang" :value="$b?->name" required />
    <x-field name="code" label="SKU / Kode" :value="$b?->code" hint="{{ $b ? 'Harus unik.' : 'Kosongkan untuk dibuat otomatis (BRG-0001, ...).' }}" :required="(bool) $b" />
    <x-field name="barcode" label="Barcode" :value="$b?->barcode" hint="Opsional (EAN/UPC/Code128). Dipakai untuk scan di halaman penjualan dan pencarian." />
    <x-select-field name="category_id" label="Kategori" placeholder="-- Pilih Kategori --" :options="$categories->pluck('name', 'id')" :value="$b?->category_id" required />
    <x-select-field name="supplier_id" label="Supplier Utama" placeholder="-- Tanpa Supplier --" :options="$suppliers->pluck('name', 'id')" :value="$b?->supplier_id" />
    <x-field name="unit" label="Satuan" :value="$b?->unit ?? 'pcs'" required />
    <x-field name="minimum_stock" label="Stok Minimum" type="number" min="0" :value="$b?->minimum_stock ?? 5" required />
    <x-field name="purchase_price" label="Harga Beli (Rp)" type="number" min="0" :value="$b?->purchase_price ?? 0" required />
    <x-field name="selling_price" label="Harga Jual (Rp)" type="number" min="0" :value="$b?->selling_price ?? 0" required />
    <x-field name="description" label="Deskripsi" type="textarea" :value="$b?->description" class="sm:col-span-2" />
    <div class="sm:col-span-2">
        <label for="image" class="block text-sm font-medium text-slate-700 mb-1.5">Gambar (JPG/PNG, maks 2MB)</label>
        @if($b?->image)<img src="{{ $b->image_url }}" class="w-20 h-20 rounded-lg object-cover border mb-2" alt="">@endif
        <input type="file" name="image" id="image" accept="image/png,image/jpeg" class="block w-full text-sm text-slate-600">
        @error('image')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2 grid gap-3 sm:grid-cols-2" x-data="{ on: {{ old('tracks_expiry', $b?->tracks_expiry ?? false) ? 'true' : 'false' }} }">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="tracks_expiry" value="0"><input type="checkbox" name="tracks_expiry" value="1" x-model="on" class="rounded border-slate-300 text-indigo-600" @checked(old('tracks_expiry', $b?->tracks_expiry ?? false))>
            Produk memiliki tanggal kedaluwarsa (batch &amp; FEFO)
        </label>
        <div x-show="on" x-cloak><x-field name="expiry_warning_days" label="Peringatan sebelum kedaluwarsa (hari)" type="number" min="1" :value="$b?->expiry_warning_days ?? 30" /></div>
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('is_active', $b?->is_active ?? true))>
        Tampilkan di katalog pelanggan
    </label>
</div>
@unless($b)
    <p class="text-xs text-slate-500 mt-4">ℹ️ Stok awal diisi lewat menu <strong>Pembelian</strong> agar tercatat sebagai transaksi dan jurnal.</p>
@endunless
