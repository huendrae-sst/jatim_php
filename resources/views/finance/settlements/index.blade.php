@extends('layouts.app')
@section('title', 'Persetujuan & Settlement Finansial Antar-Unit')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Keuangan</li>
    <li class="breadcrumb-item active" aria-current="page">Settlement Antar-Unit</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    // View Detail Modal State
    viewModal: false,
    viewSettlement: null,
    openViewModal(settlement) {
        this.viewSettlement = settlement;
        this.viewModal = true;
    },

    // Approve & Post Modal State (Dialog Konfirmasi)
    approveModal: false,
    approveSettlement: null,
    openApproveModal(settlement) {
        this.approveSettlement = settlement;
        this.approveModal = true;
    },

    formatRupiah(amount) {
        if (!amount && amount !== 0) return 'Rp 0';
        return 'Rp ' + Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
}">

    <!-- AdminLTE 4 Info-Boxes -->
    <div class="row g-3">
        <!-- Box 1: Menunggu Approval -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Menunggu Approval</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $waitingApprovalCount > 0 ? 'text-warning-emphasis' : 'text-body-emphasis' }}">{{ number_format($waitingApprovalCount) }} Draft</span>
                    <span class="fs-9 text-secondary">Verifikasi & posting finance</span>
                </div>
            </div>
        </div>

        <!-- Box 2: Telah Diposting -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Telah Diposting</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($postedCount) }} Jurnal</span>
                    <span class="fs-9 text-secondary">Realisasi anggaran selesai</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Perlu Settlement -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-receipt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Perlu Settlement</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $unsettledCount > 0 ? 'text-info-emphasis' : 'text-body-emphasis' }}">{{ number_format($unsettledCount) }} Order</span>
                    <span class="fs-9 text-secondary">Order diterima belum disettlement</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Nilai Menunggu Approval -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Nilai Menunggu Approval</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">Rp {{ number_format($waitingAmount, 0, ',', '.') }}</span>
                    <span class="fs-9 text-secondary">Total akumulasi: Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Waiting for Settlement Draft Generation -->
    @if($unsettledOrders->count() > 0)
        <div class="card card-outline card-warning shadow-xs mb-0">
            <div class="card-header border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                <h6 class="modal-title fw-bold mb-0 text-warning-emphasis fs-8">
                    Order Diterima Menunggu Pembuatan Draft Settlement
                </h6>
                <span class="badge text-bg-warning font-monospace">{{ $unsettledCount }} Order</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-8">
                        <thead class="table-light text-secondary text-uppercase fs-9">
                            <tr>
                                <th class="ps-3 py-2.5" style="width: 170px;">No. Order</th>
                                <th class="py-2.5">Unit Pembebanan</th>
                                <th class="py-2.5" style="width: 170px;">Tanggal Diterima</th>
                                <th class="py-2.5 text-end" style="width: 180px;">Total Nilai Barang</th>
                                <th class="pe-3 py-2.5 text-center" style="width: 220px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            @foreach($unsettledOrders as $ord)
                                <tr>
                                    <td class="ps-3 py-2.5 font-monospace fw-bold text-danger">
                                        {{ $ord->order_number }}
                                    </td>
                                    <td class="py-2.5">
                                        <div class="fw-bold text-body">{{ $ord->requestingOrganization->name }}</div>
                                        <div class="fs-9 text-secondary font-monospace">{{ $ord->requestingOrganization->cost_center_code ?: 'CC-BRANCH' }}</div>
                                    </td>
                                    <td class="py-2.5 text-secondary">
                                        {{ $ord->receivings->first()?->created_at?->format('d/m/Y H:i') ?? $ord->updated_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="py-2.5 text-end font-monospace fw-bold text-body">
                                        Rp {{ number_format($ord->total_estimated_value, 0, ',', '.') }}
                                    </td>
                                    <td class="pe-3 py-2.5 text-center">
                                        <form action="{{ route('finance.settlements.create', $ord->id) }}" method="POST" class="m-0 d-inline-block">
                                            @csrf
                                            <button type="submit" class="btn btn-warning btn-sm text-dark fw-bold px-3 py-1">
                                                <i class="bi bi-calculator me-1"></i> Buat Draft Settlement
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Table Card -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <!-- Status Tabs & Filter Toolbar -->
        <div class="card-header border-bottom p-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-stretch align-items-lg-center gap-3">
                <!-- Status Filter Pills -->
                <ul class="nav nav-pills nav-pills-scroll flex-nowrap fs-7">
                    <li class="nav-item">
                        <a href="{{ route('finance.settlements.index', array_merge(request()->except('page'), ['tab' => 'waiting_approval'])) }}" 
                           class="nav-link {{ $tab === 'waiting_approval' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-hourglass-split me-1"></i> Menunggu Approval
                            <span class="badge {{ $tab === 'waiting_approval' ? 'bg-white text-danger' : 'text-bg-warning' }} ms-1">{{ $waitingApprovalCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('finance.settlements.index', array_merge(request()->except('page'), ['tab' => 'posted'])) }}" 
                           class="nav-link {{ $tab === 'posted' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-check2-all me-1"></i> Telah Diposting
                            <span class="badge {{ $tab === 'posted' ? 'bg-white text-danger' : 'text-bg-success' }} ms-1">{{ $postedCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('finance.settlements.index', array_merge(request()->except('page'), ['tab' => 'all'])) }}" 
                           class="nav-link {{ $tab === 'all' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-collection me-1"></i> Semua
                            <span class="badge {{ $tab === 'all' ? 'bg-white text-danger' : 'text-bg-secondary' }} ms-1">{{ $allCount }}</span>
                        </a>
                    </li>
                </ul>

                <!-- Filter & Search Form -->
                <form action="{{ route('finance.settlements.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    
                    @if(!auth()->user()->isBranchUser() || !auth()->user()->organization_id)
                        <div class="form-group mb-0">
                            <select name="organization_id" class="form-select form-select-sm fs-8" onchange="this.form.submit()">
                                <option value="ALL">-- Semua Unit Kerja --</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ $organizationId == $org->id ? 'selected' : '' }}>
                                        {{ $org->name }} ({{ $org->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="input-group input-group-sm" style="max-width: 260px;">
                        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm fs-8" placeholder="Cari No. Settlement / Order...">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-search"></i></button>
                    </div>

                    @if($search || ($organizationId && $organizationId !== 'ALL'))
                        <a href="{{ route('finance.settlements.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
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
                            <th class="ps-3 py-3" style="width: 170px;">No. Settlement</th>
                            <th class="py-3" style="width: 150px;">No. Order Referensi</th>
                            <th class="py-3">Unit Debit (Pembebanan)</th>
                            <th class="py-3">Unit Kredit (Penyedia)</th>
                            <th class="py-3 text-end" style="width: 130px;">Nilai Barang</th>
                            <th class="py-3 text-end" style="width: 110px;">Ongkir</th>
                            <th class="py-3 text-end" style="width: 150px;">Total Settlement</th>
                            <th class="py-3 text-center" style="width: 130px;">Status</th>
                            <th class="pe-3 py-3 text-center" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        @forelse($settlements as $set)
                            @php
                                $badgeClass = match($set->status) {
                                    'POSTED' => 'text-bg-success',
                                    'WAITING_APPROVAL' => 'text-bg-warning',
                                    default => 'text-bg-secondary'
                                };
                                $isWaiting = $set->status === 'WAITING_APPROVAL';
                                $canApprove = auth()->user()->hasRole('SUPER_ADMIN', 'FINANCE_APPROVER');

                                // Lightweight serialization for Alpine.js modal
                                $settlementJson = [
                                    'id' => $set->id,
                                    'settlement_number' => $set->settlement_number,
                                    'order_number' => $set->order->order_number ?? '-',
                                    'debit_org_name' => $set->debitOrganization->name ?? '-',
                                    'credit_org_name' => $set->creditOrganization->name ?? '-',
                                    'debit_cost_center' => $set->debit_cost_center ?? '-',
                                    'credit_cost_center' => $set->credit_cost_center ?? '-',
                                    'item_amount' => (float) $set->item_amount,
                                    'shipping_amount' => (float) $set->shipping_amount,
                                    'total_amount' => (float) $set->total_amount,
                                    'status' => $set->status,
                                    'created_at_formatted' => $set->created_at->format('d/m/Y H:i'),
                                    'posted_at_formatted' => $set->posted_at?->format('d/m/Y H:i'),
                                    'creator_name' => $set->creator->name ?? '-',
                                    'approver_name' => $set->approver->name ?? null,
                                    'items' => $set->order?->items?->map(function ($it) {
                                        return [
                                            'name' => $it->item->name ?? 'Item',
                                            'sku' => $it->item->sku ?? '-',
                                            'uom' => $it->item->uom ?? '-',
                                            'qty_received' => $it->qty_received,
                                            'unit_price' => (float) $it->unit_price_ref,
                                            'subtotal' => (float) ($it->qty_received * $it->unit_price_ref),
                                        ];
                                    }) ?? [],
                                ];
                            @endphp
                            <tr>
                                <td class="ps-3 py-3 font-monospace fw-bold text-danger">
                                    {{ $set->settlement_number }}
                                    <div class="fs-9 text-secondary font-sans-serif fw-normal">{{ $set->created_at->format('d/m/Y H:i') }}</div>
                                </td>
                                <td class="py-3 font-monospace fw-semibold text-body">
                                    {{ $set->order->order_number ?? '-' }}
                                    @if($set->order)
                                        <div class="fs-9 text-secondary font-sans-serif fw-normal">{{ $set->order->created_at->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-body">{{ $set->debitOrganization->name }}</div>
                                    <div class="fs-9 text-secondary font-monospace">{{ $set->debit_cost_center }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-body">{{ $set->creditOrganization->name }}</div>
                                    <div class="fs-9 text-secondary font-monospace">{{ $set->credit_cost_center }}</div>
                                </td>
                                <td class="py-3 text-end font-monospace text-secondary">
                                    Rp {{ number_format($set->item_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end font-monospace text-secondary">
                                    Rp {{ number_format($set->shipping_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end font-monospace fw-bold text-danger">
                                    Rp {{ number_format($set->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge {{ $badgeClass }} fs-8">
                                        {{ str_replace('_', ' ', $set->status) }}
                                    </span>
                                    @if($set->approver)
                                        <div class="fs-9 text-secondary mt-0.5">Oleh: {{ $set->approver->name }}</div>
                                    @endif
                                </td>
                                <td class="pe-3 py-3 text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- View Detail Button -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary px-2 py-1" 
                                                title="Lihat Detail Settlement"
                                                @click="openViewModal({{ Js::from($settlementJson) }})">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        @if($isWaiting && $canApprove)
                                            <!-- Approve & Post Button -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-success px-2 py-1" 
                                                    title="Setujui & Posting Settlement"
                                                    @click="openApproveModal({{ Js::from($settlementJson) }})">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-secondary">
                                    <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-secondary-emphasis"></i>
                                    <p class="mb-0 fw-semibold">Tidak ada data Settlement yang sesuai dengan kriteria filter.</p>
                                    <p class="fs-9 text-secondary mb-0">Silakan ubah kata kunci pencarian atau pilih tab status lain.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <x-pagination-footer :paginator="$settlements" />
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
                    Detail Settlement Antar-Unit: <span class="font-monospace" x-text="viewSettlement?.settlement_number"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" @click="viewModal = false" aria-label="Close"></button>
            </div>

            <div class="card-body p-3 fs-8 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <!-- Settlement Overview Info -->
                <div class="row g-2 p-2.5 bg-body-tertiary rounded-3 border mb-2">
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Unit Debit (Pembebanan):</span>
                        <strong class="text-body" x-text="viewSettlement?.debit_org_name"></strong>
                        <div class="fs-9 text-secondary font-monospace" x-text="'Cost Center: ' + (viewSettlement?.debit_cost_center || '-')"></div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Unit Kredit (Penyedia):</span>
                        <strong class="text-body" x-text="viewSettlement?.credit_org_name"></strong>
                        <div class="fs-9 text-secondary font-monospace" x-text="'Cost Center: ' + (viewSettlement?.credit_cost_center || '-')"></div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Order Referensi:</span>
                        <strong class="font-monospace text-danger" x-text="viewSettlement?.order_number"></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Status Jurnal:</span>
                        <span class="badge" 
                              :class="viewSettlement?.status === 'POSTED' ? 'text-bg-success' : 'text-bg-warning'" 
                              x-text="viewSettlement?.status?.replace('_', ' ')"></span>
                        <template x-if="viewSettlement?.approver_name">
                            <span class="fs-9 text-secondary ms-1">oleh <span x-text="viewSettlement.approver_name"></span></span>
                        </template>
                    </div>
                    <div class="col-sm-6 mt-2 pt-2 border-top">
                        <span class="text-secondary fs-9 d-block">Pembuat Draft:</span>
                        <span class="text-body" x-text="(viewSettlement?.creator_name || '-') + ' • ' + (viewSettlement?.created_at_formatted || '-')"></span>
                    </div>
                    <div class="col-sm-6 mt-2 pt-2 border-top">
                        <span class="text-secondary fs-9 d-block">Tanggal Diposting:</span>
                        <span class="text-body" x-text="viewSettlement?.posted_at_formatted || 'Belum diposting'"></span>
                    </div>
                </div>

                <!-- Financial Breakdown -->
                <h6 class="fw-bold fs-8 text-secondary text-uppercase mb-1">Rincian Finansial Settlement</h6>
                <div class="table-responsive border rounded-3 mb-2">
                    <table class="table table-sm mb-0 fs-8">
                        <tbody>
                            <tr>
                                <td class="ps-3 py-2 text-secondary">Subtotal Nilai Barang Diterima</td>
                                <td class="pe-3 py-2 text-end font-monospace fw-bold text-body" x-text="formatRupiah(viewSettlement?.item_amount)"></td>
                            </tr>
                            <tr>
                                <td class="ps-3 py-2 text-secondary">Biaya Ekspedisi / Ongkos Kirim</td>
                                <td class="pe-3 py-2 text-end font-monospace fw-bold text-body" x-text="formatRupiah(viewSettlement?.shipping_amount)"></td>
                            </tr>
                            <tr class="table-light border-top border-2">
                                <td class="ps-3 py-2 fw-bold text-body">Total Nilai Settlement (Beban Anggaran)</td>
                                <td class="pe-3 py-2 text-end font-monospace fw-bold text-danger fs-7" x-text="formatRupiah(viewSettlement?.total_amount)"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Items Breakdown -->
                <template x-if="viewSettlement?.items && viewSettlement.items.length > 0">
                    <div>
                        <h6 class="fw-bold fs-8 text-secondary text-uppercase mb-1">Daftar Barang yang Diterima</h6>
                        <div class="table-responsive border rounded-3">
                            <table class="table table-sm table-striped mb-0 fs-8">
                                <thead class="table-light fs-9">
                                    <tr>
                                        <th class="ps-3">Nama Barang & SKU</th>
                                        <th class="text-center" style="width: 110px;">Qty Diterima</th>
                                        <th class="text-end" style="width: 140px;">Harga Satuan</th>
                                        <th class="pe-3 text-end" style="width: 150px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(it, idx) in viewSettlement.items" :key="idx">
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold" x-text="it.name"></div>
                                                <div class="fs-9 text-secondary font-monospace" x-text="(it.sku || '-') + ' • Satuan: ' + (it.uom || '-')"></div>
                                            </td>
                                            <td class="text-center font-monospace fw-bold text-success" x-text="it.qty_received"></td>
                                            <td class="text-end font-monospace" x-text="formatRupiah(it.unit_price)"></td>
                                            <td class="pe-3 text-end font-monospace fw-bold text-body" x-text="formatRupiah(it.subtotal)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-between align-items-center py-2.5 px-3 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="viewModal = false">Tutup</button>
                @if(auth()->user()->hasRole('SUPER_ADMIN', 'FINANCE_APPROVER'))
                    <template x-if="viewSettlement && viewSettlement.status === 'WAITING_APPROVAL'">
                        <button type="button" class="btn btn-success btn-sm px-3" 
                                @click="let target = viewSettlement; viewModal = false; openApproveModal(target)">
                            <i class="bi bi-check2-circle me-1"></i> Setujui & Posting Settlement
                        </button>
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
            
            <form :action="'/finance/settlements/' + (approveSettlement?.id || '') + '/approve-post'" method="POST">
                @csrf
                <!-- Header -->
                <div class="card-header bg-success text-white d-flex align-items-center justify-content-between py-2.5 px-3">
                    <h6 class="modal-title fw-bold mb-0">Konfirmasi Persetujuan & Posting Settlement</h6>
                    <button type="button" class="btn-close btn-close-white" @click="approveModal = false" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="card-body p-3.5 fs-8 space-y-3">
                    <p class="text-secondary mb-2">
                        Apakah Anda yakin ingin menyetujui dan memposting jurnal settlement antar-unit berikut ke buku besar realisasi anggaran?
                    </p>

                    <div class="p-3 rounded-3 bg-body-tertiary border space-y-1.5">
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Nomor Settlement:</span>
                            <strong class="font-monospace text-danger" x-text="approveSettlement?.settlement_number"></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Nomor Order:</span>
                            <span class="font-monospace fw-bold text-body" x-text="approveSettlement?.order_number"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Unit Pembebanan (Debit):</span>
                            <span class="fw-bold text-body" x-text="approveSettlement?.debit_org_name"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Unit Penyedia (Kredit):</span>
                            <span class="fw-bold text-body" x-text="approveSettlement?.credit_org_name"></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1.5 mt-1.5">
                            <span class="text-secondary">Total Nilai Settlement:</span>
                            <strong class="font-monospace text-success fs-7" x-text="formatRupiah(approveSettlement?.total_amount)"></strong>
                        </div>
                    </div>

                    <div class="alert alert-success-subtle border border-success-subtle py-2 px-3 mb-0 fs-9 text-success-emphasis rounded-3">
                        <i class="bi bi-info-circle me-1"></i> Setelah disetujui, komitmen anggaran unit pembebanan otomatis dikonversi menjadi realisasi beban anggaran dan siklus order ditutup penuh (COMPLETED).
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-3 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="approveModal = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-3">
                        <i class="bi bi-check2-all me-1"></i> Ya, Setujui & Posting
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
