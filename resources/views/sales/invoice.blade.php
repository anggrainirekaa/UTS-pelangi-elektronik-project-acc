<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $sale->sale_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 20px; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #f1f5f9; text-align: left; padding: 6px 8px; font-size: 11px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .r { text-align: right; } .c { text-align: center; }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; background: #e2e8f0; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Pelangi Elektronik</h1>
    <p class="muted">Invoice penjualan</p>
    <table style="margin-top:8px">
        <tr>
            <td style="border:none;padding:0"><strong>No. Invoice:</strong> {{ $sale->sale_number }}<br>
                <strong>Tanggal:</strong> {{ $sale->sold_at->format('d M Y H:i') }}<br>
                <strong>Status:</strong> <span class="badge">{{ $sale->statusLabel() }}</span> <span class="badge">{{ $sale->isPaid() ? 'Lunas' : 'Belum lunas' }}</span></td>
            <td style="border:none;padding:0" class="r"><strong>Pelanggan:</strong> {{ $sale->customer->name ?? 'Umum' }}<br>
                {{ $sale->shipping_address }}</td>
        </tr>
    </table>
    <table>
        <thead><tr><th>Produk</th><th class="c">Jumlah</th><th class="r">Harga</th><th class="r">Subtotal</th></tr></thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr><td>{{ $item->barang->name ?? '-' }} <span class="muted">({{ $item->barang->code ?? '' }})</span></td>
                    <td class="c">{{ $item->quantity }} {{ $item->barang->unit ?? '' }}</td>
                    <td class="r">{{ money($item->unit_price, base: true) }}</td>
                    <td class="r">{{ money($item->subtotal, base: true) }}</td></tr>
            @endforeach
            <tr><td colspan="3" class="r"><strong>Total</strong></td><td class="r"><strong>{{ money($sale->total, base: true) }}</strong></td></tr>
        </tbody>
    </table>
    <p class="muted" style="margin-top:24px">Dokumen ini dibuat otomatis oleh sistem. Nominal dalam Rupiah (IDR).</p>
</body>
</html>
