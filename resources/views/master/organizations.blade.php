@extends('layouts.app')
@section('title', 'Master Data Unit Kerja & Gudang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item text-secondary">Master Data</li>
    <li class="breadcrumb-item active" aria-current="page">Unit Kerja & Gudang</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    activeMainTab: (new URLSearchParams(window.location.search)).get('tab') || window.location.hash.replace('#', '') || '{{ $tab ?? 'organizations' }}',

    init() {
        const validTabs = ['organizations', 'warehouses'];
        const urlParams = new URLSearchParams(window.location.search);
        let tabFromUrl = urlParams.get('tab') || window.location.hash.replace('#', '');
        if (!tabFromUrl && sessionStorage.getItem('jatim_tab_' + window.location.pathname)) {
            tabFromUrl = sessionStorage.getItem('jatim_tab_' + window.location.pathname);
        }
        if (tabFromUrl && validTabs.includes(tabFromUrl)) {
            this.activeMainTab = tabFromUrl;
        }
        this.$watch('activeMainTab', (val) => {
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
                this.activeMainTab = t;
            }
        });
    },

    // View Org Modal
    viewOrgModalOpen: false,
    viewOrgData: null,
    openViewOrgModal(org) {
        this.viewOrgData = org;
        this.viewOrgModalOpen = true;
    },

    // Create Org Modal State
    createOrgModalOpen: false,
    
    // Edit Org Modal State
    editOrgModalOpen: false,
    editOrgForm: {
        id: null,
        code: '',
        name: '',
        type: 'SUB_BRANCH',
        parent_id: '',
        cost_center_code: '',
        city: 'Surabaya',
        address: '',
        phone: '',
        is_active: 1,
    },
    openEditOrgModal(org) {
        this.editOrgForm = {
            id: org.id,
            code: org.code,
            name: org.name,
            type: org.type,
            parent_id: org.parent_id || '',
            cost_center_code: org.cost_center_code || '',
            city: org.city || 'Surabaya',
            address: org.address || '',
            phone: org.phone || '',
            is_active: org.is_active ? 1 : 0,
        };
        this.editOrgModalOpen = true;
    },

    // Delete Org Modal State
    deleteOrgModalOpen: false,
    deleteOrgForm: {
        id: null,
        code: '',
        name: '',
        users_count: 0,
        warehouses_count: 0
    },
    openDeleteOrgModal(org) {
        this.deleteOrgForm = {
            id: org.id,
            code: org.code,
            name: org.name,
            users_count: org.users ? org.users.length : 0,
            warehouses_count: org.warehouses ? org.warehouses.length : 0
        };
        this.deleteOrgModalOpen = true;
    },

    // View Warehouse Modal
    viewWhModalOpen: false,
    viewWhData: null,
    openViewWhModal(wh) {
        this.viewWhData = wh;
        this.viewWhModalOpen = true;
    },

    // Create Warehouse Modal State
    createWhModalOpen: false,
    createWhForm: {
        organization_id: '',
        code: '',
        name: '',
        type: 'BRANCH_STORAGE',
        address: '',
    },
    openCreateWhForOrg(orgId) {
        this.createWhForm.organization_id = orgId;
        this.createWhModalOpen = true;
    },

    // Edit Warehouse Modal State
    editWhModalOpen: false,
    editWhForm: {
        id: null,
        organization_id: '',
        code: '',
        name: '',
        type: 'BRANCH_STORAGE',
        address: '',
        is_active: 1,
    },
    openEditWhModal(wh) {
        this.editWhForm = {
            id: wh.id,
            organization_id: wh.organization_id,
            code: wh.code,
            name: wh.name,
            type: wh.type,
            address: wh.address || '',
            is_active: wh.is_active ? 1 : 0,
        };
        this.editWhModalOpen = true;
    },

    // Delete Warehouse Modal State
    deleteWhModalOpen: false,
    deleteWhForm: {
        id: null,
        code: '',
        name: '',
        stock_count: 0
    },
    openDeleteWhModal(wh) {
        this.deleteWhForm = {
            id: wh.id,
            code: wh.code,
            name: wh.name,
            stock_count: wh.stock_balances ? wh.stock_balances.length : 0
        };
        this.deleteWhModalOpen = true;
    }
}">

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header with Navigation Tabs (Mobile-first responsive) -->
        <div class="card-header bg-body p-2 px-3 border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2">
            <ul class="nav nav-pills card-header-pills nav-pills-scroll m-0" role="tablist" aria-label="Tabs Master Organisasi">
                <li class="nav-item">
                    <button type="button" 
                            @click="activeMainTab = 'organizations'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeMainTab === 'organizations' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-diagram-3"></i>
                        <span>Unit Kerja</span>
                        <span class="badge" :class="activeMainTab === 'organizations' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $organizations->total() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" 
                            @click="activeMainTab = 'warehouses'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeMainTab === 'warehouses' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-box-seam"></i>
                        <span>Lokasi Gudang</span>
                        <span class="badge" :class="activeMainTab === 'warehouses' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $warehouses->total() }}</span>
                    </button>
                </li>
            </ul>

            <!-- Right: Action Button -->
            <div class="card-tools ms-md-auto d-flex align-items-center gap-2">
                <template x-if="activeMainTab === 'organizations'">
                    <button @click="createOrgModalOpen = true" type="button" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Unit Kerja Baru</span>
                    </button>
                </template>
                <template x-if="activeMainTab === 'warehouses'">
                    <button @click="createWhModalOpen = true" type="button" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Gudang Baru</span>
                    </button>
                </template>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold shadow-xs d-inline-flex align-items-center gap-1" title="Cetak Halaman Ini">
                    <i class="bi bi-printer"></i>
                    <span>Cetak</span>
                </button>
            </div>
        </div>

        <!-- ==================== TAB 1: UNIT KERJA ==================== -->
        <div x-show="activeMainTab === 'organizations'">
            <!-- Filter & Search Toolbar -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <form action="{{ route('master.organizations') }}" method="GET">
                    <input type="hidden" name="tab" value="organizations">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <div class="row g-2 align-items-center">
                        <!-- Type Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-funnel"></i></span>
                                <select name="type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Tipe Organisasi</option>
                                    <option value="HEAD_OFFICE" {{ $orgType === 'HEAD_OFFICE' ? 'selected' : '' }}>Kantor Pusat (HEAD_OFFICE)</option>
                                    <option value="MAIN_BRANCH" {{ $orgType === 'MAIN_BRANCH' ? 'selected' : '' }}>Cabang Utama (MAIN_BRANCH)</option>
                                    <option value="SUB_BRANCH" {{ $orgType === 'SUB_BRANCH' ? 'selected' : '' }}>Cabang Pembantu (SUB_BRANCH)</option>
                                    <option value="WAREHOUSE" {{ $orgType === 'WAREHOUSE' ? 'selected' : '' }}>Hub Logistik (WAREHOUSE)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="col-12 col-sm-6 col-md-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                                <select name="status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="">Semua Status</option>
                                    <option value="ACTIVE" {{ $orgStatus === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                    <option value="INACTIVE" {{ $orgStatus === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                                </select>
                            </div>
                        </div>

                        <!-- Reset Button -->
                        @if($orgSearch || ($orgType && $orgType !== 'ALL') || $orgStatus)
                            <div class="col-auto">
                                <a href="{{ route('master.organizations', ['tab' => 'organizations']) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                       value="{{ $orgSearch }}" 
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
                                <th class="ps-3 py-3" style="width: 240px;">Kode & Nama Unit</th>
                                <th class="py-3" style="width: 140px;">Tipe Organisasi</th>
                                <th class="py-3" style="min-width: 180px;">Induk Unit (Parent)</th>
                                <th class="py-3" style="min-width: 180px;">Kota & Alamat</th>
                                <th class="text-center py-3" style="width: 120px;">Cost Center</th>
                                <th class="text-center py-3" style="width: 130px;">Gudang / User</th>
                                <th class="text-center py-3" style="width: 90px;">Status</th>
                                <th class="text-center pe-3 py-3" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($organizations as $org)
                                <tr>
                                    <!-- Kode & Nama Unit -->
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fw-bold">{{ $org->code }}</span>
                                            <div class="fw-bold text-body">{{ $org->name }}</div>
                                        </div>
                                        @if($org->phone)
                                            <div class="fs-9 text-secondary mt-0.5"><i class="bi bi-telephone me-1"></i>{{ $org->phone }}</div>
                                        @endif
                                    </td>

                                    <!-- Tipe Organisasi -->
                                    <td>
                                        <span class="badge fs-9 text-uppercase
                                            @if($org->type === 'HEAD_OFFICE') bg-danger-subtle text-danger border border-danger-subtle
                                            @elseif($org->type === 'MAIN_BRANCH') bg-primary-subtle text-primary border border-primary-subtle
                                            @elseif($org->type === 'SUB_BRANCH') bg-info-subtle text-info-emphasis border border-info-subtle
                                            @elseif($org->type === 'WAREHOUSE') bg-warning-subtle text-warning-emphasis border border-warning-subtle
                                            @else bg-secondary-subtle text-secondary border
                                            @endif">
                                            {{ str_replace('_', ' ', $org->type ?? '-') }}
                                        </span>
                                    </td>

                                    <!-- Induk Unit -->
                                    <td>
                                        @if($org->parent)
                                            <div>
                                                <div class="fw-semibold text-body">{{ $org->parent->name }}</div>
                                                <div class="font-monospace fs-9 text-secondary">{{ $org->parent->code }}</div>
                                            </div>
                                        @else
                                            <span class="text-muted fst-italic fs-8">- Tingkat Teratas -</span>
                                        @endif
                                    </td>

                                    <!-- Kota & Alamat -->
                                    <td>
                                        <div class="fw-semibold text-body">{{ $org->city }}</div>
                                        <div class="text-secondary fs-9 text-truncate" style="max-width: 220px;" title="{{ $org->address ?? '-' }}">{{ $org->address ?? '-' }}</div>
                                    </td>

                                    <!-- Cost Center -->
                                    <td class="text-center font-monospace fw-bold fs-8 text-secondary">
                                        <span class="badge text-bg-light border">{{ $org->cost_center_code ?? '-' }}</span>
                                    </td>

                                    <!-- Gudang / User -->
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="{{ route('master.organizations', ['tab' => 'warehouses', 'wh_org' => $org->id]) }}" class="btn btn-xs btn-outline-warning py-0.5 px-1.5 fs-9 fw-bold" title="Lihat Gudang Terkait">
                                                <i class="bi bi-box-seam me-1"></i><span>{{ $org->warehouses->count() }}</span>
                                            </a>
                                            <span class="badge bg-primary-subtle text-primary fs-9" title="User Pegawai">
                                                <i class="bi bi-people me-1"></i><span>{{ $org->users->count() }}</span>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="text-center">
                                        <form action="{{ route('master.organizations.toggle_status', $org->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="badge border-0 cursor-pointer fs-9 py-1 px-2 {{ $org->is_active ? 'text-bg-success' : 'text-bg-secondary' }}" 
                                                    title="Klik untuk mengubah status">
                                                {{ $org->is_active ? 'Aktif' : 'Non-Aktif' }}
                                            </button>
                                        </form>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="text-center pe-3">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <button type="button" @click="openViewOrgModal({{ Js::from($org) }})" class="btn-action-icon text-secondary" title="Detail Unit Kerja">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" @click="openEditOrgModal({{ Js::from($org) }})" class="btn-action-icon text-primary" title="Edit Unit Kerja">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" @click="openDeleteOrgModal({{ Js::from($org) }})" class="btn-action-icon text-danger" title="Hapus Unit Kerja">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-secondary">
                                        <i class="bi bi-building fs-1 d-block mb-2 text-secondary-subtle"></i>
                                        <p class="fw-bold mb-1">Tidak ada data unit kerja ditemukan</p>
                                        <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau bersihkan filter.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card Footer / Pagination -->
            <x-pagination-footer :paginator="$organizations" :perPage="$perPage" tab="organizations" />
        </div>

        <!-- ==================== TAB 2: LOKASI GUDANG ==================== -->
        <div x-show="activeMainTab === 'warehouses'">
            <!-- Filter & Search Toolbar -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <form action="{{ route('master.organizations') }}" method="GET">
                    <input type="hidden" name="tab" value="warehouses">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <div class="row g-2 align-items-center">
                        <!-- Type Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-funnel"></i></span>
                                <select name="wh_type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Tipe Gudang</option>
                                    <option value="CENTRAL_LOGISTICS" {{ $whType === 'CENTRAL_LOGISTICS' ? 'selected' : '' }}>Gudang Logistik Pusat</option>
                                    <option value="BRANCH_STORAGE" {{ $whType === 'BRANCH_STORAGE' ? 'selected' : '' }}>Penyimpanan Cabang</option>
                                </select>
                            </div>
                        </div>

                        <!-- Organization Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                                <select name="wh_org" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Unit Kerja</option>
                                    @foreach($allOrganizations as $org)
                                        <option value="{{ $org->id }}" {{ (string)$whOrg === (string)$org->id ? 'selected' : '' }}>{{ $org->name }} ({{ $org->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="col-12 col-sm-6 col-md-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                                <select name="wh_status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="">Semua Status</option>
                                    <option value="ACTIVE" {{ $whStatus === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                    <option value="INACTIVE" {{ $whStatus === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                                </select>
                            </div>
                        </div>

                        <!-- Reset Button -->
                        @if($whSearch || ($whType && $whType !== 'ALL') || ($whOrg && $whOrg !== 'ALL') || $whStatus)
                            <div class="col-auto">
                                <a href="{{ route('master.organizations', ['tab' => 'warehouses']) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                    <i class="bi bi-x-circle me-1"></i> Reset
                                </a>
                            </div>
                        @endif

                        <!-- Search Bar -->
                        <div class="col-12 col-md ms-md-auto">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                                <input type="text" 
                                       name="wh_search" 
                                       value="{{ $whSearch }}" 
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
                                <th class="ps-3 py-3" style="width: 220px;">Kode & Nama Gudang</th>
                                <th class="py-3" style="min-width: 200px;">Unit Kerja Pemilik</th>
                                <th class="py-3" style="width: 170px;">Tipe Gudang</th>
                                <th class="py-3" style="min-width: 200px;">Alamat Lokasi</th>
                                <th class="text-center py-3" style="width: 140px;">Variasi SKU Stok</th>
                                <th class="text-center py-3" style="width: 90px;">Status</th>
                                <th class="text-center pe-3 py-3" style="width: 110px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($warehouses as $wh)
                                <tr>
                                    <!-- Kode & Nama Gudang -->
                                    <td class="ps-3">
                                        <div>
                                            <span class="badge bg-warning-subtle text-warning-emphasis font-monospace fw-bold">{{ $wh->code }}</span>
                                            <div class="fw-bold text-body mt-1">{{ $wh->name }}</div>
                                        </div>
                                    </td>

                                    <!-- Unit Kerja Pemilik -->
                                    <td>
                                        <div class="fw-semibold text-body">{{ $wh->organization ? $wh->organization->name : '-' }}</div>
                                        <div class="font-monospace fs-9 text-secondary">{{ $wh->organization ? ($wh->organization->code . ' • ' . $wh->organization->city) : '-' }}</div>
                                    </td>

                                    <!-- Tipe Gudang -->
                                    <td>
                                        <span class="badge fs-9 {{ $wh->type === 'CENTRAL_LOGISTICS' ? 'text-bg-warning' : 'text-bg-secondary' }}">
                                            {{ $wh->type === 'CENTRAL_LOGISTICS' ? 'Gudang Pusat' : 'Penyimpanan Cabang' }}
                                        </span>
                                    </td>

                                    <!-- Alamat Lokasi -->
                                    <td>
                                        <div class="text-secondary fs-9 text-truncate" style="max-width: 240px;" title="{{ $wh->address ?? '-' }}">{{ $wh->address ?? '-' }}</div>
                                    </td>

                                    <!-- Variasi SKU Stok -->
                                    <td class="text-center">
                                        <span class="badge bg-body-secondary text-secondary-emphasis font-monospace fs-8">
                                            {{ $wh->stockBalances->count() }} SKU
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td class="text-center">
                                        <form action="{{ route('master.warehouses.toggle_status', $wh->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="badge border-0 cursor-pointer fs-9 py-1 px-2 {{ $wh->is_active ? 'text-bg-success' : 'text-bg-secondary' }}" 
                                                    title="Klik untuk mengubah status">
                                                {{ $wh->is_active ? 'Aktif' : 'Non-Aktif' }}
                                            </button>
                                        </form>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="text-center pe-3">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <button type="button" @click="openViewWhModal({{ Js::from($wh) }})" class="btn-action-icon text-secondary" title="Detail Gudang">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" @click="openEditWhModal({{ Js::from($wh) }})" class="btn-action-icon text-primary" title="Edit Gudang">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button type="button" @click="openDeleteWhModal({{ Js::from($wh) }})" class="btn-action-icon text-danger" title="Hapus Gudang">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-secondary">
                                        <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary-subtle"></i>
                                        <p class="fw-bold mb-1">Tidak ada data lokasi gudang ditemukan</p>
                                        <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau bersihkan filter.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card Footer / Pagination -->
            <x-pagination-footer :paginator="$warehouses" :perPage="$perPage" tab="warehouses" />
        </div>
    </div>

    <!-- ==================== 1. MODAL: DETAIL UNIT KERJA (VIEW) ==================== -->
    <div x-show="viewOrgModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewOrgModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-building fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Detail Unit Kerja: <span class="font-monospace text-danger" x-text="viewOrgData ? viewOrgData.code : ''"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Informasi identitas unit dan hierarki organisasi</span>
                    </div>
                </div>
                <button type="button" @click="viewOrgModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;" x-if="viewOrgData">
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="row g-2 fs-8">
                        <div class="col-12 col-sm-6">
                            <span class="text-secondary d-block">Nama Unit:</span>
                            <strong class="text-body fs-7" x-text="viewOrgData ? viewOrgData.name : ''"></strong>
                        </div>
                        <div class="col-12 col-sm-6">
                            <span class="text-secondary d-block">Tipe Organisasi:</span>
                            <span class="badge text-bg-light border" x-text="viewOrgData ? (viewOrgData.type ? viewOrgData.type.replace(/_/g, ' ') : '-') : ''"></span>
                        </div>
                        <div class="col-6 col-sm-4">
                            <span class="text-secondary d-block">Cost Center:</span>
                            <span class="font-monospace fw-bold text-danger" x-text="viewOrgData ? (viewOrgData.cost_center_code || '-') : ''"></span>
                        </div>
                        <div class="col-6 col-sm-4">
                            <span class="text-secondary d-block">Kota:</span>
                            <span class="fw-bold text-body" x-text="viewOrgData ? viewOrgData.city : ''"></span>
                        </div>
                        <div class="col-12 col-sm-4">
                            <span class="text-secondary d-block">Telepon:</span>
                            <span class="text-body" x-text="viewOrgData ? (viewOrgData.phone || '-') : ''"></span>
                        </div>
                        <div class="col-12">
                            <span class="text-secondary d-block">Alamat Lengkap:</span>
                            <span class="text-body" x-text="viewOrgData ? (viewOrgData.address || '-') : ''"></span>
                        </div>
                    </div>
                </div>

                <div class="border rounded-2 p-2.5 bg-body-tertiary">
                    <div class="fs-8 fw-bold text-uppercase text-secondary mb-2 d-flex align-items-center">
                        <i class="bi bi-link-45deg text-danger me-1"></i> Entitas Terhubung
                    </div>
                    <div class="row g-2 text-center fs-8">
                        <div class="col-6">
                            <div class="p-2 rounded bg-body border">
                                <span class="fs-9 text-secondary d-block">Gudang Penyimpanan</span>
                                <strong class="fs-6 text-warning-emphasis font-monospace" x-text="viewOrgData && viewOrgData.warehouses ? viewOrgData.warehouses.length : 0"></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-body border">
                                <span class="fs-9 text-secondary d-block">Pegawai Terdaftar</span>
                                <strong class="fs-6 text-primary font-monospace" x-text="viewOrgData && viewOrgData.users ? viewOrgData.users.length : 0"></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top">
                <button type="button" @click="viewOrgModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== 2. MODAL: TAMBAH UNIT KERJA (CREATE) ==================== -->
    <div x-show="createOrgModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createOrgModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-plus-circle fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Unit Kerja Baru</h6>
                        <span class="fs-8 text-secondary">Registrasi Kantor Pusat, Cabang Utama, atau Capem Bank Jatim</span>
                    </div>
                </div>
                <button type="button" @click="createOrgModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('master.organizations.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Unit Kerja <span class="text-danger">*</span></label>
                            <input type="text" name="code" required class="form-control form-control-sm font-monospace text-uppercase fw-bold fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tipe Unit <span class="text-danger">*</span></label>
                            <select name="type" required class="form-select form-select-sm fs-8">
                                <option value="SUB_BRANCH">Cabang Pembantu (SUB_BRANCH)</option>
                                <option value="MAIN_BRANCH">Cabang Utama (MAIN_BRANCH)</option>
                                <option value="HEAD_OFFICE">Kantor Pusat (HEAD_OFFICE)</option>
                                <option value="WAREHOUSE">Hub Logistik (WAREHOUSE)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Unit Kerja <span class="text-danger">*</span></label>
                            <input type="text" name="name" required class="form-control form-control-sm fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Induk Unit (Parent)</label>
                            <select name="parent_id" class="form-select form-select-sm fs-8">
                                <option value="">-- Tingkat Teratas (Tanpa Induk) --</option>
                                @foreach($parents as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Cost Center Code</label>
                            <input type="text" name="cost_center_code" class="form-control form-control-sm font-monospace text-uppercase fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kota / Kabupaten <span class="text-danger">*</span></label>
                            <input type="text" name="city" required value="Surabaya" class="form-control form-control-sm fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nomor Telepon</label>
                            <input type="text" name="phone" class="form-control form-control-sm fs-8">
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alamat Lengkap</label>
                            <textarea name="address" rows="2" class="form-control form-control-sm fs-8"></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check p-2.5 rounded border bg-body-tertiary">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="create_warehouse" value="1" checked id="cb_wh">
                                <label class="form-check-label fs-8 text-body fw-semibold" for="cb_wh">
                                    Otomatis Buat Lokasi Gudang Penyimpanan Terkait
                                    <span class="d-block fs-9 text-secondary fw-normal">Membuat entitas gudang untuk mengelola mutasi dan saldo stok unit kerja ini.</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createOrgModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Unit Kerja
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== 3. MODAL: EDIT UNIT KERJA ==================== -->
    <div x-show="editOrgModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editOrgModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-pencil-square fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Edit Unit Kerja: <span class="font-monospace text-danger" x-text="editOrgForm.code"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Perbarui profil atau struktur hierarki</span>
                    </div>
                </div>
                <button type="button" @click="editOrgModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/master/organizations/' + editOrgForm.id" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Unit Kerja <span class="text-danger">*</span></label>
                            <input type="text" name="code" x-model="editOrgForm.code" required class="form-control form-control-sm font-monospace text-uppercase fw-bold fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tipe Unit <span class="text-danger">*</span></label>
                            <select name="type" x-model="editOrgForm.type" required class="form-select form-select-sm fs-8">
                                <option value="SUB_BRANCH">Cabang Pembantu (SUB_BRANCH)</option>
                                <option value="MAIN_BRANCH">Cabang Utama (MAIN_BRANCH)</option>
                                <option value="HEAD_OFFICE">Kantor Pusat (HEAD_OFFICE)</option>
                                <option value="WAREHOUSE">Hub Logistik (WAREHOUSE)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Unit Kerja <span class="text-danger">*</span></label>
                            <input type="text" name="name" x-model="editOrgForm.name" required class="form-control form-control-sm fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Induk Unit (Parent)</label>
                            <select name="parent_id" x-model="editOrgForm.parent_id" class="form-select form-select-sm fs-8">
                                <option value="">-- Tingkat Teratas (Tanpa Induk) --</option>
                                @foreach($parents as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Cost Center Code</label>
                            <input type="text" name="cost_center_code" x-model="editOrgForm.cost_center_code" class="form-control form-control-sm font-monospace text-uppercase fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kota / Kabupaten <span class="text-danger">*</span></label>
                            <input type="text" name="city" x-model="editOrgForm.city" required class="form-control form-control-sm fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nomor Telepon</label>
                            <input type="text" name="phone" x-model="editOrgForm.phone" class="form-control form-control-sm fs-8">
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alamat Lengkap</label>
                            <textarea name="address" x-model="editOrgForm.address" rows="2" class="form-control form-control-sm fs-8"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Status Operasional <span class="text-danger">*</span></label>
                            <select name="is_active" x-model="editOrgForm.is_active" class="form-select form-select-sm fs-8">
                                <option value="1">Aktif (Operasional)</option>
                                <option value="0">Non-Aktif (Tutup / Suspended)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="editOrgModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== 4. MODAL: HAPUS UNIT KERJA ==================== -->
    <div x-show="deleteOrgModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteOrgModalOpen = false" 
             class="card shadow-2xl border border-danger-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger-subtle d-flex align-items-center justify-content-between py-3 px-4 border-bottom border-danger-subtle">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-trash-fill fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Unit Kerja</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteOrgModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/master/organizations/' + deleteOrgForm.id" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-4 space-y-3">
                    <p class="text-body mb-2 fs-7">
                        Apakah Anda yakin ingin menghapus unit kerja berikut?
                    </p>

                    <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-1">Kode & Nama Unit:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deleteOrgForm.code + ' - ' + deleteOrgForm.name"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-shield-exclamation text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <strong>Pemeriksaan Integritas:</strong> Unit yang masih memiliki user pegawai (<strong x-text="deleteOrgForm.users_count"></strong>) atau gudang (<strong x-text="deleteOrgForm.warehouses_count"></strong>) tidak dapat dihapus. Anda dapat menonaktifkan statusnya.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="deleteOrgModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Unit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== 5. MODAL: DETAIL GUDANG (VIEW) ==================== -->
    <div x-show="viewWhModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewWhModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-box-seam fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Detail Lokasi Gudang: <span class="font-monospace text-danger" x-text="viewWhData ? viewWhData.code : ''"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Informasi spesifikasi dan kapasitas gudang</span>
                    </div>
                </div>
                <button type="button" @click="viewWhModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;" x-if="viewWhData">
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="row g-2 fs-8">
                        <div class="col-12 col-sm-6">
                            <span class="text-secondary d-block">Nama Gudang:</span>
                            <strong class="text-body fs-7" x-text="viewWhData ? viewWhData.name : ''"></strong>
                        </div>
                        <div class="col-12 col-sm-6">
                            <span class="text-secondary d-block">Tipe Gudang:</span>
                            <span class="badge text-bg-light border" x-text="viewWhData ? (viewWhData.type === 'CENTRAL_LOGISTICS' ? 'Gudang Logistik Pusat' : 'Penyimpanan Cabang') : ''"></span>
                        </div>
                        <div class="col-12 col-sm-6">
                            <span class="text-secondary d-block">Unit Kerja Pemilik:</span>
                            <span class="fw-bold text-body" x-text="viewWhData && viewWhData.organization ? (viewWhData.organization.name + ' (' + viewWhData.organization.code + ')') : '-'"></span>
                        </div>
                        <div class="col-12 col-sm-6">
                            <span class="text-secondary d-block">Status:</span>
                            <span class="badge" :class="viewWhData && viewWhData.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="viewWhData && viewWhData.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                        </div>
                        <div class="col-12">
                            <span class="text-secondary d-block">Alamat Lokasi:</span>
                            <span class="text-body" x-text="viewWhData ? (viewWhData.address || '-') : ''"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top">
                <button type="button" @click="viewWhModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== 6. MODAL: TAMBAH GUDANG BARU ==================== -->
    <div x-show="createWhModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createWhModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-box-seam fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Lokasi Gudang Baru</h6>
                        <span class="fs-8 text-secondary">Registrasi fisik lokasi penyimpanan persediaan</span>
                    </div>
                </div>
                <button type="button" @click="createWhModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('master.warehouses.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja Pemilik <span class="text-danger">*</span></label>
                            <select name="organization_id" x-model="createWhForm.organization_id" required class="form-select form-select-sm fs-8">
                                @foreach($allOrganizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->code }} • {{ $org->city }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Gudang <span class="text-danger">*</span></label>
                            <input type="text" name="code" required class="form-control form-control-sm font-monospace text-uppercase fw-bold fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tipe Gudang <span class="text-danger">*</span></label>
                            <select name="type" required class="form-select form-select-sm fs-8">
                                <option value="BRANCH_STORAGE">Penyimpanan Cabang (BRANCH_STORAGE)</option>
                                <option value="CENTRAL_LOGISTICS">Gudang Logistik Pusat (CENTRAL_LOGISTICS)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Lokasi Gudang <span class="text-danger">*</span></label>
                            <input type="text" name="name" required class="form-control form-control-sm fs-8">
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alamat Fisik Gudang</label>
                            <textarea name="address" rows="2" class="form-control form-control-sm fs-8"></textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createWhModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-warning text-white fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Gudang Baru
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== 7. MODAL: EDIT GUDANG ==================== -->
    <div x-show="editWhModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editWhModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-pencil-square fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Edit Gudang: <span class="font-monospace text-danger" x-text="editWhForm.code"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Perbarui identitas fisik atau kepemilikan unit kerja</span>
                    </div>
                </div>
                <button type="button" @click="editWhModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/master/warehouses/' + editWhForm.id" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja Pemilik <span class="text-danger">*</span></label>
                            <select name="organization_id" x-model="editWhForm.organization_id" required class="form-select form-select-sm fs-8">
                                @foreach($allOrganizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->code }} • {{ $org->city }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Gudang <span class="text-danger">*</span></label>
                            <input type="text" name="code" x-model="editWhForm.code" required class="form-control form-control-sm font-monospace text-uppercase fw-bold fs-8">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tipe Gudang <span class="text-danger">*</span></label>
                            <select name="type" x-model="editWhForm.type" required class="form-select form-select-sm fs-8">
                                <option value="BRANCH_STORAGE">Penyimpanan Cabang (BRANCH_STORAGE)</option>
                                <option value="CENTRAL_LOGISTICS">Gudang Logistik Pusat (CENTRAL_LOGISTICS)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Lokasi Gudang <span class="text-danger">*</span></label>
                            <input type="text" name="name" x-model="editWhForm.name" required class="form-control form-control-sm fs-8">
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alamat Fisik Gudang</label>
                            <textarea name="address" x-model="editWhForm.address" rows="2" class="form-control form-control-sm fs-8"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Status Operasional <span class="text-danger">*</span></label>
                            <select name="is_active" x-model="editWhForm.is_active" class="form-select form-select-sm fs-8">
                                <option value="1">Aktif (Dapat Menerima Stok)</option>
                                <option value="0">Non-Aktif (Tutup / Non-Operasional)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="editWhModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== 8. MODAL: HAPUS GUDANG ==================== -->
    <div x-show="deleteWhModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteWhModalOpen = false" 
             class="card shadow-2xl border border-danger-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger-subtle d-flex align-items-center justify-content-between py-3 px-4 border-bottom border-danger-subtle">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-trash-fill fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Gudang</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteWhModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/master/warehouses/' + deleteWhForm.id" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-4 space-y-3">
                    <p class="text-body mb-2 fs-7">
                        Apakah Anda yakin ingin menghapus gudang berikut?
                    </p>

                    <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-1">Kode & Nama Gudang:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deleteWhForm.code + ' - ' + deleteWhForm.name"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-shield-exclamation text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <strong>Pemeriksaan Saldo Stok:</strong> Terdapat <strong x-text="deleteWhForm.stock_count"></strong> catatan saldo barang. Gudang dengan riwayat stok tidak dapat dihapus permanen.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="deleteWhModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Gudang
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
