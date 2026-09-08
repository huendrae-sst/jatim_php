@extends('layouts.app')
@section('title', 'Approved PR Pool & Konsolidasi PO')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('procurement.pr.index') }}" class="text-decoration-none text-danger">Pengadaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Konsolidasi PO</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    poolItems: {{ Js::from($approvedPrItems->values()->map(fn($item, $idx) => [
        'originalIndex' => $idx,
        'pr_item_id' => $item->id,
        'pr_number' => $item->purchaseRequest->pr_number,
        'pr_date' => $item->purchaseRequest->submitted_at ? $item->purchaseRequest->submitted_at->format('d/m/Y') : ($item->purchaseRequest->created_at ? $item->purchaseRequest->created_at->format('d/m/Y') : '-'),
        'org_name' => $item->purchaseRequest->organization->name,
        'item_id' => $item->item_id,
        'item_name' => $item->item->name,
        'sku' => $item->item->sku,
        'category_name' => $item->item->category->name ?? '-',
        'uom' => $item->item->uom,
        'remaining_qty' => $item->remaining_qty_to_order,
        'unit_price' => (float) $item->estimated_unit_price,
        'selected' => false,
        'order_qty' => $item->remaining_qty_to_order
    ])) }},

    searchQuery: '',
    vendorsList: {{ Js::from($vendors->map(fn($v) => ['id' => (string) $v->id, 'text' => $v->code . ' - ' . $v->name . ' (SLA: ' . $v->sla_days . ' hr)'])) }},
    warehousesList: {{ Js::from($warehouses->map(fn($w) => ['id' => (string) $w->id, 'text' => $w->code . ' - ' . $w->name])) }},
    selectedVendorId: '',
    selectedWarehouseId: '{{ $warehouses->first()?->id ?? '' }}',
    expectedDeliveryDate: '{{ date('Y-m-d', strtotime('+7 days')) }}',
    poNotes: '',
    confirmModal: false,

    formatRupiah(amount) {
        if (!amount && amount !== 0) return 'Rp 0';
        return 'Rp ' + Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    },

    filteredItems() {
        if (!this.searchQuery.trim()) {
            return this.poolItems;
        }
        let q = this.searchQuery.toLowerCase();
        return this.poolItems.filter(i => 
            i.pr_number.toLowerCase().includes(q) ||
            i.item_name.toLowerCase().includes(q) ||
            i.sku.toLowerCase().includes(q) ||
            i.org_name.toLowerCase().includes(q) ||
            i.category_name.toLowerCase().includes(q)
        );
    },

    selectedCount() {
        return this.poolItems.filter(i => i.selected).length;
    },

    getSubtotal() {
        return this.poolItems.filter(i => i.selected).reduce((sum, i) => sum + ((i.order_qty || 0) * (i.unit_price || 0)), 0);
    },

    getTax() {
        return this.getSubtotal() * 0.11;
    },

    getTotal() {
        return this.getSubtotal() + this.getTax();
    },

    isAllSelected() {
        let items = this.filteredItems();
        return items.length > 0 && items.every(i => i.selected);
    },

    toggleAll(checked) {
        this.filteredItems().forEach(i => {
            i.selected = checked;
            if (checked && (!i.order_qty || i.order_qty <= 0)) {
                i.order_qty = i.remaining_qty;
            }
        });
    },

    selectAll() {
        this.filteredItems().forEach(i => {
            i.selected = true;
            if (!i.order_qty || i.order_qty <= 0) {
                i.order_qty = i.remaining_qty;
            }
        });
    },

    unselectAll() {
        this.poolItems.forEach(i => i.selected = false);
    },

    getSelectedVendorName() {
        if (!this.selectedVendorId) return 'Belum dipilih';
        let v = this.vendorsList.find(item => item.id === String(this.selectedVendorId));
        return v ? v.text : 'Belum dipilih';
    },

    getSelectedWarehouseName() {
        if (!this.selectedWarehouseId) return 'Belum dipilih';
        let w = this.warehousesList.find(item => item.id === String(this.selectedWarehouseId));
        return w ? w.text : 'Belum dipilih';
    },

    openConfirmModal() {
        if (!this.selectedVendorId) {
            alert('Silakan pilih Rekanan / Vendor terlebih dahulu.');
            document.getElementById('vendorSelect')?.focus();
            return;
        }
        if (!this.selectedWarehouseId) {
            alert('Silakan pilih Gudang Tujuan Pengiriman terlebih dahulu.');
            document.getElementById('warehouseSelect')?.focus();
            return;
        }
        if (!this.expectedDeliveryDate) {
            alert('Silakan tentukan Target Tanggal Pengiriman.');
            return;
        }
        if (this.selectedCount() === 0) {
            alert('Pilih minimal 1 item PR untuk dikonsolidasikan.');
            return;
        }

        // Validate quantities
        for (let item of this.poolItems) {
            if (item.selected) {
                if (!item.order_qty || item.order_qty < 1) {
                    alert('Kuantitas pesanan untuk item ' + item.item_name + ' minimal 1.');
                    return;
                }
                if (item.order_qty > item.remaining_qty) {
                    alert('Kuantitas pesanan untuk item ' + item.item_name + ' melebihi sisa kuota (' + item.remaining_qty + ').');
                    return;
                }
            }
        }

        this.confirmModal = true;
    }
}">
    <form id="consolidationForm" action="{{ route('procurement.consolidation.store') }}" method="POST">
        @csrf

        <!-- Dynamically generated hidden inputs for selected items (100% robust against UI search filtering) -->
        <template x-for="(selItem, sIdx) in poolItems.filter(i => i.selected)" :key="'sel-' + selItem.pr_item_id">
            <div>
                <input type="hidden" :name="'selections[' + sIdx + '][pr_item_id]'" :value="selItem.pr_item_id">
                <input type="hidden" :name="'selections[' + sIdx + '][qty]'" :value="selItem.order_qty">
                <input type="hidden" :name="'selections[' + sIdx + '][unit_price]'" :value="selItem.unit_price">
            </div>
        </template>

        <!-- Card 1: Parameter Purchase Order (PO) -->
        <div class="card card-outline card-danger shadow-xs mb-3">
            <div class="card-header border-bottom py-2.5 px-3">
                <h3 class="card-title fw-bold text-dark fs-7 mb-0 d-flex align-items-center">
                    Parameter Purchase Order (PO)
                </h3>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <!-- Vendor Selector -->
                    <div class="col-12 col-md-4">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Pilih Vendor Penyedia <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-shop"></i></span>
                            <select id="vendorSelect" name="vendor_id" x-model="selectedVendorId" required class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">-- Pilih Rekanan / Vendor --</option>
                                @foreach($vendors as $vnd)
                                    <option value="{{ $vnd->id }}">
                                        {{ $vnd->code }} - {{ $vnd->name }} (SLA: {{ $vnd->sla_days }} hr)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Warehouse Selector -->
                    <div class="col-12 col-md-4">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Gudang Penerima <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-geo-alt"></i></span>
                            <select id="warehouseSelect" name="warehouse_id" x-model="selectedWarehouseId" required class="form-select form-select-sm border-start-0 fs-8">
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">
                                        {{ $wh->code }} - {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Expected Delivery Date -->
                    <div class="col-12 col-md-4">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Target Tanggal Pengiriman <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" name="expected_delivery_date" x-model="expectedDeliveryDate" required class="form-control form-control-sm border-start-0 fs-8">
                        </div>
                    </div>

                    <!-- Optional Consolidation Notes -->
                    <div class="col-12">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Catatan Konsolidasi / Instruksi Tambahan (Opsional)
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-chat-left-text"></i></span>
                            <input type="text" name="notes" x-model="poNotes" placeholder="Contoh: Pengiriman batch logistik cabang..." class="form-control form-control-sm border-start-0 fs-8">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Approved PR Items Pool Table -->
        <div class="card card-outline card-danger shadow-xs mb-0">
            <!-- Card Header -->
            <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 py-2.5 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-layers-fill text-danger fs-5"></i>
                    <div>
                        <h3 class="card-title fw-bold text-dark fs-7 mb-0">Daftar Approved PR Items</h3>
                        <div class="text-secondary fs-8">Centang item PR yang akan digabungkan ke dalam Purchase Order (PO)</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-pill fw-bold fs-8">
                        <i class="bi bi-check2-square me-1"></i> <span x-text="selectedCount()"></span> dari <span x-text="poolItems.length"></span> Item Dipilih
                    </span>
                </div>
            </div>

            <!-- Toolbar Search & Bulk Actions -->
            <div class="card-body p-2.5 bg-body-tertiary border-bottom">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-sm-auto d-flex align-items-center gap-2">
                        <button type="button" @click="selectAll()" class="btn btn-xs btn-outline-secondary fw-semibold">
                            <i class="bi bi-check-all me-1"></i> Pilih Semua
                        </button>
                        <button type="button" @click="unselectAll()" class="btn btn-xs btn-outline-secondary fw-semibold">
                            <i class="bi bi-dash-circle me-1"></i> Batal Pilih
                        </button>
                    </div>
                    <div class="col-12 col-sm ms-sm-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" x-model="searchQuery" placeholder="Cari nomor PR, nama item, SKU, kategori, atau unit kerja..." class="form-control form-control-sm border-start-0 fs-8">
                            <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="btn btn-outline-secondary border-start-0 fs-8">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Responsive -->
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="table-light text-secondary border-bottom">
                        <tr>
                            <th class="py-2.5 px-3 text-center" style="width: 48px;">
                                <input type="checkbox" @change="toggleAll($event.target.checked)" :checked="isAllSelected()" class="form-check-input">
                            </th>
                            <th class="py-2.5 px-3" style="min-width: 170px;">No. PR Sumber</th>
                            <th class="py-2.5 px-3" style="min-width: 220px;">Item & SKU</th>
                            <th class="py-2.5 px-3 text-center" style="width: 130px;">Sisa Kuota PR</th>
                            <th class="py-2.5 px-3 text-center" style="width: 140px;">Qty PO</th>
                            <th class="py-2.5 px-3 text-end" style="width: 160px;">Harga Satuan (Rp)</th>
                            <th class="py-2.5 px-3 text-end" style="width: 170px;">Subtotal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in filteredItems()" :key="item.pr_item_id">
                            <tr :class="item.selected ? 'table-danger-subtle' : ''">
                                <!-- Checkbox -->
                                <td class="py-2.5 px-3 text-center">
                                    <input type="checkbox" 
                                           x-model="item.selected" 
                                           @change="if (item.selected && (!item.order_qty || item.order_qty <= 0)) { item.order_qty = item.remaining_qty; }"
                                           class="form-check-input">
                                </td>

                                <!-- No. PR Sumber -->
                                <td class="py-2.5 px-3">
                                    <div class="fw-bold text-dark" x-text="item.pr_number"></div>
                                    <div class="fs-8 text-secondary d-flex align-items-center gap-1 mt-0.5">
                                        <i class="bi bi-building fs-9"></i>
                                        <span x-text="item.org_name"></span>
                                    </div>
                                    <div class="fs-9 text-muted" x-text="'Tgl: ' + item.pr_date"></div>
                                </td>

                                <!-- Item & SKU -->
                                <td class="py-2.5 px-3">
                                    <div class="fw-bold text-dark" x-text="item.item_name"></div>
                                    <div class="fs-8 text-secondary d-flex align-items-center gap-2 mt-0.5">
                                        <span class="font-monospace text-muted" x-text="item.sku"></span>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-9" x-text="item.category_name"></span>
                                    </div>
                                </td>

                                <!-- Sisa Kuota PR -->
                                <td class="py-2.5 px-3 text-center">
                                    <span class="badge bg-info-subtle text-info border border-info-subtle fs-8 px-2.5 py-1 fw-bold">
                                        <span x-text="item.remaining_qty"></span> <span x-text="item.uom"></span>
                                    </span>
                                </td>

                                <!-- Qty Dipesan ke PO -->
                                <td class="py-2.5 px-3 text-center">
                                    <input type="number" 
                                           x-model.number="item.order_qty" 
                                           :max="item.remaining_qty" 
                                           min="1" 
                                           :disabled="!item.selected" 
                                           @input="if (item.order_qty > item.remaining_qty) { item.order_qty = item.remaining_qty; } if (item.order_qty < 1) { item.order_qty = 1; }"
                                           class="form-control form-control-sm text-center fw-bold mx-auto" 
                                           style="max-width: 100px;">
                                </td>

                                <!-- Harga Satuan -->
                                <td class="py-2.5 px-3 text-end font-monospace text-secondary fs-8">
                                    <span x-text="formatRupiah(item.unit_price)"></span>
                                </td>

                                <!-- Subtotal -->
                                <td class="py-2.5 px-3 text-end font-monospace fw-bold text-dark fs-8">
                                    <span x-text="item.selected ? formatRupiah(item.order_qty * item.unit_price) : '-'"></span>
                                </td>
                            </tr>
                        </template>

                        <!-- Empty State -->
                        <tr x-show="filteredItems().length === 0">
                            <td colspan="7" class="py-5 text-center text-secondary">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                <div class="fw-semibold" x-show="poolItems.length === 0">
                                    Tidak ada item PR yang menunggu konsolidasi saat ini.
                                </div>
                                <div class="fs-8 text-muted mt-1" x-show="poolItems.length === 0">
                                    Semua PR yang disetujui telah selesai diproses ke dalam Purchase Order (PO).
                                </div>
                                <div class="fw-semibold" x-show="poolItems.length > 0 && filteredItems().length === 0">
                                    Tidak ada item PR yang cocok dengan kriteria pencarian "<span x-text="searchQuery"></span>".
                                </div>
                                <button type="button" x-show="poolItems.length > 0 && filteredItems().length === 0" @click="searchQuery = ''" class="btn btn-xs btn-outline-danger mt-2">
                                    Reset Pencarian
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Financial Summary Bar & Action Footer -->
            <div class="card-footer bg-body-tertiary border-top p-3">
                <div class="row align-items-center g-3">
                    <!-- Left: Counter & Breakdown -->
                    <div class="col-12 col-md-6">
                        <div class="d-flex flex-wrap align-items-center gap-3 fs-7 text-secondary">
                            <div>
                                <span class="text-muted">Item Terpilih:</span> 
                                <strong class="text-dark" x-text="selectedCount()"></strong> item
                            </div>
                            <div class="vr d-none d-sm-block"></div>
                            <div>
                                <span class="text-muted">Subtotal:</span> 
                                <strong class="text-dark font-monospace" x-text="formatRupiah(getSubtotal())"></strong>
                            </div>
                            <div class="vr d-none d-sm-block"></div>
                            <div>
                                <span class="text-muted">PPN (11%):</span> 
                                <strong class="text-dark font-monospace" x-text="formatRupiah(getTax())"></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Grand Total & Submit Button -->
                    <div class="col-12 col-md-6 d-flex flex-column flex-sm-row align-items-sm-center justify-content-md-end gap-3">
                        <div class="text-md-end">
                            <div class="text-uppercase text-secondary fs-9 fw-bold">Total Nilai PO (+PPN)</div>
                            <div class="fs-4 fw-black text-danger font-monospace" x-text="formatRupiah(getTotal())"></div>
                        </div>
                        <button type="button" 
                                @click="openConfirmModal()" 
                                :disabled="selectedCount() === 0" 
                                class="btn btn-danger fw-bold shadow-xs px-3 py-2 d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-file-earmark-plus"></i>
                            <span>Terbitkan PO</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Modal Dialog Konfirmasi Penerbitan PO (Standard Bank Jatim Modal Architecture) -->
    <div x-show="confirmModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="confirmModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger text-white py-2.5 px-3 d-flex align-items-center justify-content-between">
                <h5 class="card-title fs-7 fw-bold mb-0 d-flex align-items-center gap-2">
                    Konfirmasi Penerbitan Purchase Order
                </h5>
                <button type="button" class="btn-close btn-close-white" @click="confirmModal = false" aria-label="Close"></button>
            </div>
            <div class="card-body p-3.5 fs-8 space-y-3">
                <div class="alert alert-warning-subtle border border-warning-subtle p-2.5 mb-2 fs-8 rounded-3 text-warning-emphasis d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-6 mt-0.5 flex-shrink-0"></i>
                    <div>
                        Pastikan seluruh parameter dan daftar item telah sesuai. Setelah diterbitkan, PO akan masuk ke antrean persetujuan resmi pengadaan.
                    </div>
                </div>

                <div class="p-3 rounded-3 bg-body-tertiary border space-y-1.5">
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Vendor Rekanan:</span>
                        <strong class="text-dark" x-text="getSelectedVendorName()"></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Gudang Penerima:</span>
                        <strong class="text-dark" x-text="getSelectedWarehouseName()"></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Target Pengiriman:</span>
                        <span class="fw-bold text-dark" x-text="expectedDeliveryDate"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Jumlah Item Terpilih:</span>
                        <strong class="text-primary"><span x-text="selectedCount()"></span> item</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Subtotal:</span>
                        <span class="font-monospace text-dark" x-text="formatRupiah(getSubtotal())"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">PPN (11%):</span>
                        <span class="font-monospace text-dark" x-text="formatRupiah(getTax())"></span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-1.5 mt-1.5">
                        <span class="text-danger fw-bold fs-7">Total Nilai PO:</span>
                        <strong class="text-danger font-monospace fs-6" x-text="formatRupiah(getTotal())"></strong>
                    </div>
                </div>

                <!-- Table Preview of Selected Items -->
                <div class="table-responsive rounded border mb-1" style="max-height: 180px;">
                    <table class="table table-sm table-striped align-middle mb-0 fs-8">
                        <thead class="table-light">
                            <tr>
                                <th class="py-1.5 px-2">Item</th>
                                <th class="py-1.5 px-2 text-center">Qty PO</th>
                                <th class="py-1.5 px-2 text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="selItem in poolItems.filter(i => i.selected)" :key="'modal-sel-' + selItem.pr_item_id">
                                <tr>
                                    <td class="py-1.5 px-2">
                                        <div class="fw-bold text-dark" x-text="selItem.item_name"></div>
                                        <div class="fs-9 text-muted" x-text="selItem.pr_number + ' • ' + selItem.org_name"></div>
                                    </td>
                                    <td class="py-1.5 px-2 text-center font-monospace fw-bold" x-text="selItem.order_qty + ' ' + selItem.uom"></td>
                                    <td class="py-1.5 px-2 text-end font-monospace text-dark" x-text="formatRupiah(selItem.order_qty * selItem.unit_price)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div x-show="poNotes" class="p-2 rounded bg-body-tertiary border fs-8 text-secondary">
                    <strong class="text-dark">Catatan:</strong> <span x-text="poNotes"></span>
                </div>
            </div>
            <div class="card-footer bg-body-tertiary py-2.5 px-3 d-flex justify-content-end gap-2 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" @click="confirmModal = false">
                    Batal
                </button>
                <button type="submit" form="consolidationForm" class="btn btn-sm btn-danger fw-bold px-3 d-inline-flex align-items-center gap-1">
                    <i class="bi bi-check-circle"></i>
                    <span>Ya, Terbitkan PO Sekarang</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
