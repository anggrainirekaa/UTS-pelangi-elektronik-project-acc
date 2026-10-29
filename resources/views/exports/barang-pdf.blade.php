<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Data Barang</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #1e293b;
        }
        h2 {
            margin-bottom: 4px;
        }
        p.subtitle {
            margin-top: 0;
            color: #64748b;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        td.text-right {
            text-align: right;
        }
        td.text-center {
            text-align: center;
        }
        .low-stock {
            color: #dc2626;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Laporan Data Barang</h2>
    <p class="subtitle">Dicetak pada: {{ now()->translatedFormat('d F Y, H:i') }} WIB</p>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Supplier</th>
                <th>Satuan</th>
                <th class="text-right">Harga Beli</th>
                <th class="text-right">Harga Jual</th>
                <th class="text-center">Stok</th>
            </tr>
        </thead>
        <tbody>
            @forelse($barangs as $barang)
                <tr>
                    <td>{{ $barang->code }}</td>
                    <td>{{ $barang->name }}</td>
                    <td>{{ $barang->category->name ?? '-' }}</td>
                    <td>{{ $barang->supplier->name ?? '-' }}</td>
                    <td>{{ $barang->unit }}</td>
                    <td class="text-right">Rp {{ number_format($barang->purchase_price, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($barang->selling_price, 0, ',', '.') }}</td>
                    <td class="text-center {{ $barang->isLowStock() ? 'low-stock' : '' }}">
                        {{ $barang->stock }} {{ $barang->unit }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">Tidak ada data barang.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>