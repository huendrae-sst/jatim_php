@extends('layouts.app')
@section('title', 'Vendor & Ekspedisi')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('master.vendors') }}" class="text-decoration-none text-danger">Master Data</a></li>
    <li class="breadcrumb-item active" aria-current="page">Vendor & Ekspedisi</li>
@endsection

@section('content')
@php
    $avgVendorSla = $allVendors->count() > 0 ? round($allVendors->avg('sla_days'), 1) : 0;
    $avgVendorRating = $allVendors->count() > 0 ? number_format($allVendors->avg('rating'), 1) : '0.0';
@endphp

<div class="space-y-4" x-data="{
    activeTab: (new URLSearchParams(window.location.search)).get('tab') || window.location.hash.replace('#', '') || '{{ $tab ?? 'vendors' }}',

    init() {
        const validTabs = ['vendors', 'couriers'];
        const urlParams = new URLSearchParams(window.location.search);
        let tabFromUrl = urlParams.get('tab') || window.location.hash.replace('#', '');
        if (!tabFromUrl && sessionStorage.getItem('jatim_tab_' + window.location.pathname)) {
            tabFromUrl = sessionStorage.getItem('jatim_tab_' + window.location.pathname);
        }
        if (tabFromUrl && validTabs.includes(tabFromUrl)) {
            this.activeTab = tabFromUrl;
        }
        this.$watch('activeTab', (val) => {
            if (validTabs.includes(val)) {
                sessionStorage.setItem('jatim_tab_' + window.location.pathname, val);
                const url = new URL(window.location);
                url.searchParams.set('tab', val);
                window.history.replaceState({}, '', url);
            }
        });
        window.addEventListener('popstate', () => {
            const p = new URLSearchParams(window.location.search);
            const t = p.get('tab') || window.location.hash.replace('#', '');
            if (t && validTabs.includes(t)) {
                this.activeTab = t;
            }
        });
    },

    createVendorModalOpen: false,
    createCourierModalOpen: false,

    viewVendorModalOpen: false,
    selectedVendor: null,

    viewCourierModalOpen: false,
    selectedCourier: null,

    openVendorModal(v) {
        this.selectedVendor = v;
        this.viewVendorModalOpen = true;
    },

    openCourierModal(c) {
        this.selectedCourier = c;
        this.viewCourierModalOpen = true;
    }
}">

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header with Navigation Tabs -->
        <div class="card-header bg-body p-2 px-3 border-bottom d-flex flex-column flex-md-row md:items-center justify-content-between gap-2">
            <ul class="nav nav-pills nav-pills-scroll flex-nowrap card-header-pills m-0 pb-1 pb-md-0">
                <li class="nav-item">
                    <button type="button" 
                            @click="activeTab = 'vendors'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeTab === 'vendors' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-building"></i>
                        <span>Vendor Rekanan Pengadaan</span>
                        <span class="badge" :class="activeTab === 'vendors' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $vendors->total() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" 
                            @click="activeTab = 'couriers'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeTab === 'couriers' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-truck"></i>
                        <span>Jasa Ekspedisi & Distribusi</span>
                        <span class="badge" :class="activeTab === 'couriers' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $couriers->total() }}</span>
                    </button>
                </li>
            </ul>
            <div class="card-tools ms-md-auto d-flex align-items-center gap-2">
                <template x-if="activeTab === 'vendors'">
                    <button @click="createVendorModalOpen = true" type="button" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Vendor Rekanan</span>
                    </button>
                </template>
                <template x-if="activeTab === 'couriers'">
                    <button @click="createCourierModalOpen = true" type="button" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Mitra Ekspedisi</span>
                    </button>
                </template>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold shadow-xs d-inline-flex align-items-center gap-1" title="Cetak Halaman Ini">
                    <i class="bi bi-printer"></i>
                    <span>Cetak</span>
                </button>
            </div>
        </div>

        <!-- ==================== TAB 1: VENDORS ==================== -->
        <div x-show="activeTab === 'vendors'">
            <!-- Filter & Search Toolbar -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <form action="{{ route('master.vendors') }}" method="GET">
                    <input type="hidden" name="tab" value="vendors">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <div class="row g-2 align-items-center">
                        <!-- Rating Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-star"></i></span>
                                <select name="rating" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Rating Vendor</option>
                                    <option value="4.5" {{ $vendorRating === '4.5' ? 'selected' : '' }}>Rating &ge; 4.5 Bintang</option>
                                    <option value="4.0" {{ $vendorRating === '4.0' ? 'selected' : '' }}>Rating &ge; 4.0 Bintang</option>
                                    <option value="UNDER_4" {{ $vendorRating === 'UNDER_4' ? 'selected' : '' }}>Rating &lt; 4.0 Bintang</option>
                                </select>
                            </div>
                        </div>

                        <!-- Reset Button -->
                        @if($vendorSearch || ($vendorRating && $vendorRating !== 'ALL'))
                            <div class="col-auto">
                                <a href="{{ route('master.vendors', ['tab' => 'vendors']) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                    <i class="bi bi-x-circle me-1"></i> Reset
                                </a>
                            </div>
                        @endif

                        <!-- Search Bar -->
                        <div class="col-12 col-md ms-md-auto">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                                <input type="text" 
                                       name="vendor_search" 
                                       value="{{ $vendorSearch }}" 
                                       class="form-control form-control-sm border-start-0 fs-8">
                                <button type="submit" class="btn btn-danger btn-sm">Cari</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 fs-7">
                        <thead class="bg-body-tertiary text-secondary border-bottom">
                            <tr>
                                <th class="ps-4 py-3" style="width: 280px;">Kode & Nama Vendor</th>
                                <th class="py-3" style="width: 140px;">SLA Pengadaan</th>
                                <th class="py-3" style="width: 160px;">Termin Pembayaran</th>
                                <th class="py-3" style="min-width: 220px;">Alamat Operasional</th>
                                <th class="py-3 text-center" style="width: 120px;">Rating</th>
                                <th class="py-3 text-center" style="width: 100px;">Status</th>
                                <th class="pe-4 py-3 text-center" style="width: 80px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($vendors as $v)
                                <tr>
                                    <!-- Vendor Name & Code -->
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fw-bold">{{ $v->code }}</span>
                                            <span class="fw-bold text-body">{{ $v->name }}</span>
                                        </div>
                                    </td>

                                    <!-- SLA -->
                                    <td>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-8">
                                            <i class="bi bi-clock me-1"></i>{{ $v->sla_days }} Hari
                                        </span>
                                    </td>

                                    <!-- Payment Terms -->
                                    <td>
                                        <span class="badge bg-body-secondary text-secondary-emphasis font-monospace border">{{ $v->payment_terms ?? '-' }}</span>
                                    </td>

                                    <!-- Address -->
                                    <td>
                                        <div class="text-secondary fs-9 text-truncate" style="max-width: 260px;" title="{{ $v->address ?? '-' }}">{{ $v->address ?? '-' }}</div>
                                    </td>

                                    <!-- Rating -->
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center gap-1 bg-amber-50 text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-0.5 fs-8 fw-bold">
                                            <i class="bi bi-star-fill text-warning"></i>
                                            <span>{{ number_format($v->rating, 1) }}</span>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="text-center">
                                        <span class="badge fs-9 py-1 px-2 {{ $v->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $v->is_active ? 'Aktif' : 'Non-Aktif' }}
                                        </span>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="pe-4 text-center">
                                        <button type="button" @click="openVendorModal({{ Js::from($v) }})" class="btn-action-icon text-secondary" title="Detail Vendor">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-secondary">
                                        <i class="bi bi-building fs-1 d-block mb-2 text-secondary-subtle"></i>
                                        <p class="fw-bold mb-1">Tidak ada data vendor rekanan ditemukan</p>
                                        <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau bersihkan filter.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card Footer / Pagination -->
            <x-pagination-footer :paginator="$vendors" :perPage="$perPage" tab="vendors" />
        </div>

        <!-- ==================== TAB 2: COURIERS ==================== -->
        <div x-show="activeTab === 'couriers'">
            <!-- Filter & Search Toolbar -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <form action="{{ route('master.vendors') }}" method="GET">
                    <input type="hidden" name="tab" value="couriers">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <div class="row g-2 align-items-center">
                        <!-- Status Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                                <select name="courier_status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="">Semua Status Mitra</option>
                                    <option value="ACTIVE" {{ ($courierStatus ?? '') === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                    <option value="INACTIVE" {{ ($courierStatus ?? '') === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                                </select>
                            </div>
                        </div>

                        <!-- Reset Button -->
                        @if($courierSearch || !empty($courierStatus))
                            <div class="col-auto">
                                <a href="{{ route('master.vendors', ['tab' => 'couriers']) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                    <i class="bi bi-x-circle me-1"></i> Reset
                                </a>
                            </div>
                        @endif

                        <!-- Search Bar -->
                        <div class="col-12 col-md ms-md-auto">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                                <input type="text" 
                                       name="courier_search" 
                                       value="{{ $courierSearch }}" 
                                       class="form-control form-control-sm border-start-0 fs-8">
                                <button type="submit" class="btn btn-danger btn-sm">Cari</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 fs-7">
                        <thead class="bg-body-tertiary text-secondary border-bottom">
                            <tr>
                                <th class="ps-4 py-3" style="width: 260px;">Kode & Nama Ekspedisi</th>
                                <th class="py-3" style="width: 160px;">SLA Estimasi Pengiriman</th>
                                <th class="py-3" style="min-width: 240px;">Jenis Layanan Pengiriman</th>
                                <th class="py-3 text-center" style="width: 140px;">Total Pengiriman</th>
                                <th class="py-3 text-center" style="width: 100px;">Status</th>
                                <th class="pe-4 py-3 text-center" style="width: 80px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($couriers as $c)
                                <tr>
                                    <!-- Courier Name & Code -->
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fw-bold">{{ $c->code }}</span>
                                            <span class="fw-bold text-body">{{ $c->name }}</span>
                                        </div>
                                    </td>

                                    <!-- SLA -->
                                    <td>
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fs-8">
                                            <i class="bi bi-clock-history me-1"></i>{{ $c->sla_days }} Hari
                                        </span>
                                    </td>

                                    <!-- Service Types -->
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @if(is_array($c->service_types))
                                                @foreach($c->service_types as $st)
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-9 font-monospace">{{ $st }}</span>
                                                @endforeach
                                            @else
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-9 font-monospace">REGULER</span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Total Shipments -->
                                    <td class="text-center">
                                        <span class="badge bg-body-secondary text-secondary-emphasis fs-8">
                                            <i class="bi bi-box me-1"></i>{{ $c->shipments_count ?? 0 }} Surat Jalan
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td class="text-center">
                                        <span class="badge fs-9 py-1 px-2 {{ $c->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $c->is_active ? 'Aktif' : 'Non-Aktif' }}
                                        </span>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="pe-4 text-center">
                                        <button type="button" @click="openCourierModal({{ Js::from($c) }})" class="btn-action-icon text-secondary" title="Detail Ekspedisi">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-secondary">
                                        <i class="bi bi-truck fs-1 d-block mb-2 text-secondary-subtle"></i>
                                        <p class="fw-bold mb-1">Tidak ada data jasa ekspedisi ditemukan</p>
                                        <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card Footer / Pagination -->
            <x-pagination-footer :paginator="$couriers" :perPage="$perPage" tab="couriers" />
        </div>
    </div>

    <!-- ==================== MODAL: DETAIL VENDOR ==================== -->
    <div x-show="viewVendorModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewVendorModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-building fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Profil Rekanan Pengadaan</h6>
                        <span class="fs-8 text-secondary" x-text="selectedVendor ? selectedVendor.code : ''"></span>
                    </div>
                </div>
                <button type="button" @click="viewVendorModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fs-8 text-secondary mb-0.5">Nama Perusahaan:</div>
                            <h5 class="fw-bold text-body mb-0" x-text="selectedVendor ? selectedVendor.name : '-'"></h5>
                        </div>
                        <div class="d-inline-flex align-items-center gap-1 bg-amber-50 text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1 fs-8 fw-bold">
                            <i class="bi bi-star-fill text-warning"></i>
                            <span x-text="selectedVendor ? Number(selectedVendor.rating).toFixed(1) : '-'"></span> / 5.0
                        </div>
                    </div>
                </div>

                <div class="row g-3 fs-8">
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Kode Vendor:</span>
                        <span class="font-monospace fw-bold text-body" x-text="selectedVendor ? selectedVendor.code : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">SLA Pengadaan:</span>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" x-text="selectedVendor ? selectedVendor.sla_days + ' Hari Kerja' : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Termin Pembayaran:</span>
                        <span class="font-monospace fw-semibold text-body" x-text="selectedVendor ? selectedVendor.payment_terms : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Status Operasional:</span>
                        <span class="badge" :class="selectedVendor && selectedVendor.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="selectedVendor && selectedVendor.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                    </div>
                    <div class="col-12">
                        <span class="text-secondary d-block">Alamat Kantor / Pabrik:</span>
                        <span class="text-body" x-text="selectedVendor ? (selectedVendor.address || '-') : '-'"></span>
                    </div>
                    <div class="col-12">
                        <span class="text-secondary d-block">Total Purchase Order (PO):</span>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" x-text="selectedVendor ? (selectedVendor.purchase_orders_count || 0) + ' Transaksi PO' : '0'"></span>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top">
                <button type="button" @click="viewVendorModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: DETAIL EKSPEDISI ==================== -->
    <div x-show="viewCourierModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewCourierModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-info-subtle text-info-emphasis p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-truck fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Profil Mitra Ekspedisi</h6>
                        <span class="fs-8 text-secondary" x-text="selectedCourier ? selectedCourier.code : ''"></span>
                    </div>
                </div>
                <button type="button" @click="viewCourierModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="fs-8 text-secondary mb-0.5">Nama Jasa Kurir:</div>
                    <h5 class="fw-bold text-body mb-0" x-text="selectedCourier ? selectedCourier.name : '-'"></h5>
                </div>

                <div class="row g-3 fs-8">
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Kode Kurir:</span>
                        <span class="font-monospace fw-bold text-body" x-text="selectedCourier ? selectedCourier.code : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">SLA Distribusi:</span>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" x-text="selectedCourier ? selectedCourier.sla_days + ' Hari Kerja' : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Status Operasional:</span>
                        <span class="badge" :class="selectedCourier && selectedCourier.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="selectedCourier && selectedCourier.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Pengiriman Terproses:</span>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis" x-text="selectedCourier ? (selectedCourier.shipments_count || 0) + ' Surat Jalan' : '0'"></span>
                    </div>
                    <div class="col-12">
                        <span class="text-secondary d-block mb-1">Cakupan Layanan Pengiriman:</span>
                        <div class="d-flex flex-wrap gap-1">
                            <template x-if="selectedCourier && selectedCourier.service_types">
                                <template x-for="st in selectedCourier.service_types" :key="st">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-8 font-monospace px-2 py-1" x-text="st"></span>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top">
                <button type="button" @click="viewCourierModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- CREATE VENDOR MODAL (AdminLTE 4 & Dark/Light Aware) -->
    <div x-show="createVendorModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createVendorModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-building fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Vendor Rekanan Baru</h6>
                        <span class="fs-8 text-secondary">Registrasi mitra penyedia barang persediaan</span>
                    </div>
                </div>
                <button type="button" @click="createVendorModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form action="{{ route('master.vendors.store') }}" method="POST">
                @csrf

                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Row 1: Kode & Nama -->
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Vendor <span class="text-danger">*</span></label>
                            <input type="text" name="code" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Perusahaan / Rekanan <span class="text-danger">*</span></label>
                            <input type="text" name="name" required class="form-control form-control-sm fw-bold fs-8">
                        </div>
                    </div>

                    <!-- Row 2: SLA & Termin Pembayaran -->
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">SLA Pengadaan (Hari) <span class="text-danger">*</span></label>
                            <input type="number" name="sla_days" value="7" min="1" required class="form-control form-control-sm fs-8">
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Termin Pembayaran <span class="text-danger">*</span></label>
                            <select name="payment_terms" required class="form-select form-select-sm fs-8">
                                <option value="TOP 30 Hari" selected>TOP 30 Hari</option>
                                <option value="TOP 14 Hari">TOP 14 Hari</option>
                                <option value="TOP 45 Hari">TOP 45 Hari</option>
                                <option value="TOP 60 Hari">TOP 60 Hari</option>
                                <option value="Cash On Delivery (COD)">Cash On Delivery (COD)</option>
                                <option value="Pembayaran di Muka (Advance)">Pembayaran di Muka (Advance)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 3: Rating & Telepon -->
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Rating Kinerja Awal (1 - 5)</label>
                            <input type="number" name="rating" value="5.0" min="1" max="5" step="0.1" class="form-control form-control-sm fs-8">
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">No. Telepon / Kontak</label>
                            <input type="text" name="phone" class="form-control form-control-sm fs-8">
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Email Resmi</label>
                        <input type="email" name="email" class="form-control form-control-sm fs-8">
                    </div>

                    <!-- Alamat -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alamat Kantor Operasional</label>
                        <textarea name="address" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createVendorModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Vendor Rekanan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CREATE COURIER MODAL (AdminLTE 4 & Dark/Light Aware) -->
    <div x-show="createCourierModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createCourierModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-truck fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Mitra Ekspedisi Baru</h6>
                        <span class="fs-8 text-secondary">Registrasi jasa pengiriman logistik & distribusi</span>
                    </div>
                </div>
                <button type="button" @click="createCourierModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form action="{{ route('master.couriers.store') }}" method="POST">
                @csrf

                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Row 1: Kode & Nama -->
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Ekspedisi <span class="text-danger">*</span></label>
                            <input type="text" name="code" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Jasa Ekspedisi <span class="text-danger">*</span></label>
                            <input type="text" name="name" required class="form-control form-control-sm fw-bold fs-8">
                        </div>
                    </div>

                    <!-- Row 2: SLA & Telepon -->
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">SLA Distribusi (Hari) <span class="text-danger">*</span></label>
                            <input type="number" name="sla_days" value="2" min="1" required class="form-control form-control-sm fs-8">
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">No. Kontak / Hotline CS</label>
                            <input type="text" name="phone" class="form-control form-control-sm fs-8">
                        </div>
                    </div>

                    <!-- Row 3: Cakupan Layanan -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-2">Cakupan Layanan Pengiriman</label>
                        <div class="d-flex flex-wrap gap-3 p-3 rounded-2 border bg-body-tertiary">
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" name="service_types[]" value="REGULER" id="srv_reguler" checked>
                                <label class="form-check-label fs-8 fw-semibold" for="srv_reguler">REGULER</label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" name="service_types[]" value="EXPRESS" id="srv_express" checked>
                                <label class="form-check-label fs-8 fw-semibold" for="srv_express">EXPRESS</label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" name="service_types[]" value="CARGO" id="srv_cargo">
                                <label class="form-check-label fs-8 fw-semibold" for="srv_cargo">CARGO</label>
                            </div>
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" name="service_types[]" value="INTERNAL" id="srv_internal">
                                <label class="form-check-label fs-8 fw-semibold" for="srv_internal">ARMADA INTERNAL</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createCourierModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Mitra Ekspedisi
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
