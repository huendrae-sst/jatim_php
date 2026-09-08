@extends('layouts.app')
@section('title', 'Persetujuan Purchase Request (PR)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('procurement.pr.index') }}" class="text-decoration-none text-danger">Pengadaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Persetujuan PR</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    // View Modal State
    viewModal: false,
    viewPr: null,
    openViewModal(pr) {
        this.viewPr = pr;
        this.viewModal = true;
    },

    // Approve Modal State (Dialog Konfirmasi)
    approveModal: false,
    approvePr: null,
    openApproveModal(pr) {
        this.approvePr = pr;
        this.approveModal = true;
    },

    // Reject Modal State (Dialog Penolakan)
    rejectModal: false,
    rejectPr: null,
    rejectionReason: '',
    openRejectModal(pr) {
        this.rejectPr = pr;
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
        <!-- Box 1: Menunggu Persetujuan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Menunggu Persetujuan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $pendingCount > 0 ? 'text-warning-emphasis' : 'text-body-emphasis' }}">{{ number_format($pendingCount) }} PR</span>
                    <span class="fs-9 text-secondary">Pengajuan butuh review maker/approver</span>
                </div>
            </div>
        </div>

        <!-- Box 2: Disetujui -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Telah Disetujui</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($approvedCount) }} PR</span>
                    <span class="fs-9 text-secondary">Masuk ke Approved PR Pool</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Ditolak -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-x-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Pengajuan Ditolak</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-danger">{{ number_format($rejectedCount) }} PR</span>
                    <span class="fs-9 text-secondary">Pengajuan tidak disetujui</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Total Nilai Pending -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Nilai Pengajuan Menunggu</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">Rp {{ number_format($pendingValue, 0, ',', '.') }}</span>
                    <span class="fs-9 text-secondary">Total estimasi biaya antrean pending</span>
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
                        <a href="{{ route('procurement.approvals.pr', array_merge(request()->except('page'), ['tab' => 'pending'])) }}" 
                           class="nav-link {{ $tab === 'pending' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-hourglass-split me-1"></i> Menunggu
                            <span class="badge {{ $tab === 'pending' ? 'bg-white text-danger' : 'text-bg-warning' }} ms-1">{{ $pendingCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('procurement.approvals.pr', array_merge(request()->except('page'), ['tab' => 'approved'])) }}" 
                           class="nav-link {{ $tab === 'approved' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-check2-all me-1"></i> Disetujui
                            <span class="badge {{ $tab === 'approved' ? 'bg-white text-danger' : 'text-bg-success' }} ms-1">{{ $approvedCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('procurement.approvals.pr', array_merge(request()->except('page'), ['tab' => 'rejected'])) }}" 
                           class="nav-link {{ $tab === 'rejected' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-x-octagon me-1"></i> Ditolak
                            <span class="badge {{ $tab === 'rejected' ? 'bg-white text-danger' : 'text-bg-danger' }} ms-1">{{ $rejectedCount }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('procurement.approvals.pr', array_merge(request()->except('page'), ['tab' => 'all'])) }}" 
                           class="nav-link {{ $tab === 'all' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                            <i class="bi bi-collection me-1"></i> Semua
                            <span class="badge {{ $tab === 'all' ? 'bg-white text-danger' : 'text-bg-secondary' }} ms-1">{{ $allCount }}</span>
                        </a>
                    </li>
                </ul>

                <!-- Filter & Search Form -->
                <form action="{{ route('procurement.approvals.pr') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 m-0">
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

                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm fs-8" placeholder="Cari No. PR / Pemohon...">
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-search"></i></button>
                    </div>

                    @if($search || ($organizationId && $organizationId !== 'ALL'))
                        <a href="{{ route('procurement.approvals.pr', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
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
                            <th class="ps-3 py-3" style="width: 140px;">No. Dokumen PR</th>
                            <th class="py-3">Unit Pemohon & Maker</th>
                            <th class="py-3">Tujuan Pengadaan</th>
                            <th class="py-3 text-center" style="width: 100px;">Item Diajukan</th>
                            <th class="py-3 text-end" style="width: 160px;">Estimasi Biaya</th>
                            <th class="py-3 text-center" style="width: 130px;">Status</th>
                            <th class="pe-3 py-3 text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        @forelse($prs as $pr)
                            @php
                                $badgeClass = match($pr->status) {
                                    'APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED' => 'text-bg-success',
                                    'SUBMITTED', 'WAITING_APPROVAL' => 'text-bg-warning',
                                    'REJECTED' => 'text-bg-danger',
                                    default => 'text-bg-secondary'
                                };
                                $isPending = in_array($pr->status, ['SUBMITTED', 'WAITING_APPROVAL']);
                                $canApprove = auth()->user()->hasRole('SUPER_ADMIN', 'PROCUREMENT_APPROVER');

                                // Lightweight serialization to prevent quote breakages in HTML attributes
                                $prJson = [
                                    'id' => $pr->id,
                                    'pr_number' => $pr->pr_number,
                                    'procurement_method' => $pr->procurement_method,
                                    'purpose' => $pr->purpose,
                                    'estimated_total_cost' => (float) $pr->estimated_total_cost,
                                    'status' => $pr->status,
                                    'rejection_reason' => $pr->rejection_reason,
                                    'created_at_formatted' => $pr->created_at->format('d/m/Y H:i'),
                                    'organization' => [
                                        'id' => $pr->organization->id ?? null,
                                        'name' => $pr->organization->name ?? '-',
                                        'code' => $pr->organization->code ?? '',
                                    ],
                                    'requester' => [
                                        'name' => $pr->requester->name ?? '-',
                                    ],
                                    'approver' => $pr->approver ? ['name' => $pr->approver->name] : null,
                                    'items' => $pr->items->map(function ($it) {
                                        return [
                                            'id' => $it->id,
                                            'item_name' => $it->item->name ?? 'Item',
                                            'sku' => $it->item->sku ?? '-',
                                            'uom' => $it->item->uom ?? '-',
                                            'qty_requested' => $it->qty_requested,
                                            'qty_approved' => $it->qty_approved,
                                            'estimated_unit_price' => (float) $it->estimated_unit_price,
                                            'estimated_subtotal' => (float) $it->estimated_subtotal,
                                        ];
                                    }),
                                ];
                            @endphp
                            <tr>
                                <td class="ps-3 py-3 font-monospace fw-bold text-danger">
                                    {{ $pr->pr_number }}
                                    <div class="fs-9 text-secondary font-sans-serif fw-normal">{{ $pr->created_at->format('d/m/Y H:i') }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-body">{{ $pr->organization->name }}</div>
                                    <div class="fs-9 text-secondary">Maker: {{ $pr->requester->name }} • {{ $pr->procurement_method }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="text-truncate" style="max-width: 260px;" title="{{ $pr->purpose }}">
                                        {{ $pr->purpose }}
                                    </div>
                                    @if($pr->status === 'REJECTED' && $pr->rejection_reason)
                                        <div class="fs-9 text-danger mt-0.5">
                                            <i class="bi bi-info-circle me-0.5"></i> Alasan: {{ $pr->rejection_reason }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge text-bg-light border font-monospace">{{ $pr->items->count() }} item</span>
                                    <div class="fs-9 text-secondary">{{ $pr->items->sum('qty_requested') }} unit</div>
                                </td>
                                <td class="py-3 text-end font-monospace fw-bold text-body">
                                    Rp {{ number_format($pr->estimated_total_cost, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge {{ $badgeClass }} fs-8">
                                        {{ str_replace('_', ' ', $pr->status) }}
                                    </span>
                                    @if($pr->approver)
                                        <div class="fs-9 text-secondary mt-0.5">Oleh: {{ $pr->approver->name }}</div>
                                    @endif
                                </td>
                                <td class="pe-3 py-3 text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- View Detail Button -->
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-secondary px-2 py-1" 
                                                title="Lihat Rincian Item"
                                                @click="openViewModal({{ Js::from($prJson) }})">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        @if($isPending && $canApprove)
                                            <!-- Approve Button (Opens Approval Dialog) -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-success px-2 py-1" 
                                                    title="Setujui PR (Approve Dialog)"
                                                    @click="openApproveModal({{ Js::from($prJson) }})">
                                                <i class="bi bi-check2"></i>
                                            </button>

                                            <!-- Reject Button (Opens Reject Dialog) -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger px-2 py-1" 
                                                    title="Tolak PR (Reject Dialog)"
                                                    @click="openRejectModal({{ Js::from($prJson) }})">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="bi bi-file-earmark-x fs-1 d-block mb-2 text-secondary-emphasis"></i>
                                    <p class="mb-0 fw-semibold">Tidak ada Purchase Request yang sesuai dengan kriteria filter.</p>
                                    <p class="fs-9 text-secondary mb-0">Silakan ubah kata kunci pencarian atau pilih tab status lain.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <x-pagination-footer :paginator="$prs" />
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
                    <i class="bi bi-file-earmark-text me-1.5"></i> Detail Purchase Request: <span class="font-monospace" x-text="viewPr?.pr_number"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" @click="viewModal = false" aria-label="Close"></button>
            </div>

            <div class="card-body p-3 fs-8 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <!-- PR Overview Info -->
                <div class="row g-2 p-2.5 bg-body-tertiary rounded-3 border mb-2">
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Unit Kerja Pemohon:</span>
                        <strong class="text-body" x-text="viewPr?.organization?.name"></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Maker & Tanggal:</span>
                        <span class="text-body" x-text="(viewPr?.requester?.name || '-') + ' • ' + (viewPr?.created_at_formatted || '-')"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Metode Pengadaan:</span>
                        <strong class="text-body" x-text="viewPr?.procurement_method"></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary fs-9 d-block">Status Dokumen:</span>
                        <span class="badge text-bg-primary" x-text="viewPr?.status"></span>
                        <template x-if="viewPr?.approver">
                            <span class="fs-9 text-secondary ms-1">oleh <span x-text="viewPr.approver.name"></span></span>
                        </template>
                    </div>
                    <div class="col-12 mt-2 pt-2 border-top">
                        <span class="text-secondary fs-9 d-block">Tujuan Pengadaan:</span>
                        <p class="mb-0 text-body" x-text="viewPr?.purpose"></p>
                    </div>
                    <template x-if="viewPr?.rejection_reason">
                        <div class="col-12 mt-2 pt-2 border-top text-danger">
                            <span class="fw-bold fs-9 d-block"><i class="bi bi-exclamation-octagon me-1"></i> Alasan Penolakan:</span>
                            <p class="mb-0" x-text="viewPr.rejection_reason"></p>
                        </div>
                    </template>
                </div>

                <!-- Items Table -->
                <h6 class="fw-bold fs-8 text-secondary text-uppercase mb-1">Rincian Barang / Layanan</h6>
                <div class="table-responsive border rounded-3">
                    <table class="table table-sm table-striped mb-0 fs-8">
                        <thead class="table-light fs-9">
                            <tr>
                                <th>Item & SKU</th>
                                <th class="text-center">Qty Diminta</th>
                                <th class="text-center">Qty Disetujui</th>
                                <th class="text-end">Est. Harga Satuan</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="it in viewPr?.items || []" :key="it.id">
                                <tr>
                                    <td>
                                        <div class="fw-bold" x-text="it.item_name"></div>
                                        <div class="fs-9 text-secondary font-monospace" x-text="(it.sku || '-') + ' • Satuan: ' + (it.uom || '-')"></div>
                                    </td>
                                    <td class="text-center font-monospace fw-bold" x-text="it.qty_requested"></td>
                                    <td class="text-center font-monospace text-success fw-bold" x-text="it.qty_approved"></td>
                                    <td class="text-end font-monospace" x-text="formatRupiah(it.estimated_unit_price)"></td>
                                    <td class="text-end font-monospace fw-bold" x-text="formatRupiah(it.estimated_subtotal)"></td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="table-light fw-bold font-monospace">
                            <tr>
                                <td colspan="4" class="text-end">Total Estimasi:</td>
                                <td class="text-end text-danger" x-text="formatRupiah(viewPr?.estimated_total_cost)"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-between align-items-center py-2.5 px-3 border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="viewModal = false">Tutup</button>
                @if(auth()->user()->hasRole('SUPER_ADMIN', 'PROCUREMENT_APPROVER'))
                    <template x-if="viewPr && ['SUBMITTED', 'WAITING_APPROVAL'].includes(viewPr.status)">
                        <div class="d-inline-flex gap-2">
                            <button type="button" class="btn btn-danger btn-sm px-3" 
                                    @click="let target = viewPr; viewModal = false; openRejectModal(target)">
                                <i class="bi bi-x-circle me-1"></i> Tolak PR
                            </button>
                            <button type="button" class="btn btn-success btn-sm px-3" 
                                    @click="let target = viewPr; viewModal = false; openApproveModal(target)">
                                <i class="bi bi-check2-circle me-1"></i> Setujui PR
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
            
            <form :action="'/procurement/pr/' + (approvePr?.id || '') + '/approve'" method="POST">
                @csrf
                <!-- Header -->
                <div class="card-header bg-success text-white d-flex align-items-center justify-content-between py-2.5 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle fs-5"></i>
                        <h6 class="modal-title fw-bold mb-0">Konfirmasi Persetujuan PR</h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white" @click="approveModal = false" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="card-body p-3.5 fs-8 space-y-3">
                    <p class="text-secondary mb-2">
                        Apakah Anda yakin ingin menyetujui pengajuan Purchase Request berikut?
                    </p>

                    <div class="p-3 rounded-3 bg-body-tertiary border space-y-1.5">
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Nomor PR:</span>
                            <strong class="font-monospace text-danger" x-text="approvePr?.pr_number"></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Unit Pemohon:</span>
                            <span class="fw-bold text-body" x-text="approvePr?.organization?.name"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Maker:</span>
                            <span class="text-body" x-text="approvePr?.requester?.name"></span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-1.5 mt-1.5">
                            <span class="text-secondary">Total Estimasi:</span>
                            <strong class="font-monospace text-success fs-7" x-text="formatRupiah(approvePr?.estimated_total_cost)"></strong>
                        </div>
                    </div>

                    <div class="alert alert-success-subtle border border-success-subtle py-2 px-3 mb-0 fs-9 text-success-emphasis rounded-3">
                        <i class="bi bi-info-circle me-1"></i> Setelah disetujui, item PR otomatis dialokasikan ke <strong>Approved PR Pool</strong> untuk proses konsolidasi ke Purchase Order (PO).
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-3 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="approveModal = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold px-3">
                        <i class="bi bi-check2-all me-1"></i> Ya, Setujui PR
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
            
            <form :action="'/procurement/pr/' + (rejectPr?.id || '') + '/reject'" method="POST">
                @csrf
                <!-- Header -->
                <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-2.5 px-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-octagon fs-5"></i>
                        <h6 class="modal-title fw-bold mb-0">Konfirmasi Penolakan PR</h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white" @click="rejectModal = false" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="card-body p-3.5 fs-8 space-y-3">
                    <p class="text-secondary mb-2">
                        Anda akan menolak pengajuan Purchase Request <strong class="text-danger font-monospace" x-text="rejectPr?.pr_number"></strong> milik <strong class="text-body" x-text="rejectPr?.organization?.name"></strong>.
                    </p>

                    <div class="mb-2">
                        <label class="form-label fw-bold text-body fs-8 mb-1">
                            Alasan Penolakan <span class="text-danger">*</span>:
                        </label>
                        <textarea name="rejection_reason" 
                                  x-model="rejectionReason" 
                                  class="form-control form-control-sm fs-8" 
                                  rows="3" 
                                  placeholder="Tuliskan alasan penolakan secara jelas agar pemohon dapat merevisi..."
                                  required></textarea>
                    </div>

                    <div class="alert alert-warning py-1.5 px-2 mb-0 fs-9 rounded-3">
                        <i class="bi bi-info-circle me-1"></i> Notifikasi penolakan beserta catatan ini akan otomatis dikirimkan langsung ke akun pemohon.
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-3 border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" @click="rejectModal = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold px-3">
                        <i class="bi bi-x-octagon me-1"></i> Konfirmasi Tolak PR
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
