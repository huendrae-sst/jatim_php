@extends('layouts.app')
@section('title', 'Manajemen User & Hak Akses')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('master.users') }}" class="text-decoration-none text-danger">Master Data</a></li>
    <li class="breadcrumb-item active" aria-current="page">User & Hak Akses</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    createUserModalOpen: false,
    viewModalOpen: false,
    editModalOpen: false,
    deleteModalOpen: false,
    selectedUser: null,
    editUserData: {
        id: null,
        name: '',
        nip: '',
        email: '',
        phone: '',
        role: '',
        organization_id: '',
        warehouse_id: '',
        approval_limit: 0,
        is_active: 1
    },
    userUpdateUrl: '',
    deleteUserData: {
        id: null,
        name: '',
        nip: ''
    },
    userDeleteUrl: '',

    openViewModal(user) {
        this.selectedUser = user;
        this.viewModalOpen = true;
    },

    openEditModal(user) {
        this.editUserData = {
            id: user.id,
            name: user.name,
            nip: user.nip,
            email: user.email,
            phone: user.phone || '',
            role: user.role,
            organization_id: user.organization_id || '',
            warehouse_id: user.warehouse_id || '',
            approval_limit: user.approval_limit || 0,
            is_active: user.is_active ? 1 : 0
        };
        this.userUpdateUrl = '{{ url('master/users') }}/' + user.id;
        this.editModalOpen = true;
    },

    openDeleteModal(user) {
        this.deleteUserData = {
            id: user.id,
            name: user.name,
            nip: user.nip
        };
        this.userDeleteUrl = '{{ url('master/users') }}/' + user.id;
        this.deleteModalOpen = true;
    },

    formatRupiah(num) {
        if (!num || num <= 0) return 'Tidak Terbatas / Tanpa Limit';
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
    }
}">

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header -->
        <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 py-3 px-4">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                Daftar Pegawai & Hak Akses
            </h3>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">{{ $users->total() }} Pegawai Ditemukan</span>
                <button type="button" @click="createUserModalOpen = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1 ms-auto">
                    <i class="bi bi-plus-circle"></i>
                    <span>Tambah Pengguna Baru</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('master.users') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Role Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-shield-lock"></i></span>
                            <select name="role" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Peran (Role)</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r }}" {{ $role === $r ? 'selected' : '' }}>
                                        {{ str_replace('_', ' ', $r) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Organization Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                            <select name="organization_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Unit Kerja</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ (string)$orgId === (string)$org->id ? 'selected' : '' }}>
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
                                <option value="">Semua Status</option>
                                <option value="ACTIVE" {{ $status === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                <option value="INACTIVE" {{ $status === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($role && $role !== 'ALL') || ($orgId && $orgId !== 'ALL') || $status)
                        <div class="col-auto">
                            <a href="{{ route('master.users') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                   class="form-control form-control-sm border-start-0 fs-8">
                            <button class="btn btn-danger btn-sm" type="submit">Cari</button>
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
                            <th class="ps-4 py-3" style="width: 240px;">Nama Pegawai & NIP</th>
                            <th class="py-3" style="width: 200px;">Email</th>
                            <th class="py-3" style="width: 180px;">Peran (Role)</th>
                            <th class="py-3" style="min-width: 200px;">Unit Kerja (Scope)</th>
                            <th class="py-3 text-end" style="width: 150px;">Approval Limit</th>
                            <th class="py-3 text-center" style="width: 100px;">Status</th>
                            <th class="pe-4 py-3 text-center" style="width: 110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $u)
                            @php
                                $userData = [
                                    'id' => $u->id,
                                    'name' => $u->name,
                                    'nip' => $u->nip,
                                    'email' => $u->email,
                                    'phone' => $u->phone,
                                    'role' => $u->role,
                                    'role_display' => $u->role_display_name,
                                    'org_name' => $u->organization ? $u->organization->name : 'Kantor Pusat',
                                    'org_code' => $u->organization ? $u->organization->code : '-',
                                    'org_city' => $u->organization ? $u->organization->city : '-',
                                    'organization_id' => $u->organization_id,
                                    'wh_name' => $u->warehouse ? $u->warehouse->name : null,
                                    'wh_code' => $u->warehouse ? $u->warehouse->code : null,
                                    'warehouse_id' => $u->warehouse_id,
                                    'approval_limit' => (float) $u->approval_limit,
                                    'is_active' => (bool) $u->is_active,
                                ];
                            @endphp
                            <tr>
                                <!-- Pegawai & NIP -->
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-danger-subtle text-danger fw-bold d-flex align-items-center justify-content-center flex-shrink-0 fs-9" style="width: 32px; height: 32px;">
                                            {{ strtoupper(substr($u->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-body">{{ $u->name }}</div>
                                            <div class="font-monospace text-secondary fs-9"><i class="bi bi-person-badge me-1"></i>{{ $u->nip }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Email -->
                                <td class="text-body font-monospace fs-8">
                                    {{ $u->email }}
                                </td>

                                <!-- Role -->
                                <td>
                                    <span class="badge fs-9 py-1 px-2 text-uppercase
                                        @if(str_contains($u->role, 'ADMIN')) bg-danger-subtle text-danger border border-danger-subtle
                                        @elseif(str_contains($u->role, 'APPROVER') || str_contains($u->role, 'MANAGEMENT')) bg-warning-subtle text-warning-emphasis border border-warning-subtle
                                        @elseif(str_contains($u->role, 'OFFICER')) bg-primary-subtle text-primary border border-primary-subtle
                                        @else bg-secondary-subtle text-secondary border
                                        @endif">
                                        {{ $u->role_display_name }}
                                    </span>
                                </td>

                                <!-- Unit Kerja -->
                                <td>
                                    <div class="fw-semibold text-body">{{ $u->organization->name ?? 'Kantor Pusat' }}</div>
                                    @if($u->warehouse)
                                        <div class="fs-9 text-secondary mt-0.5">
                                            <i class="bi bi-box-seam me-1 text-warning"></i>{{ $u->warehouse->name }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Approval Limit -->
                                <td class="text-end font-monospace">
                                    @if($u->approval_limit > 0)
                                        <span class="fw-bold text-body">Rp {{ number_format($u->approval_limit, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-muted fs-8">-</span>
                                    @endif
                                </td>

                                <!-- Status -->
                                <td class="text-center">
                                    <span class="badge fs-9 py-1 px-2 {{ $u->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $u->is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="pe-4 text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" @click="openViewModal({{ json_encode($userData) }})" class="btn-action-icon text-secondary" title="Detail Pengguna">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" @click="openEditModal({{ json_encode($userData) }})" class="btn-action-icon text-primary" title="Edit Pengguna">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" @click="openDeleteModal({{ json_encode($userData) }})" class="btn-action-icon text-danger" title="Hapus Pengguna">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada data pengguna ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau bersihkan filter.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <x-pagination-footer :paginator="$users" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL: DETAIL USER & HAK AKSES ==================== -->
    <div x-show="viewModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-person-badge fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Profil Pengguna & Hak Akses</h6>
                        <span class="fs-8 text-secondary">Informasi identitas dan kewenangan sistem</span>
                    </div>
                </div>
                <button type="button" @click="viewModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-3.5 space-y-3">
                <!-- User Profile Header -->
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle d-flex align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3 overflow-hidden">
                        <div class="rounded-circle bg-danger text-white fw-bold d-flex align-items-center justify-content-center flex-shrink-0 fs-5" style="width: 44px; height: 44px;">
                            <span x-text="selectedUser ? selectedUser.name.substring(0, 2).toUpperCase() : ''"></span>
                        </div>
                        <div class="overflow-hidden">
                            <h6 class="fw-bold text-body mb-0.5 text-truncate" x-text="selectedUser ? selectedUser.name : '-'"></h6>
                            <div class="d-flex flex-wrap gap-2 text-secondary fs-8">
                                <span class="font-monospace"><i class="bi bi-person-badge me-1"></i>NIP: <span x-text="selectedUser ? selectedUser.nip : '-'"></span></span>
                                <span>&bull;</span>
                                <span class="font-monospace"><i class="bi bi-envelope me-1"></i><span x-text="selectedUser ? selectedUser.email : '-'"></span></span>
                            </div>
                        </div>
                    </div>
                    <span class="badge flex-shrink-0 fs-8 px-2.5 py-1" :class="selectedUser && selectedUser.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="selectedUser && selectedUser.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                </div>

                <!-- Detail Roles & Scopes -->
                <div class="row g-2.5 fs-8">
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Peran Sistem (Role):</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-8 text-uppercase mt-0.5" x-text="selectedUser ? selectedUser.role_display : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Batas Otorisasi (Approval Limit):</span>
                        <span class="font-monospace fw-bold fs-7 text-danger mt-0.5 d-inline-block" x-text="selectedUser ? formatRupiah(selectedUser.approval_limit) : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Unit Kerja Penempatan:</span>
                        <div class="fw-bold text-body" x-text="selectedUser ? selectedUser.org_name : '-'"></div>
                        <div class="font-monospace text-secondary fs-9" x-text="selectedUser ? 'Kode: ' + selectedUser.org_code : ''"></div>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Gudang Bertugas:</span>
                        <template x-if="selectedUser && selectedUser.wh_name">
                            <div>
                                <div class="fw-bold text-body" x-text="selectedUser.wh_name"></div>
                                <div class="font-monospace text-secondary fs-9" x-text="'Kode: ' + selectedUser.wh_code"></div>
                            </div>
                        </template>
                        <template x-if="!selectedUser || !selectedUser.wh_name">
                            <span class="text-muted fst-italic fs-9">- Seluruh Gudang Cabang / N/A -</span>
                        </template>
                    </div>
                </div>

                <!-- Scope Explanation Notice -->
                <div class="alert alert-info py-2 px-3 fs-8 mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-info fs-6 flex-shrink-0"></i>
                    <div>
                        Akses transaksi dibatasi sesuai unit kerja dan peran pegawai yang bersangkutan.
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-2.5 px-4 border-top">
                <button type="button" @click="viewModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: TAMBAH PENGGUNA ==================== -->
    <div x-show="createUserModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createUserModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-3xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-person-plus fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Pengguna Baru</h6>
                        <span class="fs-8 text-secondary">Registrasi pegawai & hak akses sistem persediaan J-IMS</span>
                    </div>
                </div>
                <button type="button" @click="createUserModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form action="{{ route('master.users.store') }}" method="POST">
                @csrf

                <div class="card-body p-3.5 p-md-4">
                    <div class="row g-3">
                        <!-- Kolom Kiri: Identitas & Kontak -->
                        <div class="col-12 col-md-6 space-y-2.5">
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Lengkap Pegawai <span class="text-danger">*</span></label>
                                <input type="text" name="name" required class="form-control form-control-sm fw-bold fs-8">
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">NIP Pegawai <span class="text-danger">*</span></label>
                                <input type="text" name="nip" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Email Resmi Bank Jatim <span class="text-danger">*</span></label>
                                <input type="email" name="email" required class="form-control form-control-sm fs-8">
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">No. Kontak / HP</label>
                                <input type="text" name="phone" class="form-control form-control-sm fs-8">
                            </div>
                        </div>

                        <!-- Kolom Kanan: Peran, Penempatan & Otorisasi -->
                        <div class="col-12 col-md-6 space-y-2.5">
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Peran Sistem (Role) <span class="text-danger">*</span></label>
                                <select name="role" required class="form-select form-select-sm fs-8">
                                    <option value="" disabled selected>Pilih Peran Sistem...</option>
                                    <option value="REQUESTER_CABANG">Requester Cabang/Capem</option>
                                    <option value="ORDER_APPROVER">Order Approver (Pemimpin Cabang)</option>
                                    <option value="SWITCHING_APPROVER">Switching Stock Approver</option>
                                    <option value="INVENTORY_OFFICER">Inventory Officer</option>
                                    <option value="WAREHOUSE_OFFICER">Warehouse Officer</option>
                                    <option value="PROCUREMENT_OFFICER">Procurement Officer</option>
                                    <option value="PROCUREMENT_APPROVER">Procurement Approver</option>
                                    <option value="DISTRIBUTION_OFFICER">Distribution Officer</option>
                                    <option value="RECEIVING_OFFICER">Receiving Officer</option>
                                    <option value="BUDGET_OFFICER">Budget Officer</option>
                                    <option value="FINANCE_OFFICER">Finance Officer</option>
                                    <option value="FINANCE_APPROVER">Finance Approver</option>
                                    <option value="MASTER_MAKER">Master Data Maker</option>
                                    <option value="MASTER_APPROVER">Master Data Approver</option>
                                    <option value="USER_ADMIN">User Administrator</option>
                                    <option value="SUPER_ADMIN">Super Administrator</option>
                                    <option value="AUDITOR">Internal Auditor</option>
                                    <option value="MANAGEMENT">Executive Management</option>
                                    <option value="IT_OPS">IT Operations</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja Penempatan</label>
                                <select name="organization_id" class="form-select form-select-sm fs-8">
                                    <option value="">- Tanpa Unit / Kantor Pusat -</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Gudang Bertugas (Opsional)</label>
                                <select name="warehouse_id" class="form-select form-select-sm fs-8">
                                    <option value="">- Tanpa Gudang Khusus -</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Limit Approval</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-body text-secondary fs-8">Rp</span>
                                        <input type="number" name="approval_limit" min="0" step="1000" value="0" class="form-control form-control-sm font-monospace fs-8">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Password Awal</label>
                                    <input type="password" name="password" class="form-control form-control-sm fs-8">
                                    <span class="fs-9 text-secondary d-block text-truncate">Default: password123</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="createUserModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: EDIT PENGGUNA ==================== -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-3xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-pencil-square fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Edit Data Pengguna</h6>
                        <span class="fs-8 text-secondary">Perbarui informasi profil pegawai dan hak akses</span>
                    </div>
                </div>
                <button type="button" @click="editModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="userUpdateUrl" method="POST">
                @csrf
                @method('PUT')

                <div class="card-body p-3.5 p-md-4">
                    <div class="row g-3">
                        <!-- Kolom Kiri: Identitas & Kontak -->
                        <div class="col-12 col-md-6 space-y-2.5">
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Lengkap Pegawai <span class="text-danger">*</span></label>
                                <input type="text" name="name" x-model="editUserData.name" required class="form-control form-control-sm fw-bold fs-8">
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">NIP Pegawai <span class="text-danger">*</span></label>
                                <input type="text" name="nip" x-model="editUserData.nip" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Email Resmi Bank Jatim <span class="text-danger">*</span></label>
                                <input type="email" name="email" x-model="editUserData.email" required class="form-control form-control-sm fs-8">
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">No. Kontak / HP</label>
                                <input type="text" name="phone" x-model="editUserData.phone" class="form-control form-control-sm fs-8">
                            </div>
                        </div>

                        <!-- Kolom Kanan: Peran, Status, Penempatan & Otorisasi -->
                        <div class="col-12 col-md-6 space-y-2.5">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-0">Peran Sistem <span class="text-danger">*</span></label>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_user_active" :checked="editUserData.is_active == 1" @change="editUserData.is_active = $event.target.checked ? 1 : 0">
                                        <label class="form-check-label fs-8 fw-semibold text-body" for="edit_user_active">Status Aktif</label>
                                    </div>
                                </div>
                                <select name="role" x-model="editUserData.role" required class="form-select form-select-sm fs-8">
                                    <option value="REQUESTER_CABANG">Requester Cabang/Capem</option>
                                    <option value="ORDER_APPROVER">Order Approver (Pemimpin Cabang)</option>
                                    <option value="SWITCHING_APPROVER">Switching Stock Approver</option>
                                    <option value="INVENTORY_OFFICER">Inventory Officer</option>
                                    <option value="WAREHOUSE_OFFICER">Warehouse Officer</option>
                                    <option value="PROCUREMENT_OFFICER">Procurement Officer</option>
                                    <option value="PROCUREMENT_APPROVER">Procurement Approver</option>
                                    <option value="DISTRIBUTION_OFFICER">Distribution Officer</option>
                                    <option value="RECEIVING_OFFICER">Receiving Officer</option>
                                    <option value="BUDGET_OFFICER">Budget Officer</option>
                                    <option value="FINANCE_OFFICER">Finance Officer</option>
                                    <option value="FINANCE_APPROVER">Finance Approver</option>
                                    <option value="MASTER_MAKER">Master Data Maker</option>
                                    <option value="MASTER_APPROVER">Master Data Approver</option>
                                    <option value="USER_ADMIN">User Administrator</option>
                                    <option value="SUPER_ADMIN">Super Administrator</option>
                                    <option value="AUDITOR">Internal Auditor</option>
                                    <option value="MANAGEMENT">Executive Management</option>
                                    <option value="IT_OPS">IT Operations</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja Penempatan</label>
                                <select name="organization_id" x-model="editUserData.organization_id" class="form-select form-select-sm fs-8">
                                    <option value="">- Tanpa Unit / Kantor Pusat -</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Gudang Bertugas (Opsional)</label>
                                <select name="warehouse_id" x-model="editUserData.warehouse_id" class="form-select form-select-sm fs-8">
                                    <option value="">- Tanpa Gudang Khusus -</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Limit Approval</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-body text-secondary fs-8">Rp</span>
                                        <input type="number" name="approval_limit" x-model="editUserData.approval_limit" min="0" step="1000" class="form-control form-control-sm font-monospace fs-8">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Password Baru</label>
                                    <input type="password" name="password" class="form-control form-control-sm fs-8">
                                    <span class="fs-9 text-secondary d-block text-truncate">Kosongkan jika tak diubah</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: HAPUS PENGGUNA ==================== -->
    <div x-show="deleteModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteModalOpen = false" 
             class="card shadow-2xl border border-danger-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger-subtle d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom border-danger-subtle">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-trash-fill fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Pengguna</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="userDeleteUrl" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin menghapus pengguna berikut dari sistem?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">Nama & NIP Pegawai:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deleteUserData.name + ' (' + deleteUserData.nip + ')'"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-shield-exclamation text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Akun pengguna yang memiliki riwayat transaksi order atau persetujuan sebaiknya dinonaktifkan daripada dihapus.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
