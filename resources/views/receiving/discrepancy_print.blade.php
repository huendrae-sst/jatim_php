<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara Discrepancy - {{ $discrepancy->receiving->receiving_number ?? 'Klaim' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/poppins@5.0.14/index.css" crossorigin="anonymous" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Poppins', system-ui, -apple-system, sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; padding: 0 !important; }
            .print-container { border: none !important; box-shadow: none !important; max-width: 100% !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 p-6 md:p-10">
    <div class="print-container max-w-4xl mx-auto bg-white p-8 md:p-10 border border-slate-300 shadow-md rounded-sm">
        <!-- Top Toolbar (Hidden when printing) -->
        <div class="no-print mb-6 flex justify-between items-center bg-slate-50 p-4 rounded-lg border border-slate-200">
            <div class="flex items-center gap-2">
                <a href="{{ route('receiving.discrepancies') }}" class="text-xs text-slate-600 hover:text-red-700 font-medium inline-flex items-center gap-1">
                    &larr; Kembali ke Daftar Discrepancy
                </a>
                <span class="text-slate-300">|</span>
                <span class="text-xs text-slate-500 font-mono">Berita Acara Selisih & Klaim Resmi Bank Jatim</span>
            </div>
            <button onclick="window.print()" class="bg-red-700 hover:bg-red-800 text-white text-xs font-bold px-4 py-2 rounded shadow flex items-center gap-2 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Berita Acara (Print)
            </button>
        </div>

        <!-- Official Bank Jatim Letterhead -->
        <div class="border-b-2 border-red-700 pb-4 mb-6 flex justify-between items-start">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/logo-bankjatim.png') }}" alt="Bank Jatim" class="h-12 w-auto object-contain">
                <div>
                    <h1 class="text-base font-black tracking-tight text-red-700">PT BANK PEMBANGUNAN DAERAH JAWA TIMUR TBK</h1>
                    <p class="text-xs font-semibold text-slate-700">DIVISI LOGISTIK & UMUM - PENGAWASAN & PENERIMAAN BARANG</p>
                    <p class="text-[10px] text-slate-500">Kantor Pusat: Jl. Basuki Rahmat No. 98-104, Surabaya | Telp: (031) 5310090</p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-lg font-black tracking-tight text-slate-900">BERITA ACARA DISCREPANCY</h2>
                <p class="text-sm font-mono font-bold text-red-700">{{ $discrepancy->receiving->receiving_number ?? 'DISC-' . $discrepancy->id }}</p>
                <div class="text-[11px] text-slate-500 mt-0.5">Status Klaim: <span class="font-bold text-red-700">{{ str_replace('_', ' ', $discrepancy->resolution_status) }}</span></div>
            </div>
        </div>

        <!-- Meta Information Grid -->
        <div class="grid grid-cols-2 gap-6 text-xs mb-6">
            <!-- Receiving & Delivery Information -->
            <div class="space-y-1.5 p-3.5 bg-slate-50 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 pb-1 mb-1">
                    Informasi Penerimaan & Ekspedisi:
                </div>
                <div class="grid grid-cols-2 gap-1 text-[11px]">
                    <div class="text-slate-500">No. Penerimaan:</div>
                    <div class="font-mono font-bold text-slate-900">{{ $discrepancy->receiving->receiving_number ?? '-' }}</div>

                    <div class="text-slate-500">Tanggal Diterima:</div>
                    <div class="font-semibold text-slate-800 font-mono">{{ $discrepancy->receiving && $discrepancy->receiving->receipt_date ? $discrepancy->receiving->receipt_date->format('d/m/Y') : '-' }}</div>

                    <div class="text-slate-500">No. Order Acuan:</div>
                    <div class="font-mono text-slate-800">{{ $discrepancy->receiving->order->order_number ?? '-' }}</div>

                    <div class="text-slate-500">No. Manifest Kirim:</div>
                    <div class="font-mono text-slate-800">{{ $discrepancy->receiving->shipment->manifest_number ?? '-' }}</div>

                    <div class="text-slate-500">Jasa Ekspedisi / Kurir:</div>
                    <div class="font-semibold text-slate-800">{{ $discrepancy->receiving->shipment->courier->name ?? 'Armada Internal Bank Jatim' }}</div>
                </div>
            </div>

            <!-- Locations & Personnel Information -->
            <div class="space-y-1.5 p-3.5 bg-slate-50 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 pb-1 mb-1">
                    Lokasi & Petugas Penerima:
                </div>
                <div class="grid grid-cols-2 gap-1 text-[11px]">
                    <div class="text-slate-500">Cabang Penerima:</div>
                    <div class="font-bold text-slate-900">{{ $discrepancy->receiving->order->requestingOrganization->name ?? '-' }}</div>

                    <div class="text-slate-500">Kota / Wilayah:</div>
                    <div class="text-slate-800">{{ $discrepancy->receiving->order->requestingOrganization->city ?? '-' }}</div>

                    <div class="text-slate-500">Gudang Pengirim:</div>
                    <div class="font-semibold text-slate-800">{{ $discrepancy->receiving->shipment->originWarehouse->name ?? 'Gudang Logistik Pusat' }}</div>

                    <div class="text-slate-500">Petugas Penerima:</div>
                    <div class="font-semibold text-slate-800">{{ $discrepancy->receiving->receiver->name ?? '-' }}</div>
                </div>
            </div>
        </div>

        <!-- Discrepancy Details Box -->
        <div class="mb-6 p-4 rounded border-2 border-red-200 bg-red-50/50">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-red-800">
                    Rincian Barang Berselisih (Discrepancy Breakdown)
                </h3>
                <span class="px-2.5 py-0.5 rounded text-[11px] font-bold bg-red-100 text-red-800 border border-red-200">
                    Kategori: {{ str_replace('_', ' ', $discrepancy->discrepancy_type) }}
                </span>
            </div>

            <div class="bg-white p-3 rounded border border-red-100 mb-3 text-xs">
                <div class="font-bold text-sm text-slate-900">{{ $discrepancy->item->name ?? 'Item Barang' }}</div>
                <div class="text-slate-500 font-mono text-[11px] mt-0.5">
                    Kode SKU: <span class="text-slate-800 font-bold">{{ $discrepancy->item->sku ?? '-' }}</span> | 
                    Kategori: {{ $discrepancy->item->category->name ?? '-' }} | 
                    Satuan: {{ $discrepancy->item->uom ?? 'PCS' }}
                </div>
            </div>

            <!-- Comparison Table -->
            <table class="w-full text-center text-xs border-collapse bg-white border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 font-bold text-slate-700 border-b border-slate-300">
                        <th class="p-2 border-r border-slate-300">Kuantitas Dikirim (Manifest)</th>
                        <th class="p-2 border-r border-slate-300 text-green-700">Kuantitas Diterima Baik</th>
                        <th class="p-2 border-r border-slate-300 text-red-700">Kuantitas Rusak / Kurang</th>
                        <th class="p-2 text-slate-700">Satuan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="font-mono text-sm font-bold">
                        <td class="p-3 border-r border-slate-200 text-slate-900">{{ number_format($discrepancy->qty_expected) }}</td>
                        <td class="p-3 border-r border-slate-200 text-green-700 bg-green-50/30">{{ number_format($discrepancy->qty_actual) }}</td>
                        <td class="p-3 border-r border-slate-200 text-red-700 bg-red-50/50">{{ number_format($discrepancy->qty_damaged) }}</td>
                        <td class="p-3 text-slate-600 font-normal text-xs">{{ $discrepancy->item->uom ?? 'PCS' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Notes / Remarks -->
        <div class="mb-6 p-3 bg-slate-50 rounded border border-slate-200 text-xs">
            <div class="font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">
                Keterangan / Kronologi Temuan Selisih:
            </div>
            <p class="text-slate-800 leading-relaxed font-mono">
                {{ $discrepancy->resolution_notes ?? $discrepancy->receiving->notes ?? 'Tidak ada catatan tambahan.' }}
            </p>
        </div>

        <!-- Four Legal Signatures Section -->
        <div class="grid grid-cols-4 gap-4 text-center text-xs mt-10 pt-4 border-t border-slate-200">
            <div>
                <p class="text-slate-500 mb-1">Penerima Cabang:</p>
                <div class="h-20 flex items-center justify-center">
                    <span class="text-slate-400 italic text-[11px]">[Tanda Tangan]</span>
                </div>
                <p class="font-bold text-slate-900 underline">{{ $discrepancy->receiving->receiver->name ?? 'Staf Penerima' }}</p>
                <p class="text-[10px] text-slate-500">{{ $discrepancy->receiving->order->requestingOrganization->name ?? 'Cabang' }}</p>
            </div>
            <div>
                <p class="text-slate-500 mb-1">Kurir / Ekspedisi:</p>
                <div class="h-20 flex items-center justify-center">
                    <span class="text-slate-400 italic text-[11px]">[Tanda Tangan]</span>
                </div>
                <p class="font-bold text-slate-900 underline">Petugas Ekspedisi</p>
                <p class="text-[10px] text-slate-500">{{ $discrepancy->receiving->shipment->courier->name ?? 'Driver Pengantar' }}</p>
            </div>
            <div>
                <p class="text-slate-500 mb-1">Pimpinan Cabang:</p>
                <div class="h-20 flex items-center justify-center">
                    <span class="text-slate-400 italic text-[11px]">[Tanda Tangan & Cap]</span>
                </div>
                <p class="font-bold text-slate-900 underline">Pimpinan Unit Penerima</p>
                <p class="text-[10px] text-slate-500">Pemimpin Bagian / Unit</p>
            </div>
            <div>
                <p class="text-slate-500 mb-1">Logistik Pusat:</p>
                <div class="h-20 flex items-center justify-center">
                    <span class="text-slate-400 italic text-[11px]">[Investigasi & Klaim]</span>
                </div>
                <p class="font-bold text-slate-900 underline">Petugas Logistik Pusat</p>
                <p class="text-[10px] text-slate-500">Divisi Logistik & Umum</p>
            </div>
        </div>

        <!-- Document Footer -->
        <div class="mt-8 pt-3 border-t border-slate-200 flex justify-between items-center text-[10px] text-slate-400">
            <span>Berita Acara ini merupakan bukti sah pemeriksaan fisik penerimaan barang dan dasar klaim/pergantian logistik Bank Jatim.</span>
            <span class="font-mono">Tanggal Cetak: {{ date('d/m/Y H:i') }}</span>
        </div>
    </div>
</body>
</html>
