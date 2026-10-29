<?php

namespace Database\Seeders;

use App\Models\Barang;
use App\Models\Category;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\JournalService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\StockTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo realistis (tanpa data pribadi/kredensial asli).
 * Seluruh stok dan transaksi dibuat lewat service yang sama dengan aplikasi,
 * sehingga stok, jurnal, dan laporan konsisten.
 *
 * Akun demo (password: "password"):
 *   owner@pelangi.test, staff@pelangi.test, pelanggan@pelangi.test
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $journal = app(JournalService::class);
        $journal->ensureAccounts();

        // ---- Pengguna (admin, owner, staff, pelanggan) ----
        $admin = $this->user('Pemilik Toko', 'owner@pelangi.test', User::ROLE_OWNER);
        $staff = $this->user('Staff Gudang', 'staff@pelangi.test', User::ROLE_STAFF);
        $budi = $this->user('Budi Santoso', 'pelanggan@pelangi.test', User::ROLE_PELANGGAN, '081234567801', 'Jl. Merdeka No. 10, Lhokseumawe');
        $siti = $this->user('Siti Aminah', 'siti@pelangi.test', User::ROLE_PELANGGAN, '081234567802', 'Jl. Sudirman No. 5, Aceh Utara');
        $this->user('Rahmat Hidayat', 'rahmat@pelangi.test', User::ROLE_PELANGGAN, '081234567803', 'Jl. Pase No. 22, Lhokseumawe');

        // ---- Gudang ----
        $main = Warehouse::default();
        $main->update(['name' => 'Gudang Utama Pelangi Elektronik']);
        $branch = Warehouse::firstOrCreate(['code' => 'GDG-002'], ['name' => 'Gudang Cabang Banda Aceh', 'address' => 'Jl. T. Nyak Arief No. 3, Banda Aceh']);

        // ---- Kategori & supplier ----
        $categories = collect([
            'Televisi' => 'Televisi LED dan Smart TV',
            'Audio' => 'Speaker, headphone, earphone, dan soundbar',
            'Aksesoris Elektronik' => 'Kabel, charger, power bank, baterai, dan perlengkapan',
            'Peralatan Rumah Tangga' => 'Peralatan elektronik rumah tangga',
            'Komponen Elektronik' => 'Komponen dan modul elektronik',
            'Komputer & Periferal' => 'Laptop, monitor, printer, keyboard, mouse, dan penyimpanan',
            'Smartphone' => 'Telepon pintar berbagai kelas',
            'Jaringan' => 'Router dan perangkat jaringan',
        ])->mapWithKeys(fn ($desc, $name) => [$name => Category::firstOrCreate(['name' => $name], ['description' => $desc])]);

        $suppliers = collect([
            ['PT Maju Elektro Distribusi', 'Hendra Wijaya', '061-4512345', 'sales@majuelektro.test', 'Jl. Gatot Subroto No. 88, Medan', 7],
            ['CV Cahaya Audio Nusantara', 'Rina Marlina', '0645-771234', 'order@cahayaaudio.test', 'Jl. Perdagangan No. 8, Lhokseumawe', 4],
            ['PT Nusantara Aksesoris', 'Dedi Kurniawan', '021-5557788', 'cs@nusantaraaksesoris.test', 'Kawasan Industri Pulogadung, Jakarta', 6],
            ['UD Rumah Tangga Sejahtera', 'Ahmad Fauzi', '0812-3456-7890', 'sejahtera@supplier.test', 'Pasar Inpres Blok C-12, Aceh Utara', 3],
            ['CV Komponen Mandiri', 'Yusuf Ramadhan', '0645-882211', 'komponen@mandiri.test', 'Jl. Pase No. 14, Lhokseumawe', 5],
            ['PT Digital Prima Komputer', 'Maya Sari', '061-7788990', 'sales@digitalprima.test', 'Jl. Sisingamangaraja No. 120, Medan', 5],
            ['CV Gadget Sentosa', 'Fajar Nugroho', '0811-2233-4455', 'order@gadgetsentosa.test', 'Jl. Teuku Umar No. 7, Banda Aceh', 4],
        ])->mapWithKeys(fn ($s) => [$s[0] => Supplier::firstOrCreate(['name' => $s[0]], ['contact_person' => $s[1], 'phone' => $s[2], 'email' => $s[3], 'address' => $s[4], 'lead_time_days' => $s[5]])]);

        // Subkategori contoh: Televisi > Smart TV
        $categories['Smart TV'] = Category::firstOrCreate(['name' => 'Smart TV'], ['parent_id' => $categories['Televisi']->id, 'description' => 'Televisi dengan sistem operasi pintar']);

        // ---- Produk (stok awal 0; stok masuk lewat pembelian) ----
        // [SKU, nama, kategori, supplier, satuan, harga beli, harga jual, stok minimum, deskripsi]
        $catalog = [
            ['TV-0001', 'LED TV 32 Inci HD', 'Televisi', 'PT Maju Elektro Distribusi', 'unit', 1650000, 1999000, 4, 'Televisi LED 32 inci resolusi HD, 2 HDMI, 1 USB'],
            ['TV-0002', 'Smart TV 43 Inci Full HD', 'Smart TV', 'PT Maju Elektro Distribusi', 'unit', 3200000, 3799000, 3, 'Smart TV 43 inci Full HD, WiFi, aplikasi streaming'],
            ['TV-0003', 'Smart TV 55 Inci 4K UHD', 'Smart TV', 'PT Maju Elektro Distribusi', 'unit', 5400000, 6299000, 2, 'Smart TV 55 inci 4K UHD, HDR, WiFi'],
            ['AU-0001', 'Speaker Bluetooth Portabel', 'Audio', 'CV Cahaya Audio Nusantara', 'unit', 185000, 259000, 8, 'Speaker Bluetooth 5.0 tahan percikan air'],
            ['AU-0002', 'Headphone Over-Ear', 'Audio', 'CV Cahaya Audio Nusantara', 'unit', 220000, 315000, 6, 'Headphone over-ear dengan mikrofon'],
            ['AU-0003', 'Earphone Nirkabel TWS', 'Audio', 'CV Cahaya Audio Nusantara', 'unit', 150000, 229000, 10, 'Earphone TWS dengan casing pengisi daya'],
            ['AU-0004', 'Soundbar 2.1 Channel', 'Audio', 'PT Maju Elektro Distribusi', 'unit', 780000, 1099000, 3, 'Soundbar 2.1 channel dengan subwoofer'],
            ['AK-0001', 'Kabel HDMI 2 Meter', 'Aksesoris Elektronik', 'PT Nusantara Aksesoris', 'pcs', 28000, 45000, 15, 'Kabel HDMI 2.0 panjang 2 meter'],
            ['AK-0002', 'Charger Fast Charging 20W', 'Aksesoris Elektronik', 'PT Nusantara Aksesoris', 'pcs', 65000, 99000, 12, 'Adaptor charger USB-C 20W'],
            ['AK-0003', 'Power Bank 10.000 mAh', 'Aksesoris Elektronik', 'PT Nusantara Aksesoris', 'unit', 120000, 179000, 8, 'Power bank 10.000 mAh dua port'],
            ['AK-0004', 'Remote TV Universal', 'Aksesoris Elektronik', 'PT Nusantara Aksesoris', 'pcs', 22000, 39000, 15, 'Remote pengganti untuk berbagai merek TV'],
            ['AK-0005', 'Baterai Alkaline AA (isi 4)', 'Aksesoris Elektronik', 'PT Nusantara Aksesoris', 'pack', 18000, 28000, 20, 'Baterai alkaline AA, memiliki tanggal kedaluwarsa'],
            ['RT-0001', 'Rice Cooker 1,8 Liter', 'Peralatan Rumah Tangga', 'UD Rumah Tangga Sejahtera', 'unit', 275000, 369000, 5, 'Rice cooker 1,8 liter dengan fungsi hangat'],
            ['RT-0002', 'Kipas Angin Berdiri 16 Inci', 'Peralatan Rumah Tangga', 'UD Rumah Tangga Sejahtera', 'unit', 210000, 289000, 5, 'Kipas angin berdiri 3 kecepatan'],
            ['RT-0003', 'Setrika Listrik', 'Peralatan Rumah Tangga', 'UD Rumah Tangga Sejahtera', 'unit', 85000, 129000, 6, 'Setrika listrik 350 watt'],
            ['KM-0001', 'Resistor Kit 600 Pcs', 'Komponen Elektronik', 'CV Komponen Mandiri', 'pack', 45000, 75000, 6, 'Kumpulan resistor 1/4 watt berbagai nilai'],
            ['KM-0002', 'Mikrokontroler Uno Kompatibel', 'Komponen Elektronik', 'CV Komponen Mandiri', 'unit', 75000, 115000, 8, 'Papan mikrokontroler kompatibel Uno beserta kabel USB'],
            ['CP-0001', 'Laptop 14 Inci Core i5 8/512GB', 'Komputer & Periferal', 'PT Digital Prima Komputer', 'unit', 6800000, 7999000, 3, 'Laptop 14 inci Core i5, RAM 8GB, SSD 512GB, Windows 11'],
            ['CP-0002', 'Monitor LED 24 Inci Full HD', 'Komputer & Periferal', 'PT Digital Prima Komputer', 'unit', 1250000, 1599000, 4, 'Monitor IPS 24 inci 75Hz, HDMI + VGA'],
            ['CP-0003', 'Keyboard Mekanikal RGB', 'Komputer & Periferal', 'PT Digital Prima Komputer', 'unit', 280000, 399000, 6, 'Keyboard mekanikal blue switch dengan lampu RGB'],
            ['CP-0004', 'Mouse Wireless 2.4GHz', 'Komputer & Periferal', 'PT Digital Prima Komputer', 'unit', 75000, 119000, 10, 'Mouse nirkabel ergonomis, baterai awet'],
            ['CP-0005', 'Printer Inkjet Multifungsi', 'Komputer & Periferal', 'PT Digital Prima Komputer', 'unit', 1350000, 1699000, 3, 'Printer print, scan, copy dengan tangki tinta'],
            ['CP-0006', 'Flashdisk 64GB USB 3.0', 'Komputer & Periferal', 'PT Digital Prima Komputer', 'pcs', 55000, 85000, 12, 'Flashdisk 64GB USB 3.0 kecepatan tinggi'],
            ['CP-0007', 'Headset Gaming Berkabel', 'Komputer & Periferal', 'PT Digital Prima Komputer', 'unit', 210000, 319000, 6, 'Headset gaming dengan mikrofon dan bantalan empuk'],
            ['JR-0001', 'Router WiFi Dual Band AC1200', 'Jaringan', 'PT Digital Prima Komputer', 'unit', 320000, 449000, 5, 'Router WiFi dual band 2.4 + 5GHz, 4 antena'],
            ['HP-0001', 'Smartphone 6,5 Inci 8/128GB', 'Smartphone', 'CV Gadget Sentosa', 'unit', 2600000, 2999000, 4, 'Smartphone layar 6,5 inci, RAM 8GB, memori 128GB, kamera 50MP'],
            ['HP-0002', 'Smartphone 6,7 Inci 12/256GB', 'Smartphone', 'CV Gadget Sentosa', 'unit', 4100000, 4699000, 3, 'Smartphone layar AMOLED 6,7 inci, RAM 12GB, memori 256GB'],
        ];

        // Gambar lokal (public/images/products/*.svg), tidak bergantung pada URL luar.
        $images = [
            'TV-0001' => 'tv', 'TV-0002' => 'tv', 'TV-0003' => 'tv', 'AU-0001' => 'speaker', 'AU-0002' => 'headphone', 'AU-0003' => 'earbuds',
            'AU-0004' => 'soundbar', 'AK-0001' => 'kabel', 'AK-0002' => 'charger', 'AK-0003' => 'powerbank', 'AK-0004' => 'remote',
            'AK-0005' => 'baterai', 'RT-0001' => 'ricecooker', 'RT-0002' => 'kipas', 'RT-0003' => 'setrika', 'KM-0001' => 'resistor',
            'KM-0002' => 'mikrokontroler', 'CP-0001' => 'laptop', 'CP-0002' => 'monitor', 'CP-0003' => 'keyboard', 'CP-0004' => 'mouse',
            'CP-0005' => 'printer', 'CP-0006' => 'flashdisk', 'CP-0007' => 'headset', 'JR-0001' => 'router', 'HP-0001' => 'smartphone', 'HP-0002' => 'smartphone',
        ];

        // Hanya produk yang memerlukan tanggal kedaluwarsa (batch + FEFO) yang melacaknya.
        $perishable = ['Baterai Alkaline AA (isi 4)'];

        $products = [];
        foreach ($catalog as $i => [$sku, $name, $cat, $sup, $unit, $buy, $sell, $min, $desc]) {
            $products[$name] = Barang::firstOrCreate(['code' => $sku], [
                'name' => $name,
                'category_id' => $categories[$cat]->id,
                'supplier_id' => $suppliers[$sup]->id,
                'barcode' => '899'.str_pad((string) (200001 + $i), 10, '0', STR_PAD_LEFT),
                'unit' => $unit, 'purchase_price' => $buy, 'selling_price' => $sell,
                'stock' => 0, 'minimum_stock' => $min, 'description' => $desc, 'is_active' => true,
                'tracks_expiry' => in_array($name, $perishable, true), 'expiry_warning_days' => 30,
            ]);
            if (! $products[$name]->image && isset($images[$sku])) {
                $products[$name]->update(['image' => 'images/products/'.$images[$sku].'.svg']);
            }
        }

        if (Purchase::exists()) {
            return; // seeder idempoten: transaksi demo hanya dibuat sekali
        }

        $journal->postOpeningCapital(500_000_000, $admin);

        $purchases = app(PurchaseService::class);
        $sales = app(SaleService::class);

        // ---- Pembelian multi-item (tersebar beberapa bulan agar grafik terisi) ----
        $this->purchase($purchases, $staff, $suppliers['PT Maju Elektro Distribusi'], $main, 75, [
            ['LED TV 32 Inci HD', 12], ['Smart TV 43 Inci Full HD', 8], ['Smart TV 55 Inci 4K UHD', 4], ['Soundbar 2.1 Channel', 6],
        ], $products);
        $this->purchase($purchases, $staff, $suppliers['CV Cahaya Audio Nusantara'], $main, 60, [
            ['Speaker Bluetooth Portabel', 30], ['Headphone Over-Ear', 15], ['Earphone Nirkabel TWS', 40],
        ], $products);
        $this->purchase($purchases, $staff, $suppliers['PT Nusantara Aksesoris'], $main, 45, [
            ['Kabel HDMI 2 Meter', 60], ['Charger Fast Charging 20W', 40], ['Power Bank 10.000 mAh', 25], ['Remote TV Universal', 40],
            ['Baterai Alkaline AA (isi 4)', 30, 25, 'BAT-A'], ['Baterai Alkaline AA (isi 4)', 40, 700, 'BAT-B'],
        ], $products);
        $this->purchase($purchases, $admin, $suppliers['UD Rumah Tangga Sejahtera'], $main, 30, [
            ['Rice Cooker 1,8 Liter', 15], ['Kipas Angin Berdiri 16 Inci', 12], ['Setrika Listrik', 15],
        ], $products);
        $this->purchase($purchases, $staff, $suppliers['CV Komponen Mandiri'], $main, 10, [
            ['Resistor Kit 600 Pcs', 20], ['Mikrokontroler Uno Kompatibel', 20],
        ], $products);
        $this->purchase($purchases, $staff, $suppliers['PT Digital Prima Komputer'], $main, 50, [
            ['Laptop 14 Inci Core i5 8/512GB', 8], ['Monitor LED 24 Inci Full HD', 10], ['Keyboard Mekanikal RGB', 15], ['Mouse Wireless 2.4GHz', 25],
            ['Printer Inkjet Multifungsi', 6], ['Flashdisk 64GB USB 3.0', 30], ['Headset Gaming Berkabel', 14], ['Router WiFi Dual Band AC1200', 12],
        ], $products);
        $this->purchase($purchases, $admin, $suppliers['CV Gadget Sentosa'], $main, 35, [
            ['Smartphone 6,5 Inci 8/128GB', 12], ['Smartphone 6,7 Inci 12/256GB', 8],
        ], $products);

        // ---- Transfer ke gudang cabang ----
        app(StockTransferService::class)->transfer($staff, [
            'from_warehouse_id' => $main->id, 'to_warehouse_id' => $branch->id,
            'barang_id' => $products['Kabel HDMI 2 Meter']->id, 'quantity' => 15, 'note' => 'Stok awal cabang',
        ]);

        // ---- Penjualan langsung di toko (POS), tersebar beberapa bulan ----
        $this->sale($sales, $staff, $main, 70, null, [['LED TV 32 Inci HD', 2], ['Kabel HDMI 2 Meter', 4]], true, $products);
        $this->sale($sales, $staff, $main, 55, $budi, [['Smart TV 43 Inci Full HD', 2], ['Soundbar 2.1 Channel', 3]], true, $products);
        $this->sale($sales, $staff, $main, 40, null, [['Earphone Nirkabel TWS', 8], ['Speaker Bluetooth Portabel', 6], ['Charger Fast Charging 20W', 10], ['Baterai Alkaline AA (isi 4)', 6]], true, $products);
        $this->sale($sales, $staff, $main, 28, $siti, [['Rice Cooker 1,8 Liter', 4], ['Kipas Angin Berdiri 16 Inci', 3], ['Setrika Listrik', 5]], true, $products, ['percent', 5]);
        $this->sale($sales, $staff, $main, 15, null, [['Power Bank 10.000 mAh', 6], ['Remote TV Universal', 10]], false, $products); // belum lunas -> piutang
        $this->sale($sales, $staff, $main, 6, $budi, [['Smart TV 55 Inci 4K UHD', 2], ['Kabel HDMI 2 Meter', 6]], true, $products);
        $this->sale($sales, $staff, $main, 2, null, [['Headphone Over-Ear', 4], ['Earphone Nirkabel TWS', 10], ['Power Bank 10.000 mAh', 5]], true, $products);

        $this->sale($sales, $staff, $main, 33, $siti, [['Laptop 14 Inci Core i5 8/512GB', 2], ['Mouse Wireless 2.4GHz', 3], ['Flashdisk 64GB USB 3.0', 5]], true, $products);
        $this->sale($sales, $staff, $main, 21, null, [['Smartphone 6,5 Inci 8/128GB', 4], ['Charger Fast Charging 20W', 4]], true, $products);
        $this->sale($sales, $staff, $main, 9, $budi, [['Monitor LED 24 Inci Full HD', 3], ['Keyboard Mekanikal RGB', 4], ['Headset Gaming Berkabel', 3]], true, $products);
        $this->sale($sales, $staff, $main, 3, null, [['Smartphone 6,7 Inci 12/256GB', 2], ['Router WiFi Dual Band AC1200', 3], ['Printer Inkjet Multifungsi', 1]], true, $products);
        $this->sale($sales, $staff, $main, 1, null, [['Mouse Wireless 2.4GHz', 6], ['Flashdisk 64GB USB 3.0', 8]], true, $products);

        // ---- Penjualan dibatalkan (contoh pembatalan oleh admin; stok kembali) ----
        $cancelled = $this->sale($sales, $staff, $main, 4, null, [['Speaker Bluetooth Portabel', 3]], true, $products);
        $sales->cancel($cancelled, $admin, 'Salah input pelanggan');

        // ---- Pesanan online pelanggan ----
        $pending = $sales->createOrder($budi, [
            'shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe',
            'note' => 'Tolong dikirim sore hari',
            'items' => [
                ['barang_id' => $products['Charger Fast Charging 20W']->id, 'quantity' => 3],
                ['barang_id' => $products['Remote TV Universal']->id, 'quantity' => 2],
            ],
        ]);
        app(\App\Services\PaymentService::class)->pay($pending, 'qris');

        $sales->createOrder($siti, [
            'shipping_address' => 'Jl. Sudirman No. 5, Aceh Utara',
            'items' => [['barang_id' => $products['Setrika Listrik']->id, 'quantity' => 2]],
        ]);

        $done = $sales->createOrder($budi, [
            'shipping_address' => 'Jl. Merdeka No. 10, Lhokseumawe',
            'items' => [['barang_id' => $products['Kipas Angin Berdiri 16 Inci']->id, 'quantity' => 1]],
        ]);
        app(\App\Services\PaymentService::class)->pay($done, 'transfer_bank');
        $sales->complete($done, $staff);

        // ---- Retur penjualan (admin; di luar jendela retur staff) ----
        $returned = Sale::where('customer_id', $budi->id)->where('channel', 'pos')->orderBy('id')->first();
        if ($returned) {
            $line = $returned->items()->where('barang_id', $products['Soundbar 2.1 Channel']->id)->first();
            $sales->returnItems($returned, $admin, ['reason' => 'Perangkat tidak berfungsi saat diterima', 'items' => [['sale_item_id' => $line->id, 'quantity' => 1]]]);
        }

        // ---- Alur PO: diajukan (menunggu persetujuan), disetujui + diterima sebagian ----
        $po1 = $purchases->createDraft($staff, [
            'supplier_id' => $suppliers['CV Cahaya Audio Nusantara']->id, 'warehouse_id' => $main->id, 'note' => 'Restok produk audio',
            'items' => [
                ['barang_id' => $products['Earphone Nirkabel TWS']->id, 'quantity' => 30, 'unit_cost' => $products['Earphone Nirkabel TWS']->purchase_price],
                ['barang_id' => $products['Headphone Over-Ear']->id, 'quantity' => 10, 'unit_cost' => $products['Headphone Over-Ear']->purchase_price],
            ],
        ]);
        $purchases->submit($po1, $staff);

        $po2 = $purchases->createDraft($staff, [
            'supplier_id' => $suppliers['UD Rumah Tangga Sejahtera']->id, 'warehouse_id' => $main->id, 'note' => 'Pesanan rice cooker & setrika',
            'items' => [
                ['barang_id' => $products['Rice Cooker 1,8 Liter']->id, 'quantity' => 20, 'unit_cost' => $products['Rice Cooker 1,8 Liter']->purchase_price],
                ['barang_id' => $products['Setrika Listrik']->id, 'quantity' => 10, 'unit_cost' => $products['Setrika Listrik']->purchase_price],
            ],
        ]);
        $purchases->submit($po2, $staff);
        $purchases->approve($po2, $admin);
        $riceLine = $po2->items()->where('barang_id', $products['Rice Cooker 1,8 Liter']->id)->first();
        $purchases->receive($po2->fresh(), $staff, [['purchase_item_id' => $riceLine->id, 'quantity' => 12]], 'Pengiriman tahap 1');

        // ---- Transfer yang perlu persetujuan (jumlah >= ambang untuk staff) ----
        config(['stockflow.transfer_approval_threshold' => 20]);
        app(StockTransferService::class)->request($staff, [
            'from_warehouse_id' => $main->id, 'to_warehouse_id' => $branch->id,
            'barang_id' => $products['Remote TV Universal']->id, 'quantity' => 20, 'note' => 'Stok remote cabang',
        ]);
        config(['stockflow.transfer_approval_threshold' => 100]);

        // ---- Stock opname: satu disetujui, satu menunggu persetujuan ----
        $opnames = app(\App\Services\StockOpnameService::class);
        $o1 = $opnames->create($staff, $main->id, [$products['Resistor Kit 600 Pcs']->id], 'Opname rutin bulanan');
        $opnames->saveCounts($o1, [$products['Resistor Kit 600 Pcs']->id => ['counted' => $products['Resistor Kit 600 Pcs']->fresh()->stock - 1, 'reason' => 'Satu pack rusak karena lembap']]);
        $opnames->submit($o1, $staff);
        $opnames->approve($o1, $admin, 'Sesuai hasil pengecekan');

        $o2 = $opnames->create($staff, $branch->id, [$products['Kabel HDMI 2 Meter']->id], 'Opname gudang cabang');
        $opnames->saveCounts($o2, [$products['Kabel HDMI 2 Meter']->id => ['counted' => 14, 'reason' => 'Selisih 1 pcs, dicurigai salah hitung saat transfer']]);
        $opnames->submit($o2, $staff);

        // ---- Beban operasional ----
        foreach ([[40, 'Sewa toko', 6_000_000], [25, 'Listrik & air', 1_200_000], [12, 'Gaji karyawan', 8_000_000]] as [$ago, $title, $amount]) {
            $expense = Expense::create(['user_id' => $admin->id, 'expense_date' => now()->subDays($ago)->toDateString(), 'title' => $title, 'amount' => $amount]);
            $journal->postExpense($expense);
        }

        // Notifikasi kedaluwarsa & pindai anomali awal (juga berjalan otomatis berkala).
        app(\App\Services\MaintenanceService::class)->run();
    }

    private function user(string $name, string $email, string $role, ?string $phone = null, ?string $address = null): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name, 'role' => $role, 'phone' => $phone, 'address' => $address,
            'password' => Hash::make('password'), 'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function purchase(PurchaseService $service, User $user, Supplier $supplier, Warehouse $wh, int $daysAgo, array $lines, array $products): void
    {
        $purchase = $service->create($user, [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $wh->id,
            'note' => 'Pembelian rutin',
            'items' => collect($lines)->map(fn ($l) => [
                'barang_id' => $products[$l[0]]->id, 'quantity' => $l[1], 'unit_cost' => $products[$l[0]]->purchase_price,
                'expiry_date' => isset($l[2]) ? now()->addDays($l[2])->toDateString() : null,
                'batch_number' => $l[3] ?? null,
            ])->all(),
        ]);

        $this->backdate($purchase, 'purchased_at', $daysAgo);
    }

    private function sale(SaleService $service, User $user, Warehouse $wh, int $daysAgo, ?User $customer, array $lines, bool $paid, array $products, ?array $discount = null): Sale
    {
        $sale = $service->createPos($user, [
            'discount_type' => $discount[0] ?? null, 'discount_value' => $discount[1] ?? null,
            'customer_id' => $customer?->id,
            'warehouse_id' => $wh->id,
            'payment_method' => $paid ? 'cash' : null,
            'mark_paid' => $paid,
            'items' => collect($lines)->map(fn ($l) => ['barang_id' => $products[$l[0]]->id, 'quantity' => $l[1]])->all(),
        ]);

        $this->backdate($sale, 'sold_at', $daysAgo);

        return $sale->fresh();
    }

    /** Geser tanggal transaksi demo ke masa lalu (termasuk jurnalnya). */
    private function backdate($model, string $dateColumn, int $daysAgo): void
    {
        $date = now()->subDays($daysAgo);
        $changes = [$dateColumn => $date, 'created_at' => $date];

        if ($model instanceof Sale) {
            $changes['completed_at'] = $date;
            $changes['paid_at'] = $model->paid_at ? $date : null;
        }

        DB::table($model->getTable())->where('id', $model->id)->update(
            collect($changes)->map(fn ($v) => $v?->toDateTimeString())->all()
        );

        DB::table('stock_movements')->where('reference_type', $model->getMorphClass())->where('reference_id', $model->id)
            ->update(['occurred_at' => $date->toDateTimeString(), 'created_at' => $date->toDateTimeString()]);

        if ($model instanceof Purchase) {
            DB::table('purchase_receipts')->where('purchase_id', $model->id)
                ->update(['received_at' => $date->toDateTimeString(), 'created_at' => $date->toDateTimeString()]);
            DB::table('purchases')->where('id', $model->id)->update(['approved_at' => $date->toDateTimeString()]);
        }

        JournalEntry::where('reference_type', $model->getMorphClass())
            ->where('reference_id', $model->id)
            ->update(['entry_date' => $date->toDateString()]);
    }
}
