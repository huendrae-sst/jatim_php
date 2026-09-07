@extends('layouts.app')
@section('title', 'Approved PR Pool & Konsolidasi PO')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('procurement.pr.index') }}" class="text-decoration-none text-danger">Pengadaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Konsolidasi PO</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    poolItems: {{ Js::from($approvedPrItems->map(fn($item) => [
        'pr_item_id' => $item->id,
        'pr_number' => $item->purchaseRequest->pr_number,
        'org_name' => $item->purchaseRequest->organization->name,
        'item_id' => $item->item_id,
        'item_name' => $item->item->name,
        'sku' => $item->item->sku,
        'uom' => $item->item->uom,
        'remaining_qty' => $item->remaining_qty_to_order,
        'unit_price' => (float) $item->estimated_unit_price,
        'selected' => false,
        'order_qty' => $item->remaining_qty_to_order
    ])) }},
    selectedCount() {
        return this.poolItems.filter(i => i.selected).length;
    },
    getSubtotal() {
        return this.poolItems.filter(i => i.selected).reduce((sum, i) => sum + (i.order_qty * i.unit_price), 0);
    },
    getTax() {
        return this.getSubtotal() * 0.11;
    },
    getTotal() {
        return this.getSubtotal() + this.getTax();
    }
}">
    <!-- Counter Badge -->
    <div class="d-flex justify-content-end">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill font-bold">
            <i class="bi bi-layers me-1"></i> <span x-text="poolItems.length"></span> Item PR Tersedia
        </span>
    </div>

    <!-- Info Banner (Skenario A Specification) -->
    <div class="bg-indigo-50/60 border border-indigo-200 rounded-2xl p-4 flex items-start space-x-3 text-indigo-900">
        <i class="fa-solid fa-circle-info text-indigo-600 text-base mt-0.5"></i>
        <div class="text-xs leading-relaxed">
            <strong>Aturan Konsolidasi Bank Jatim:</strong> 1 PO dapat dibentuk dari kumpulan 1 atau lebih PR yang telah disetujui. Setiap PO item otomatis menyimpan traceability ke nomor PR item sumber dan sisa kuota yang belum dipesan.
        </div>
    </div>

    <form action="{{ route('procurement.consolidation.store') }}" method="POST" class="space-y-4">
        @csrf

        <!-- Vendor & PO Header -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Pilih Vendor Penyedia</label>
                <select name="vendor_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-jatim-700">
                    <option value="">-- Pilih Vendor --</option>
                    @foreach($vendors as $vnd)
                        <option value="{{ $vnd->id }}">{{ $vnd->code }} - {{ $vnd->name }} (SLA: {{ $vnd->sla_days }} hr)</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Gudang Penerima</label>
                <select name="warehouse_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-jatim-700">
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->code }} - {{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Target Tanggal Pengiriman</label>
                <input type="date" name="expected_delivery_date" value="{{ date('Y-m-d', strtotime('+7 days')) }}" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-jatim-700">
            </div>
        </div>

        <!-- Available PR Items Pool Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-sm text-slate-900">Daftar Approved PR Items (Centang untuk Konsolidasi)</h3>
                <span class="text-xs text-slate-400 font-medium" x-text="selectedCount() + ' item dipilih'"></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4 w-12 text-center">Pilih</th>
                            <th class="py-3 px-4">No. PR Sumber</th>
                            <th class="py-3 px-4">Item & SKU</th>
                            <th class="py-3 px-4 text-center">Sisa Kuota PR</th>
                            <th class="py-3 px-4 w-32 text-center">Qty Dipesan ke PO</th>
                            <th class="py-3 px-4 text-right">Harga Satuan (Rp)</th>
                            <th class="py-3 px-4 text-right">Subtotal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, idx) in poolItems" :key="item.pr_item_id">
                            <tr :class="item.selected ? 'bg-rose-50/40' : 'hover:bg-slate-50'">
                                <td class="py-3 px-4 text-center">
                                    <input type="checkbox" x-model="item.selected" class="w-4 h-4 text-jatim-700 rounded border-slate-300 focus:ring-jatim-700">
                                </td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-slate-900" x-text="item.pr_number"></span>
                                    <div class="text-[10px] text-slate-400" x-text="item.org_name"></div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800" x-text="item.item_name"></div>
                                    <div class="text-[10px] text-slate-400 font-mono" x-text="item.sku + ' (' + item.uom + ')'"></div>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-indigo-700">
                                    <span x-text="item.remaining_qty"></span> <span x-text="item.uom"></span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <input type="number" :name="'selections[' + idx + '][qty]'" x-model="item.order_qty" :max="item.remaining_qty" min="1" :disabled="!item.selected" class="w-24 bg-white border border-slate-200 rounded-lg p-1.5 text-center text-xs font-bold disabled:bg-slate-100 disabled:text-slate-400">
                                    <input type="hidden" :name="'selections[' + idx + '][pr_item_id]'" :value="item.pr_item_id" :disabled="!item.selected">
                                    <input type="hidden" :name="'selections[' + idx + '][unit_price]'" :value="item.unit_price" :disabled="!item.selected">
                                </td>
                                <td class="py-3 px-4 text-right text-slate-600">
                                    <span x-text="'Rp ' + (item.unit_price).toLocaleString('id-ID')"></span>
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-slate-900">
                                    <span x-text="item.selected ? ('Rp ' + (item.order_qty * item.unit_price).toLocaleString('id-ID')) : '-'"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="poolItems.length === 0">
                            <td colspan="7" class="py-6 text-center text-slate-400">Tidak ada Approved PR yang menunggu konsolidasi saat ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Financial Summary Bar -->
            <div class="bg-slate-50 p-6 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1 text-xs text-slate-500">
                    <div>Item Terpilih: <strong class="text-slate-800" x-text="selectedCount()"></strong> item</div>
                    <div>PPN (11%): <strong class="text-slate-800" x-text="'Rp ' + Math.round(getTax()).toLocaleString('id-ID')"></strong></div>
                </div>

                <div class="flex items-center space-x-6">
                    <div class="text-right">
                        <div class="text-[10px] font-bold uppercase text-slate-400">Total Nilai PO:</div>
                        <div class="text-xl font-black text-jatim-700" x-text="'Rp ' + Math.round(getTotal()).toLocaleString('id-ID')"></div>
                    </div>

                    <button type="submit" :disabled="selectedCount() === 0" class="px-6 py-3 bg-jatim-700 hover:bg-jatim-800 disabled:bg-slate-300 disabled:cursor-not-allowed text-white text-xs font-bold rounded-xl shadow-md transition inline-flex items-center space-x-2">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>Terbitkan Purchase Order (PO)</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
