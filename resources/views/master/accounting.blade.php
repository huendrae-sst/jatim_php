@extends('layouts.app')
@section('title', 'Master Data Accounting')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item text-secondary">Master Data</li>
    <li class="breadcrumb-item active" aria-current="page">Accounting & Cost Center</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    activeMainTab: (new URLSearchParams(window.location.search)).get('tab') || window.location.hash.replace('#', '') || '{{ $tab ?? 'coa' }}',

    init() {
        const validTabs = ['coa', 'cost_centers'];
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

    // CoA Modals
    createCoaModal: false,
    editCoaModal: false,
    viewCoaModal: false,
    deleteCoaModal: false,
    selectedCoa: null,

    editCoaForm: {
        id: null,
        account_code: '',
        account_name: '',
        account_type: 'ASSET',
        classification: '',
        normal_balance: 'DEBIT',
        description: '',
        is_active: true,
    },

    openCreateCoaModal() {
        this.createCoaModal = true;
    },

    openEditCoaModal(coa) {
        this.selectedCoa = coa;
        this.editCoaForm = {
            id: coa.id,
            account_code: coa.account_code,
            account_name: coa.account_name,
            account_type: coa.account_type,
            classification: coa.classification || '',
            normal_balance: coa.normal_balance,
            description: coa.description || '',
            is_active: Boolean(coa.is_active),
        };
        this.editCoaModal = true;
    },

    openViewCoaModal(coa) {
        this.selectedCoa = coa;
        this.viewCoaModal = true;
    },

    openDeleteCoaModal(coa) {
        this.selectedCoa = coa;
        this.deleteCoaModal = true;
    },

    // Cost Center Modals
    createCcModal: false,
    editCcModal: false,
    viewCcModal: false,
    deleteCcModal: false,
    selectedCc: null,

    editCcForm: {
        id: null,
        code: '',
        name: '',
        organization_id: '',
        department: '',
        pic_name: '',
        notes: '',
        is_active: true,
    },

    openCreateCcModal() {
        this.createCcModal = true;
    },

    openEditCcModal(cc) {
        this.selectedCc = cc;
        this.editCcForm = {
            id: cc.id,
            code: cc.code,
            name: cc.name,
            organization_id: cc.organization_id || '',
            department: cc.department || '',
            pic_name: cc.pic_name || '',
            notes: cc.notes || '',
            is_active: Boolean(cc.is_active),
        };
        this.editCcModal = true;
    },

    openViewCcModal(cc) {
        this.selectedCc = cc;
        this.viewCcModal = true;
    },

    openDeleteCcModal(cc) {
        this.selectedCc = cc;
        this.deleteCcModal = true;
    }
}">
    <!-- Main Card (Tabbed Navigation) -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <!-- Card Header with Navigation Tabs (Pills Scrollable) -->
        <div class="card-header bg-body p-2 px-3 border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2">
            <ul class="nav nav-pills card-header-pills nav-pills-scroll m-0">
                <li class="nav-item">
                    <button type="button" 
                            @click="activeMainTab = 'coa'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeMainTab === 'coa' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-journal-text"></i>
                        <span>Chart of Accounts (Rekening GL)</span>
                        <span class="badge" :class="activeMainTab === 'coa' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $totalCoa }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" 
                            @click="activeMainTab = 'cost_centers'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeMainTab === 'cost_centers' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-diagram-3"></i>
                        <span>Master Cost Center</span>
                        <span class="badge" :class="activeMainTab === 'cost_centers' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $totalCostCenter }}</span>
                    </button>
                </li>
            </ul>
            <div class="card-tools ms-md-auto">
                <template x-if="activeMainTab === 'coa'">
                    <button type="button" 
                            @click="openCreateCoaModal()" 
                            class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Rekening GL</span>
                    </button>
                </template>
                <template x-if="activeMainTab === 'cost_centers'">
                    <button type="button" 
                            @click="openCreateCcModal()" 
                            class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Cost Center</span>
                    </button>
                </template>
            </div>
        </div>

        <!-- ==================== TAB 1: CHART OF ACCOUNTS ==================== -->
        <div x-show="activeMainTab === 'coa'">
            <!-- Filter & Search Toolbar -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <form action="{{ route('master.accounting') }}" method="GET">
                    <input type="hidden" name="tab" value="coa">
                    <input type="hidden" name="coa_per_page" value="{{ $coaPerPage }}">
                    <div class="row g-2 align-items-center">
                        <!-- Type Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-tag"></i></span>
                                <select name="coa_type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Tipe Akun</option>
                                    <option value="ASSET" {{ ($coaType ?? '') === 'ASSET' ? 'selected' : '' }}>Aset (Asset)</option>
                                    <option value="LIABILITY" {{ ($coaType ?? '') === 'LIABILITY' ? 'selected' : '' }}>Kewajiban (Liability)</option>
                                    <option value="EQUITY" {{ ($coaType ?? '') === 'EQUITY' ? 'selected' : '' }}>Ekuitas / RAK (Equity)</option>
                                    <option value="REVENUE" {{ ($coaType ?? '') === 'REVENUE' ? 'selected' : '' }}>Pendapatan (Revenue)</option>
                                    <option value="EXPENSE" {{ ($coaType ?? '') === 'EXPENSE' ? 'selected' : '' }}>Beban (Expense)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="col-12 col-sm-6 col-md-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                                <select name="coa_status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Status</option>
                                    <option value="ACTIVE" {{ ($coaStatus ?? '') === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                    <option value="INACTIVE" {{ ($coaStatus ?? '') === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                                </select>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="col-12 col-sm-6 col-md">
                            <div class="input-group input-group-sm">
                                <input type="text" name="coa_search" value="{{ $coaSearch ?? '' }}" placeholder="Cari kode akun, nama, klasifikasi..." class="form-control form-control-sm border-end-0 fs-8">
                                <button type="submit" class="btn btn-sm btn-danger px-3">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Reset Filter -->
                        @if(!empty($coaSearch) || (!empty($coaType) && $coaType !== 'ALL') || (!empty($coaStatus) && $coaStatus !== 'ALL'))
                            <div class="col-12 col-sm-auto">
                                <a href="{{ route('master.accounting', ['tab' => 'coa']) }}" class="btn btn-sm btn-outline-secondary w-100 fs-8">
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
                            <th class="py-2.5 px-3" style="width: 140px;">Kode Rekening</th>
                            <th class="py-2.5 px-3">Nama Akun Buku Besar (GL)</th>
                            <th class="py-2.5 px-3">Tipe Akun</th>
                            <th class="py-2.5 px-3">Klasifikasi</th>
                            <th class="py-2.5 px-3 text-center" style="width: 120px;">Saldo Normal</th>
                            <th class="py-2.5 px-3 text-center" style="width: 100px;">Status</th>
                            <th class="py-2.5 px-3 text-center" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($coas as $coa)
                            @php
                                $badgeClass = match($coa->account_type) {
                                    'ASSET' => 'text-bg-success',
                                    'LIABILITY' => 'text-bg-warning',
                                    'EQUITY' => 'text-bg-info',
                                    'REVENUE' => 'text-bg-primary',
                                    'EXPENSE' => 'text-bg-danger',
                                    default => 'text-bg-secondary',
                                };
                            @endphp
                            <tr>
                                <td class="py-2.5 px-3 font-monospace fw-bold text-dark fs-8">
                                    {{ $coa->account_code }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="fw-semibold text-dark">{{ $coa->account_name }}</div>
                                    @if($coa->description)
                                        <div class="fs-9 text-muted text-truncate" style="max-width: 320px;">{{ $coa->description }}</div>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-9 fw-semibold">
                                        {{ $coa->account_type }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-secondary fs-8">
                                    {{ $coa->classification ?? '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="badge {{ $coa->normal_balance === 'DEBIT' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle' }} px-2 py-0.5 fs-9 font-monospace fw-bold">
                                        {{ $coa->normal_balance }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="badge {{ $coa->is_active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} px-2 py-0.5 fs-9">
                                        {{ $coa->is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- View Button -->
                                        <button type="button" 
                                                @click="openViewCoaModal({{ Js::from($coa) }})" 
                                                class="btn btn-sm btn-outline-secondary btn-action-icon" 
                                                title="Lihat Rincian Akun">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Edit Button -->
                                        <button type="button" 
                                                @click="openEditCoaModal({{ Js::from($coa) }})" 
                                                class="btn btn-sm btn-outline-primary btn-action-icon" 
                                                title="Edit Akun GL">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <!-- Toggle Status Form -->
                                        <form action="{{ route('master.coa.toggle_status', $coa->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="btn btn-sm {{ $coa->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} btn-action-icon" 
                                                    title="{{ $coa->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}">
                                                <i class="bi {{ $coa->is_active ? 'bi-toggle2-on' : 'bi-toggle2-off' }}"></i>
                                            </button>
                                        </form>

                                        <!-- Delete Button -->
                                        <button type="button" 
                                                @click="openDeleteCoaModal({{ Js::from($coa) }})" 
                                                class="btn btn-sm btn-outline-danger btn-action-icon" 
                                                title="Hapus Akun GL">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="bi bi-journal-x fs-1 d-block mb-2 text-muted"></i>
                                    <div class="fw-semibold">Tidak ada Rekening Akun GL yang ditemukan.</div>
                                    <div class="fs-8 text-muted mt-1">Gunakan tombol "Tambah Rekening GL" untuk mendaftarkan akun baru.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <x-pagination-footer :paginator="$coas" :perPage="$coaPerPage" pageName="coa_page" />
        </div>

        <!-- ==================== TAB 2: MASTER COST CENTER ==================== -->
        <div x-show="activeMainTab === 'cost_centers'" style="display: none;">
            <!-- Filter & Search Toolbar -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <form action="{{ route('master.accounting') }}" method="GET">
                    <input type="hidden" name="tab" value="cost_centers">
                    <input type="hidden" name="cc_per_page" value="{{ $ccPerPage }}">
                    <div class="row g-2 align-items-center">
                        <!-- Organization Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                                <select name="cc_org_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Unit Kerja</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}" {{ (string)($ccOrgId ?? '') === (string)$org->id ? 'selected' : '' }}>
                                            {{ $org->code }} - {{ $org->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="col-12 col-sm-6 col-md-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                                <select name="cc_status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Status</option>
                                    <option value="ACTIVE" {{ ($ccStatus ?? '') === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                    <option value="INACTIVE" {{ ($ccStatus ?? '') === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                                </select>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="col-12 col-sm-6 col-md">
                            <div class="input-group input-group-sm">
                                <input type="text" name="cc_search" value="{{ $ccSearch ?? '' }}" placeholder="Cari kode CC, nama, bagian, PIC..." class="form-control form-control-sm border-end-0 fs-8">
                                <button type="submit" class="btn btn-sm btn-danger px-3">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Reset Filter -->
                        @if(!empty($ccSearch) || (!empty($ccOrgId) && $ccOrgId !== 'ALL') || (!empty($ccStatus) && $ccStatus !== 'ALL'))
                            <div class="col-12 col-sm-auto">
                                <a href="{{ route('master.accounting', ['tab' => 'cost_centers']) }}" class="btn btn-sm btn-outline-secondary w-100 fs-8">
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
                            <th class="py-2.5 px-3" style="width: 140px;">Kode Cost Center</th>
                            <th class="py-2.5 px-3">Nama Pusat Biaya (Cost Center)</th>
                            <th class="py-2.5 px-3">Unit Kerja Terkait</th>
                            <th class="py-2.5 px-3">Departemen / Bagian</th>
                            <th class="py-2.5 px-3">Penanggung Jawab (PIC)</th>
                            <th class="py-2.5 px-3 text-center" style="width: 100px;">Status</th>
                            <th class="py-2.5 px-3 text-center" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($costCenters as $cc)
                            <tr>
                                <td class="py-2.5 px-3 font-monospace fw-bold text-danger fs-8">
                                    {{ $cc->code }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="fw-semibold text-dark">{{ $cc->name }}</div>
                                    @if($cc->notes)
                                        <div class="fs-9 text-muted text-truncate" style="max-width: 280px;">{{ $cc->notes }}</div>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="text-dark">{{ $cc->organization->name ?? '-' }}</div>
                                    <div class="fs-9 text-muted font-monospace">{{ $cc->organization->code ?? '-' }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-secondary fs-8">
                                    {{ $cc->department ?? '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-secondary fs-8">
                                    <i class="bi bi-person me-1 fs-9"></i>{{ $cc->pic_name ?? '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="badge {{ $cc->is_active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} px-2 py-0.5 fs-9">
                                        {{ $cc->is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- View Button -->
                                        <button type="button" 
                                                @click="openViewCcModal({{ Js::from($cc) }})" 
                                                class="btn btn-sm btn-outline-secondary btn-action-icon" 
                                                title="Lihat Rincian Cost Center">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Edit Button -->
                                        <button type="button" 
                                                @click="openEditCcModal({{ Js::from($cc) }})" 
                                                class="btn btn-sm btn-outline-primary btn-action-icon" 
                                                title="Edit Cost Center">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <!-- Toggle Status Form -->
                                        <form action="{{ route('master.cost_centers.toggle_status', $cc->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" 
                                                    class="btn btn-sm {{ $cc->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} btn-action-icon" 
                                                    title="{{ $cc->is_active ? 'Nonaktifkan Cost Center' : 'Aktifkan Cost Center' }}">
                                                <i class="bi {{ $cc->is_active ? 'bi-toggle2-on' : 'bi-toggle2-off' }}"></i>
                                            </button>
                                        </form>

                                        <!-- Delete Button -->
                                        <button type="button" 
                                                @click="openDeleteCcModal({{ Js::from($cc) }})" 
                                                class="btn btn-sm btn-outline-danger btn-action-icon" 
                                                title="Hapus Cost Center">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="bi bi-diagram-3 fs-1 d-block mb-2 text-muted"></i>
                                    <div class="fw-semibold">Tidak ada Cost Center yang ditemukan.</div>
                                    <div class="fs-8 text-muted mt-1">Gunakan tombol "Tambah Cost Center" untuk mendaftarkan pusat biaya baru.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <x-pagination-footer :paginator="$costCenters" :perPage="$ccPerPage" pageName="cc_page" />
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODALS: CHART OF ACCOUNTS (COA) -->
    <!-- ========================================================================= -->

    <!-- Modal Create CoA -->
    <div x-show="createCoaModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createCoaModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-2.5 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-journal-plus fs-5"></i>
                    <h6 class="mb-0 fw-bold">Tambah Rekening Akun GL Baru</h6>
                </div>
                <button type="button" @click="createCoaModal = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <form action="{{ route('master.coa.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Kode Akun / Rekening GL <span class="text-danger">*</span></label>
                        <input type="text" name="account_code" required placeholder="Contoh: 11301" class="form-control form-control-sm fs-8 font-monospace">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Nama Akun Buku Besar <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" required placeholder="Contoh: Persediaan Alat Tulis Kantor" class="form-control form-control-sm fs-8">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Tipe Akun <span class="text-danger">*</span></label>
                            <select name="account_type" required class="form-select form-select-sm fs-8">
                                <option value="ASSET">ASSET (Aset)</option>
                                <option value="LIABILITY">LIABILITY (Kewajiban)</option>
                                <option value="EQUITY">EQUITY (Ekuitas/RAK)</option>
                                <option value="REVENUE">REVENUE (Pendapatan)</option>
                                <option value="EXPENSE">EXPENSE (Beban)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Saldo Normal <span class="text-danger">*</span></label>
                            <select name="normal_balance" required class="form-select form-select-sm fs-8 font-monospace">
                                <option value="DEBIT">DEBIT</option>
                                <option value="CREDIT">CREDIT</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Klasifikasi Akun</label>
                        <input type="text" name="classification" placeholder="Contoh: Aset Lancar / Beban Operasional" class="form-control form-control-sm fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Deskripsi / Peruntukan Akun</label>
                        <textarea name="description" rows="2" placeholder="Keterangan peruntukan posting akun ini..." class="form-control form-control-sm fs-8"></textarea>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="createCoaModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check2-circle"></i>
                        <span>Simpan Akun GL</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit CoA -->
    <div x-show="editCoaModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editCoaModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-2.5 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square fs-5"></i>
                    <h6 class="mb-0 fw-bold">Edit Rekening Akun GL</h6>
                </div>
                <button type="button" @click="editCoaModal = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <form :action="'/master/coa/' + (selectedCoa ? selectedCoa.id : '')" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Kode Akun / Rekening GL <span class="text-danger">*</span></label>
                        <input type="text" name="account_code" x-model="editCoaForm.account_code" required class="form-control form-control-sm fs-8 font-monospace">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Nama Akun Buku Besar <span class="text-danger">*</span></label>
                        <input type="text" name="account_name" x-model="editCoaForm.account_name" required class="form-control form-control-sm fs-8">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Tipe Akun <span class="text-danger">*</span></label>
                            <select name="account_type" x-model="editCoaForm.account_type" required class="form-select form-select-sm fs-8">
                                <option value="ASSET">ASSET (Aset)</option>
                                <option value="LIABILITY">LIABILITY (Kewajiban)</option>
                                <option value="EQUITY">EQUITY (Ekuitas/RAK)</option>
                                <option value="REVENUE">REVENUE (Pendapatan)</option>
                                <option value="EXPENSE">EXPENSE (Beban)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Saldo Normal <span class="text-danger">*</span></label>
                            <select name="normal_balance" x-model="editCoaForm.normal_balance" required class="form-select form-select-sm fs-8 font-monospace">
                                <option value="DEBIT">DEBIT</option>
                                <option value="CREDIT">CREDIT</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Klasifikasi Akun</label>
                        <input type="text" name="classification" x-model="editCoaForm.classification" class="form-control form-control-sm fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Deskripsi / Peruntukan Akun</label>
                        <textarea name="description" x-model="editCoaForm.description" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Status Akun</label>
                        <select name="is_active" x-model="editCoaForm.is_active" class="form-select form-select-sm fs-8">
                            <option :value="true">Aktif</option>
                            <option :value="false">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editCoaModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check2-circle"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal View CoA -->
    <div x-show="viewCoaModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewCoaModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text text-danger fs-5"></i>
                    <h6 class="mb-0 fw-bold">Rincian Rekening Akun GL</h6>
                </div>
                <button type="button" @click="viewCoaModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="card-body p-4 space-y-2.5 text-xs" x-show="selectedCoa">
                <div class="p-3 bg-body-tertiary rounded border space-y-2">
                    <div>
                        <span class="text-secondary">Kode Rekening:</span>
                        <div class="fw-bold font-monospace text-dark fs-6" x-text="selectedCoa ? selectedCoa.account_code : ''"></div>
                    </div>
                    <div>
                        <span class="text-secondary">Nama Akun GL:</span>
                        <div class="fw-bold text-dark fs-7" x-text="selectedCoa ? selectedCoa.account_name : ''"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1 border-t">
                        <div>
                            <span class="text-secondary">Tipe Akun:</span>
                            <div class="fw-bold text-dark" x-text="selectedCoa ? selectedCoa.account_type : ''"></div>
                        </div>
                        <div>
                            <span class="text-secondary">Saldo Normal:</span>
                            <div class="fw-bold font-monospace text-dark" x-text="selectedCoa ? selectedCoa.normal_balance : ''"></div>
                        </div>
                    </div>
                    <div>
                        <span class="text-secondary">Klasifikasi:</span>
                        <div class="fw-semibold text-dark" x-text="selectedCoa ? (selectedCoa.classification || '-') : '-'"></div>
                    </div>
                    <div>
                        <span class="text-secondary">Status:</span>
                        <div>
                            <span class="badge" :class="selectedCoa && selectedCoa.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="selectedCoa && selectedCoa.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                        </div>
                    </div>
                </div>
                <div x-show="selectedCoa && selectedCoa.description" class="p-2.5 bg-body-tertiary rounded border">
                    <strong class="text-dark">Deskripsi / Peruntukan:</strong>
                    <p class="text-secondary mb-0 mt-0.5" x-text="selectedCoa ? selectedCoa.description : ''"></p>
                </div>
            </div>
            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-2.5 px-4 border-top">
                <button type="button" @click="viewCoaModal = false" class="btn btn-sm btn-outline-secondary">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Delete CoA -->
    <div x-show="deleteCoaModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteCoaModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-2.5 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <h6 class="mb-0 fw-bold">Konfirmasi Hapus Akun GL</h6>
                </div>
                <button type="button" @click="deleteCoaModal = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <form :action="'/master/coa/' + (selectedCoa ? selectedCoa.id : '')" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-4 space-y-3">
                    <p class="fs-8 text-secondary mb-0">Apakah Anda yakin ingin menghapus rekening akun buku besar ini dari master data?</p>
                    <div class="p-3 bg-body-tertiary rounded border fs-8" x-show="selectedCoa">
                        <div><strong>Kode Akun:</strong> <span class="font-monospace fw-bold text-dark" x-text="selectedCoa ? selectedCoa.account_code : ''"></span></div>
                        <div><strong>Nama Akun:</strong> <span class="fw-semibold text-dark" x-text="selectedCoa ? selectedCoa.account_name : ''"></span></div>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteCoaModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-trash"></i>
                        <span>Ya, Hapus Akun</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODALS: COST CENTER -->
    <!-- ========================================================================= -->

    <!-- Modal Create Cost Center -->
    <div x-show="createCcModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createCcModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-2.5 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3 fs-5"></i>
                    <h6 class="mb-0 fw-bold">Tambah Master Cost Center Baru</h6>
                </div>
                <button type="button" @click="createCcModal = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <form action="{{ route('master.cost_centers.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Kode Cost Center <span class="text-danger">*</span></label>
                        <input type="text" name="code" required placeholder="Contoh: CC-KC-SBY" class="form-control form-control-sm fs-8 font-monospace text-uppercase">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Nama Pusat Biaya (Cost Center) <span class="text-danger">*</span></label>
                        <input type="text" name="name" required placeholder="Contoh: Cost Center Cabang Utama Surabaya" class="form-control form-control-sm fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Unit Kerja / Cabang Terkait</label>
                        <select name="organization_id" class="form-select form-select-sm fs-8">
                            <option value="">-- Tanpa Relasi Unit Khusus --</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->code }} - {{ $org->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Departemen / Bagian</label>
                            <input type="text" name="department" placeholder="Contoh: Bagian Operasional" class="form-control form-control-sm fs-8">
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Penanggung Jawab (PIC)</label>
                            <input type="text" name="pic_name" placeholder="Nama PIC..." class="form-control form-control-sm fs-8">
                        </div>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Catatan / Keterangan</label>
                        <textarea name="notes" rows="2" placeholder="Catatan peruntukan cost center..." class="form-control form-control-sm fs-8"></textarea>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="createCcModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check2-circle"></i>
                        <span>Simpan Cost Center</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Cost Center -->
    <div x-show="editCcModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editCcModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-2.5 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square fs-5"></i>
                    <h6 class="mb-0 fw-bold">Edit Cost Center</h6>
                </div>
                <button type="button" @click="editCcModal = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <form :action="'/master/cost-centers/' + (selectedCc ? selectedCc.id : '')" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Kode Cost Center <span class="text-danger">*</span></label>
                        <input type="text" name="code" x-model="editCcForm.code" required class="form-control form-control-sm fs-8 font-monospace text-uppercase">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Nama Pusat Biaya (Cost Center) <span class="text-danger">*</span></label>
                        <input type="text" name="name" x-model="editCcForm.name" required class="form-control form-control-sm fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Unit Kerja / Cabang Terkait</label>
                        <select name="organization_id" x-model="editCcForm.organization_id" class="form-select form-select-sm fs-8">
                            <option value="">-- Tanpa Relasi Unit Khusus --</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->code }} - {{ $org->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Departemen / Bagian</label>
                            <input type="text" name="department" x-model="editCcForm.department" class="form-control form-control-sm fs-8">
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary mb-1">Penanggung Jawab (PIC)</label>
                            <input type="text" name="pic_name" x-model="editCcForm.pic_name" class="form-control form-control-sm fs-8">
                        </div>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Catatan / Keterangan</label>
                        <textarea name="notes" x-model="editCcForm.notes" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary mb-1">Status</label>
                        <select name="is_active" x-model="editCcForm.is_active" class="form-select form-select-sm fs-8">
                            <option :value="true">Aktif</option>
                            <option :value="false">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editCcModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check2-circle"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal View Cost Center -->
    <div x-show="viewCcModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewCcModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3 text-danger fs-5"></i>
                    <h6 class="mb-0 fw-bold">Rincian Cost Center</h6>
                </div>
                <button type="button" @click="viewCcModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="card-body p-4 space-y-2.5 text-xs" x-show="selectedCc">
                <div class="p-3 bg-body-tertiary rounded border space-y-2">
                    <div>
                        <span class="text-secondary">Kode Cost Center:</span>
                        <div class="fw-bold font-monospace text-danger fs-6" x-text="selectedCc ? selectedCc.code : ''"></div>
                    </div>
                    <div>
                        <span class="text-secondary">Nama Cost Center:</span>
                        <div class="fw-bold text-dark fs-7" x-text="selectedCc ? selectedCc.name : ''"></div>
                    </div>
                    <div>
                        <span class="text-secondary">Unit Kerja:</span>
                        <div class="fw-semibold text-dark" x-text="selectedCc && selectedCc.organization ? (selectedCc.organization.code + ' - ' + selectedCc.organization.name) : '-'"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-1 border-t">
                        <div>
                            <span class="text-secondary">Departemen:</span>
                            <div class="fw-semibold text-dark" x-text="selectedCc ? (selectedCc.department || '-') : '-'"></div>
                        </div>
                        <div>
                            <span class="text-secondary">PIC:</span>
                            <div class="fw-semibold text-dark" x-text="selectedCc ? (selectedCc.pic_name || '-') : '-'"></div>
                        </div>
                    </div>
                    <div>
                        <span class="text-secondary">Status:</span>
                        <div>
                            <span class="badge" :class="selectedCc && selectedCc.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="selectedCc && selectedCc.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                        </div>
                    </div>
                </div>
                <div x-show="selectedCc && selectedCc.notes" class="p-2.5 bg-body-tertiary rounded border">
                    <strong class="text-dark">Catatan / Keterangan:</strong>
                    <p class="text-secondary mb-0 mt-0.5" x-text="selectedCc ? selectedCc.notes : ''"></p>
                </div>
            </div>
            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-2.5 px-4 border-top">
                <button type="button" @click="viewCcModal = false" class="btn btn-sm btn-outline-secondary">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Delete Cost Center -->
    <div x-show="deleteCcModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteCcModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-2.5 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <h6 class="mb-0 fw-bold">Konfirmasi Hapus Cost Center</h6>
                </div>
                <button type="button" @click="deleteCcModal = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <form :action="'/master/cost-centers/' + (selectedCc ? selectedCc.id : '')" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-4 space-y-3">
                    <p class="fs-8 text-secondary mb-0">Apakah Anda yakin ingin menghapus Cost Center ini dari master data?</p>
                    <div class="p-3 bg-body-tertiary rounded border fs-8" x-show="selectedCc">
                        <div><strong>Kode Cost Center:</strong> <span class="font-monospace fw-bold text-danger" x-text="selectedCc ? selectedCc.code : ''"></span></div>
                        <div><strong>Nama:</strong> <span class="fw-semibold text-dark" x-text="selectedCc ? selectedCc.name : ''"></span></div>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteCcModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-trash"></i>
                        <span>Ya, Hapus Cost Center</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
