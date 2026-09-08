<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Request - {{ $pr->pr_number }}</title>
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
                <a href="{{ route('procurement.pr.index') }}" class="text-xs text-slate-600 hover:text-red-700 font-medium inline-flex items-center gap-1">
                    &larr; Kembali ke Daftar PR
                </a>
                <span class="text-slate-300">|</span>
                <span class="text-xs text-slate-500 font-mono">Form Pengajuan Pengadaan Barang Resmi Bank Jatim</span>
            </div>
            <button onclick="window.print()" class="bg-red-700 hover:bg-red-800 text-white text-xs font-bold px-4 py-2 rounded shadow flex items-center gap-2 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Purchase Request (Print)
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
                <h2 class="text-xl font-black tracking-tight text-slate-900">PURCHASE REQUEST</h2>
                <p class="text-sm font-mono font-bold text-red-700">{{ $pr->pr_number }}</p>
                <div class="text-[11px] text-slate-500 mt-0.5">Status: <span class="font-semibold text-slate-700">{{ str_replace('_', ' ', $pr->status) }}</span></div>
            </div>
        </div>

        <!-- Meta Information Grid -->
        <div class="grid grid-cols-2 gap-6 text-xs mb-6">
            <!-- Requester Information -->
            <div class="space-y-1.5 p-3.5 bg-slate-50 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 pb-1 mb-1">
                    Unit Pengaju & Pemohon:
                </div>
                <div class="font-bold text-sm text-slate-900">{{ $pr->organization->name ?? '-' }}</div>
                <div class="text-slate-600 font-mono text-[11px]">Kode Unit: {{ $pr->organization->code ?? '-' }}</div>
                <div class="text-slate-600">Kota: {{ $pr->organization->city ?? '-' }}</div>
                <div class="text-slate-600">Diajukan Oleh: <span class="font-medium text-slate-800">{{ $pr->requester->name ?? '-' }}</span> {{ $pr->requester?->nip ? '(NIP: ' . $pr->requester->nip . ')' : '' }}</div>
            </div>

            <!-- Procurement & Budget Information -->
            <div class="space-y-1.5 p-3.5 bg-slate-50 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[11px] border-b border-slate-200 pb-1 mb-1">
                    Parameter Pengadaan & Anggaran:
                </div>
                <div class="grid grid-cols-2 gap-1 text-[11px]">
                    <div class="text-slate-500">Tanggal Pengajuan:</div>
                    <div class="font-semibold text-slate-800 font-mono">{{ $pr->created_at->format('d/m/Y H:i') }} WIB</div>

                    <div class="text-slate-500">Metode Pengadaan:</div>
                    <div class="font-semibold text-slate-800">{{ str_replace('_', ' ', $pr->procurement_method) }}</div>

                    <div class="text-slate-500">Ketersediaan Anggaran:</div>
                    <div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $pr->budget_status === 'VALIDATED' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $pr->budget_status === 'VALIDATED' ? 'Tersedia (VALIDATED)' : 'Tidak Mencukupi' }}
                        </span>
                    </div>

                    <div class="text-slate-500">Pejabat Penyetuju:</div>
                    <div class="font-semibold text-slate-800">{{ $pr->approver->name ?? 'Menunggu Persetujuan' }}</div>
                </div>
            </div>
        </div>

        <!-- Purpose / Justification -->
        @if($pr->purpose)
            <div class="mb-6 p-3 bg-slate-50 rounded border border-slate-200 text-xs">
                <div class="font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Tujuan & Justifikasi Pengadaan:</div>
                <p class="text-slate-800 leading-relaxed font-mono">{{ $pr->purpose }}</p>
            </div>
        @endif

        <!-- Items Table -->
        <div class="mb-6">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                Rincian Barang yang Diajukan:
            </h3>
            <table class="w-full text-left text-xs border-collapse border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-300">
                        <th class="p-2 border-r border-slate-300 text-center w-10">No</th>
                        <th class="p-2 border-r border-slate-300">Kode & Nama Barang</th>
                        <th class="p-2 border-r border-slate-300">Kategori</th>
                        <th class="p-2 border-r border-slate-300 text-center w-16">Satuan</th>
                        <th class="p-2 border-r border-slate-300 text-center w-20">Qty Diajukan</th>
                        <th class="p-2 border-r border-slate-300 text-center w-20">Qty Disetujui</th>
                        <th class="p-2 border-r border-slate-300 text-right w-28">Harga Satuan (Rp)</th>
                        <th class="p-2 text-right w-36">Subtotal (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($pr->items as $idx => $it)
                        <tr class="hover:bg-slate-50">
                            <td class="p-2 border-r border-slate-200 text-center text-slate-500 font-mono">{{ $idx + 1 }}</td>
                            <td class="p-2 border-r border-slate-200">
                                <div class="font-bold text-slate-900">{{ $it->item->name ?? ('Item #' . $it->item_id) }}</div>
                                <div class="text-[10px] text-slate-500 font-mono">SKU: {{ $it->item->sku ?? '-' }}</div>
                            </td>
                            <td class="p-2 border-r border-slate-200 text-slate-600">{{ $it->item->category->name ?? '-' }}</td>
                            <td class="p-2 border-r border-slate-200 text-center font-mono">{{ $it->item->uom ?? 'PCS' }}</td>
                            <td class="p-2 border-r border-slate-200 text-center font-bold font-mono text-slate-800">{{ number_format($it->qty_requested) }}</td>
                            <td class="p-2 border-r border-slate-200 text-center font-bold font-mono text-green-700">{{ number_format($it->qty_approved) }}</td>
                            <td class="p-2 border-r border-slate-200 text-right font-mono text-slate-700">Rp {{ number_format($it->estimated_unit_price, 0, ',', '.') }}</td>
                            <td class="p-2 text-right font-bold font-mono text-slate-900">Rp {{ number_format($it->estimated_subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-4 text-center text-slate-500 italic">Tidak ada item dalam Purchase Request ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 border-t-2 border-slate-300 font-bold">
                        <td colspan="7" class="p-2 text-right uppercase text-[11px] text-slate-700">Total Estimasi Nilai Pengadaan:</td>
                        <td class="p-2 text-right font-mono text-red-700 text-sm">
                            Rp {{ number_format($pr->estimated_total_cost, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Three Signatures Section -->
        <div class="grid grid-cols-3 gap-6 text-center text-xs mt-10 pt-4 border-t border-slate-200">
            <div>
                <p class="text-slate-500 mb-1">Diajukan Oleh (Pemohon):</p>
                <div class="h-20 flex items-center justify-center">
                    <span class="text-slate-400 italic text-[11px]">[Tanda Tangan]</span>
                </div>
                <p class="font-bold text-slate-900 underline">{{ $pr->requester->name ?? 'Pemohon' }}</p>
                <p class="text-[10px] text-slate-500">{{ $pr->organization->name ?? 'Unit Pengaju' }}</p>
            </div>
            <div>
                <p class="text-slate-500 mb-1">Verifikasi Anggaran:</p>
                <div class="h-20 flex items-center justify-center">
                    @if($pr->budget_status === 'VALIDATED')
                        <div class="border border-green-600 bg-green-50 text-green-700 px-3 py-1 rounded text-[10px] font-bold">
                            ANGGARAN TERSEDIA<br>
                            <span class="text-[9px] font-normal">Sistem Otomasi Anggaran</span>
                        </div>
                    @else
                        <span class="text-slate-400 italic text-[11px]">[Verifikasi]</span>
                    @endif
                </div>
                <p class="font-bold text-slate-900 underline">Bagian Anggaran & Keuangan</p>
                <p class="text-[10px] text-slate-500">Divisi Keuangan & Akuntansi</p>
            </div>
            <div>
                <p class="text-slate-500 mb-1">Disetujui Oleh (Approver):</p>
                <div class="h-20 flex items-center justify-center">
                    @if($pr->approver)
                        <div class="border border-green-600 bg-green-50 text-green-700 px-3 py-1 rounded text-[10px] font-bold">
                            DISETUJUI SECARA SISTEM<br>
                            <span class="text-[9px] font-normal">{{ $pr->approved_at ? $pr->approved_at->format('d/m/Y H:i') : '' }}</span>
                        </div>
                    @else
                        <span class="text-slate-400 italic text-[11px]">[Tanda Tangan & Cap]</span>
                    @endif
                </div>
                <p class="font-bold text-slate-900 underline">{{ $pr->approver->name ?? 'Procurement Approver' }}</p>
                <p class="text-[10px] text-slate-500">Pejabat Pengadaan Barang & Jasa</p>
            </div>
        </div>

        <!-- Document Footer -->
        <div class="mt-8 pt-3 border-t border-slate-200 flex justify-between items-center text-[10px] text-slate-400">
            <span>Form Purchase Request ini merupakan dokumen sah pengajuan kebutuhan persediaan internal Bank Jatim.</span>
            <span class="font-mono">Tanggal Cetak: {{ date('d/m/Y H:i') }}</span>
        </div>
    </div>
</body>
</html>
