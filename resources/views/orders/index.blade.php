@extends('layouts.app')
@section('title', 'Daftar Order Permintaan Cabang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Overview</a></li>
    <li class="breadcrumb-item"><a href="{{ route('orders.index') }}" class="text-decoration-none text-danger">Orders</a></li>
    <li class="breadcrumb-item active" aria-current="page">Daftar Order</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    itemsCatalog: {{ Js::from($itemsCatalog) }},
    createModal: false,
    createRows: [],
    createTotal: 0,
    openCreateModal() {
        this.createRows = [{
            item_id: '',
            qty: 1,
            price: 0,
            subtotal: 0
        }];
        this.recalculateCreateTotal();
        this.createModal = true;
    },
    addCreateRow() {
        this.createRows.push({
            item_id: '',
            qty: 1,
            price: 0,
            subtotal: 0
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
        this.createRows[index].price = p;
        let q = parseInt(this.createRows[index].qty) || 0;
        this.createRows[index].subtotal = p * q;
        this.recalculateCreateTotal();
    },
    onCreateQtyChange(index) {
        let q = parseInt(this.createRows[index].qty) || 0;
        let p = parseFloat(this.createRows[index].price) || 0;
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

    viewModal: false,
    viewOrder: null,
    openViewModal(ord) {
        this.viewOrder = ord;
        this.viewModal = true;
    },

    deleteModal: false,
    deleteOrder: {
        id: null,
        order_number: '',
        organization_name: '',
        total_estimated_value: 0,
        delete_url: ''
    },
    openDeleteModal(ord) {
        this.deleteOrder = {
            id: ord.id,
            order_number: ord.order_number,
            organization_name: ord.requesting_organization?.name || '-',
            total_estimated_value: ord.total_estimated_value,
            delete_url: '/orders/' + ord.id
        };
        this.deleteModal = true;
    },

    editModal: false,
    editOrder: {
        id: null,
        order_number: '',
        organization_name: '',
        requester_name: '',
        priority: 'NORMAL',
        required_date: '',
        notes: '',
        status: '',
        status_label: '',
        badge_class: '',
        total_estimated_value: 0,
        created_at_formatted: '',
        items: [],
        is_editable: true,
        update_url: ''
    },
    openEditModal(ord) {
        let mappedItems = [];
        if (ord.items && ord.items.length > 0) {
            mappedItems = ord.items.map(it => {
                let unitP = parseFloat(it.unit_price_ref || (it.item ? it.item.estimated_unit_price : 0));
                let q = parseInt(it.qty_requested || 1);
                return {
                    item_id: it.item_id,
                    qty: q,
                    price: unitP,
                    subtotal: parseFloat(it.subtotal_ref) || (unitP * q)
                };
            });
        } else {
            mappedItems = [{
                item_id: '',
                qty: 1,
                price: 0,
                subtotal: 0
            }];
        }

        this.editOrder = {
            id: ord.id,
            order_number: ord.order_number,
            organization_name: ord.requesting_organization?.name || '-',
            requester_name: ord.requester?.name || '-',
            priority: ord.priority || 'NORMAL',
            required_date: ord.required_date ? ord.required_date.substring(0, 10) : '',
            notes: ord.notes || '',
            status: ord.status,
            status_label: (ord.status || '').replace(/_/g, ' '),
            badge_class: this.getBadgeClass(ord.status),
            total_estimated_value: parseFloat(ord.total_estimated_value || 0),
            created_at_formatted: ord.created_at ? new Date(ord.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-',
            items: mappedItems,
            is_editable: ['DRAFT', 'SUBMITTED', 'WAITING_APPROVAL'].includes(ord.status),
            update_url: '/orders/' + ord.id
        };
        this.recalculateTotal();
        this.editModal = true;
    },
    addItemRow() {
        this.editOrder.items.push({
            item_id: '',
            qty: 1,
            price: 0,
            subtotal: 0
        });
        this.recalculateTotal();
    },
    removeItemRow(index) {
        if (this.editOrder.items.length > 1) {
            this.editOrder.items.splice(index, 1);
            this.recalculateTotal();
        }
    },
    onItemChange(index) {
        let selId = this.editOrder.items[index].item_id;
        let found = this.itemsCatalog.find(i => i.id == selId);
        let p = found ? parseFloat(found.estimated_unit_price || 0) : 0;
        this.editOrder.items[index].price = p;
        let q = parseInt(this.editOrder.items[index].qty) || 0;
        this.editOrder.items[index].subtotal = p * q;
        this.recalculateTotal();
    },
    onQtyChange(index) {
        let q = parseInt(this.editOrder.items[index].qty) || 0;
        let p = parseFloat(this.editOrder.items[index].price) || 0;
        this.editOrder.items[index].subtotal = p * q;
        this.recalculateTotal();
    },
    recalculateTotal() {
        let total = 0;
        this.editOrder.items.forEach(it => {
            total += (parseFloat(it.subtotal) || 0);
        });
        this.editOrder.total_estimated_value = total;
    },
    formatRupiah(num) {
        return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    },
    getBadgeClass(status) {
        switch(status) {
            case 'COMPLETED':
            case 'RECEIVED': return 'text-bg-success';
            case 'IN_TRANSIT': return 'text-bg-info';
            case 'READY_TO_SHIP':
            case 'ALLOCATED': return 'text-bg-primary';
            case 'WAITING_APPROVAL': return 'text-bg-warning';
            case 'SUBMITTED': return 'text-bg-secondary';
            case 'CANCELLED':
            case 'REJECTED': return 'text-bg-danger';
            default: return 'text-bg-light border';
        }
    }
}">

    <!-- KPI Metric Widgets (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- Box 1: Total Orders -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cart-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Pesanan Masuk</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($totalOrdersCount) }}</span>
                    <span class="fs-9 text-secondary">Semua Permintaan Cabang</span>
                </div>
            </div>
        </div>

        <!-- Box 2: Open Orders -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Order Terbuka / Open</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($openOrdersCount) }}</span>
                    <span class="fs-9 text-warning-emphasis">Menunggu Approval / Alokasi</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Riwayat Order Selesai -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Riwayat Order Selesai</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($completedOrdersCount) }}</span>
                    <span class="fs-9 text-secondary">Pesanan Selesai / Diterima</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Average Order Value -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Rata-Rata Nilai Order</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">Rp {{ number_format($averageOrderValue / 1000, 0, ',', '.') }}<span class="fs-7 fw-normal">rb</span></span>
                    <span class="fs-9 text-secondary">Rp {{ number_format($averageOrderValue, 0, ',', '.') }} per transaksi</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header with Tabs and Action Button Aligned Side-by-Side -->
        <div class="card-header border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <!-- Status Navigation Tabs -->
            <ul class="nav nav-pills nav-pills-scroll flex-nowrap card-header-pills fs-7 pb-1 pb-md-0">
                <li class="nav-item">
                    <a href="{{ route('orders.index', array_merge(request()->except(['page', 'status']), ['tab' => 'open'])) }}" 
                       class="nav-link {{ $currentTab === 'open' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        Order Terbuka
                        <span class="badge {{ $currentTab === 'open' ? 'bg-white text-danger' : 'text-bg-warning' }} ms-1">{{ number_format($openOrdersCount) }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('orders.index', array_merge(request()->except(['page', 'status']), ['tab' => 'completed'])) }}" 
                       class="nav-link {{ $currentTab === 'completed' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        Riwayat Order
                        <span class="badge {{ $currentTab === 'completed' ? 'bg-white text-danger' : 'text-bg-success' }} ms-1">{{ number_format($completedOrdersCount) }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('orders.index', array_merge(request()->except(['page', 'status']), ['tab' => 'all'])) }}" 
                       class="nav-link {{ $currentTab === 'all' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        Semua
                        <span class="badge {{ $currentTab === 'all' ? 'bg-white text-danger' : 'text-bg-secondary' }} ms-1">{{ number_format($totalOrdersCount) }}</span>
                    </a>
                </li>
            </ul>

            <!-- Action Button Aligned with Tabs -->
            <div class="card-tools ms-md-auto">
                <button type="button" @click="openCreateModal()" class="btn btn-sm btn-danger fw-bold shadow-xs">
                    Buat Order Baru
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('orders.index') }}" method="GET">
                <input type="hidden" name="tab" value="{{ $currentTab }}">
                <input type="hidden" name="sort_dir" value="{{ $sortDir }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Organization Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                            <select name="organization_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Unit Kerja</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ (string)($organizationId ?? '') === (string)$org->id ? 'selected' : '' }}>
                                        {{ $org->name }}
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
                                <option value="DRAFT" {{ ($status ?? '') === 'DRAFT' ? 'selected' : '' }}>Draft</option>
                                <option value="SUBMITTED" {{ ($status ?? '') === 'SUBMITTED' ? 'selected' : '' }}>Diajukan</option>
                                <option value="APPROVED" {{ ($status ?? '') === 'APPROVED' ? 'selected' : '' }}>Disetujui</option>
                                <option value="ALLOCATED" {{ ($status ?? '') === 'ALLOCATED' ? 'selected' : '' }}>Teralokasi</option>
                                <option value="PICKING" {{ ($status ?? '') === 'PICKING' ? 'selected' : '' }}>Picking</option>
                                <option value="READY_TO_SHIP" {{ ($status ?? '') === 'READY_TO_SHIP' ? 'selected' : '' }}>Siap Kirim</option>
                                <option value="IN_TRANSIT" {{ ($status ?? '') === 'IN_TRANSIT' ? 'selected' : '' }}>Dalam Perjalanan</option>
                                <option value="RECEIVED" {{ ($status ?? '') === 'RECEIVED' ? 'selected' : '' }}>Diterima</option>
                                <option value="COMPLETED" {{ ($status ?? '') === 'COMPLETED' ? 'selected' : '' }}>Selesai</option>
                                <option value="REJECTED" {{ ($status ?? '') === 'REJECTED' ? 'selected' : '' }}>Ditolak</option>
                            </select>
                        </div>
                    </div>

                    <!-- Sort Field Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-sort-down"></i></span>
                            <select name="sort_by" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="created_at" {{ ($sortBy ?? 'created_at') === 'created_at' ? 'selected' : '' }}>Tanggal Dibuat</option>
                                <option value="order_number" {{ ($sortBy ?? '') === 'order_number' ? 'selected' : '' }}>Nomor Order</option>
                                <option value="total_estimated_value" {{ ($sortBy ?? '') === 'total_estimated_value' ? 'selected' : '' }}>Total Estimasi Nilai</option>
                                <option value="status" {{ ($sortBy ?? '') === 'status' ? 'selected' : '' }}>Status Transaksi</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($organizationId && $organizationId !== 'ALL') || ($status && $status !== 'ALL') || ($sortBy && $sortBy !== 'created_at'))
                        <div class="col-auto">
                            <a href="{{ route('orders.index', ['tab' => $currentTab]) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                Reset
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
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Responsive Container -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-7">
                    <thead class="border-bottom fs-8 text-uppercase fw-semibold text-secondary bg-body-tertiary">
                        <tr>
                            <th class="ps-3 ps-md-4 py-3" style="min-width: 170px;">
                                <a href="{{ route('orders.index', array_merge(request()->query(), ['sort_by' => 'order_number', 'sort_dir' => $sortBy === 'order_number' && $sortDir === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1">
                                    <span>Nomor Order</span>
                                    @if($sortBy === 'order_number')
                                        <i class="bi {{ $sortDir === 'asc' ? 'bi-sort-up text-danger fw-bold' : 'bi-sort-down text-danger fw-bold' }}"></i>
                                    @else
                                        <i class="bi bi-arrow-down-up opacity-25 fs-9"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="py-3" style="min-width: 190px;">Unit Kerja Peminta</th>
                            <th class="text-center py-3" style="width: 150px;">
                                <a href="{{ route('orders.index', array_merge(request()->query(), ['sort_by' => 'status', 'sort_dir' => $sortBy === 'status' && $sortDir === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1">
                                    <span>Status Alur</span>
                                    @if($sortBy === 'status')
                                        <i class="bi {{ $sortDir === 'asc' ? 'bi-sort-up text-danger fw-bold' : 'bi-sort-down text-danger fw-bold' }}"></i>
                                    @else
                                        <i class="bi bi-arrow-down-up opacity-25 fs-9"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-end py-3" style="min-width: 140px;">
                                <a href="{{ route('orders.index', array_merge(request()->query(), ['sort_by' => 'total_estimated_value', 'sort_dir' => $sortBy === 'total_estimated_value' && $sortDir === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-secondary d-inline-flex align-items-center justify-content-end gap-1">
                                    <span>Total Nilai</span>
                                    @if($sortBy === 'total_estimated_value')
                                        <i class="bi {{ $sortDir === 'asc' ? 'bi-sort-numeric-up text-danger fw-bold' : 'bi-sort-numeric-down text-danger fw-bold' }}"></i>
                                    @else
                                        <i class="bi bi-arrow-down-up opacity-25 fs-9"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-end py-3" style="min-width: 130px;">Ongkir Ekspedisi</th>
                            <th class="py-3" style="min-width: 160px;">
                                <a href="{{ route('orders.index', array_merge(request()->query(), ['sort_by' => 'created_at', 'sort_dir' => $sortBy === 'created_at' && $sortDir === 'asc' ? 'desc' : 'asc'])) }}" class="text-decoration-none text-secondary d-inline-flex align-items-center gap-1">
                                    <span>Tanggal Order</span>
                                    @if($sortBy === 'created_at')
                                        <i class="bi {{ $sortDir === 'asc' ? 'bi-sort-up text-danger fw-bold' : 'bi-sort-down text-danger fw-bold' }}"></i>
                                    @else
                                        <i class="bi bi-arrow-down-up opacity-25 fs-9"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="text-center pe-3 pe-md-4 py-3" style="width: 105px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $ord)
                            <tr>
                                <!-- Order Number & Maker -->
                                <td class="ps-3 ps-md-4">
                                    <span class="fw-bold font-monospace text-danger">
                                        {{ $ord->order_number }}
                                    </span>
                                    <div class="fs-8 text-secondary">
                                        <i class="bi bi-person me-1"></i>{{ $ord->requester->name }}
                                    </div>
                                </td>

                                <!-- Organization -->
                                <td>
                                    <span class="fw-semibold text-body">{{ $ord->requestingOrganization->name }}</span>
                                    <div class="fs-8 font-monospace text-secondary">
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-8">{{ $ord->requestingOrganization->code }}</span>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="text-center">
                                    @php
                                        $badgeClass = match($ord->status) {
                                             'COMPLETED', 'RECEIVED' => 'text-bg-success',
                                             'IN_TRANSIT' => 'text-bg-info',
                                             'READY_TO_SHIP', 'ALLOCATED' => 'text-bg-primary',
                                             'WAITING_APPROVAL' => 'text-bg-warning',
                                             'SUBMITTED' => 'text-bg-secondary',
                                             'CANCELLED', 'REJECTED' => 'text-bg-danger',
                                             default => 'text-bg-light border'
                                         };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} fs-8 text-uppercase">
                                        {{ str_replace('_', ' ', $ord->status) }}
                                    </span>
                                </td>

                                <!-- Total Value -->
                                <td class="text-end fw-bold font-monospace text-body-emphasis">
                                    Rp {{ number_format($ord->total_estimated_value, 0, ',', '.') }}
                                </td>

                                <!-- Shipping Cost -->
                                <td class="text-end font-monospace text-secondary">
                                    {{ $ord->shipment ? 'Rp ' . number_format($ord->shipment->shipping_cost, 0, ',', '.') : '-' }}
                                </td>

                                <!-- Date -->
                                <td class="text-secondary fs-8">
                                    {{ $ord->created_at->format('d M Y, H:i') }} WIB
                                </td>

                                <!-- Actions -->
                                <td class="text-center pe-3 pe-md-4 py-2">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from($ord) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Order">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="{{ route('orders.print', $ord->id) }}" 
                                           target="_blank" 
                                           class="btn-action-icon text-dark" 
                                           title="Cetak Dokumen Order">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($ord) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Order">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($ord) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Hapus Order">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada data order ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau bersihkan filter.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Standardized Bank Jatim Pagination Footer -->
        <x-pagination-footer :paginator="$orders" :perPage="$perPage" :tab="$currentTab" />
    </div>

    <!-- ==================== MODAL: BUAT ORDER BARU ==================== -->
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
                        <i class="bi bi-cart-plus fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Buat Order Permintaan Baru</h6>
                        <span class="fs-8 text-secondary">Pengajuan pesanan barang inventaris / ATK unit kerja</span>
                    </div>
                </div>
                <button type="button" @click="createModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form action="{{ route('orders.store') }}" method="POST">
                @csrf

                <div class="card-body p-3.5 p-md-4">
                    <!-- Section: Parameter Order -->
                    <div class="row g-2.5 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja Peminta <span class="text-danger">*</span></label>
                            <select name="organization_id" required class="form-select form-select-sm fs-8">
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ $org->id === auth()->user()->organization_id ? 'selected' : '' }}>
                                        {{ $org->name }} ({{ $org->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Prioritas Permintaan <span class="text-danger">*</span></label>
                            <select name="priority" required class="form-select form-select-sm fs-8">
                                <option value="NORMAL">Normal (Standar Operasional)</option>
                                <option value="HIGH">High (Prioritas Tinggi)</option>
                                <option value="URGENT">Urgent (Sangat Mendesak)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Target Tanggal Dibutuhkan <span class="text-danger">*</span></label>
                            <input type="date" name="required_date" value="{{ date('Y-m-d', strtotime('+3 days')) }}" required class="form-control form-control-sm fs-8">
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan / Keterangan Order</label>
                            <input type="text" name="notes" class="form-control form-control-sm fs-8">
                        </div>
                    </div>

                    <!-- Section: Daftar Item -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-0">
                                Daftar Barang yang Diminta (<span x-text="createRows.length"></span> item)
                            </label>
                            <button type="button" @click="addCreateRow()" class="btn btn-sm btn-outline-danger py-1 px-2 fs-8 fw-semibold">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Item
                            </button>
                        </div>

                        <div class="table-responsive border rounded bg-body mb-2" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0 fs-8">
                                <thead class="table-light text-secondary fs-9 text-uppercase sticky-top">
                                    <tr>
                                        <th class="ps-3 py-1.5">Pilih Barang / Item</th>
                                        <th class="text-center py-1.5" style="width: 140px;">Jumlah (Qty)</th>
                                        <th class="text-end py-1.5" style="width: 140px;">Subtotal Ref</th>
                                        <th class="text-center py-1.5" style="width: 45px;">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, index) in createRows" :key="index">
                                        <tr>
                                            <td class="ps-3 py-1.5">
                                                <select :name="'items[' + index + '][item_id]'" 
                                                        x-model="row.item_id" 
                                                        @change="onCreateItemChange(index)" 
                                                        class="form-select form-select-sm" 
                                                        required>
                                                    <option value="">-- Pilih Barang / Item --</option>
                                                    <template x-for="it in itemsCatalog" :key="it.id">
                                                        <option :value="it.id" x-text="it.name + ' [' + it.sku + '] (' + it.uom + ')'"></option>
                                                    </template>
                                                </select>
                                                <div class="fs-9 text-secondary font-monospace mt-1" x-show="row.item_id" x-text="'Harga Ref: ' + formatRupiah(row.price)"></div>
                                            </td>
                                            <td class="text-center py-1.5">
                                                <input type="number" 
                                                       :name="'items[' + index + '][qty]'" 
                                                       x-model.number="row.qty" 
                                                       @input="onCreateQtyChange(index)" 
                                                       min="1" 
                                                       required 
                                                       class="form-control form-control-sm text-center fw-bold font-monospace fs-8">
                                            </td>
                                            <td class="text-end font-monospace pe-2 py-1.5">
                                                <span class="fw-semibold text-body" x-text="formatRupiah(row.subtotal)"></span>
                                            </td>
                                            <td class="text-center py-1.5">
                                                <button type="button" 
                                                        @click="removeCreateRow(index)" 
                                                        :disabled="createRows.length <= 1" 
                                                        class="btn btn-sm text-danger py-0 px-1" 
                                                        title="Hapus baris barang">
                                                    <i class="bi bi-trash fs-8"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-top">
                    <div class="fs-8">
                        <span class="text-secondary">Total Estimasi:</span>
                        <span class="fw-bold font-monospace fs-7 text-danger ms-1" x-text="formatRupiah(createTotal)"></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" @click="createModal = false" class="btn btn-sm btn-outline-secondary px-3">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                            <i class="bi bi-send me-1"></i> Submit Order
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: DETAIL ORDER ==================== -->
    <div x-show="viewModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-3xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-eye fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Detail Order: <span class="font-monospace text-danger" x-text="viewOrder ? viewOrder.order_number : '-'"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Rincian pesanan dan barang permintaan cabang</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge fs-8 text-uppercase" :class="viewOrder ? getBadgeClass(viewOrder.status) : ''" x-text="viewOrder ? (viewOrder.status || '').replace(/_/g, ' ') : ''"></span>
                    <button type="button" @click="viewModal = false" class="btn-close ms-2" aria-label="Close"></button>
                </div>
            </div>

            <div class="card-body p-3.5 p-md-4 space-y-3">
                <!-- Order Summary Cards -->
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="row g-2 fs-8">
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Unit Pemohon:</span>
                            <strong class="text-body" x-text="viewOrder?.requesting_organization?.name || '-'"></strong>
                            <div class="font-monospace text-secondary fs-9" x-text="viewOrder?.requesting_organization?.code ? 'Kode: ' + viewOrder.requesting_organization.code : ''"></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Dibuat Oleh:</span>
                            <span class="text-body fw-semibold" x-text="viewOrder?.requester?.name || '-'"></span>
                            <div class="font-monospace text-secondary fs-9" x-text="viewOrder?.requester?.nip ? 'NIP: ' + viewOrder.requester.nip : ''"></div>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Prioritas Permintaan:</span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-8" x-text="viewOrder?.priority || 'NORMAL'"></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Tanggal Transaksi:</span>
                            <span class="text-body font-monospace" x-text="viewOrder?.created_at ? new Date(viewOrder.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'"></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Target Kebutuhan:</span>
                            <span class="text-body font-monospace" x-text="viewOrder?.required_date ? viewOrder.required_date.substring(0, 10) : '-'"></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Total Nilai Estimasi:</span>
                            <span class="fw-bold font-monospace fs-7 text-danger" x-text="formatRupiah(viewOrder?.total_estimated_value)"></span>
                        </div>
                    </div>

                    <template x-if="viewOrder?.notes">
                        <div class="mt-2.5 pt-2 border-top border-secondary-subtle fs-8">
                            <span class="text-secondary fw-semibold">Catatan:</span>
                            <span class="text-body ms-1 fst-italic" x-text="viewOrder.notes"></span>
                        </div>
                    </template>
                </div>

                <!-- Items Table -->
                <div>
                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1.5">Rincian Barang Diminta</label>
                    <div class="table-responsive rounded border border-secondary-subtle" style="max-height: 220px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0 fs-8">
                            <thead class="bg-body-tertiary text-secondary sticky-top">
                                <tr>
                                    <th class="ps-3" style="width: 40px;">No</th>
                                    <th>Nama / SKU Barang</th>
                                    <th class="text-center" style="width: 90px;">Satuan</th>
                                    <th class="text-center" style="width: 90px;">Jumlah</th>
                                    <th class="text-end" style="width: 130px;">Harga Ref</th>
                                    <th class="text-end pe-3" style="width: 140px;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(it, idx) in (viewOrder?.items || [])" :key="idx">
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
                                        <td class="text-end font-monospace text-secondary" x-text="formatRupiah(it.unit_price_ref || it.item?.estimated_unit_price)"></td>
                                        <td class="text-end font-monospace pe-3 fw-bold text-body" x-text="formatRupiah(it.subtotal_ref || ((it.unit_price_ref || it.item?.estimated_unit_price || 0) * it.qty_requested))"></td>
                                    </tr>
                                </template>
                                <template x-if="!viewOrder?.items || viewOrder.items.length === 0">
                                    <tr>
                                        <td colspan="6" class="text-center py-3 text-secondary">Tidak ada rincian item.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-top">
                <a :href="'/orders/' + (viewOrder ? viewOrder.id : '') + '/print'" 
                   target="_blank" 
                   class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 shadow-xs">
                    <i class="bi bi-printer"></i>
                    <span>Cetak Order</span>
                </a>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: EDIT ORDER ==================== -->
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
                            Edit Order: <span class="font-monospace text-danger" x-text="editOrder.order_number"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Perbarui prioritas, jadwal kebutuhan, atau catatan pesanan</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge fs-8 text-uppercase" :class="editOrder.badge_class" x-text="editOrder.status_label"></span>
                    <button type="button" @click="editModal = false" class="btn-close ms-2" aria-label="Close"></button>
                </div>
            </div>

            <!-- Form -->
            <form :action="editOrder.update_url" method="POST">
                @csrf
                @method('PUT')

                <div class="card-body p-3.5 p-md-4 space-y-3">
                    <!-- Status Notice if not editable -->
                    <template x-if="!editOrder.is_editable">
                        <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-6 flex-shrink-0"></i>
                            <div>
                                Order berstatus <strong x-text="editOrder.status_label"></strong>. Prioritas dan tanggal kebutuhan terkunci; hanya catatan operasional yang dapat diubah.
                            </div>
                        </div>
                    </template>

                    <!-- Order Summary Box -->
                    <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="row g-2 fs-8">
                            <div class="col-6 col-md-3">
                                <span class="text-secondary d-block">Unit Pemohon:</span>
                                <strong class="text-body" x-text="editOrder.organization_name"></strong>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="text-secondary d-block">Dibuat Oleh:</span>
                                <span class="text-body fw-semibold" x-text="editOrder.requester_name"></span>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="text-secondary d-block">Waktu Transaksi:</span>
                                <span class="text-body" x-text="editOrder.created_at_formatted"></span>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="text-secondary d-block">Total Estimasi:</span>
                                <span class="fw-bold font-monospace text-danger" x-text="formatRupiah(editOrder.total_estimated_value)"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Priority & Required Date -->
                    <div class="row g-2.5">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                                Prioritas Permintaan <span class="text-danger" x-show="editOrder.is_editable">*</span>
                            </label>
                            <select name="priority" 
                                    class="form-select form-select-sm fs-8" 
                                    x-model="editOrder.priority" 
                                    :disabled="!editOrder.is_editable" 
                                    required>
                                <option value="NORMAL">Normal (Standar Operasional)</option>
                                <option value="HIGH">High (Prioritas Tinggi)</option>
                                <option value="URGENT">Urgent (Kebutuhan Sangat Mendesak)</option>
                            </select>
                            <template x-if="!editOrder.is_editable">
                                <input type="hidden" name="priority" :value="editOrder.priority">
                            </template>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                                Tanggal Kebutuhan
                            </label>
                            <input type="date" 
                                   name="required_date" 
                                   class="form-control form-control-sm fs-8" 
                                   x-model="editOrder.required_date" 
                                   :disabled="!editOrder.is_editable">
                            <template x-if="!editOrder.is_editable">
                                <input type="hidden" name="required_date" :value="editOrder.required_date">
                            </template>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Catatan & Keterangan Tambahan
                        </label>
                        <input type="text" 
                               name="notes" 
                               class="form-control form-control-sm fs-8" 
                               x-model="editOrder.notes">
                    </div>

                    <!-- Items Editor in Order -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-0">
                                Daftar Barang Pesanan (<span x-text="editOrder.items ? editOrder.items.length : 0"></span> item)
                            </label>
                            <template x-if="editOrder.is_editable">
                                <button type="button" @click="addItemRow()" class="btn btn-sm btn-outline-danger py-1 px-2 fs-8 fw-semibold">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Item
                                </button>
                            </template>
                        </div>

                        <div class="table-responsive border rounded bg-body mb-2" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0 fs-8">
                                <thead class="table-light text-secondary fs-9 text-uppercase sticky-top">
                                    <tr>
                                        <th class="ps-3 py-1.5">Pilih Barang / Item</th>
                                        <th class="text-center py-1.5" style="width: 140px;">Jumlah (Qty)</th>
                                        <th class="text-end py-1.5" style="width: 140px;">Subtotal</th>
                                        <template x-if="editOrder.is_editable">
                                            <th class="text-center py-1.5" style="width: 45px;">Hapus</th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in editOrder.items" :key="index">
                                        <tr>
                                            <td class="ps-3 py-1.5">
                                                <template x-if="editOrder.is_editable">
                                                    <div>
                                                        <select :name="'items[' + index + '][item_id]'" 
                                                                x-model="item.item_id" 
                                                                @change="onItemChange(index)" 
                                                                class="form-select form-select-sm" 
                                                                required>
                                                            <option value="">-- Pilih Barang / Item --</option>
                                                            <template x-for="it in itemsCatalog" :key="it.id">
                                                                <option :value="it.id" x-text="it.name + ' [' + it.sku + '] (' + it.uom + ')'"></option>
                                                            </template>
                                                        </select>
                                                        <div class="fs-9 text-secondary font-monospace mt-1" x-show="item.item_id" x-text="'Harga Ref: ' + formatRupiah(item.price)"></div>
                                                    </div>
                                                </template>
                                                <template x-if="!editOrder.is_editable">
                                                    <div>
                                                        <span class="fw-semibold text-body" x-text="(itemsCatalog.find(c => c.id == item.item_id)?.name) || 'Item #' + item.item_id"></span>
                                                        <div class="fs-9 text-secondary font-monospace" x-text="(itemsCatalog.find(c => c.id == item.item_id)?.sku) || ''"></div>
                                                    </div>
                                                </template>
                                            </td>
                                            <td class="text-center py-1.5">
                                                <template x-if="editOrder.is_editable">
                                                    <input type="number" 
                                                           :name="'items[' + index + '][qty]'" 
                                                           x-model.number="item.qty" 
                                                           @input="onQtyChange(index)" 
                                                           min="1" 
                                                           required 
                                                           class="form-control form-control-sm text-center fw-bold font-monospace fs-8">
                                                </template>
                                                <template x-if="!editOrder.is_editable">
                                                    <span class="fw-bold font-monospace" x-text="item.qty"></span>
                                                </template>
                                            </td>
                                            <td class="text-end font-monospace pe-2 py-1.5">
                                                <span class="fw-semibold text-body" x-text="formatRupiah(item.subtotal)"></span>
                                            </td>
                                            <template x-if="editOrder.is_editable">
                                                <td class="text-center py-1.5">
                                                    <button type="button" 
                                                            @click="removeItemRow(index)" 
                                                            class="btn btn-sm text-danger py-0 px-1" 
                                                            :disabled="editOrder.items.length <= 1" 
                                                            title="Hapus baris barang">
                                                        <i class="bi bi-trash fs-8"></i>
                                                    </button>
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                    <template x-if="!editOrder.items || editOrder.items.length === 0">
                                        <tr>
                                            <td colspan="4" class="text-center py-3 text-secondary">Tidak ada rincian item.</td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-xs px-3">
                        <i class="bi bi-save me-1"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: HAPUS ORDER ==================== -->
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
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Order</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="deleteOrder.delete_url" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin menghapus order permintaan berikut dari sistem?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">Nomor & Unit Pemohon:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deleteOrder.order_number"></div>
                        <div class="fs-8 text-body fw-semibold" x-text="deleteOrder.organization_name"></div>
                        <div class="fs-8 text-secondary font-monospace" x-text="formatRupiah(deleteOrder.total_estimated_value)"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-shield-exclamation text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Order yang telah dihapus tidak dapat diproses lagi dan rincian alokasi barang akan dibatalkan.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Order
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
