@extends('layouts.app')
@section('title', 'Penerimaan Barang PO & QC')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Penerimaan & QC</li>
    <li class="breadcrumb-item active" aria-current="page">Penerimaan Barang PO</li>
@endsection

@section('content')
<div class="space-y-4" x-data="poReceivingManager()">

    <!-- Main Card with Tabs -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <div class="card-header p-0 pt-1 border-bottom">
            <ul class="nav nav-tabs px-3" id="poReceivingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link fw-bold {{ $tab === 'queue' ? 'active text-danger border-bottom-0' : 'text-secondary' }}" 
                       href="{{ route('receiving.po.index', array_merge(request()->except(['tab', 'pos_page', 'grn_page']), ['tab' => 'queue'])) }}">
                        <i class="bi bi-truck me-1"></i> Antrean Penerimaan PO
                        @if($pos->total() > 0 && $tab === 'queue')
                            <span class="badge bg-danger rounded-pill ms-1">{{ $pos->total() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link fw-bold {{ $tab === 'history' ? 'active text-danger border-bottom-0' : 'text-secondary' }}" 
                       href="{{ route('receiving.po.index', array_merge(request()->except(['tab', 'pos_page', 'grn_page']), ['tab' => 'history'])) }}">
                        <i class="bi bi-clock-history me-1"></i> Riwayat Dokumen Penerimaan (GRN)
                        @if($goodsReceipts->total() > 0 && $tab === 'history')
                            <span class="badge bg-secondary rounded-pill ms-1">{{ $goodsReceipts->total() }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            @if($tab === 'queue')
                <!-- TAB 1: Antrean PO -->
                <!-- Filter Bar -->
                <div class="p-3 bg-body-tertiary border-bottom">
                    <form method="GET" action="{{ route('receiving.po.index') }}" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="queue">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">

                        <!-- Vendor Filter -->
                        <div class="col-12 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-muted"><i class="bi bi-shop"></i></span>
                                <select name="vendor_id" class="form-select" onchange="this.form.submit()">
                                    <option value="ALL">Semua Vendor Rekanan</option>
                                    @foreach($vendors as $vnd)
                                        <option value="{{ $vnd->id }}" {{ ($vendorId == $vnd->id) ? 'selected' : '' }}>
                                            {{ $vnd->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Warehouse Filter -->
                        <div class="col-12 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-muted"><i class="bi bi-geo-alt"></i></span>
                                <select name="warehouse_id" class="form-select" onchange="this.form.submit()">
                                    <option value="ALL">Semua Gudang Tujuan</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ ($warehouseId == $wh->id) ? 'selected' : '' }}>
                                            {{ $wh->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="col-12 col-md-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-muted"><i class="bi bi-toggle-on"></i></span>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="ALL">Semua Status PO</option>
                                    <option value="ISSUED" {{ $status === 'ISSUED' ? 'selected' : '' }}>Diterbitkan</option>
                                    <option value="VENDOR_PROCESS" {{ $status === 'VENDOR_PROCESS' ? 'selected' : '' }}>Diproses Vendor</option>
                                    <option value="IN_DELIVERY" {{ $status === 'IN_DELIVERY' ? 'selected' : '' }}>Dalam Pengiriman</option>
                                    <option value="PARTIAL_RECEIVED" {{ $status === 'PARTIAL_RECEIVED' ? 'selected' : '' }}>Sebagian Diterima</option>
                                    <option value="COMPLETED" {{ $status === 'COMPLETED' ? 'selected' : '' }}>Selesai Diterima</option>
                                </select>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="col-12 col-md ms-md-auto">
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Cari No. PO, vendor, atau item..." value="{{ $search ?? '' }}">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-search"></i> Cari
                                </button>
                                @if(!empty($search) || (!empty($status) && $status !== 'ALL') || (!empty($vendorId) && $vendorId !== 'ALL') || (!empty($warehouseId) && $warehouseId !== 'ALL'))
                                    <a href="{{ route('receiving.po.index', ['tab' => 'queue']) }}" class="btn btn-outline-secondary" title="Reset Pencarian">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Table PO -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 fs-7">
                        <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                            <tr>
                                <th class="ps-3 py-2" style="width: 170px;">No. PO & Tanggal</th>
                                <th class="py-2" style="width: 220px;">Vendor Rekanan</th>
                                <th class="py-2" style="width: 180px;">Gudang Tujuan</th>
                                <th class="py-2">Item & Progres Penerimaan</th>
                                <th class="py-2 text-center" style="width: 140px;">Status PO</th>
                                <th class="pe-3 py-2 text-center" style="width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pos as $po)
                                @php
                                    $totalOrdered = $po->items->sum('qty_ordered');
                                    $totalReceived = $po->items->sum('qty_received');
                                    $pct = $totalOrdered > 0 ? min(100, round(($totalReceived / $totalOrdered) * 100)) : 0;
                                    $canReceive = in_array($po->status, ['ISSUED', 'VENDOR_PROCESS', 'IN_DELIVERY', 'PARTIAL_RECEIVED'], true);

                                    $poJson = [
                                        'id' => $po->id,
                                        'po_number' => $po->po_number,
                                        'order_date' => $po->order_date ? $po->order_date->format('d/m/Y') : '-',
                                        'expected_delivery_date' => $po->expected_delivery_date ? $po->expected_delivery_date->format('d/m/Y') : '-',
                                        'vendor_name' => $po->vendor->name ?? '-',
                                        'warehouse_name' => $po->warehouse->name ?? '-',
                                        'warehouse_address' => $po->warehouse->address ?? '-',
                                        'status' => $po->status,
                                        'notes' => $po->notes ?? '-',
                                        'total_amount' => 'Rp ' . number_format($po->total_amount, 0, ',', '.'),
                                        'items' => $po->items->map(fn($it) => [
                                            'id' => $it->id,
                                            'item_id' => $it->item_id,
                                            'name' => $it->item->name ?? 'Item',
                                            'sku' => $it->item->sku ?? '-',
                                            'uom' => $it->item->uom ?? 'PCS',
                                            'category' => $it->item->category->name ?? '-',
                                            'qty_ordered' => $it->qty_ordered,
                                            'qty_received' => $it->qty_received,
                                            'outstanding_qty' => $it->outstanding_qty,
                                            'qty_accepted' => $it->outstanding_qty,
                                            'qty_rejected' => 0,
                                            'notes' => '',
                                        ])->values(),
                                        'receipts' => $po->goodsReceipts->map(fn($gr) => [
                                            'grn_number' => $gr->grn_number,
                                            'receipt_date' => $gr->receipt_date ? $gr->receipt_date->format('d/m/Y') : '-',
                                            'delivery_note' => $gr->vendor_delivery_note_number,
                                            'receiver' => $gr->receiver->name ?? '-',
                                        ])->values(),
                                    ];
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <span class="font-monospace fw-bold text-slate-900 d-block">{{ $po->po_number }}</span>
                                        <small class="text-muted fs-8">{{ $po->order_date ? $po->order_date->format('d M Y') : '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-slate-800 d-block">{{ $po->vendor->name ?? '-' }}</span>
                                        <small class="text-muted fs-8">
                                            Kode: {{ $po->vendor->code ?? '-' }}
                                            @if($po->vendor && $po->vendor->lead_time_days)
                                                &bull; SLA: {{ $po->vendor->lead_time_days }} hari
                                            @endif
                                        </small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-slate-800 d-block">{{ $po->warehouse->name ?? '-' }}</span>
                                        <small class="text-muted fs-8">{{ Str::limit($po->warehouse->address ?? '', 30) }}</small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="fs-8 text-muted">
                                                <strong class="text-slate-800">{{ $totalReceived }}</strong> dari {{ $totalOrdered }} unit diterima
                                            </span>
                                            <span class="fs-8 fw-bold {{ $pct >= 100 ? 'text-success' : ($pct > 0 ? 'text-info' : 'text-secondary') }}">
                                                {{ $pct }}%
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : ($pct > 0 ? 'bg-info' : 'bg-secondary') }}" 
                                                 role="progressbar" 
                                                 style="width: {{ $pct }}%" 
                                                 aria-valuenow="{{ $pct }}" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted fs-8 mt-1 d-block">
                                            {{ $po->items->count() }} SKU barang
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $badgeClass = match($po->status) {
                                                'ISSUED' => 'text-bg-warning',
                                                'VENDOR_PROCESS', 'IN_DELIVERY' => 'text-bg-info',
                                                'PARTIAL_RECEIVED' => 'text-bg-primary',
                                                'COMPLETED' => 'text-bg-success',
                                                'REJECTED', 'CANCELLED' => 'text-bg-danger',
                                                default => 'text-bg-secondary',
                                            };
                                            $statusLabel = match($po->status) {
                                                'ISSUED' => 'Diterbitkan',
                                                'VENDOR_PROCESS' => 'Diproses Vendor',
                                                'IN_DELIVERY' => 'Dalam Pengiriman',
                                                'PARTIAL_RECEIVED' => 'Sebagian Diterima',
                                                'COMPLETED' => 'Selesai Diterima',
                                                'REJECTED' => 'Ditolak',
                                                'CANCELLED' => 'Dibatalkan',
                                                default => str_replace('_', ' ', $po->status),
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} fs-8">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="pe-3 text-center">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            @if($canReceive)
                                                <button type="button" 
                                                        class="btn-action-icon text-danger"
                                                        @click="openReceiveModal({{ json_encode($poJson) }})"
                                                        title="Proses Penerimaan Barang & QC">
                                                    <i class="bi bi-box-arrow-in-down"></i>
                                                </button>
                                            @endif
                                            <button type="button" 
                                                    class="btn-action-icon text-secondary"
                                                    @click="openViewModal({{ json_encode($poJson) }})"
                                                    title="Lihat Rincian PO">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-truck fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                        <span class="fw-semibold">Tidak ada Purchase Order yang menunggu penerimaan.</span>
                                        <p class="fs-8 text-muted mb-0">Pastikan PO telah disetujui dan diterbitkan oleh unit pengadaan.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-3 border-top">
                    <x-pagination-footer :paginator="$pos" :perPage="$perPage" />
                </div>

            @else
                <!-- TAB 2: Riwayat Dokumen GRN -->
                <!-- Filter Bar -->
                <div class="p-3 bg-body-tertiary border-bottom">
                    <form method="GET" action="{{ route('receiving.po.index') }}" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="history">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">

                        <!-- Warehouse Filter -->
                        <div class="col-12 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-muted"><i class="bi bi-geo-alt"></i></span>
                                <select name="warehouse_id" class="form-select" onchange="this.form.submit()">
                                    <option value="ALL">Semua Gudang Penerima</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ ($warehouseId == $wh->id) ? 'selected' : '' }}>
                                            {{ $wh->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="col-12 col-md ms-md-auto">
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Cari No. GRN, No. PO, Surat Jalan, atau Vendor..." value="{{ $search ?? '' }}">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-search"></i> Cari
                                </button>
                                @if(!empty($search) || (!empty($warehouseId) && $warehouseId !== 'ALL'))
                                    <a href="{{ route('receiving.po.index', ['tab' => 'history']) }}" class="btn btn-outline-secondary" title="Reset Pencarian">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Table GRN -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 fs-7">
                        <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                            <tr>
                                <th class="ps-3 py-2" style="width: 170px;">No. GRN & Tanggal</th>
                                <th class="py-2" style="width: 170px;">No. PO Referensi</th>
                                <th class="py-2" style="width: 190px;">Vendor Rekanan</th>
                                <th class="py-2" style="width: 180px;">No. Surat Jalan Vendor</th>
                                <th class="py-2" style="width: 160px;">Gudang Penerima</th>
                                <th class="py-2" style="width: 160px;">Petugas QC</th>
                                <th class="py-2 text-center" style="width: 110px;">Total Diterima</th>
                                <th class="pe-3 py-2 text-center" style="width: 80px;">Rincian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($goodsReceipts as $grn)
                                @php
                                    $totAccepted = $grn->items->sum('qty_accepted');
                                    $totRejected = $grn->items->sum('qty_rejected');

                                    $grnJson = [
                                        'id' => $grn->id,
                                        'grn_number' => $grn->grn_number,
                                        'receipt_date' => $grn->receipt_date ? $grn->receipt_date->format('d/m/Y') : '-',
                                        'po_number' => $grn->purchaseOrder->po_number ?? '-',
                                        'vendor_name' => $grn->purchaseOrder->vendor->name ?? '-',
                                        'vendor_delivery_note' => $grn->vendor_delivery_note_number,
                                        'warehouse_name' => $grn->warehouse->name ?? '-',
                                        'receiver_name' => $grn->receiver->name ?? '-',
                                        'status' => $grn->status,
                                        'notes' => $grn->notes ?? '-',
                                        'items' => $grn->items->map(fn($gi) => [
                                            'name' => $gi->item->name ?? 'Item',
                                            'sku' => $gi->item->sku ?? '-',
                                            'category' => $gi->item->category->name ?? '-',
                                            'qty_received' => $gi->qty_received,
                                            'qty_accepted' => $gi->qty_accepted,
                                            'qty_rejected' => $gi->qty_rejected,
                                            'condition_notes' => $gi->condition_notes ?? '-',
                                        ])->values(),
                                    ];
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <span class="font-monospace fw-bold text-danger d-block">{{ $grn->grn_number }}</span>
                                        <small class="text-muted fs-8">{{ $grn->receipt_date ? $grn->receipt_date->format('d M Y') : '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="font-monospace fw-semibold text-slate-800">{{ $grn->purchaseOrder->po_number ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-slate-800 d-block">{{ $grn->purchaseOrder->vendor->name ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-light border text-slate-700 font-monospace">{{ $grn->vendor_delivery_note_number }}</span>
                                    </td>
                                    <td>
                                        <span class="text-slate-800">{{ $grn->warehouse->name ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="text-slate-800 d-block">{{ $grn->receiver->name ?? '-' }}</span>
                                        <small class="text-muted fs-8">Gudang / QC</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge text-bg-success">{{ $totAccepted }} Unit</span>
                                        @if($totRejected > 0)
                                            <span class="badge text-bg-danger mt-1">{{ $totRejected }} Rusak</span>
                                        @endif
                                    </td>
                                    <td class="pe-3 text-center">
                                        <button type="button" 
                                                class="btn-action-icon text-secondary"
                                                @click="openGrnModal({{ json_encode($grnJson) }})"
                                                title="Lihat Detail GRN">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="bi bi-file-earmark-check fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                        <span class="fw-semibold">Belum ada dokumen penerimaan barang (GRN) yang dicatat.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-3 border-top">
                    <x-pagination-footer :paginator="$goodsReceipts" :perPage="$perPage" />
                </div>
            @endif
        </div>
    </div>

    <!-- MODAL 1: FORM PENERIMAAN BARANG & QC (GRN) -->
    <div x-show="receiveModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         style="display: none;"
         @keydown.escape.window="receiveModalOpen = false">
        
        <div class="bg-white rounded-3 shadow-xl max-w-4xl w-full flex flex-col max-h-[92vh] overflow-hidden" 
             @click.outside="receiveModalOpen = false">
            
            <!-- Modal Header -->
            <div class="bg-danger text-white px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-in-down fs-5"></i>
                    <div>
                        <h5 class="modal-title mb-0 fs-6 fw-bold">Penerimaan Barang Vendor (Goods Receipt Note / GRN)</h5>
                        <small class="text-white-50 fs-8">Pencatatan fisik barang masuk & posting otomatis ke Stock Ledger</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="receiveModalOpen = false" aria-label="Close"></button>
            </div>

            <!-- Modal Form Body -->
            <form :action="receiveActionUrl" method="POST" class="d-flex flex-column flex-grow-1 overflow-hidden" id="receiveGoodsForm">
                @csrf
                <div class="p-4 overflow-y-auto flex-grow-1 space-y-4">
                    
                    <!-- PO Information Card -->
                    <div class="p-3 bg-light rounded-2 border">
                        <div class="row g-2">
                            <div class="col-12 col-md-3">
                                <span class="fs-8 text-muted d-block">Nomor PO</span>
                                <span class="font-monospace fw-bold text-danger" x-text="activePo.po_number"></span>
                            </div>
                            <div class="col-12 col-md-3">
                                <span class="fs-8 text-muted d-block">Vendor Rekanan</span>
                                <span class="fw-semibold text-slate-800" x-text="activePo.vendor_name"></span>
                            </div>
                            <div class="col-12 col-md-3">
                                <span class="fs-8 text-muted d-block">Gudang Penerima</span>
                                <span class="fw-semibold text-slate-800" x-text="activePo.warehouse_name"></span>
                            </div>
                            <div class="col-12 col-md-3">
                                <span class="fs-8 text-muted d-block">Target Kirim</span>
                                <span class="fw-semibold text-slate-800" x-text="activePo.expected_delivery_date"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Receipt Header Inputs -->
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-7 fw-bold text-slate-800">
                                Nomor Surat Jalan / Delivery Note Vendor <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="vendor_delivery_note_number" 
                                   class="form-control form-control-sm font-monospace" 
                                   placeholder="Contoh: SJ/2026/09/0142" 
                                   x-model="deliveryNote" 
                                   required>
                            <small class="text-muted fs-8">Wajib diisi sesuai dokumen fisik surat jalan yang menyertai barang.</small>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-7 fw-bold text-slate-800">Catatan Pemeriksaan Gudang / QC</label>
                            <input type="text" 
                                   name="qc_notes" 
                                   class="form-control form-control-sm" 
                                   placeholder="Catatan kondisi paket, kemasan, atau kurir..." 
                                   x-model="qcNotes">
                        </div>
                    </div>

                    <!-- Items Verification Table -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fs-7 fw-bold text-slate-800 mb-0">
                                Pemeriksaan & Penerimaan Fisik Barang <span class="text-danger">*</span>
                            </label>
                            <span class="fs-8 text-muted">Periksa kuantitas fisik dan pisahkan barang cacat/rusak.</span>
                        </div>

                        <div class="table-responsive border rounded-2">
                            <table class="table table-sm table-striped align-middle mb-0 fs-7">
                                <thead class="table-light fs-8 text-uppercase">
                                    <tr>
                                        <th class="ps-3 py-2">Barang & SKU</th>
                                        <th class="py-2 text-center" style="width: 80px;">Dipesan</th>
                                        <th class="py-2 text-center" style="width: 80px;">Sisa</th>
                                        <th class="py-2 text-center" style="width: 120px;">Diterima Baik</th>
                                        <th class="py-2 text-center" style="width: 100px;">Ditolak / Rusak</th>
                                        <th class="pe-3 py-2" style="width: 160px;">Keterangan Item</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, idx) in activePo.items" :key="item.id">
                                        <tr>
                                            <td class="ps-3">
                                                <input type="hidden" :name="`items[${idx}][po_item_id]`" :value="item.id">
                                                <span class="fw-bold text-slate-800 d-block" x-text="item.name"></span>
                                                <small class="text-muted font-monospace fs-8" x-text="item.sku"></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge text-bg-light border" x-text="`${item.qty_ordered} ${item.uom}`"></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge" 
                                                      :class="item.outstanding_qty > 0 ? 'text-bg-warning' : 'text-bg-secondary'"
                                                      x-text="`${item.outstanding_qty} ${item.uom}`"></span>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       :name="`items[${idx}][qty_accepted]`" 
                                                       class="form-control form-control-sm text-center fw-bold text-success" 
                                                       x-model.number="item.qty_accepted" 
                                                       min="0" 
                                                       :max="item.outstanding_qty" 
                                                       @input="validateItemQty(item)"
                                                       required>
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       :name="`items[${idx}][qty_rejected]`" 
                                                       class="form-control form-control-sm text-center text-danger" 
                                                       x-model.number="item.qty_rejected" 
                                                       min="0" 
                                                       :max="item.outstanding_qty" 
                                                       @input="validateItemQty(item)">
                                            </td>
                                            <td class="pe-3">
                                                <input type="text" 
                                                       :name="`items[${idx}][notes]`" 
                                                       class="form-control form-control-sm fs-8" 
                                                       placeholder="Kondisi barang..." 
                                                       x-model="item.notes">
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary & Verification Notice -->
                    <div class="bg-amber-50/70 border border-amber-200 rounded-2 p-3 d-flex align-items-start gap-2 fs-8 text-amber-900">
                        <i class="bi bi-info-circle-fill text-amber-600 fs-6 mt-0.5"></i>
                        <div>
                            <strong>Konsekuensi Pembaruan Stok:</strong>
                            Kuantitas <em>Diterima Baik</em> akan otomatis dicatat sebagai mutasi <code>STOCK_IN</code> di Buku Besar Stok (Stock Ledger) gudang tujuan, dan saldo persediaan (On Hand & Available) barang terkait akan langsung bertambah.
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-between">
                    <div class="fs-7 text-muted">
                        Total Diterima: <strong class="text-success" x-text="totalAcceptedSum()"></strong> Unit &bull; 
                        Ditolak: <strong class="text-danger" x-text="totalRejectedSum()"></strong> Unit
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="receiveModalOpen = false">
                            Batal
                        </button>
                        <button type="submit" 
                                class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1"
                                :disabled="!isFormValid()">
                            <i class="bi bi-check2-circle"></i>
                            <span>Konfirmasi & Simpan GRN</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: DETAIL PURCHASE ORDER (VIEW) -->
    <div x-show="viewPoModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         style="display: none;"
         @keydown.escape.window="viewPoModalOpen = false">
        
        <div class="bg-white rounded-3 shadow-xl max-w-3xl w-full flex flex-col max-h-[90vh] overflow-hidden" 
             @click.outside="viewPoModalOpen = false">
            
            <div class="bg-secondary text-white px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-text fs-5"></i>
                    <div>
                        <h5 class="modal-title mb-0 fs-6 fw-bold">Rincian Dokumen Purchase Order</h5>
                        <small class="text-white-50 fs-8 font-monospace" x-text="viewPoData.po_number"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="viewPoModalOpen = false"></button>
            </div>

            <div class="p-4 overflow-y-auto flex-grow-1 space-y-3">
                <div class="row g-3 p-3 bg-light rounded-2 border">
                    <div class="col-12 col-sm-6">
                        <span class="fs-8 text-muted d-block">Vendor Rekanan</span>
                        <span class="fw-bold text-slate-800" x-text="viewPoData.vendor_name"></span>
                    </div>
                    <div class="col-12 col-sm-6">
                        <span class="fs-8 text-muted d-block">Gudang Tujuan</span>
                        <span class="fw-bold text-slate-800" x-text="viewPoData.warehouse_name"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="fs-8 text-muted d-block">Tanggal Order</span>
                        <span class="fw-semibold text-slate-700" x-text="viewPoData.order_date"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="fs-8 text-muted d-block">Target Kirim</span>
                        <span class="fw-semibold text-slate-700" x-text="viewPoData.expected_delivery_date"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="fs-8 text-muted d-block">Status PO</span>
                        <span class="badge text-bg-primary fs-8" x-text="viewPoData.status"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="fs-8 text-muted d-block">Total Nilai (+PPN)</span>
                        <span class="fw-bold text-danger font-monospace" x-text="viewPoData.total_amount"></span>
                    </div>
                </div>

                <div>
                    <h6 class="fw-bold text-slate-800 fs-7 mb-2">Item Barang Dipesan</h6>
                    <div class="table-responsive border rounded-2">
                        <table class="table table-sm table-striped align-middle mb-0 fs-7">
                            <thead class="table-light fs-8 text-uppercase">
                                <tr>
                                    <th class="ps-3 py-2">Barang</th>
                                    <th class="py-2 text-center">Kategori</th>
                                    <th class="py-2 text-center">Dipesan</th>
                                    <th class="py-2 text-center">Diterima</th>
                                    <th class="pe-3 py-2 text-center">Sisa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in viewPoData.items" :key="item.id">
                                    <tr>
                                        <td class="ps-3">
                                            <span class="fw-bold text-slate-800 d-block" x-text="item.name"></span>
                                            <small class="text-muted font-monospace fs-8" x-text="item.sku"></small>
                                        </td>
                                        <td class="text-center" x-text="item.category"></td>
                                        <td class="text-center fw-bold" x-text="`${item.qty_ordered} ${item.uom}`"></td>
                                        <td class="text-center text-success fw-bold" x-text="`${item.qty_received} ${item.uom}`"></td>
                                        <td class="pe-3 text-center text-danger fw-bold" x-text="`${item.outstanding_qty} ${item.uom}`"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Receipts list if any -->
                <template x-if="viewPoData.receipts && viewPoData.receipts.length > 0">
                    <div>
                        <h6 class="fw-bold text-slate-800 fs-7 mb-2">Dokumen Penerimaan Terkait (GRN)</h6>
                        <div class="list-group">
                            <template x-for="rc in viewPoData.receipts" :key="rc.grn_number">
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3">
                                    <div>
                                        <span class="font-monospace fw-bold text-danger" x-text="rc.grn_number"></span>
                                        <small class="text-muted d-block fs-8" x-text="`Surat Jalan: ${rc.delivery_note}`"></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fs-8 text-muted d-block" x-text="rc.receipt_date"></span>
                                        <small class="text-muted fs-8" x-text="`Pemeriksa: ${rc.receiver}`"></small>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-4 py-3 bg-light border-top text-end">
                <button type="button" class="btn btn-sm btn-secondary" @click="viewPoModalOpen = false">Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL 3: DETAIL DOKUMEN GRN (VIEW) -->
    <div x-show="viewGrnModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         style="display: none;"
         @keydown.escape.window="viewGrnModalOpen = false">
        
        <div class="bg-white rounded-3 shadow-xl max-w-3xl w-full flex flex-col max-h-[90vh] overflow-hidden" 
             @click.outside="viewGrnModalOpen = false">
            
            <div class="bg-danger text-white px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check fs-5"></i>
                    <div>
                        <h5 class="modal-title mb-0 fs-6 fw-bold">Rincian Dokumen Goods Receipt Note (GRN)</h5>
                        <small class="text-white-50 fs-8 font-monospace" x-text="viewGrnData.grn_number"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="viewGrnModalOpen = false"></button>
            </div>

            <div class="p-4 overflow-y-auto flex-grow-1 space-y-3">
                <div class="row g-3 p-3 bg-light rounded-2 border">
                    <div class="col-12 col-sm-4">
                        <span class="fs-8 text-muted d-block">Nomor GRN</span>
                        <span class="font-monospace fw-bold text-danger" x-text="viewGrnData.grn_number"></span>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="fs-8 text-muted d-block">Nomor PO Referensi</span>
                        <span class="font-monospace fw-semibold text-slate-800" x-text="viewGrnData.po_number"></span>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="fs-8 text-muted d-block">Tanggal Terima</span>
                        <span class="fw-semibold text-slate-700" x-text="viewGrnData.receipt_date"></span>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="fs-8 text-muted d-block">Surat Jalan Vendor</span>
                        <span class="font-monospace fw-bold text-slate-800" x-text="viewGrnData.vendor_delivery_note"></span>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="fs-8 text-muted d-block">Vendor Penyedia</span>
                        <span class="fw-semibold text-slate-800" x-text="viewGrnData.vendor_name"></span>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="fs-8 text-muted d-block">Petugas Penerima (QC)</span>
                        <span class="fw-semibold text-slate-800" x-text="viewGrnData.receiver_name"></span>
                    </div>
                </div>

                <div>
                    <h6 class="fw-bold text-slate-800 fs-7 mb-2">Item Barang Diterima</h6>
                    <div class="table-responsive border rounded-2">
                        <table class="table table-sm table-striped align-middle mb-0 fs-7">
                            <thead class="table-light fs-8 text-uppercase">
                                <tr>
                                    <th class="ps-3 py-2">Barang & SKU</th>
                                    <th class="py-2 text-center" style="width: 100px;">Diterima Baik</th>
                                    <th class="py-2 text-center" style="width: 100px;">Ditolak</th>
                                    <th class="pe-3 py-2">Keterangan / Kondisi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in viewGrnData.items" :key="item.sku">
                                    <tr>
                                        <td class="ps-3">
                                            <span class="fw-bold text-slate-800 d-block" x-text="item.name"></span>
                                            <small class="text-muted font-monospace fs-8" x-text="item.sku"></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge text-bg-success" x-text="`${item.qty_accepted} Unit`"></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge" 
                                                  :class="item.qty_rejected > 0 ? 'text-bg-danger' : 'text-bg-light border text-muted'" 
                                                  x-text="`${item.qty_rejected} Unit`"></span>
                                        </td>
                                        <td class="pe-3 text-muted fs-8" x-text="item.condition_notes || '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="px-4 py-3 bg-light border-top text-end">
                <button type="button" class="btn btn-sm btn-secondary" @click="viewGrnModalOpen = false">Tutup</button>
            </div>
        </div>
    </div>

</div>

<script>
function poReceivingManager() {
    return {
        receiveModalOpen: false,
        viewPoModalOpen: false,
        viewGrnModalOpen: false,
        activePo: { items: [] },
        viewPoData: { items: [], receipts: [] },
        viewGrnData: { items: [] },
        deliveryNote: '',
        qcNotes: '',
        receiveActionUrl: '',

        openReceiveModal(po) {
            this.activePo = JSON.parse(JSON.stringify(po));
            this.deliveryNote = '';
            this.qcNotes = '';
            this.receiveActionUrl = '{{ url("/receiving/po") }}/' + po.id + '/receive';
            this.receiveModalOpen = true;
        },

        openViewModal(po) {
            this.viewPoData = po;
            this.viewPoModalOpen = true;
        },

        openGrnModal(grn) {
            this.viewGrnData = grn;
            this.viewGrnModalOpen = true;
        },

        validateItemQty(item) {
            if (item.qty_accepted < 0) item.qty_accepted = 0;
            if (item.qty_rejected < 0) item.qty_rejected = 0;

            const maxAllowed = item.outstanding_qty;
            if (item.qty_accepted + item.qty_rejected > maxAllowed) {
                item.qty_accepted = Math.max(0, maxAllowed - item.qty_rejected);
            }
        },

        totalAcceptedSum() {
            if (!this.activePo.items) return 0;
            return this.activePo.items.reduce((sum, it) => sum + (parseInt(it.qty_accepted) || 0), 0);
        },

        totalRejectedSum() {
            if (!this.activePo.items) return 0;
            return this.activePo.items.reduce((sum, it) => sum + (parseInt(it.qty_rejected) || 0), 0);
        },

        isFormValid() {
            if (!this.deliveryNote || this.deliveryNote.trim() === '') return false;
            const total = this.totalAcceptedSum() + this.totalRejectedSum();
            return total > 0;
        }
    };
}
</script>
@endsection
