<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Manifest Produksi - {{ $manifestData['manifest_number'] }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #111; font-size: 13px; }
        .document-box { max-width: 850px; margin: 20px auto; padding: 35px 45px; background: white; border: 1px solid #ddd; }
        .header-title { text-transform: uppercase; font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 20px; }
        .table-items th, .table-items td { border: 1px solid #333; padding: 5px 8px; font-size: 12px; }
        .signature-box { margin-top: 40px; }
        @media print {
            .no-print { display: none !important; }
            .document-box { border: none; padding: 0; margin: 0; }
        }
    </style>
</head>
<body class="bg-light">

<div class="no-print text-center py-3">
    <button onclick="window.print()" class="btn btn-primary btn-sm px-4 shadow-sm">
        <i class="bi bi-printer"></i> Cetak / Simpan PDF
    </button>
    <a href="{{ route('production.show', $prodOrder->id) }}" class="btn btn-secondary btn-sm ms-2">Kembali</a>
</div>

<div class="document-box shadow-sm">
    <div class="text-center header-title">
        <h4 class="mb-1 fw-bold">PT BANK PEMBANGUNAN DAERAH JAWA TIMUR, Tbk.</h4>
        <h5 class="mb-1 text-uppercase text-decoration-underline">REKAP MANIFEST PRODUKSI & PERSONALISASI KARTU</h5>
        <span class="fs-6 font-monospace">Nomor Manifest: {{ $manifestData['manifest_number'] }}</span>
    </div>

    <div class="row g-3 mb-3 border p-3 rounded bg-light">
        <div class="col-6">
            <span class="fs-8 text-uppercase fw-bold text-secondary d-block">Unit Kerja / Cabang Tujuan:</span>
            <strong class="fs-6">{{ $manifestData['destination']['name'] }} ({{ $manifestData['destination']['code'] }})</strong>
            <p class="mb-0 text-dark">
                Alamat: {{ $manifestData['destination']['address'] }}<br>
                Kota: {{ $manifestData['destination']['city'] }} | Telp: {{ $manifestData['destination']['phone'] }}<br>
                Cost Center: <span class="font-monospace fw-semibold">{{ $manifestData['destination']['cost_center'] ?: '-' }}</span>
            </p>
        </div>
        <div class="col-6 text-end">
            <span class="fs-8 text-uppercase fw-bold text-secondary d-block">Data Bon Produksi:</span>
            <strong class="font-monospace fs-6 text-danger">{{ $prodOrder->production_number }}</strong>
            <p class="mb-0 text-secondary fs-8">
                Tanggal: {{ $manifestData['generated_at'] }}<br>
                Gudang Asal: {{ $manifestData['warehouse']->name }}<br>
                @if($prodOrder->embossFile)
                    Ref. File Emboss: <span class="font-monospace fw-semibold">{{ $prodOrder->embossFile->file_id }}</span><br>
                @endif
                @if($prodOrder->order)
                    Ref. Order: <span class="font-monospace fw-semibold">{{ $prodOrder->order->order_number }}</span>
                @endif
            </p>
        </div>
    </div>

    <h6 class="fw-bold mb-2">RINCIAN BAHAN KARTU / TOKEN / KUE YANG DIKELUARKAN:</h6>
    <table class="table table-items w-100 mb-3">
        <thead>
            <tr class="table-secondary text-center">
                <th style="width: 5%">No.</th>
                <th>Kode / SKU</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th>Satuan</th>
                <th class="text-center">Qty Rencana</th>
                <th class="text-center">Qty Dikeluarkan</th>
                <th class="text-end">Estimasi Nilai</th>
            </tr>
        </thead>
        <tbody>
            @foreach($manifestData['items'] as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-monospace text-center">{{ $item['sku'] }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['category'] ?: '-' }}</td>
                    <td class="text-center">{{ $item['uom'] }}</td>
                    <td class="text-center font-monospace">{{ number_format($item['qty_planned']) }}</td>
                    <td class="text-center font-monospace fw-bold">{{ number_format($item['qty_issued']) }}</td>
                    <td class="text-end font-monospace">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="fw-bold table-light">
                <td colspan="5" class="text-center text-uppercase">Total Rekap</td>
                <td class="text-center font-monospace">{{ number_format($manifestData['total_qty']) }}</td>
                <td class="text-center font-monospace">{{ number_format($prodOrder->total_issued_qty) }}</td>
                <td class="text-end font-monospace text-danger">Rp {{ number_format($manifestData['items']->sum('subtotal'), 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="text-justify mb-4 fs-8 text-secondary">
        Catatan: Dokumen ini merupakan bukti sah pengeluaran bahan baku personalisasi kartu ATM / instrumen perbankan dari Gudang Logistik Pusat untuk didistribusikan ke unit kerja peminta.
    </p>

    <div class="row text-center signature-box">
        <div class="col-4">
            <p class="mb-0 fs-8">Direncanakan Oleh,</p>
            <p class="mb-0 fs-8 text-secondary">Staff Personalisasi Kartu</p>
            <div style="height: 50px;"></div>
            <strong><u>{{ $prodOrder->creator?->name }}</u></strong>
        </div>
        <div class="col-4">
            <p class="mb-0 fs-8">Dikeluarkan Oleh,</p>
            <p class="mb-0 fs-8 text-secondary">Petugas Gudang Bahan</p>
            <div style="height: 50px;"></div>
            <strong><u>{{ $prodOrder->issuer?->name ?: '(............................................)' }}</u></strong>
        </div>
        <div class="col-4">
            <p class="mb-0 fs-8">Disetujui Oleh,</p>
            <p class="mb-0 fs-8 text-secondary">Supervisor Logistik & Distribusi</p>
            <div style="height: 50px;"></div>
            <strong><u>(............................................)</u></strong>
        </div>
    </div>
</div>

</body>
</html>
