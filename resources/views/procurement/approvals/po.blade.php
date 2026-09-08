@extends('layouts.app')
@section('title', 'Persetujuan Purchase Order (PO)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('procurement.po.index') }}" class="text-decoration-none text-danger">Pengadaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Persetujuan PO</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    // View Modal State
    viewModal: false,
    viewPo: null,
    openViewModal(po) {
        this.viewPo = po;
        this.viewModal = true;
    },

    // Approve Modal State (Dialog Konfirmasi)
    approveModal: false,
    approvePo: null,
    openApproveModal(po) {
        this.approvePo = po;
        this.approveModal = true;
    },

    // Reject Modal State (Dialog Penolakan)
    rejectModal: false,
    rejectPo: null,
    rejectionReason: '',
    openRejectModal(po) {
        this.rejectPo = po;
        this.rejectionReason = '';
        this.rejectModal = true;
    },

    formatRupiah(amount) {
        if (!amount && amount !== 0) return 'Rp 0';
        return 'Rp ' + Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
}">

    <!-- AdminLTE 4 Info-Boxes -->
    <div class="row g-3">
        <!-- Box 1: Menunggu Persetujuan Penerbitan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Menunggu Penerbitan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $pendingCount > 0 ? 'text-warning-emphasis' : 'text-body-emphasis' }}">{{ number_format($pendingCount) }} PO</span>
                    <span class="fs-9 text-secondary">Konsolidasi butuh persetujuan terbit</span>
                </div>
            </div>
        </div>

        <!-- Box 2: PO Diterbitkan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-send-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">PO Diterbitkan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-info-emphasis">{{ number_format($issuedCount) }} PO</span>
                    <span class="fs-9 text-secondary">Dalam proses kirim / GRN vendor</span>
                </div>
            </div>
        </div>

        <!-- Box 3: PO Selesai -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">PO Selesai Diterima</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($completedCount) }} PO</span>
                    <span class="fs-9 text-secondary">Penerimaan barang telah lengkap</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Total Nilai Pending -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Nilai Komitmen Menunggu</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">Rp {{ number_format($pendingValue, 0, ',', '.') }}</span>
                    <span class="fs-9 text-secondary">Total komitmen biaya PO pending</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <!-- Status Tabs & Filter Toolbar -->
        <div class="card-header border-bottom p-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-stretch align-items-lg-center gap-3">
                <!-- Status Filter Pills -->
                <ul class="nav nav-pills nav-pills-scroll flex-nowrap fs-7">
                    <li class="nav-item">
                        <a href="{{ route('procurement.approvals.po', array_merge(request()->except('page'), ['tab' => 'pending'])) }}" 
                           class="nav-link {{ $tab === 'pending' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-hourglass-split me-1"></i> Menunggu
                            <span class="badge {{ $tab === 'pending' ? 'bg-white text-danger' : 'text-bg-warning' }} ms-1">{{ $pendingCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('procurement.approvals.po', array_merge(request()->except('page'), ['tab' => 'issued'])) }}" 
                           class="nav-link {{ $tab === 'issued' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-send me-1"></i> Diterbitkan
                            <span class="badge {{ $tab === 'issued' ? 'bg-white text-danger' : 'text-bg-info' }} ms-1">{{ $issuedCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('procurement.approvals.po', array_merge(request()->except('page'), ['tab' => 'completed'])) }}" 
                           class="nav-link {{ $tab === 'completed' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-check2-all me-1"></i> Selesai
                            <span class="badge {{ $tab === 'completed' ? 'bg-white text-danger' : 'text-bg-success' }} ms-1">{{ $completedCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('procurement.approvals.po', array_merge(request()->except('page'), ['tab' => 'rejected'])) }}" 
                           class="nav-link {{ $tab === 'rejected' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-x-octagon me-1"></i> Ditolak
                            <span class="badge {{ $tab === 'rejected' ? 'bg-white text-danger' : 'text-bg-danger' }} ms-1">{{ $rejectedCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('procurement.approvals.po', array_merge(request()->except('page'), ['tab' => 'all'])) }}" 
                           class="nav-link {{ $tab === 'all' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-collection me-1"></i> Semua
                            <span class="badge {{ $tab === 'all' ? 'bg-white text-danger' : 'text-bg-secondary' }} ms-1">{{ $allCount }}</span>
                        </a>
                    </li>
                </ul>

                <!-- Filter & Search Form -->
                <form action="{{ route('procurement.approvals.po') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    
                    <div class="form-group mb-0">
                        <select name="vendor_id" class="form-select form-select-sm fs-8" onchange="this.form.submit()">
                            <option value="ALL">-- Semua Vendor --</option>
                            @foreach($vendors as $vnd)
                                <option value="{{ $vnd->id }}" {{ $vendorId == $vnd->id ? 'selected' : '' }}>
                                    {{ $vnd->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <select name="warehouse_id" class="form-select form-select-sm fs-8" onchange="this.form.submit()">
                            <option value="ALL">-- Semua Gudang --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="input-group input-group-sm" style="max-width: 230px;">
                        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm fs-8" placeholder="Cari No. PO / Vendor...">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-search"></i></button>
                    </div>

                    @if($search || ($vendorId && $vendorId !== 'ALL') || ($warehouseId && $warehouseId !== 'ALL'))
                        <a href="{{ route('procurement.approvals.po', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>

        <!-- Table Body -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9">
                        <tr>
                            <th class="ps-3 py-3" style="width: 140px;">No. Dokumen PO</th>
                            <th class="py-3">Vendor Rekanan</th>
                            <th class="py-3">Gudang Tujuan</th>
                            <th class="py-3">Tanggal & Target Kirim</th>
                            <th class="py-3 text-center" style="width: 90px;">Item PO</th>
                            <th class="py-3 text-end" style="width: 160px;">Total Nilai (+PPN)</th>
                            <th class="py-3 text-center" style="width: 130px;">Status</th>
                            <th class="pe-3 py-3 text-center" style="width: 160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        @forelse($pos as $po)
                            @php
                                $badgeClass = match($po->status) {
                                    'COMPLETED' => 'text-bg-success',
                                    'ISSUED' => 'text-bg-info',
                                    'PARTIAL_RECEIVED', 'VENDOR_PROCESS', 'IN_DELIVERY' => 'text-bg-primary',
                                    'WAITING_APPROVAL', 'DRAFT' => 'text-bg-warning',
                                    'REJECTED', 'CANCELLED' => 'text-bg-danger',
                                    default => 'text-bg-secondary'
                                };
                                $isPending = in_array($po->status, ['WAITING_APPROVAL', 'DRAFT']);
                                $canApprove = auth()->user()->hasRole('SUPER_ADMIN', 'PROCUREMENT_APPROVER');

                                // Lightweight serialization to prevent quote breakages in HTML attributes
                                $poJson = [
                                    'id' => $po->id,
                                    'po_number' => $po->po_number,
                                    'notes' => $po->notes,
                                    'subtotal' => (float) $po->subtotal,
                                    'tax_amount' => (float) $po->tax_amount,
                                    'total_amount' => (float) $po->total_amount,
                                    'status' => $po->status,
                                    'rejection_reason' => $po->rejection_reason,
                                    'order_date_formatted' => $po->order_date ? $po->order_date->format('d/m/Y') : '-',
                                    'delivery_date_formatted' => $po->expected_delivery_date ? $po->expected_delivery_date->format('d/m/Y') : '-',
                                    'vendor' => [
                                        'name' => $po->vendor->name ?? '-',
                                        'phone' => $po->vendor->phone ?? '-',
                                        'payment_terms' => $po->vendor->payment_terms ?? '-',
                                    ],
                                    'warehouse' => [
                                        'name' => $po->warehouse->name ?? '-',
                                        'address' => $po->warehouse->address ?? '-',
                                    ],
                                    'creator' => [
                                        'name' => $po->creator->name ?? '-',
                                    ],
                                    'approver' => $po->approver ? ['name' => $po->approver->name] : null,
                                    'items' => $po->items->map(function ($it) {
                                        return [
                                            'id' => $it->id,
                                            'item_name' => $it->item->name ?? 'Item',
                                            'sku' => $it->item->sku ?? '-',
                                            'uom' => $it->item->uom ?? '-',
                                            'pr_number' => $it->purchaseRequestItem->purchaseRequest->pr_number ?? '-',
                                            'qty_ordered' => $it->qty_ordered,
                                            'unit_price' => (float) $it->unit_price,
                                            'subtotal' => (float) $it->subtotal,
                                        ];
                                    }),
                                ];
                            @endphp
                            <tr>
                                <td class="ps-3 py-3 font-monospace fw-bold text-danger">
                                    {{ $po->po_number }}
                                    <div class="fs-9 text-secondary font-sans-serif fw-normal">Maker: {{ $po->creator->name ?? '-' }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-body">{{ $po->vendor->name }}</div>
                                    <div class="fs-9 text-secondary">Syarat: {{ $po->vendor->payment_terms ?? '-' }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-semibold text-body">{{ $po->warehouse->name }}</div>
                                    <div class="fs-9 text-secondary text-truncate" style="max-width: 200px;">{{ $po->warehouse->address ?? '-' }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="text-body">{{ $po->order_date->format('d/m/Y') }}</div>
                                    <div class="fs-9 text-secondary">
                                        Target: {{ $po->expected_delivery_date ? $po->expected_delivery_date->format('d/m/Y') : '-' }}
                                    </div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge text-bg-light border font-monospace">{{ $po->items->count() }} item</span>
                                    <div class="fs-9 text-secondary">{{ $po->items->sum('qty_ordered') }} unit</div>
                                </td>
                                <td class="py-3 text-end font-monospace fw-bold text-body">
                                    Rp {{ number_format($po->total_amount, 0, ',', '.') }}
                                    <div class="fs-9 text-secondary font-sans-serif fw-normal">DPP: Rp {{ number_format($po->subtotal, 0, ',', '.') }}</div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge {{ $badgeClass }} fs-8">
                                        {{ str_replace('_', ' ', $po->status) }}
                                    </span>
                                    @if($po->approver)
                                        <div class="fs-9 text-secondary mt-0.5">Oleh: {{ $po->approver->name }}</div>
                                    @endif
                                </td>
                                <td class="pe-3 py-3 text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- View Detail Button -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary px-2 py-1" 
                                                title="Lihat Rincian & Traceability PR"
                                                @click="openViewModal({{ Js::from($poJson) }})">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        @if($isPending && $canApprove)
                                            <!-- Approve Button (Opens Approval Dialog) -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-success px-2 py-1" 
                                                    title="Setujui & Terbitkan PO (Dialog)"
                                                    @click="openApproveModal({{ Js::from($poJson) }})">
                                                <i class="bi bi-check2"></i>
                                            </button>

                                            <!-- Reject Button (Opens Reject Dialog) -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger px-2 py-1" 
                                                    title="Tolak PO (Dialog)"
                                                    @click="openRejectModal({{ Js::from($poJson) }})">
                                                <i class="bi bi-x-lg"></i>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-secondary">
                                    <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary-emphasis"></i>
                                    <p class="mb-0 fw-semibold">Tidak ada Purchase Order yang sesuai dengan kriteria filter.</p>
                                    <p class="fs-9 text-secondary mb-0">Silakan ubah filter vendor/gudang atau pilih tab status lain.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <x-pagination-footer :paginator="$pos" />
    </div>

    <!-- ==================== 1. DETAIL MODAL (Alpine.js) ==================== -->
    <div x-show="viewModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-3xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-2.5 px-3">
                <h6 class="modal-title fw-bold mb-0">
                    Detail Purchase Order: <span class="font-monospace" x-text="viewPo?.po_number"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" @click="viewModal = false" aria-label="Close"></button>
            </div>

            <div class="card-body p-3 fs-8 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <!-- PO Overview -->
                <div class="row g-2 p-2.5 bg-body-tertiary rounded-3 border mb-2">
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Vendor Rekanan:</span>
                        <strong class="text-body" x-text="viewPo?.vendor?.name"></strong>
                        <div class="fs-9 text-secondary" x-text="(viewPo?.vendor?.phone || '-') + ' • ' + (viewPo?.vendor?.payment_terms || '-')"></div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Gudang Tujuan Penerimaan:</span>
                        <strong class="text-body" x-text="viewPo?.warehouse?.name"></strong>
                        <div class="fs-9 text-secondary" x-text="viewPo?.warehouse?.address || '-'"></div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Tanggal Pesan & Target:</span>
                        <span class="text-body" x-text="(viewPo?.order_date_formatted || '-') + ' (Target Kirim: ' + (viewPo?.delivery_date_formatted || '-') + ')'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Status & Maker:</span>
                        <span class="badge text-bg-primary" x-text="viewPo?.status"></span>
                        <span class="fs-9 text-secondary ms-1">oleh <span x-text="viewPo?.creator?.name || '-'"></span></span>
                    </div>
                    <template x-if="viewPo?.notes">
                        <div class="col-12 mt-2 pt-2 border-top">
                            <span class="text-secondary fs-9 d-block">Catatan Pengadaan / Konsolidasi:</span>
                            <p class="mb-0 text-body" x-text="viewPo.notes"></p>
                        </div>
                    </template>
                    <template x-if="viewPo?.rejection_reason">
                        <div class="col-12 mt-2 pt-2 border-top text-danger">
                            <span class="fw-bold fs-9 d-block"><i class="bi bi-exclamation-octagon me-1"></i> Alasan Penolakan:</span>
                            <p class="mb-0" x-text="viewPo.rejection_reason"></p>
                        </div>
                    </template>
                </div>

                <!-- Items Table -->
                <h6 class="fw-bold fs-8 text-secondary text-uppercase mb-1">Rincian Barang & Traceability PR</h6>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm table-striped mb-0 fs-8">
                        <thead class="table-light fs-9">
                            <tr>
                                <th>Item & SKU</th>
                                <th>Referensi PR</th>
                                <th class="text-center">Qty Dipesan</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="it in viewPo?.items || []" :key="it.id">
                                <tr>
                                    <td>
                                        <div class="fw-bold" x-text="it.item_name"></div>
                                        <div class="fs-9 text-secondary font-monospace" x-text="(it.sku || '-') + ' • Satuan: ' + (it.uom || '-')"></div>
                                    </td>
                                    <td class="font-monospace text-primary fw-semibold" x-text="it.pr_number"></td>
                                    <td class="text-center font-monospace fw-bold" x-text="it.qty_ordered"></td>
                                    <td class="text-end font-monospace" x-text="formatRupiah(it.unit_price)"></td>
                                    <td class="text-end font-monospace fw-bold" x-text="formatRupiah(it.subtotal)"></td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="table-light fw-bold font-monospace fs-8">
                            <tr>
                                <td colspan="4" class="text-end">DPP Subtotal:</td>
                                <td class="text-end text-body" x-text="formatRupiah(viewPo?.subtotal)"></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end">PPN (11%):</td>
                                <td class="text-end text-secondary" x-text="formatRupiah(viewPo?.tax_amount)"></td>
                            </tr>
                            <tr class="table-active text-danger fs-7">
                                <td colspan="4" class="text-end">Total PO:</td>
                                <td class="text-end fw-bold" x-text="formatRupiah(viewPo?.total_amount)"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-between align-items-center py-2.5 px-3 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="viewModal = false">Tutup</button>
                @if(auth()->user()->hasRole('SUPER_ADMIN', 'PROCUREMENT_APPROVER'))
                    <template x-if="viewPo && ['WAITING_APPROVAL', 'DRAFT'].includes(viewPo.status)">
                        <div class="d-inline-flex gap-2">
                            <button type="button" class="btn btn-danger btn-sm px-3" 
                                    @click="let target = viewPo; viewModal = false; openRejectModal(target)">
                                <i class="bi bi-x-circle me-1"></i> Tolak PO
                            </button>
                            <button type="button" class="btn btn-success btn-sm px-3" 
                                    @click="let target = viewPo; viewModal = false; openApproveModal(target)">
                                <i class="bi bi-check2-circle me-1"></i> Setujui & Terbitkan PO
                            </button>
                        </div>
                    </template>
                @endif
            </div>
        </div>
    </div>

    <!-- ==================== 2. APPROVAL CONFIRMATION DIALOG (Alpine.js) ==================== -->
    <div x-show="approveModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="approveModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <form :action="'/procurement/po/' + (approvePo?.id || '') + '/approve'" method="POST">
                @csrf
                <!-- Header -->
                <div class="card-header bg-success text-white d-flex align-items-center justify-content-between py-2.5 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle fs-5"></i>
                        <h6 class="modal-title fw-bold mb-0">Konfirmasi Persetujuan PO</h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white" @click="approveModal = false" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="card-body p-3.5 fs-8 space-y-3">
                    <p class="text-secondary mb-2">
                        Apakah Anda yakin ingin menyetujui dan menerbitkan Purchase Order berikut?
                    </p>

                    <div class="p-3 rounded-3 bg-body-tertiary border space-y-1.5">
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Nomor PO:</span>
                            <strong class="font-monospace text-danger" x-text="approvePo?.po_number"></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Vendor Rekanan:</span>
                            <span class="fw-bold text-body" x-text="approvePo?.vendor?.name"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Gudang Tujuan:</span>
                            <span class="text-body" x-text="approvePo?.warehouse?.name"></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1.5 mt-1.5">
                            <span class="text-secondary">Total PO (+PPN):</span>
                            <strong class="font-monospace text-success fs-7" x-text="formatRupiah(approvePo?.total_amount)"></strong>
                        </div>
                    </div>

                    <div class="alert alert-success-subtle border border-success-subtle py-2 px-3 mb-0 fs-9 text-success-emphasis rounded-3">
                        <i class="bi bi-info-circle me-1"></i> Setelah disetujui, status PO berubah menjadi <strong>ISSUED</strong> dan siap untuk proses pengiriman serta penerimaan barang vendor (GRN).
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-3 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="approveModal = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-3">
                        <i class="bi bi-check2-all me-1"></i> Ya, Setujui & Terbitkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== 3. REJECT DIALOG (Alpine.js) ==================== -->
    <div x-show="rejectModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="rejectModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <form :action="'/procurement/po/' + (rejectPo?.id || '') + '/reject'" method="POST">
                @csrf
                <!-- Header -->
                <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-2.5 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-octagon fs-5"></i>
                        <h6 class="modal-title fw-bold mb-0">Konfirmasi Penolakan PO</h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white" @click="rejectModal = false" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="card-body p-3.5 fs-8 space-y-3">
                    <p class="text-secondary mb-2">
                        Anda akan menolak penerbitan Purchase Order <strong class="text-danger font-monospace" x-text="rejectPo?.po_number"></strong> untuk vendor <strong class="text-body" x-text="rejectPo?.vendor?.name"></strong>.
                    </p>

                    <div class="mb-2">
                        <label class="form-label fw-bold text-body fs-8 mb-1">
                            Alasan Penolakan <span class="text-danger">*</span>:
                        </label>
                        <textarea name="rejection_reason" 
                                  x-model="rejectionReason" 
                                  class="form-control form-control-sm fs-8" 
                                  rows="3" 
                                  placeholder="Tuliskan catatan atau alasan penolakan PO..."
                                  required></textarea>
                    </div>

                    <div class="alert alert-warning py-1.5 px-2 mb-0 fs-9 rounded-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> <strong>Sistem Otomatisasi:</strong> Alokasi kuantitas item PR yang ada pada PO ini akan dikembalikan secara utuh ke Approved PR Pool.
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-3 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="rejectModal = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold px-3">
                        <i class="bi bi-x-octagon me-1"></i> Konfirmasi Tolak PO
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
