<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $po->po_number }}</title>
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
                <a href="{{ route('procurement.po.index') }}" class="text-xs text-slate-600 hover:text-red-700 font-medium inline-flex items-center gap-1">
                    &larr; Kembali ke Daftar PO
                </a>
                <span class="text-slate-300">|</span>
                <span class="text-xs text-slate-500 font-mono">Surat Pesanan Resmi Bank Jatim</span>
            </div>
            <button onclick="window.print()" class="bg-red-700 hover:bg-red-800 text-white text-xs font-bold px-4 py-2 rounded shadow flex items-center gap-2 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Purchase Order (Print)
            </button>
        </div>

        <!-- Official Bank Jatim Letterhead -->
        <div class="border-b-2 border-red-700 pb-4 mb-6 flex justify-between items-start">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/logo-bankjatim.png') }}" alt="Bank Jatim" class="h-12 w-auto object-contain">
                <div>
                    <h1 class="text-base font-black tracking-tight text-red-700">PT BANK PEMBANGUNAN DAERAH JAWA TIMUR TBK</h1>
                    <p class="text-xs font-semibold text-slate-700">DIVISI LOGISTIK & UMUM - PENGADAAN BARANG & JASA</p>
                    <p class="text-[10px] text-slate-500">Kantor Pusat: Jl. Basuki Rahmat No. 98-104, Surabaya | Telp: (031) 5310090</p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-black tracking-tight text-slate-900">PURCHASE ORDER</h2>
                <p class="text-sm font-mono font-bold text-red-700">{{ $po->po_number }}</p>
                <div class="text-[11px] text-slate-500 mt-0.5">Status: <span class="font-semibold text-slate-700">{{ str_replace('_', ' ', $po->status) }}</span></div>
            </div>
        </div>

        <!-- Meta Information Grid -->
        <div class="grid grid-cols-2 gap-6 text-xs mb-6">
            <!-- Vendor Information -->
            <div class="space-y-1.5 p-3.5 bg-slate-50 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 pb-1 mb-1">
                    Kepada Rekanan Vendor:
                </div>
                <div class="font-bold text-sm text-slate-900">{{ $po->vendor->name ?? '-' }}</div>
                <div class="text-slate-600 font-mono text-[11px]">Kode Vendor: {{ $po->vendor->code ?? '-' }}</div>
                <div class="text-slate-600">{{ $po->vendor->address ?? '-' }}</div>
                <div class="text-slate-600">Kontak / Telp: {{ $po->vendor->phone ?? '-' }} ({{ $po->vendor->pic_name ?? 'PIC' }})</div>
            </div>

            <!-- Delivery & Warehouse Information -->
            <div class="space-y-1.5 p-3.5 bg-slate-50 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 pb-1 mb-1">
                    Tujuan Pengiriman & Gudang:
                </div>
                <div class="font-bold text-sm text-slate-900">{{ $po->warehouse->name ?? '-' }}</div>
                <div class="text-slate-600 font-mono text-[11px]">Kode Gudang: {{ $po->warehouse->code ?? '-' }}</div>
                <div class="text-slate-600">{{ $po->warehouse->address ?? 'Gudang Pusat Logistik Bank Jatim' }}</div>
                <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-200 text-[11px]">
                    <div><strong>Tgl. Order:</strong> {{ $po->order_date ? $po->order_date->format('d/m/Y') : '-' }}</div>
                    <div><strong>Target Kirim:</strong> {{ $po->expected_delivery_date ? $po->expected_delivery_date->format('d/m/Y') : '-' }}</div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="w-full text-left text-xs border border-slate-300 mb-6">
            <thead class="bg-slate-100 border-b border-slate-300 font-bold uppercase text-slate-700">
                <tr>
                    <th class="p-2.5 border-r border-slate-300 w-10 text-center">No</th>
                    <th class="p-2.5 border-r border-slate-300">Deskripsi & Nama Barang</th>
                    <th class="p-2.5 border-r border-slate-300 text-center w-24">Satuan</th>
                    <th class="p-2.5 border-r border-slate-300 text-center w-24">Qty Order</th>
                    <th class="p-2.5 border-r border-slate-300 text-end w-32">Harga Satuan</th>
                    <th class="p-2.5 text-end w-36">Subtotal (Rp)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach($po->items as $idx => $it)
                    <tr>
                        <td class="p-2.5 border-r border-slate-300 text-center text-slate-600">{{ $idx + 1 }}</td>
                        <td class="p-2.5 border-r border-slate-300">
                            <strong class="text-slate-900">{{ $it->item->name ?? '-' }}</strong>
                            <div class="text-[10px] text-slate-500 font-mono">{{ $it->item->sku ?? '-' }} &bull; {{ $it->item->category->name ?? '-' }}</div>
                        </td>
                        <td class="p-2.5 border-r border-slate-300 text-center font-semibold text-slate-700">{{ $it->item->uom ?? '-' }}</td>
                        <td class="p-2.5 border-r border-slate-300 text-center font-bold text-slate-900 font-mono">{{ number_format($it->qty_ordered) }}</td>
                        <td class="p-2.5 border-r border-slate-300 text-end font-mono text-slate-700">Rp {{ number_format($it->unit_price, 0, ',', '.') }}</td>
                        <td class="p-2.5 text-end font-mono font-bold text-slate-900">Rp {{ number_format($it->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Summary & Notes Section -->
        <div class="grid grid-cols-2 gap-6 text-xs mb-8">
            <div class="p-3.5 bg-slate-50 rounded border border-slate-200 space-y-2">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Syarat & Ketentuan Pengadaan:</div>
                <ul class="list-disc list-inside text-slate-600 text-[11px] space-y-1">
                    <li>Barang harus sesuai dengan spesifikasi yang telah disepakati bersama Bank Jatim.</li>
                    <li>Surat Jalan asli dan salinan PO ini wajib dilampirkan saat serah terima barang ke gudang tujuan.</li>
                    <li>Faktur tagihan / invoice resmi diproses setelah Berita Acara Penerimaan Barang (GRN) diterbitkan.</li>
                </ul>
                @if($po->notes)
                    <div class="pt-2 border-t border-slate-200">
                        <strong class="text-slate-700">Catatan Khusus:</strong>
                        <p class="text-slate-600 text-[11px] mt-0.5">{{ $po->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="space-y-2 text-xs flex flex-col justify-end">
                <div class="flex justify-between items-center px-3 py-1.5 bg-slate-50 rounded border border-slate-200">
                    <span class="text-slate-600 font-medium">Subtotal Barang:</span>
                    <span class="font-mono font-semibold text-slate-900">Rp {{ number_format($po->subtotal, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center px-3 py-1.5 bg-slate-50 rounded border border-slate-200">
                    <span class="text-slate-600 font-medium">PPN (11%):</span>
                    <span class="font-mono font-semibold text-slate-900">Rp {{ number_format($po->tax_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center px-3 py-2 bg-red-50 rounded border border-red-200">
                    <span class="text-red-700 font-bold text-sm">TOTAL NILAI PO:</span>
                    <span class="font-mono font-black text-red-700 text-base">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Signature Boxes (3 Columns) -->
        <div class="grid grid-cols-3 gap-6 text-center text-xs pt-4 border-t border-slate-200">
            <div>
                <p class="text-slate-500 mb-14">Dibuat Oleh (Procurement Officer)</p>
                <p class="font-bold underline text-slate-900">{{ $po->creator->name ?? 'Petugas Pengadaan' }}</p>
                <p class="text-[10px] text-slate-400">Divisi Logistik & Umum</p>
            </div>
            <div>
                <p class="text-slate-500 mb-14">Disetujui Oleh (Approver)</p>
                <p class="font-bold underline text-slate-900">{{ $po->approver->name ?? 'Pemimpin Bagian Logistik' }}</p>
                <p class="text-[10px] text-slate-400">Bank Jatim Kantor Pusat</p>
            </div>
            <div>
                <p class="text-slate-500 mb-14">Konfirmasi Rekanan Vendor</p>
                <p class="font-bold underline text-slate-900">__________________________</p>
                <p class="text-[10px] text-slate-400">Tanda Tangan & Cap Perusahaan</p>
            </div>
        </div>
    </div>
</body>
</html>
