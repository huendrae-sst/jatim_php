@extends('layouts.app')
@section('title', 'Purchase Orders (PO)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Pengadaan</li>
    <li class="breadcrumb-item active" aria-current="page">Purchase Orders</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    viewModal: false,
    viewPo: null,

    editModal: false,
    editPo: null,
    editForm: {
        vendor_id: '',
        warehouse_id: '',
        expected_delivery_date: '',
        notes: '',
        items: [],
        subtotal: 0,
        tax_amount: 0,
        total_amount: 0
    },

    deleteModal: false,
    deletePo: null,

    formatRupiah(amount) {
        if (!amount && amount !== 0) return 'Rp 0';
        return 'Rp ' + Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    },

    getBadgeClass(status) {
        switch (status) {
            case 'COMPLETED': return 'text-bg-success';
            case 'PARTIAL_RECEIVED': return 'text-bg-primary';
            case 'ISSUED': return 'text-bg-info';
            case 'WAITING_APPROVAL': return 'text-bg-warning';
            case 'REJECTED': return 'text-bg-danger';
            case 'CANCELLED': return 'text-bg-secondary';
            default: return 'text-bg-secondary';
        }
    },

    openViewModal(po) {
        this.viewPo = po;
        this.viewModal = true;
    },

    openEditModal(po) {
        this.editPo = po;
        this.editForm = {
            vendor_id: po.vendor_id,
            warehouse_id: po.warehouse_id,
            expected_delivery_date: po.expected_delivery_date_raw || '',
            notes: po.notes || '',
            items: po.items.map(item => ({
                id: item.id,
                item_name: item.item_name,
                sku: item.sku,
                uom: item.uom,
                qty_ordered: item.qty_ordered,
                unit_price: item.unit_price,
                subtotal: item.subtotal
            })),
            subtotal: po.subtotal,
            tax_amount: po.tax_amount,
            total_amount: po.total_amount
        };
        this.recalculateEditTotals();
        this.editModal = true;
    },

    onUnitPriceChange(index) {
        let item = this.editForm.items[index];
        let price = parseFloat(item.unit_price) || 0;
        if (price < 0) price = 0;
        item.subtotal = price * item.qty_ordered;
        this.recalculateEditTotals();
    },

    recalculateEditTotals() {
        let subtotal = 0;
        this.editForm.items.forEach(it => {
            subtotal += (parseFloat(it.subtotal) || 0);
        });
        this.editForm.subtotal = subtotal;
        this.editForm.tax_amount = Math.round(subtotal * 0.11);
        this.editForm.total_amount = this.editForm.subtotal + this.editForm.tax_amount;
    },

    openDeleteModal(po) {
        this.deletePo = po;
        this.deleteModal = true;
    }
}">
    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('procurement.po.index') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <input type="hidden" name="sort_dir" value="{{ $sortDir }}">
                <div class="row g-2 align-items-center">
                    <!-- Vendor Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-shop"></i></span>
                            <select name="vendor_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Rekanan Vendor</option>
                                @foreach($vendors as $vnd)
                                    <option value="{{ $vnd->id }}" {{ (string)($vendorId ?? '') === (string)$vnd->id ? 'selected' : '' }}>
                                        {{ $vnd->code }} - {{ $vnd->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Warehouse Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-geo-alt"></i></span>
                            <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Gudang Tujuan</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ (string)($warehouseId ?? '') === (string)$wh->id ? 'selected' : '' }}>
                                        {{ $wh->code }} - {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Status</option>
                                <option value="WAITING_APPROVAL" {{ ($status ?? '') === 'WAITING_APPROVAL' ? 'selected' : '' }}>Menunggu Approval</option>
                                <option value="ISSUED" {{ ($status ?? '') === 'ISSUED' ? 'selected' : '' }}>Diterbitkan (Issued)</option>
                                <option value="PARTIAL_RECEIVED" {{ ($status ?? '') === 'PARTIAL_RECEIVED' ? 'selected' : '' }}>Sebagian Diterima</option>
                                <option value="COMPLETED" {{ ($status ?? '') === 'COMPLETED' ? 'selected' : '' }}>Selesai Diterima</option>
                                <option value="REJECTED" {{ ($status ?? '') === 'REJECTED' ? 'selected' : '' }}>Ditolak</option>
                                <option value="CANCELLED" {{ ($status ?? '') === 'CANCELLED' ? 'selected' : '' }}>Dibatalkan</option>
                            </select>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="col-12 col-sm-6 col-md">
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Cari No. PO, vendor, catatan..." class="form-control form-control-sm border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger px-3">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if(!empty($search) || (!empty($status) && $status !== 'ALL') || (!empty($vendorId) && $vendorId !== 'ALL') || (!empty($warehouseId) && $warehouseId !== 'ALL'))
                        <div class="col-12 col-sm-auto">
                            <a href="{{ route('procurement.po.index') }}" class="btn btn-sm btn-outline-secondary w-100 fs-8">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </a>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <!-- Table Responsive -->
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
                <thead class="table-light text-secondary border-bottom">
                    <tr>
                        <th class="py-2.5 px-3">No. PO</th>
                        <th class="py-2.5 px-3">Vendor Rekanan</th>
                        <th class="py-2.5 px-3">Gudang Penerima</th>
                        <th class="py-2.5 px-3">Tgl Order & Target Kirim</th>
                        <th class="py-2.5 px-3 text-end">Subtotal (Rp)</th>
                        <th class="py-2.5 px-3 text-end">Total (+PPN)</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pos as $po)
                        @php
                            $canEdit = $po->goodsReceipts->isEmpty() && !in_array($po->status, ['COMPLETED', 'PARTIAL_RECEIVED', 'CANCELLED', 'REJECTED']);
                            $canDelete = $po->goodsReceipts->isEmpty() && !in_array($po->status, ['COMPLETED', 'PARTIAL_RECEIVED']);
                            $poData = [
                                'id' => $po->id,
                                'po_number' => $po->po_number,
                                'vendor_id' => $po->vendor_id,
                                'vendor_name' => $po->vendor->name ?? '-',
                                'vendor_code' => $po->vendor->code ?? '-',
                                'warehouse_id' => $po->warehouse_id,
                                'warehouse_name' => $po->warehouse->name ?? '-',
                                'warehouse_code' => $po->warehouse->code ?? '-',
                                'order_date' => $po->order_date ? $po->order_date->format('d/m/Y') : '-',
                                'expected_delivery_date' => $po->expected_delivery_date ? $po->expected_delivery_date->format('d/m/Y') : '-',
                                'expected_delivery_date_raw' => $po->expected_delivery_date ? $po->expected_delivery_date->format('Y-m-d') : '',
                                'subtotal' => (float) $po->subtotal,
                                'tax_amount' => (float) $po->tax_amount,
                                'total_amount' => (float) $po->total_amount,
                                'status' => $po->status,
                                'notes' => $po->notes,
                                'creator_name' => $po->creator->name ?? '-',
                                'items' => $po->items->map(fn($i) => [
                                    'id' => $i->id,
                                    'item_name' => $i->item->name ?? '-',
                                    'sku' => $i->item->sku ?? '-',
                                    'uom' => $i->item->uom ?? '-',
                                    'qty_ordered' => $i->qty_ordered,
                                    'qty_received' => $i->qty_received,
                                    'unit_price' => (float) $i->unit_price,
                                    'subtotal' => (float) $i->subtotal,
                                ]),
                            ];
                        @endphp
                        <tr>
                            <!-- No. PO -->
                            <td class="py-2.5 px-3">
                                <div class="fw-bold font-monospace text-dark">{{ $po->po_number }}</div>
                                <div class="fs-9 text-muted d-flex align-items-center gap-1">
                                    <i class="bi bi-person fs-9"></i>
                                    <span>{{ $po->creator->name ?? '-' }}</span>
                                </div>
                            </td>

                            <!-- Vendor Rekanan -->
                            <td class="py-2.5 px-3">
                                <div class="fw-semibold text-dark">{{ $po->vendor->name ?? '-' }}</div>
                                <div class="fs-9 text-muted font-monospace">
                                    {{ $po->vendor->code ?? '-' }}
                                    @if($po->vendor?->sla_days)
                                        <span class="ms-1">&bull; SLA: {{ $po->vendor->sla_days }} hr</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Gudang Penerima -->
                            <td class="py-2.5 px-3">
                                <div class="fw-semibold text-dark">{{ $po->warehouse->name ?? '-' }}</div>
                                <div class="fs-9 text-muted font-monospace">{{ $po->warehouse->code ?? '-' }}</div>
                            </td>

                            <!-- Tanggal Order & Target Kirim -->
                            <td class="py-2.5 px-3">
                                <div class="text-dark">{{ $po->order_date ? $po->order_date->format('d/m/Y') : '-' }}</div>
                                <div class="fs-9 text-muted">Target: {{ $po->expected_delivery_date ? $po->expected_delivery_date->format('d/m/Y') : '-' }}</div>
                            </td>

                            <!-- Subtotal -->
                            <td class="py-2.5 px-3 text-end font-monospace text-secondary fs-8">
                                Rp {{ number_format($po->subtotal, 0, ',', '.') }}
                            </td>

                            <!-- Total Amount (+PPN) -->
                            <td class="py-2.5 px-3 text-end font-monospace fw-bold text-dark fs-8">
                                Rp {{ number_format($po->total_amount, 0, ',', '.') }}
                            </td>

                            <!-- Status -->
                            <td class="py-2.5 px-3 text-center">
                                @php
                                    $badgeClass = match($po->status) {
                                        'COMPLETED' => 'text-bg-success',
                                        'PARTIAL_RECEIVED' => 'text-bg-primary',
                                        'ISSUED' => 'text-bg-info',
                                        'WAITING_APPROVAL' => 'text-bg-warning',
                                        'REJECTED' => 'text-bg-danger',
                                        'CANCELLED' => 'text-bg-secondary',
                                        default => 'text-bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2.5 py-1 fs-8 fw-semibold">
                                    {{ str_replace('_', ' ', $po->status) }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="py-2.5 px-3 text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <!-- View Modal Button -->
                                    <button type="button" 
                                            @click="openViewModal({{ Js::from($poData) }})" 
                                            class="btn btn-sm btn-outline-secondary btn-action-icon" 
                                            title="Lihat Detail Ringkas">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <!-- Print PO Button -->
                                    <a href="{{ route('procurement.po.print', $po->id) }}" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-secondary btn-action-icon" 
                                       title="Cetak Purchase Order">
                                        <i class="bi bi-printer"></i>
                                    </a>

                                    <!-- Edit PO Button -->
                                    @if($canEdit)
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($poData) }})" 
                                                class="btn btn-sm btn-outline-primary btn-action-icon" 
                                                title="Edit Purchase Order">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    @else
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary btn-action-icon opacity-50" 
                                                disabled 
                                                title="PO tidak dapat diedit karena sudah diproses atau dibatalkan">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    @endif

                                    <!-- Delete PO Button -->
                                    @if($canDelete)
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($poData) }})" 
                                                class="btn btn-sm btn-outline-danger btn-action-icon" 
                                                title="Hapus Purchase Order">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @else
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary btn-action-icon opacity-50" 
                                                disabled 
                                                title="PO tidak dapat dihapus karena sudah ada penerimaan barang">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-secondary">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                                <div class="fw-semibold">Belum ada Purchase Order (PO) yang ditemukan.</div>
                                <div class="fs-8 text-muted mt-1">Belum ada Purchase Order (PO) yang diterbitkan.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <x-pagination-footer :paginator="$pos" :perPage="$perPage" />
    </div>

    <!-- Modal Detail Ringkas PO (Standard Bank Jatim Modal) -->
    <div x-show="viewModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-file-earmark-spreadsheet fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Rincian Purchase Order (PO)</h6>
                        <span class="fs-8 text-secondary" x-text="viewPo ? viewPo.po_number : ''"></span>
                    </div>
                </div>
                <button type="button" @click="viewModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="card-body p-4 space-y-3" x-show="viewPo">
                <div class="row g-2 p-3 bg-body-tertiary rounded-3 border fs-8">
                    <div class="col-12 col-sm-6">
                        <span class="text-secondary">Vendor Rekanan:</span>
                        <div class="fw-bold text-dark" x-text="viewPo ? (viewPo.vendor_code + ' - ' + viewPo.vendor_name) : '-'"></div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <span class="text-secondary">Gudang Penerima:</span>
                        <div class="fw-bold text-dark" x-text="viewPo ? viewPo.warehouse_name : '-'"></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="text-secondary">Tanggal Order:</span>
                        <div class="fw-bold text-dark" x-text="viewPo ? viewPo.order_date : '-'"></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="text-secondary">Target Kirim:</span>
                        <div class="fw-bold text-dark" x-text="viewPo ? viewPo.expected_delivery_date : '-'"></div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="text-secondary">Status PO:</span>
                        <div>
                            <span class="badge px-2 py-0.5 fs-9" :class="viewPo ? getBadgeClass(viewPo.status) : ''" x-text="viewPo ? viewPo.status : ''"></span>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="text-secondary">Maker:</span>
                        <div class="fw-semibold text-dark" x-text="viewPo ? viewPo.creator_name : '-'"></div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive rounded border" style="max-height: 220px;">
                    <table class="table table-sm table-striped align-middle mb-0 fs-8">
                        <thead class="table-light">
                            <tr>
                                <th class="py-1.5 px-2">Item & SKU</th>
                                <th class="py-1.5 px-2 text-center">Qty Order</th>
                                <th class="py-1.5 px-2 text-center">Qty Diterima</th>
                                <th class="py-1.5 px-2 text-end">Harga Satuan</th>
                                <th class="py-1.5 px-2 text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in (viewPo ? viewPo.items : [])" :key="item.sku">
                                <tr>
                                    <td class="py-1.5 px-2">
                                        <div class="fw-bold text-dark" x-text="item.item_name"></div>
                                        <div class="fs-9 text-muted font-monospace" x-text="item.sku"></div>
                                    </td>
                                    <td class="py-1.5 px-2 text-center font-monospace" x-text="item.qty_ordered + ' ' + item.uom"></td>
                                    <td class="py-1.5 px-2 text-center font-monospace">
                                        <span class="badge" :class="item.qty_received >= item.qty_ordered ? 'text-bg-success' : (item.qty_received > 0 ? 'text-bg-primary' : 'text-bg-secondary')" x-text="item.qty_received + ' / ' + item.qty_ordered"></span>
                                    </td>
                                    <td class="py-1.5 px-2 text-end font-monospace" x-text="formatRupiah(item.unit_price)"></td>
                                    <td class="py-1.5 px-2 text-end font-monospace fw-bold text-dark" x-text="formatRupiah(item.subtotal)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Financial Summary -->
                <div class="d-flex flex-column align-items-end fs-8 space-y-1">
                    <div class="d-flex justify-content-between w-100" style="max-width: 280px;">
                        <span class="text-secondary">Subtotal:</span>
                        <strong class="font-monospace text-dark" x-text="viewPo ? formatRupiah(viewPo.subtotal) : 'Rp 0'"></strong>
                    </div>
                    <div class="d-flex justify-content-between w-100" style="max-width: 280px;">
                        <span class="text-secondary">PPN (11%):</span>
                        <strong class="font-monospace text-dark" x-text="viewPo ? formatRupiah(viewPo.tax_amount) : 'Rp 0'"></strong>
                    </div>
                    <div class="d-flex justify-content-between w-100 border-top pt-1" style="max-width: 280px;">
                        <span class="text-danger fw-bold fs-7">Total PO:</span>
                        <strong class="font-monospace text-danger fs-6" x-text="viewPo ? formatRupiah(viewPo.total_amount) : 'Rp 0'"></strong>
                    </div>
                </div>

                <div x-show="viewPo && viewPo.notes" class="p-2 bg-body-tertiary rounded border fs-8 text-secondary">
                    <strong class="text-dark">Catatan:</strong> <span x-text="viewPo ? viewPo.notes : ''"></span>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="card-footer bg-body-tertiary d-flex justify-content-between align-items-center py-2.5 px-4 border-top">
                <a :href="'/procurement/po/' + (viewPo ? viewPo.id : '') + '/print'" 
                   target="_blank" 
                   class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1.5 shadow-xs">
                    <i class="bi bi-printer"></i>
                    <span>Cetak Dokumen PO</span>
                </a>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Edit PO -->
    <div x-show="editModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-3xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-pencil-square fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Edit Purchase Order (PO)</h6>
                        <span class="fs-8 text-secondary" x-text="editPo ? editPo.po_number : ''"></span>
                    </div>
                </div>
                <button type="button" @click="editModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Modal Form -->
            <form :action="'/procurement/po/' + (editPo ? editPo.id : '')" method="POST">
                @csrf
                @method('PUT')
                
                <div class="card-body p-4 space-y-4" style="max-height: calc(85vh - 120px); overflow-y: auto;">
                    <!-- General Information Row -->
                    <div class="row g-3">
                        <!-- Vendor -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Vendor Rekanan <span class="text-danger">*</span></label>
                            <select name="vendor_id" x-model="editForm.vendor_id" required class="form-select form-select-sm fs-8">
                                @foreach($vendors as $vnd)
                                    <option value="{{ $vnd->id }}">{{ $vnd->code }} - {{ $vnd->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Warehouse -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Gudang Penerima <span class="text-danger">*</span></label>
                            <select name="warehouse_id" x-model="editForm.warehouse_id" required class="form-select form-select-sm fs-8">
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->code }} - {{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Expected Delivery Date -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Target Tanggal Kirim</label>
                            <input type="date" name="expected_delivery_date" x-model="editForm.expected_delivery_date" class="form-control form-control-sm fs-8">
                        </div>

                        <!-- Notes -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Catatan Tambahan</label>
                            <input type="text" name="notes" x-model="editForm.notes" placeholder="Catatan pengadaan / instruksi khusus..." class="form-control form-control-sm fs-8">
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fs-8 fw-bold text-secondary text-uppercase tracking-wider">Daftar Item & Penyesuaian Harga Satuan</span>
                            <span class="fs-9 text-muted">Kuantitas order mengacu pada konsolidasi PR</span>
                        </div>
                        <div class="table-responsive rounded border">
                            <table class="table table-sm table-striped align-middle mb-0 fs-8">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-2 px-3">Item & SKU</th>
                                        <th class="py-2 px-2 text-center" style="width: 90px;">Qty Order</th>
                                        <th class="py-2 px-2 text-center" style="width: 80px;">Satuan</th>
                                        <th class="py-2 px-3 text-end" style="width: 170px;">Harga Satuan (Rp)</th>
                                        <th class="py-2 px-3 text-end" style="width: 150px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in editForm.items" :key="item.id">
                                        <tr>
                                            <td class="py-2 px-3">
                                                <input type="hidden" :name="'items[' + index + '][id]'" :value="item.id">
                                                <div class="fw-bold text-dark" x-text="item.item_name"></div>
                                                <div class="fs-9 text-muted font-monospace" x-text="item.sku"></div>
                                            </td>
                                            <td class="py-2 px-2 text-center font-monospace fw-semibold" x-text="item.qty_ordered"></td>
                                            <td class="py-2 px-2 text-center text-muted" x-text="item.uom"></td>
                                            <td class="py-2 px-3 text-end">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-body-tertiary fs-9 py-0 px-1.5">Rp</span>
                                                    <input type="number" 
                                                           :name="'items[' + index + '][unit_price]'" 
                                                           x-model.number="item.unit_price" 
                                                           @input="onUnitPriceChange(index)" 
                                                           min="0" 
                                                           step="100" 
                                                           required 
                                                           class="form-control form-control-sm text-end font-monospace fs-8 py-1">
                                                </div>
                                            </td>
                                            <td class="py-2 px-3 text-end font-monospace fw-bold text-dark" x-text="formatRupiah(item.subtotal)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="d-flex flex-column align-items-end fs-8 space-y-1.5 bg-body-tertiary p-3 rounded border">
                        <div class="d-flex justify-content-between w-100" style="max-width: 320px;">
                            <span class="text-secondary">Subtotal Barang:</span>
                            <strong class="font-monospace text-dark" x-text="formatRupiah(editForm.subtotal)"></strong>
                        </div>
                        <div class="d-flex justify-content-between w-100" style="max-width: 320px;">
                            <span class="text-secondary">PPN (11%):</span>
                            <strong class="font-monospace text-dark" x-text="formatRupiah(editForm.tax_amount)"></strong>
                        </div>
                        <div class="d-flex justify-content-between w-100 border-top pt-1.5" style="max-width: 320px;">
                            <span class="text-danger fw-bold fs-7">Total Nilai PO:</span>
                            <strong class="font-monospace text-danger fs-6" x-text="formatRupiah(editForm.total_amount)"></strong>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editModal = false" class="btn btn-sm btn-outline-secondary">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check2-circle"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus PO -->
    <div x-show="deleteModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-danger-subtle text-danger d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom border-danger-subtle">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <h6 class="mb-0 fw-bold">Konfirmasi Hapus Purchase Order</h6>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Modal Form -->
            <form :action="'/procurement/po/' + (deletePo ? deletePo.id : '')" method="POST">
                @csrf
                @method('DELETE')
                
                <div class="card-body p-4 space-y-3">
                    <p class="fs-8 text-secondary mb-0">
                        Apakah Anda yakin ingin menghapus Purchase Order ini secara permanen dari sistem?
                    </p>

                    <div class="p-3 bg-body-tertiary rounded-3 border fs-8 space-y-1">
                        <div><strong class="text-dark">No. PO:</strong> <span class="font-monospace text-danger fw-bold" x-text="deletePo ? deletePo.po_number : '-'"></span></div>
                        <div><strong class="text-dark">Vendor:</strong> <span x-text="deletePo ? deletePo.vendor_name : '-'"></span></div>
                        <div><strong class="text-dark">Total Nilai:</strong> <span class="font-monospace fw-bold" x-text="deletePo ? formatRupiah(deletePo.total_amount) : '-'"></span></div>
                    </div>

                    <div class="alert alert-warning py-2.5 px-3 fs-9 mb-0 d-flex gap-2">
                        <i class="bi bi-info-circle-fill text-warning flex-shrink-0 mt-0.5"></i>
                        <div>
                            <strong>Pengembalian Alokasi PR:</strong> Seluruh kuantitas barang yang telah dialokasikan pada PO ini akan secara otomatis dikembalikan ke status <strong>Approved PR Pool</strong> sehingga dapat dikonsolidasikan ulang pada penerbitan PO berikutnya.
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-trash"></i>
                        <span>Ya, Hapus PO</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
