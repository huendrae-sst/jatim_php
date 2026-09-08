@extends('layouts.app')
@section('title', 'Purchase Requests (PR)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Pengadaan</li>
    <li class="breadcrumb-item active" aria-current="page">Purchase Requests</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    itemsCatalog: {{ Js::from($itemsCatalog) }},
    organizations: {{ Js::from($organizations) }},

    formatRupiah(amount) {
        if (!amount && amount !== 0) return 'Rp 0';
        return 'Rp ' + Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    },

    getBadgeClass(status) {
        switch (status) {
            case 'APPROVED': return 'text-bg-success';
            case 'FULLY_ORDERED': return 'text-bg-purple';
            case 'PARTIALLY_ORDERED': return 'text-bg-primary';
            case 'SUBMITTED': return 'text-bg-warning';
            case 'REJECTED': return 'text-bg-danger';
            default: return 'text-bg-secondary';
        }
    },

    // ==================== CREATE MODAL STATE ====================
    createModal: false,
    createRows: [],
    createTotal: 0,
    openCreateModal() {
        let first = this.itemsCatalog[0];
        let p = first ? parseFloat(first.estimated_unit_price || 0) : 0;
        this.createRows = [{
            item_id: first ? first.id : '',
            qty: 1,
            unit_price: p,
            subtotal: p,
            notes: ''
        }];
        this.recalculateCreateTotal();
        this.createModal = true;
    },
    addCreateRow() {
        let first = this.itemsCatalog[0];
        let p = first ? parseFloat(first.estimated_unit_price || 0) : 0;
        this.createRows.push({
            item_id: first ? first.id : '',
            qty: 1,
            unit_price: p,
            subtotal: p,
            notes: ''
        });
        this.recalculateCreateTotal();
    },
    removeCreateRow(index) {
        if (this.createRows.length > 1) {
            this.createRows.splice(index, 1);
            this.recalculateCreateTotal();
        }
    },
    onCreateItemChange(index) {
        let selId = this.createRows[index].item_id;
        let found = this.itemsCatalog.find(i => i.id == selId);
        let p = found ? parseFloat(found.estimated_unit_price || 0) : 0;
        this.createRows[index].unit_price = p;
        let q = parseInt(this.createRows[index].qty) || 0;
        this.createRows[index].subtotal = p * q;
        this.recalculateCreateTotal();
    },
    onCreateQtyOrPriceChange(index) {
        let q = parseInt(this.createRows[index].qty) || 0;
        let p = parseFloat(this.createRows[index].unit_price) || 0;
        this.createRows[index].subtotal = p * q;
        this.recalculateCreateTotal();
    },
    recalculateCreateTotal() {
        let total = 0;
        this.createRows.forEach(it => {
            total += (parseFloat(it.subtotal) || 0);
        });
        this.createTotal = total;
    },

    // ==================== VIEW MODAL STATE ====================
    viewModal: false,
    viewPr: null,
    openViewModal(pr) {
        this.viewPr = pr;
        this.viewModal = true;
    },

    // ==================== EDIT MODAL STATE ====================
    editModal: false,
    editPr: {
        id: null,
        pr_number: '',
        organization_id: '',
        organization_name: '',
        procurement_method: 'PENGADAAN_LANGSUNG',
        purpose: '',
        status: '',
        status_label: '',
        badge_class: '',
        estimated_total_cost: 0,
        items: [],
        is_editable: true,
        update_url: ''
    },
    editTotal: 0,
    openEditModal(pr) {
        let mappedItems = [];
        if (pr.items && pr.items.length > 0) {
            mappedItems = pr.items.map(it => {
                let unitP = parseFloat(it.estimated_unit_price || (it.item ? it.item.estimated_unit_price : 0));
                let q = parseInt(it.qty_requested || 1);
                return {
                    item_id: it.item_id,
                    qty: q,
                    unit_price: unitP,
                    subtotal: parseFloat(it.estimated_subtotal) || (unitP * q),
                    notes: it.notes || ''
                };
            });
        } else {
            let first = this.itemsCatalog[0];
            let p = first ? parseFloat(first.estimated_unit_price || 0) : 0;
            mappedItems = [{
                item_id: first ? first.id : '',
                qty: 1,
                unit_price: p,
                subtotal: p,
                notes: ''
            }];
        }

        let hasPo = false;
        if (pr.items) {
            hasPo = pr.items.some(it => it.purchase_order_items && it.purchase_order_items.length > 0);
        }
        let canEdit = ['DRAFT', 'SUBMITTED', 'REJECTED'].includes(pr.status) || (pr.status === 'APPROVED' && !hasPo);

        this.editPr = {
            id: pr.id,
            pr_number: pr.pr_number,
            organization_id: pr.organization_id,
            organization_name: pr.organization?.name || '-',
            procurement_method: pr.procurement_method || 'PENGADAAN_LANGSUNG',
            purpose: pr.purpose || '',
            status: pr.status,
            status_label: (pr.status || '').replace(/_/g, ' '),
            badge_class: this.getBadgeClass(pr.status),
            estimated_total_cost: parseFloat(pr.estimated_total_cost || 0),
            items: mappedItems,
            is_editable: canEdit,
            update_url: '/procurement/pr/' + pr.id
        };
        this.recalculateEditTotal();
        this.editModal = true;
    },
    addEditRow() {
        let first = this.itemsCatalog[0];
        let p = first ? parseFloat(first.estimated_unit_price || 0) : 0;
        this.editPr.items.push({
            item_id: first ? first.id : '',
            qty: 1,
            unit_price: p,
            subtotal: p,
            notes: ''
        });
        this.recalculateEditTotal();
    },
    removeEditRow(index) {
        if (this.editPr.items.length > 1) {
            this.editPr.items.splice(index, 1);
            this.recalculateEditTotal();
        }
    },
    onEditItemChange(index) {
        let selId = this.editPr.items[index].item_id;
        let found = this.itemsCatalog.find(i => i.id == selId);
        let p = found ? parseFloat(found.estimated_unit_price || 0) : 0;
        this.editPr.items[index].unit_price = p;
        let q = parseInt(this.editPr.items[index].qty) || 0;
        this.editPr.items[index].subtotal = p * q;
        this.recalculateEditTotal();
    },
    onEditQtyOrPriceChange(index) {
        let q = parseInt(this.editPr.items[index].qty) || 0;
        let p = parseFloat(this.editPr.items[index].unit_price) || 0;
        this.editPr.items[index].subtotal = p * q;
        this.recalculateEditTotal();
    },
    recalculateEditTotal() {
        let total = 0;
        this.editPr.items.forEach(it => {
            total += (parseFloat(it.subtotal) || 0);
        });
        this.editTotal = total;
    },

    // ==================== DELETE MODAL STATE ====================
    deleteModal: false,
    deletePr: {
        id: null,
        pr_number: '',
        organization_name: '',
        estimated_total_cost: 0,
        delete_url: ''
    },
    openDeleteModal(pr) {
        this.deletePr = {
            id: pr.id,
            pr_number: pr.pr_number,
            organization_name: pr.organization?.name || '-',
            estimated_total_cost: pr.estimated_total_cost,
            delete_url: '/procurement/pr/' + pr.id
        };
        this.deleteModal = true;
    }
}">

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <!-- Card Header Actions -->
        <div class="card-header border-bottom d-flex align-items-center justify-content-end py-2.5 px-3">
            <button type="button" @click="openCreateModal()" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                <i class="bi bi-plus-circle"></i>
                <span>Buat PR Baru</span>
            </button>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('procurement.pr.index') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <input type="hidden" name="sort_dir" value="{{ $sortDir }}">
                <div class="row g-2 align-items-center">
                    <!-- Organization Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                            <select name="organization_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Unit Kerja</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ (string)($organizationId ?? '') === (string)$org->id ? 'selected' : '' }}>
                                        {{ $org->name }} ({{ $org->code }})
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
                                <option value="SUBMITTED" {{ ($status ?? '') === 'SUBMITTED' ? 'selected' : '' }}>Diajukan (Submitted)</option>
                                <option value="APPROVED" {{ ($status ?? '') === 'APPROVED' ? 'selected' : '' }}>Disetujui (Approved)</option>
                                <option value="PARTIALLY_ORDERED" {{ ($status ?? '') === 'PARTIALLY_ORDERED' ? 'selected' : '' }}>Sebagian Dipesan</option>
                                <option value="FULLY_ORDERED" {{ ($status ?? '') === 'FULLY_ORDERED' ? 'selected' : '' }}>Selesai PO</option>
                                <option value="REJECTED" {{ ($status ?? '') === 'REJECTED' ? 'selected' : '' }}>Ditolak</option>
                            </select>
                        </div>
                    </div>

                    <!-- Procurement Method Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-gear"></i></span>
                            <select name="procurement_method" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Metode</option>
                                <option value="PENGADAAN_LANGSUNG" {{ ($method ?? '') === 'PENGADAAN_LANGSUNG' ? 'selected' : '' }}>Pengadaan Langsung</option>
                                <option value="TENDER" {{ ($method ?? '') === 'TENDER' ? 'selected' : '' }}>Tender / Lelang</option>
                                <option value="E_KATALOG" {{ ($method ?? '') === 'E_KATALOG' ? 'selected' : '' }}>E-Katalog</option>
                                <option value="PENUNJUKAN_LANGSUNG" {{ ($method ?? '') === 'PENUNJUKAN_LANGSUNG' ? 'selected' : '' }}>Penunjukan Langsung</option>
                            </select>
                        </div>
                    </div>

                    <!-- Sort Field Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-sort-down"></i></span>
                            <select name="sort_by" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="created_at" {{ ($sortBy ?? 'created_at') === 'created_at' ? 'selected' : '' }}>Tanggal Dibuat</option>
                                <option value="pr_number" {{ ($sortBy ?? '') === 'pr_number' ? 'selected' : '' }}>Nomor PR</option>
                                <option value="estimated_total_cost" {{ ($sortBy ?? '') === 'estimated_total_cost' ? 'selected' : '' }}>Estimasi Biaya</option>
                                <option value="status" {{ ($sortBy ?? '') === 'status' ? 'selected' : '' }}>Status Alur</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($organizationId && $organizationId !== 'ALL') || ($status && $status !== 'ALL') || ($method && $method !== 'ALL') || ($sortBy && $sortBy !== 'created_at'))
                        <div class="col-auto">
                            <a href="{{ route('procurement.pr.index') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   name="search" 
                                   value="{{ $search }}" 
                                   placeholder="Cari No. PR, tujuan, unit kerja, maker..."
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Responsive Container (Zebra Grid) -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="border-bottom fs-8 text-uppercase fw-semibold text-secondary bg-body-tertiary">
                        <tr>
                            <th class="ps-3 ps-md-4 py-3" style="min-width: 170px;">
                                <a href="{{ route('procurement.pr.index', array_merge(request()->query(), ['sort_by' => 'pr_number', 'sort_dir' => $sortBy === 'pr_number' && $sortDir === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1">
                                    <span>Nomor PR</span>
                                    @if($sortBy === 'pr_number')
                                        <i class="bi {{ $sortDir === 'asc' ? 'bi-sort-up text-danger fw-bold' : 'bi-sort-down text-danger fw-bold' }}"></i>
                                    @else
                                        <i class="bi bi-arrow-down-up opacity-25 fs-9"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-3" style="min-width: 180px;">Unit Kerja Pemohon</th>
                            <th class="py-3" style="width: 140px;">Metode Pengadaan</th>
                            <th class="py-3" style="min-width: 200px;">Tujuan Pengadaan</th>
                            <th class="text-end py-3" style="min-width: 140px;">
                                <a href="{{ route('procurement.pr.index', array_merge(request()->query(), ['sort_by' => 'estimated_total_cost', 'sort_dir' => $sortBy === 'estimated_total_cost' && $sortDir === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-secondary d-inline-flex align-items-center justify-content-end gap-1">
                                    <span>Estimasi Biaya</span>
                                    @if($sortBy === 'estimated_total_cost')
                                        <i class="bi {{ $sortDir === 'asc' ? 'bi-sort-numeric-up text-danger fw-bold' : 'bi-sort-numeric-down text-danger fw-bold' }}"></i>
                                    @else
                                        <i class="bi bi-arrow-down-up opacity-25 fs-9"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-center py-3" style="width: 120px;">Pagu Anggaran</th>
                            <th class="text-center py-3" style="width: 140px;">
                                <a href="{{ route('procurement.pr.index', array_merge(request()->query(), ['sort_by' => 'status', 'sort_dir' => $sortBy === 'status' && $sortDir === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1">
                                    <span>Status Alur</span>
                                    @if($sortBy === 'status')
                                        <i class="bi {{ $sortDir === 'asc' ? 'bi-sort-up text-danger fw-bold' : 'bi-sort-down text-danger fw-bold' }}"></i>
                                    @else
                                        <i class="bi bi-arrow-down-up opacity-25 fs-9"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-center pe-3 pe-md-4 py-3" style="width: 110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($prs as $pr)
                            <tr>
                                <!-- PR Number & Maker -->
                                <td class="ps-3 ps-md-4">
                                    <span class="fw-bold font-monospace text-danger">
                                        {{ $pr->pr_number }}
                                    </span>
                                    <div class="fs-8 text-secondary">
                                        <i class="bi bi-person me-1"></i>{{ $pr->requester->name ?? '-' }}
                                    </div>
                                    <div class="fs-9 text-secondary font-monospace">
                                        {{ $pr->created_at ? $pr->created_at->format('d/m/Y H:i') : '-' }}
                                    </div>
                                </td>

                                <!-- Organization -->
                                <td>
                                    <span class="fw-semibold text-body">{{ $pr->organization->name ?? '-' }}</span>
                                    <div class="fs-8 font-monospace text-secondary">
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-9">{{ $pr->organization->code ?? '-' }}</span>
                                    </div>
                                </td>

                                <!-- Procurement Method -->
                                <td>
                                    <span class="badge bg-body-secondary text-secondary-emphasis font-monospace border fs-9">
                                        {{ str_replace('_', ' ', $pr->procurement_method) }}
                                    </span>
                                </td>

                                <!-- Purpose -->
                                <td>
                                    <div class="text-truncate text-secondary" style="max-width: 250px;" title="{{ $pr->purpose }}">
                                        {{ $pr->purpose }}
                                    </div>
                                    <div class="fs-9 text-muted">
                                        <i class="bi bi-box me-1"></i>{{ $pr->items->count() }} jenis barang
                                    </div>
                                </td>

                                <!-- Estimated Cost -->
                                <td class="text-end font-monospace fw-bold text-body">
                                    Rp {{ number_format($pr->estimated_total_cost, 0, ',', '.') }}
                                </td>

                                <!-- Budget Status -->
                                <td class="text-center">
                                    @if($pr->budget_status === 'VALIDATED')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-9">
                                            <i class="bi bi-check-circle me-0.5"></i> VALID
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-9">
                                            <i class="bi bi-exclamation-circle me-0.5"></i> KURANG
                                        </span>
                                    @endif
                                </td>

                                <!-- Status Badge -->
                                <td class="text-center">
                                    @php
                                        $badgeClass = match($pr->status) {
                                            'APPROVED' => 'text-bg-success',
                                            'FULLY_ORDERED' => 'text-bg-purple',
                                            'PARTIALLY_ORDERED' => 'text-bg-primary',
                                            'SUBMITTED' => 'text-bg-warning',
                                            'REJECTED' => 'text-bg-danger',
                                            default => 'text-bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} fs-9 text-uppercase">
                                        {{ str_replace('_', ' ', $pr->status) }}
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="text-center pe-3 pe-md-4 py-2">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- View Detail Button -->
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from($pr) }})" 
                                                class="btn btn-sm btn-light border text-secondary shadow-2xs py-1 px-2" 
                                                title="Lihat Detail PR">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Edit Button -->
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($pr) }})" 
                                                class="btn btn-sm btn-light border text-primary shadow-2xs py-1 px-2" 
                                                title="Edit PR">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($pr) }})" 
                                                class="btn btn-sm btn-light border text-danger shadow-2xs py-1 px-2" 
                                                title="Hapus PR">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-secondary">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada data Purchase Request ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau sesuaikan filter Anda.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Standardized Reusable Pagination Footer -->
        <x-pagination-footer :paginator="$prs" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL 1: VIEW DETAIL PR ==================== -->
    <div x-show="viewModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-3xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-file-earmark-text fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Detail Purchase Request: <span class="font-monospace text-danger" x-text="viewPr?.pr_number"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Informasi lengkap pengajuan pengadaan barang</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge fs-8 text-uppercase" :class="viewPr ? getBadgeClass(viewPr.status) : ''" x-text="viewPr ? (viewPr.status || '').replace(/_/g, ' ') : ''"></span>
                    <button type="button" @click="viewModal = false" class="btn-close ms-2" aria-label="Close"></button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="card-body p-3.5 p-md-4 space-y-3">
                <!-- Summary Card -->
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="row g-2.5 fs-8">
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Unit Pemohon:</span>
                            <strong class="text-body" x-text="viewPr?.organization?.name || '-'"></strong>
                            <div class="font-monospace text-secondary fs-9" x-text="viewPr?.organization?.code ? 'Kode: ' + viewPr.organization.code : ''"></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Dibuat Oleh:</span>
                            <span class="text-body fw-semibold" x-text="viewPr?.requester?.name || '-'"></span>
                            <div class="font-monospace text-secondary fs-9" x-text="viewPr?.created_at ? new Date(viewPr.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'"></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Metode Pengadaan:</span>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace border fs-9" x-text="(viewPr?.procurement_method || '-').replace(/_/g, ' ')"></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Pagu Anggaran:</span>
                            <span class="badge fs-9" :class="viewPr?.budget_status === 'VALIDATED' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'" x-text="viewPr?.budget_status === 'VALIDATED' ? 'Tersedia (VALID)' : 'Tidak Mencukupi'"></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Approver:</span>
                            <span class="text-body fw-semibold" x-text="viewPr?.approver?.name || 'Menunggu Persetujuan'"></span>
                            <div class="font-monospace text-secondary fs-9" x-text="viewPr?.approved_at ? new Date(viewPr.approved_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : ''"></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Total Estimasi Biaya:</span>
                            <span class="fw-bold font-monospace fs-7 text-danger" x-text="formatRupiah(viewPr?.estimated_total_cost)"></span>
                        </div>
                    </div>

                    <template x-if="viewPr?.purpose">
                        <div class="mt-2.5 pt-2 border-top border-secondary-subtle fs-8">
                            <span class="text-secondary fw-semibold">Tujuan Pengadaan:</span>
                            <span class="text-body ms-1" x-text="viewPr.purpose"></span>
                        </div>
                    </template>
                </div>

                <!-- Items Table -->
                <div>
                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1.5">
                        <i class="bi bi-box-seam me-1 text-danger"></i> Rincian Barang Diminta
                    </label>
                    <div class="table-responsive rounded border border-secondary-subtle" style="max-height: 220px; overflow-y: auto;">
                        <table class="table table-sm table-striped table-hover align-middle mb-0 fs-8">
                            <thead class="bg-body-tertiary text-secondary sticky-top">
                                <tr>
                                    <th class="ps-3" style="width: 40px;">No</th>
                                    <th>Nama & SKU Barang</th>
                                    <th class="text-center" style="width: 80px;">Satuan</th>
                                    <th class="text-center" style="width: 80px;">Qty Diminta</th>
                                    <th class="text-center" style="width: 80px;">Qty Disetujui</th>
                                    <th class="text-end" style="width: 120px;">Harga Satuan</th>
                                    <th class="text-end pe-3" style="width: 130px;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(it, idx) in (viewPr?.items || [])" :key="idx">
                                    <tr>
                                        <td class="ps-3 text-secondary font-monospace" x-text="idx + 1"></td>
                                        <td>
                                            <span class="fw-semibold text-body" x-text="it.item?.name || ('Item #' + it.item_id)"></span>
                                            <div class="fs-9 text-secondary font-monospace" x-text="it.item?.sku || ''"></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-secondary" x-text="it.item?.uom || '-'"></span>
                                        </td>
                                        <td class="text-center font-monospace fw-bold" x-text="it.qty_requested"></td>
                                        <td class="text-center font-monospace fw-semibold text-success" x-text="it.qty_approved"></td>
                                        <td class="text-end font-monospace text-secondary" x-text="formatRupiah(it.estimated_unit_price)"></td>
                                        <td class="text-end font-monospace pe-3 fw-bold text-body" x-text="formatRupiah(it.estimated_subtotal)"></td>
                                    </tr>
                                </template>
                                <template x-if="!viewPr?.items || viewPr.items.length === 0">
                                    <tr>
                                        <td colspan="7" class="text-center py-3 text-secondary">Tidak ada rincian item.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-top">
                <div>
                    <template x-if="viewPr?.status === 'SUBMITTED'">
                        <form :action="'/procurement/pr/' + viewPr.id + '/approve'" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success fw-bold d-inline-flex align-items-center gap-1 shadow-xs" onclick="return confirm('Setujui Purchase Request ini?')">
                                <i class="bi bi-check-circle"></i>
                                <span>Setujui PR Ini</span>
                            </button>
                        </form>
                    </template>
                </div>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL 2: EDIT PR ==================== -->
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
                        <h6 class="mb-0 fw-bold text-body">
                            Edit PR: <span class="font-monospace text-danger" x-text="editPr.pr_number"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Perbarui metode pengadaan, tujuan, atau daftar item barang</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge fs-8 text-uppercase" :class="editPr.badge_class" x-text="editPr.status_label"></span>
                    <button type="button" @click="editModal = false" class="btn-close ms-2" aria-label="Close"></button>
                </div>
            </div>

            <!-- Form -->
            <form :action="editPr.update_url" method="POST">
                @csrf
                @method('PUT')

                <div class="card-body p-3.5 p-md-4 space-y-3">
                    <!-- Warning if not editable -->
                    <template x-if="!editPr.is_editable">
                        <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-6 flex-shrink-0"></i>
                            <div>
                                PR ini telah dikonsolidasikan ke Purchase Order dan tidak dapat diedit lagi.
                            </div>
                        </div>
                    </template>

                    <!-- Order Parameter Fields -->
                    <div class="row g-2.5">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja Pemohon <span class="text-danger">*</span></label>
                            <select name="organization_id" x-model="editPr.organization_id" required class="form-select form-select-sm fs-8">
                                <template x-for="org in organizations" :key="org.id">
                                    <option :value="org.id" x-text="org.name + ' (' + org.code + ')'" :selected="org.id == editPr.organization_id"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Metode Pengadaan <span class="text-danger">*</span></label>
                            <select name="procurement_method" x-model="editPr.procurement_method" required class="form-select form-select-sm fs-8">
                                <option value="PENGADAAN_LANGSUNG">Pengadaan Langsung</option>
                                <option value="TENDER">Tender / Lelang</option>
                                <option value="E_KATALOG">E-Katalog</option>
                                <option value="PENUNJUKAN_LANGSUNG">Penunjukan Langsung</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tujuan Pengadaan <span class="text-danger">*</span></label>
                            <textarea name="purpose" x-model="editPr.purpose" rows="2" required class="form-control form-control-sm fs-8" placeholder="Jelaskan kebutuhan dan tujuan pengadaan barang ini..."></textarea>
                        </div>
                    </div>

                    <!-- Items Repeater Section -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-0">
                                <i class="bi bi-box-seam me-1 text-danger"></i> Daftar Barang Diminta (<span x-text="editPr.items.length"></span> item)
                            </label>
                            <template x-if="editPr.is_editable">
                                <button type="button" @click="addEditRow()" class="btn btn-sm btn-outline-danger py-1 px-2 fs-8 fw-semibold">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Item
                                </button>
                            </template>
                        </div>

                        <div class="table-responsive rounded border border-secondary-subtle" style="max-height: 240px; overflow-y: auto;">
                            <table class="table table-sm table-striped table-hover align-middle mb-0 fs-8">
                                <thead class="bg-body-tertiary text-secondary sticky-top">
                                    <tr>
                                        <th class="ps-3">Nama / SKU Barang</th>
                                        <th class="text-center" style="width: 100px;">Jumlah (Qty)</th>
                                        <th class="text-end" style="width: 130px;">Harga Satuan</th>
                                        <th class="text-end" style="width: 140px;">Subtotal</th>
                                        <th class="text-center" style="width: 50px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, index) in editPr.items" :key="index">
                                        <tr>
                                            <td class="ps-3">
                                                <select :name="'items[' + index + '][item_id]'" 
                                                        x-model="row.item_id" 
                                                        @change="onEditItemChange(index)" 
                                                        required 
                                                        class="form-select form-select-sm fs-8">
                                                    <template x-for="cat in itemsCatalog" :key="cat.id">
                                                        <option :value="cat.id" x-text="cat.name + ' (' + cat.sku + ') - ' + cat.uom" :selected="cat.id == row.item_id"></option>
                                                    </template>
                                                </select>
                                                <input type="text" 
                                                       :name="'items[' + index + '][notes]'" 
                                                       x-model="row.notes" 
                                                       placeholder="Catatan barang (opsional)" 
                                                       class="form-control form-control-sm fs-9 mt-1 py-0.5">
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       :name="'items[' + index + '][qty]'" 
                                                       x-model="row.qty" 
                                                       @input="onEditQtyOrPriceChange(index)" 
                                                       min="1" 
                                                       required 
                                                       class="form-control form-control-sm text-center font-monospace fw-bold fs-8">
                                            </td>
                                            <td class="text-end">
                                                <input type="number" 
                                                       :name="'items[' + index + '][unit_price]'" 
                                                       x-model="row.unit_price" 
                                                       @input="onEditQtyOrPriceChange(index)" 
                                                       min="0" 
                                                       required 
                                                       class="form-control form-control-sm text-end font-monospace fs-8">
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-danger pe-2">
                                                <span x-text="formatRupiah(row.subtotal)"></span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" 
                                                        @click="removeEditRow(index)" 
                                                        class="btn btn-sm btn-outline-danger p-1 border-0" 
                                                        :disabled="editPr.items.length <= 1"
                                                        title="Hapus baris">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Total Banner -->
                        <div class="d-flex justify-content-between align-items-center mt-2 p-2 rounded bg-body-tertiary border">
                            <span class="fs-8 fw-semibold text-secondary">Total Nilai Estimasi:</span>
                            <span class="fs-7 fw-bold font-monospace text-danger" x-text="formatRupiah(editTotal)"></span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <template x-if="editPr.is_editable">
                        <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-xs px-3">
                            <i class="bi bi-save me-1"></i>
                            <span>Simpan Perubahan</span>
                        </button>
                    </template>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL 3: DELETE PR ==================== -->
    <div x-show="deleteModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteModal = false" 
             class="card shadow-2xl border border-danger-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger-subtle d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom border-danger-subtle">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-trash-fill fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Purchase Request</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="deletePr.delete_url" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin menghapus Purchase Request berikut dari sistem?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">Nomor & Unit Pemohon:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deletePr.pr_number"></div>
                        <div class="fs-8 text-body fw-semibold" x-text="deletePr.organization_name"></div>
                        <div class="fs-8 text-secondary font-monospace" x-text="formatRupiah(deletePr.estimated_total_cost)"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-shield-exclamation text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            PR yang telah dihapus tidak dapat diproses lagi dan riwayat pengajuannya akan dicatat pada audit log.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 shadow-xs px-3">
                        <i class="bi bi-trash-fill me-1"></i>
                        <span>Ya, Hapus PR</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL 4: BUAT PR BARU ==================== -->
    <div x-show="createModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-3xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-plus-circle fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Buat Purchase Request (PR) Baru</h6>
                        <span class="fs-8 text-secondary">Formulir permohonan pengadaan barang persediaan / ATK</span>
                    </div>
                </div>
                <button type="button" @click="createModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form action="{{ route('procurement.pr.store') }}" method="POST">
                @csrf

                <div class="card-body p-3.5 p-md-4 space-y-3">
                    <!-- Order Parameter Fields -->
                    <div class="row g-2.5">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja Pemohon <span class="text-danger">*</span></label>
                            <select name="organization_id" required class="form-select form-select-sm fs-8">
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ $org->id === auth()->user()->organization_id ? 'selected' : '' }}>
                                        {{ $org->name }} ({{ $org->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Metode Pengadaan <span class="text-danger">*</span></label>
                            <select name="procurement_method" required class="form-select form-select-sm fs-8">
                                <option value="PENGADAAN_LANGSUNG">Pengadaan Langsung</option>
                                <option value="TENDER">Tender / Lelang</option>
                                <option value="E_KATALOG">E-Katalog</option>
                                <option value="PENUNJUKAN_LANGSUNG">Penunjukan Langsung</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tujuan Pengadaan <span class="text-danger">*</span></label>
                            <textarea name="purpose" rows="2" required class="form-control form-control-sm fs-8" placeholder="Contoh: Pemenuhan stok kertas dan perlengkapan cetak bilyet giro Q2 2026..."></textarea>
                        </div>
                    </div>

                    <!-- Items Repeater Section -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-0">
                                <i class="bi bi-box-seam me-1 text-danger"></i> Daftar Barang yang Diminta (<span x-text="createRows.length"></span> item)
                            </label>
                            <button type="button" @click="addCreateRow()" class="btn btn-sm btn-outline-danger py-1 px-2 fs-8 fw-semibold">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Item
                            </button>
                        </div>

                        <div class="table-responsive rounded border border-secondary-subtle" style="max-height: 240px; overflow-y: auto;">
                            <table class="table table-sm table-striped table-hover align-middle mb-0 fs-8">
                                <thead class="bg-body-tertiary text-secondary sticky-top">
                                    <tr>
                                        <th class="ps-3">Nama / SKU Barang</th>
                                        <th class="text-center" style="width: 100px;">Jumlah (Qty)</th>
                                        <th class="text-end" style="width: 130px;">Harga Satuan</th>
                                        <th class="text-end" style="width: 140px;">Subtotal</th>
                                        <th class="text-center" style="width: 50px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, index) in createRows" :key="index">
                                        <tr>
                                            <td class="ps-3">
                                                <select :name="'items[' + index + '][item_id]'" 
                                                        x-model="row.item_id" 
                                                        @change="onCreateItemChange(index)" 
                                                        required 
                                                        class="form-select form-select-sm fs-8">
                                                    <template x-for="cat in itemsCatalog" :key="cat.id">
                                                        <option :value="cat.id" x-text="cat.name + ' (' + cat.sku + ') - ' + cat.uom"></option>
                                                    </template>
                                                </select>
                                                <input type="text" 
                                                       :name="'items[' + index + '][notes]'" 
                                                       x-model="row.notes" 
                                                       placeholder="Catatan barang (opsional)" 
                                                       class="form-control form-control-sm fs-9 mt-1 py-0.5">
                                            </td>
                                            <td class="text-center">
                                                <input type="number" 
                                                       :name="'items[' + index + '][qty]'" 
                                                       x-model="row.qty" 
                                                       @input="onCreateQtyOrPriceChange(index)" 
                                                       min="1" 
                                                       required 
                                                       class="form-control form-control-sm text-center font-monospace fw-bold fs-8">
                                            </td>
                                            <td class="text-end">
                                                <input type="number" 
                                                       :name="'items[' + index + '][unit_price]'" 
                                                       x-model="row.unit_price" 
                                                       @input="onCreateQtyOrPriceChange(index)" 
                                                       min="0" 
                                                       required 
                                                       class="form-control form-control-sm text-end font-monospace fs-8">
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-danger pe-2">
                                                <span x-text="formatRupiah(row.subtotal)"></span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" 
                                                        @click="removeCreateRow(index)" 
                                                        class="btn btn-sm btn-outline-danger p-1 border-0" 
                                                        :disabled="createRows.length <= 1"
                                                        title="Hapus baris">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Total Banner -->
                        <div class="d-flex justify-content-between align-items-center mt-2 p-2 rounded bg-body-tertiary border">
                            <span class="fs-8 fw-semibold text-secondary">Total Nilai Estimasi:</span>
                            <span class="fs-7 fw-bold font-monospace text-danger" x-text="formatRupiah(createTotal)"></span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="createModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 shadow-xs px-3">
                        <i class="bi bi-send me-1"></i>
                        <span>Simpan & Ajukan PR</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
