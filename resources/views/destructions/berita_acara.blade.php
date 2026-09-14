<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara Pemusnahan - {{ $destruction->berita_acara_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #111; font-size: 14px; }
        .document-box { max-width: 850px; margin: 20px auto; padding: 40px 50px; background: white; border: 1px solid #ddd; }
        .header-title { text-transform: uppercase; font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 25px; }
        .table-items th, .table-items td { border: 1px solid #333; padding: 6px 10px; font-size: 13px; }
        .signature-box { margin-top: 50px; }
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
    <a href="{{ route('destructions.show', $destruction->id) }}" class="btn btn-secondary btn-sm ms-2">Kembali</a>
</div>

<div class="document-box shadow-sm">
    <div class="text-center header-title">
        <h4 class="mb-1 fw-bold">PT BANK PEMBANGUNAN DAERAH JAWA TIMUR, Tbk.</h4>
        <h5 class="mb-1 text-uppercase text-decoration-underline">BERITA ACARA PEMUSNAHAN BARANG PERSEDIAAN</h5>
        <span class="fs-6 font-monospace">Nomor: {{ $destruction->berita_acara_number }}</span>
    </div>

    <p class="text-justify leading-relaxed mb-3">
        Pada hari ini, tanggal <strong>{{ $destruction->executed_at ? $destruction->executed_at->translatedFormat('d F Y') : now()->translatedFormat('d F Y') }}</strong>, bertempat di lokasi <strong>{{ $destruction->warehouse?->name }}</strong>, telah dilaksanakan pemusnahan barang persediaan yang dinyatakan tidak dapat dipergunakan kembali karena <em>{{ str_replace('_', ' ', $destruction->reason) }}</em>.
    </p>

    <p class="mb-2">Pelaksanaan pemusnahan ini disaksikan oleh pihak-pihak yang bertanda tangan di bawah ini:</p>
    <ol class="mb-4">
        <li><strong>{{ $destruction->witness_name_1 }}</strong> - Jabatan: {{ $destruction->witness_title_1 }} (Saksi 1)</li>
        <li><strong>{{ $destruction->witness_name_2 }}</strong> - Jabatan: {{ $destruction->witness_title_2 }} (Saksi 2)</li>
    </ol>

    <h6 class="fw-bold mb-2">DAFTAR BARANG YANG DIMUSNAHKAN:</h6>
    <table class="table table-items w-100 mb-3">
        <thead>
            <tr class="table-secondary text-center">
                <th style="width: 5%">No.</th>
                <th>Kode / SKU</th>
                <th>Nama Barang Persediaan</th>
                <th>No. Batch / Seri</th>
                <th>Kuantitas</th>
                <th>Satuan</th>
                <th class="text-end">Nilai Kerugian</th>
            </tr>
        </thead>
        <tbody>
            @foreach($destruction->items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-monospace text-center">{{ $item->item->sku }}</td>
                    <td>{{ $item->item->name }}</td>
                    <td class="font-monospace text-center">{{ $item->batch_or_serial_number ?: '-' }}</td>
                    <td class="text-center font-monospace">{{ number_format($item->qty) }}</td>
                    <td class="text-center">{{ $item->item->uom }}</td>
                    <td class="text-end font-monospace">Rp {{ number_format($item->total_loss_value, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="fw-bold">
                <td colspan="4" class="text-center text-uppercase">Total Barang Dimusnahkan</td>
                <td class="text-center font-monospace">{{ number_format($destruction->total_qty) }}</td>
                <td></td>
                <td class="text-end font-monospace">Rp {{ number_format($destruction->total_loss_value, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="text-justify mb-4">
        Pemusnahan fisik barang persediaan di atas dilaksanakan dengan metode perusakan fisik total (pemotongan chip / pencacahan) sehingga barang tersebut dipastikan tidak dapat disalahgunakan atau diedarkan kembali.
    </p>

    <p class="mb-4">Demikian Berita Acara ini dibuat dengan sebenarnya dalam rangkap secukupnya untuk dipergunakan sebagaimana mestinya.</p>

    <div class="row text-center signature-box">
        <div class="col-6 mb-4">
            <p class="mb-0 fs-8">Diajukan Oleh,</p>
            <p class="mb-0 fs-8 text-secondary">Petugas Gudang / Maker</p>
            <div style="height: 60px;"></div>
            <strong><u>{{ $destruction->requester?->name }}</u></strong>
        </div>
        <div class="col-6 mb-4">
            <p class="mb-0 fs-8">Disetujui / Diotorisasi Oleh,</p>
            <p class="mb-0 fs-8 text-secondary">Pejabat Berwenang / Checker</p>
            <div style="height: 60px;"></div>
            <strong><u>{{ $destruction->approver?->name ?: '(............................................)' }}</u></strong>
        </div>
        <div class="col-6">
            <p class="mb-0 fs-8">Saksi I,</p>
            <p class="mb-0 fs-8 text-secondary">{{ $destruction->witness_title_1 }}</p>
            <div style="height: 60px;"></div>
            <strong><u>{{ $destruction->witness_name_1 }}</u></strong>
        </div>
        <div class="col-6">
            <p class="mb-0 fs-8">Saksi II,</p>
            <p class="mb-0 fs-8 text-secondary">{{ $destruction->witness_title_2 }}</p>
            <div style="height: 60px;"></div>
            <strong><u>{{ $destruction->witness_name_2 }}</u></strong>
        </div>
    </div>
</div>

</body>
</html>
