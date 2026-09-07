@extends('layouts.app')
@section('title', 'Detail PO: ' . $po->po_number)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('procurement.po.index') }}" class="text-decoration-none text-danger">Purchase Orders</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $po->po_number }}</li>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-4" x-data="{ grnModal: false }">
    <!-- Status & Action Toolbar -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 bg-white p-3 rounded-2xl border border-slate-200 shadow-xs">
        <div class="d-flex align-items-center gap-2">
            <span class="text-xs font-semibold text-slate-500">Status Dokumen:</span>
            <span class="px-2.5 py-1 rounded-full font-bold text-xs
                @if($po->status === 'COMPLETED') bg-emerald-100 text-emerald-800
                @elseif($po->status === 'PARTIAL_RECEIVED') bg-amber-100 text-amber-800
                @elseif($po->status === 'ISSUED') bg-sky-100 text-sky-800
                @else bg-slate-100 text-slate-700 @endif">
                {{ str_replace('_', ' ', $po->status) }}
            </span>
            <span class="text-xs text-slate-400 ms-2">Diterbitkan: {{ $po->order_date->format('d M Y') }}</span>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if(in_array($po->status, ['ISSUED', 'VENDOR_PROCESS', 'IN_DELIVERY', 'PARTIAL_RECEIVED']))
                <button @click="grnModal = true" class="btn btn-sm btn-success fw-bold shadow-xs">
                    <i class="bi bi-box-seam me-1"></i> Terima Barang Vendor (GRN)
                </button>
            @endif
            <a href="{{ route('procurement.po.index') }}" class="btn btn-sm btn-light border fw-bold text-slate-600">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Metadata Card -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Vendor Penyedia</div>
            <div class="text-xs font-bold text-slate-900 mt-1">{{ $po->vendor->name }}</div>
            <div class="text-[11px] text-slate-500">Kontak: {{ $po->vendor->phone }} • {{ $po->vendor->payment_terms }}</div>
        </div>

        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Gudang Tujuan Penerimaan</div>
            <div class="text-xs font-bold text-slate-900 mt-1">{{ $po->warehouse->name }}</div>
            <div class="text-[11px] text-slate-500">{{ $po->warehouse->address }}</div>
        </div>

        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Traceability PR ➔ PO</div>
            <div class="text-xs font-bold text-indigo-700 mt-1 flex items-center space-x-1">
                <i class="fa-solid fa-link"></i>
                <span>Terkonsolidasi dari {{ $po->items->pluck('purchaseRequestItem.purchaseRequest.pr_number')->unique()->count() }} PR</span>
            </div>
            <div class="text-[10px] text-slate-400">Dibuat oleh: {{ $po->creator->name }}</div>
        </div>
    </div>

    <!-- PO Items Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <h3 class="font-bold text-sm text-slate-900">Rincian Barang & Traceability PR Sumber</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Item & SKU</th>
                        <th class="py-3 px-4">Referensi PR</th>
                        <th class="py-3 px-4 text-center">Dipesan (PO)</th>
                        <th class="py-3 px-4 text-center">Diterima (GRN)</th>
                        <th class="py-3 px-4 text-center">Outstanding</th>
                        <th class="py-3 px-4 text-right">Harga Satuan</th>
                        <th class="py-3 px-4 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($po->items as $it)
                        <tr>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900">{{ $it->item->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $it->item->sku }} • {{ $it->item->uom }}</div>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-semibold text-indigo-700">
                                {{ $it->purchaseRequestItem->purchaseRequest->pr_number ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-800">{{ $it->qty_ordered }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600">{{ $it->qty_received }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-rose-600">{{ $it->outstanding_qty }}</td>
                            <td class="py-3.5 px-4 text-right text-slate-600">Rp {{ number_format($it->unit_price, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-bold text-slate-900">Rp {{ number_format($it->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-slate-50 p-4 border-t border-slate-200 flex flex-col items-end space-y-1">
            <div class="text-xs text-slate-500">Subtotal: <strong class="text-slate-800">Rp {{ number_format($po->subtotal, 0, ',', '.') }}</strong></div>
            <div class="text-xs text-slate-500">PPN (11%): <strong class="text-slate-800">Rp {{ number_format($po->tax_amount, 0, ',', '.') }}</strong></div>
            <div class="text-base font-black text-jatim-700 pt-1 border-t border-slate-200">
                Total PO: Rp {{ number_format($po->total_amount, 0, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Goods Receipts History (GRN) -->
    @if($po->goodsReceipts->count() > 0)
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
            <h3 class="font-bold text-sm text-slate-900">Histori Penerimaan Barang (Goods Receipt Notes)</h3>
            <div class="space-y-3">
                @foreach($po->goodsReceipts as $grn)
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between">
                        <div>
                            <div class="text-xs font-bold text-slate-900">{{ $grn->grn_number }}</div>
                            <div class="text-[11px] text-slate-500">Surat Jalan Vendor: <strong>{{ $grn->vendor_delivery_note_number }}</strong> • Tanggal: {{ $grn->receipt_date->format('d M Y') }}</div>
                            <div class="text-[10px] text-emerald-700 font-semibold mt-1">Diposting ke Stock Ledger oleh: {{ $grn->receiver->name }}</div>
                        </div>
                        <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2.5 py-1 rounded-full">
                            POSTED TO STOCK LEDGER
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Goods Receipt Modal -->
    <div x-show="grnModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="grnModal = false" class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto relative">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4 pe-5">
                <div>
                    <h3 class="font-bold text-lg text-slate-900">Penerimaan Barang Vendor (GRN)</h3>
                    <p class="text-xs text-slate-500">Verifikasi fisik barang dari vendor dan posting langsung ke Stock Ledger gudang logistik.</p>
                </div>
                <button type="button" @click="grnModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('procurement.po.receive', $po->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nomor Surat Jalan / Delivery Note Vendor</label>
                    <input type="text" name="vendor_delivery_note_number" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-jatim-700">
                </div>

                <div class="border-t border-slate-100 pt-3 space-y-3">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Item yang Diterima</h4>
                    @foreach($po->items as $idx => $it)
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div class="text-xs font-bold text-slate-900">{{ $it->item->name }}</div>
                                <div class="text-[10px] text-slate-500">Dipesan: {{ $it->qty_ordered }} {{ $it->item->uom }} | Sisa: {{ $it->outstanding_qty }} {{ $it->item->uom }}</div>
                            </div>
                            <input type="hidden" name="items[{{ $idx }}][po_item_id]" value="{{ $it->id }}">
                            <div class="flex items-center space-x-2">
                                <div>
                                    <label class="text-[10px] font-bold text-slate-500">Diterima Baik:</label>
                                    <input type="number" name="items[{{ $idx }}][qty_accepted]" value="{{ $it->outstanding_qty }}" max="{{ $it->outstanding_qty }}" min="0" required class="w-20 bg-white border border-slate-200 rounded-lg p-1.5 text-xs text-center font-bold">
                                </div>
                                <div>
                                    <label class="text-[10px] font-bold text-rose-500">Ditolak/Rusak:</label>
                                    <input type="number" name="items[{{ $idx }}][qty_rejected]" value="0" min="0" class="w-16 bg-white border border-slate-200 rounded-lg p-1.5 text-xs text-center font-bold text-rose-600">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-4 flex justify-end space-x-3">
                    <button type="button" @click="grnModal = false" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition">
                        Verifikasi & Posting ke Stock Ledger
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
