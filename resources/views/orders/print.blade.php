<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Permintaan Barang - {{ $order->order_number }}</title>
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
                <a href="{{ route('orders.index') }}" class="text-xs text-slate-600 hover:text-red-700 font-medium inline-flex items-center gap-1">
                    &larr; Kembali ke Daftar Order
                </a>
                <span class="text-slate-300">|</span>
                <span class="text-xs text-slate-500 font-mono">Surat Permintaan Barang Resmi Bank Jatim</span>
            </div>
            <button onclick="window.print()" class="bg-red-700 hover:bg-red-800 text-white text-xs font-bold px-4 py-2 rounded shadow flex items-center gap-2 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Dokumen Order (Print)
            </button>
        </div>

        <!-- Official Bank Jatim Letterhead -->
        <div class="border-b-2 border-red-700 pb-4 mb-6 flex justify-between items-start">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/logo-bankjatim.png') }}" alt="Bank Jatim" class="h-12 w-auto object-contain">
                <div>
                    <h1 class="text-base font-black tracking-tight text-red-700">PT BANK PEMBANGUNAN DAERAH JAWA TIMUR TBK</h1>
                    <p class="text-xs font-semibold text-slate-700">DIVISI LOGISTIK & UMUM - PENGELOLAAN PERSEDIAAN BARANG</p>
                    <p class="text-[10px] text-slate-500">Kantor Pusat: Jl. Basuki Rahmat No. 98-104, Surabaya | Telp: (031) 5310090</p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-black tracking-tight text-slate-900">FORM PERMINTAAN BARANG</h2>
                <p class="text-sm font-mono font-bold text-red-700">{{ $order->order_number }}</p>
                <div class="text-[11px] text-slate-500 mt-0.5">Status: <span class="font-semibold text-slate-700">{{ str_replace('_', ' ', $order->status) }}</span></div>
            </div>
        </div>

        <!-- Meta Information Grid -->
        <div class="grid grid-cols-2 gap-6 text-xs mb-6">
            <!-- Unit Pemohon Information -->
            <div class="space-y-1.5 p-3.5 bg-slate-50 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 pb-1 mb-1">
                    Unit Kerja Pemohon:
                </div>
                <div class="font-bold text-sm text-slate-900">{{ $order->requestingOrganization->name ?? '-' }}</div>
                <div class="text-slate-600 font-mono text-[11px]">Kode Unit: {{ $order->requestingOrganization->code ?? '-' }}</div>
                <div class="text-slate-600">Kota / Wilayah: {{ $order->requestingOrganization->city ?? '-' }}</div>
                <div class="text-slate-600">Pemohon: <span class="font-medium text-slate-800">{{ $order->requester->name ?? '-' }}</span> {{ $order->requester?->nip ? '(NIP: ' . $order->requester->nip . ')' : '' }}</div>
            </div>

            <!-- Detail Order & Logistik Information -->
            <div class="space-y-1.5 p-3.5 bg-slate-50 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 pb-1 mb-1">
                    Informasi Kebutuhan & Logistik:
                </div>
                <div class="grid grid-cols-2 gap-1 text-[11px]">
                    <div class="text-slate-500">Tanggal Pengajuan:</div>
                    <div class="font-semibold text-slate-800 font-mono">{{ $order->created_at->format('d/m/Y H:i') }} WIB</div>

                    <div class="text-slate-500">Target Tanggal Dibutuhkan:</div>
                    <div class="font-semibold text-slate-800 font-mono">{{ $order->required_date ? $order->required_date->format('d/m/Y') : '-' }}</div>

                    <div class="text-slate-500">Tingkat Prioritas:</div>
                    <div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $order->priority === 'URGENT' ? 'bg-red-100 text-red-800' : 'bg-slate-200 text-slate-800' }}">
                            {{ $order->priority }}
                        </span>
                    </div>

                    <div class="text-slate-500">Gudang Pemenuhan:</div>
                    <div class="font-semibold text-slate-800">{{ $order->requestingWarehouse->name ?? 'Gudang Logistik Pusat Surabaya' }}</div>

                    <div class="text-slate-500">Pejabat Penyetuju:</div>
                    <div class="font-semibold text-slate-800">{{ $order->approver->name ?? 'Menunggu Persetujuan' }}</div>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="mb-6">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                Daftar Barang yang Diminta:
            </h3>
            <table class="w-full text-left text-xs border-collapse border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-300">
                        <th class="p-2 border-r border-slate-300 text-center w-10">No</th>
                        <th class="p-2 border-r border-slate-300">Kode & Nama Barang</th>
                        <th class="p-2 border-r border-slate-300">Kategori</th>
                        <th class="p-2 border-r border-slate-300 text-center w-20">Satuan</th>
                        <th class="p-2 border-r border-slate-300 text-center w-20">Kuantitas</th>
                        <th class="p-2 border-r border-slate-300 text-right w-32">Harga Satuan Ref (Rp)</th>
                        <th class="p-2 text-right w-36">Subtotal (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($order->items as $idx => $it)
                        @php
                            $unitPrice = (float) ($it->unit_price_ref ?? $it->item->estimated_unit_price ?? 0);
                            $subtotal = $unitPrice * $it->qty_requested;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="p-2 border-r border-slate-200 text-center text-slate-500 font-mono">{{ $idx + 1 }}</td>
                            <td class="p-2 border-r border-slate-200">
                                <div class="font-bold text-slate-900">{{ $it->item->name ?? '-' }}</div>
                                <div class="text-[10px] text-slate-500 font-mono">SKU: {{ $it->item->sku ?? '-' }}</div>
                            </td>
                            <td class="p-2 border-r border-slate-200 text-slate-600">{{ $it->item->category->name ?? '-' }}</td>
                            <td class="p-2 border-r border-slate-200 text-center font-mono">{{ $it->item->uom ?? 'PCS' }}</td>
                            <td class="p-2 border-r border-slate-200 text-center font-bold font-mono text-slate-900">{{ number_format($it->qty_requested) }}</td>
                            <td class="p-2 border-r border-slate-200 text-right font-mono text-slate-700">Rp {{ number_format($unitPrice, 0, ',', '.') }}</td>
                            <td class="p-2 text-right font-bold font-mono text-slate-900">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-center text-slate-500 italic">Tidak ada item dalam order ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 border-t-2 border-slate-300 font-bold">
                        <td colspan="6" class="p-2 text-right uppercase text-[11px] text-slate-700">Total Estimasi Nilai Permintaan:</td>
                        <td class="p-2 text-right font-mono text-red-700 text-sm">
                            Rp {{ number_format($order->total_estimated_value, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Notes / Remarks -->
        @if($order->notes)
            <div class="mb-6 p-3 bg-slate-50 rounded border border-slate-200 text-xs">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Catatan / Keterangan Kebutuhan:</div>
                <p class="text-slate-800 leading-relaxed font-mono">{{ $order->notes }}</p>
            </div>
        @endif

        <!-- Signatures / Legal Approval Section -->
        <div class="grid grid-cols-3 gap-6 text-center text-xs mt-10 pt-4 border-t border-slate-200">
            <div>
                <p class="text-slate-500 mb-1">Pemohon / Pengaju:</p>
                <div class="h-20 flex items-center justify-center">
                    <span class="text-slate-400 italic text-[11px]">[Tanda Tangan]</span>
                </div>
                <p class="font-bold text-slate-900 underline">{{ $order->requester->name ?? 'Staf Pemohon' }}</p>
                <p class="text-[10px] text-slate-500">{{ $order->requestingOrganization->name ?? 'Unit Pemohon' }}</p>
            </div>
            <div>
                <p class="text-slate-500 mb-1">Menyetujui (Pimpinan Unit):</p>
                <div class="h-20 flex items-center justify-center">
                    @if($order->approver)
                        <div class="border border-green-600 bg-green-50 text-green-700 px-3 py-1 rounded text-[10px] font-bold">
                            DISETUJUI SECARA SISTEM<br>
                            <span class="text-[9px] font-normal">{{ $order->approved_at ? $order->approved_at->format('d/m/Y H:i') : '' }}</span>
                        </div>
                    @else
                        <span class="text-slate-400 italic text-[11px]">[Tanda Tangan & Cap]</span>
                    @endif
                </div>
                <p class="font-bold text-slate-900 underline">{{ $order->approver->name ?? 'Pimpinan Cabang / Unit' }}</p>
                <p class="text-[10px] text-slate-500">Pemimpin Cabang / Bagian</p>
            </div>
            <div>
                <p class="text-slate-500 mb-1">Penerima di Logistik Pusat:</p>
                <div class="h-20 flex items-center justify-center">
                    <span class="text-slate-400 italic text-[11px]">[Verifikasi & Distribusi]</span>
                </div>
                <p class="font-bold text-slate-900 underline">Petugas Logistik & Gudang</p>
                <p class="text-[10px] text-slate-500">Divisi Logistik & Umum</p>
            </div>
        </div>

        <!-- Document Footer -->
        <div class="mt-8 pt-3 border-t border-slate-200 flex justify-between items-center text-[10px] text-slate-400">
            <span>Dokumen dicetak secara elektronik melalui Sistem Informasi Logistik & Persediaan Bank Jatim.</span>
            <span class="font-mono">Tanggal Cetak: {{ date('d/m/Y H:i') }}</span>
        </div>
    </div>
</body>
</html>
