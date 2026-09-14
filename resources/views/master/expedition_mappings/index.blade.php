@extends('layouts.app')
@section('title', 'Pemetaan Ekspedisi & Logistik Wilayah')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('master.vendors') }}" class="text-decoration-none text-danger">Master Data</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pemetaan Ekspedisi</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    createModalOpen: false,
    viewModalOpen: false,
    editModalOpen: false,
    deleteModalOpen: false,
    selectedMapping: null,
    editMappingForm: {
        id: null,
        organization_id: '',
        courier_id: '',
        default_service_type: 'REGULER',
        notes: '',
        is_active: 1
    },
    deleteMappingForm: {
        id: null,
        organization_name: '',
        courier_name: '',
        delete_url: ''
    },
    openViewModal(map) {
        this.selectedMapping = map;
        this.viewModalOpen = true;
    },
    openEditModal(map) {
        this.editMappingForm = {
            id: map.id,
            organization_id: map.organization_id,
            courier_id: map.courier_id,
            default_service_type: map.default_service_type || 'REGULER',
            notes: map.notes || '',
            is_active: map.is_active ? 1 : 0
        };
        this.editModalOpen = true;
    },
    openDeleteModal(map) {
        this.deleteMappingForm = {
            id: map.id,
            organization_name: map.organization ? map.organization.name : 'Semua Unit',
            courier_name: map.courier ? map.courier.name : '-',
            delete_url: '/master/expedition-mappings/' + map.id
        };
        this.deleteModalOpen = true;
    }
}">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-xs border-start border-4 border-success d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-xs border-start border-4 border-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header (Judul Form) -->
        <div class="card-header bg-body p-2 px-3 border-bottom d-flex flex-column flex-md-row md:items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                    <i class="bi bi-truck text-danger"></i>
                    <span>Pemetaan Default Ekspedisi per Wilayah Cabang</span>
                    <span class="badge text-bg-light text-danger">{{ $mappings->total() }}</span>
                </h3>
            </div>
            <div class="card-tools ms-md-auto d-flex align-items-center gap-2">
                <button @click="createModalOpen = true" type="button" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle"></i>
                    <span>Tambah Pemetaan Ekspedisi</span>
                </button>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold shadow-xs d-inline-flex align-items-center gap-1" title="Cetak Halaman Ini">
                    <i class="bi bi-printer"></i>
                    <span>Cetak</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar (seperti master/vendors-couriers) -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('master.expedition_mappings') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Dropdown Filter: Ekspedisi / Kurir -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-truck"></i></span>
                            <select name="courier_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Mitra Ekspedisi</option>
                                @foreach($couriers as $cr)
                                    <option value="{{ $cr->id }}" {{ (string)$courierId === (string)$cr->id ? 'selected' : '' }}>
                                        {{ $cr->name }} ({{ $cr->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Dropdown Filter: Layanan -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-lightning-charge"></i></span>
                            <select name="service_type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Tipe Layanan</option>
                                <option value="REGULER" {{ $serviceType === 'REGULER' ? 'selected' : '' }}>REGULER</option>
                                <option value="EXPRESS" {{ $serviceType === 'EXPRESS' ? 'selected' : '' }}>EXPRESS</option>
                                <option value="CARGO" {{ $serviceType === 'CARGO' ? 'selected' : '' }}>CARGO</option>
                                <option value="INTERNAL" {{ $serviceType === 'INTERNAL' ? 'selected' : '' }}>INTERNAL</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dropdown Filter: Status -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Status</option>
                                <option value="ACTIVE" {{ $status === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                <option value="INACTIVE" {{ $status === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($courierId && $courierId !== 'ALL') || ($serviceType && $serviceType !== 'ALL') || ($status && $status !== 'ALL'))
                        <div class="col-auto">
                            <a href="{{ route('master.expedition_mappings') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                   class="form-control form-control-sm border-start-0 fs-8"
                                   placeholder="Cari cabang, kode, kurir, catatan...">
                            <button type="submit" class="btn btn-danger btn-sm">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table View -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="min-width: 220px;">Unit Kerja / Cabang Tujuan</th>
                            <th class="py-3" style="min-width: 200px;">Ekspedisi Rekanan Default</th>
                            <th class="py-3 text-center" style="width: 140px;">Tipe Layanan</th>
                            <th class="py-3 text-center" style="width: 100px;">Status</th>
                            <th class="py-3" style="min-width: 220px;">Catatan Rute / Instruksi</th>
                            <th class="pe-4 py-3 text-center" style="width: 110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mappings as $map)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $map->organization->name ?? 'Semua Unit' }}</div>
                                    <div class="small text-muted font-monospace">{{ $map->organization->code ?? '-' }} • {{ $map->organization->type ?? 'BRANCH' }}</div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fw-bold">{{ $map->courier->code ?? '-' }}</span>
                                        <span class="fw-bold text-body">{{ $map->courier->name ?? '-' }}</span>
                                    </div>
                                    @if($map->courier?->phone)
                                        <div class="small text-muted"><i class="bi bi-telephone me-1"></i>{{ $map->courier->phone }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($map->default_service_type === 'EXPRESS')
                                        <span class="badge text-bg-danger fw-bold"><i class="bi bi-lightning-fill me-1"></i> EXPRESS</span>
                                    @elseif($map->default_service_type === 'CARGO')
                                        <span class="badge text-bg-warning text-dark fw-bold"><i class="bi bi-truck me-1"></i> CARGO</span>
                                    @elseif($map->default_service_type === 'INTERNAL')
                                        <span class="badge text-bg-info text-dark fw-bold"><i class="bi bi-building me-1"></i> INTERNAL</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis border fw-semibold">REGULER</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge fs-9 py-1 px-2 {{ $map->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $map->is_active ? 'Aktif' : 'Non-Aktif' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $map->notes ?? '-' }}</span>
                                </td>
                                <td class="pe-4 text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <button type="button" @click="openViewModal({{ Js::from($map) }})" class="btn-action-icon text-secondary" title="Detail Pemetaan">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" @click="openEditModal({{ Js::from($map) }})" class="btn-action-icon text-secondary" title="Edit Pemetaan">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" @click="openDeleteModal({{ Js::from($map) }})" class="btn-action-icon text-danger" title="Hapus Pemetaan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">
                                    <i class="bi bi-truck fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada data pemetaan ekspedisi ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau bersihkan filter.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card Footer / Pagination -->
        <x-pagination-footer :paginator="$mappings" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL: TAMBAH PEMETAAN EKSPEDISI ==================== -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <form action="{{ route('master.expedition_mappings.store') }}" method="POST">
                @csrf
                <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-plus-circle fs-6"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-body">Tambah Pemetaan Ekspedisi</h6>
                            <span class="fs-8 text-secondary">Atur ekspedisi default untuk kantor cabang tujuan</span>
                        </div>
                    </div>
                    <button type="button" @click="createModalOpen = false" class="btn-close" aria-label="Close"></button>
                </div>

                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja / Cabang Tujuan <span class="text-danger">*</span></label>
                        <select name="organization_id" required class="form-select form-select-sm">
                            <option value="">-- Pilih Kantor Cabang Tujuan --</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->code }})</option>
                            @endforeach
                        </select>
                        <div class="form-text fs-9 text-muted">Kantor cabang yang akan menerima paket kiriman barang persediaan</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kurir / Ekspedisi Rekanan <span class="text-danger">*</span></label>
                        <select name="courier_id" required class="form-select form-select-sm">
                            <option value="">-- Pilih Ekspedisi Default --</option>
                            @foreach($couriers as $cr)
                                <option value="{{ $cr->id }}">{{ $cr->name }} ({{ $cr->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tipe Layanan Pengiriman <span class="text-danger">*</span></label>
                        <select name="default_service_type" required class="form-select form-select-sm">
                            <option value="REGULER">REGULER (Standar SLA 2-3 Hari)</option>
                            <option value="EXPRESS">EXPRESS (SLA 1 Hari / Next Day)</option>
                            <option value="CARGO">CARGO (Volume Besar &gt; 10 Kg)</option>
                            <option value="INTERNAL">INTERNAL (Armada Logistik Bank Sendiri)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan Tambahan / Kontak Penerima</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Contoh: Titip di Security Gedung Kantor Cabang Lt. 1..."></textarea>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top gap-2">
                    <button type="button" @click="createModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">Simpan Konfigurasi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: EDIT PEMETAAN EKSPEDISI ==================== -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <form action="{{ route('master.expedition_mappings.store') }}" method="POST">
                @csrf
                <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-pencil-square fs-6"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-body">Edit Pemetaan Ekspedisi</h6>
                            <span class="fs-8 text-secondary">Perbarui konfigurasi ekspedisi default cabang</span>
                        </div>
                    </div>
                    <button type="button" @click="editModalOpen = false" class="btn-close" aria-label="Close"></button>
                </div>

                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja / Cabang Tujuan <span class="text-danger">*</span></label>
                        <select name="organization_id" x-model="editMappingForm.organization_id" required class="form-select form-select-sm">
                            <option value="">-- Pilih Kantor Cabang Tujuan --</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kurir / Ekspedisi Rekanan <span class="text-danger">*</span></label>
                        <select name="courier_id" x-model="editMappingForm.courier_id" required class="form-select form-select-sm">
                            <option value="">-- Pilih Ekspedisi Default --</option>
                            @foreach($couriers as $cr)
                                <option value="{{ $cr->id }}">{{ $cr->name }} ({{ $cr->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tipe Layanan Pengiriman <span class="text-danger">*</span></label>
                        <select name="default_service_type" x-model="editMappingForm.default_service_type" required class="form-select form-select-sm">
                            <option value="REGULER">REGULER (Standar SLA 2-3 Hari)</option>
                            <option value="EXPRESS">EXPRESS (SLA 1 Hari / Next Day)</option>
                            <option value="CARGO">CARGO (Volume Besar &gt; 10 Kg)</option>
                            <option value="INTERNAL">INTERNAL (Armada Logistik Bank Sendiri)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan Tambahan / Kontak Penerima</label>
                        <textarea name="notes" x-model="editMappingForm.notes" rows="2" class="form-control form-control-sm" placeholder="Contoh: Titip di Security Gedung Kantor Cabang Lt. 1..."></textarea>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top gap-2">
                    <button type="button" @click="editModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: DETAIL PEMETAAN (VIEW) ==================== -->
    <div x-show="viewModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-truck fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Rincian Pemetaan Ekspedisi</h6>
                        <span class="fs-8 text-secondary" x-text="selectedMapping && selectedMapping.organization ? selectedMapping.organization.code : ''"></span>
                    </div>
                </div>
                <button type="button" @click="viewModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fs-8 text-secondary mb-0.5">Cabang Penerima:</div>
                            <h5 class="fw-bold text-body mb-0" x-text="selectedMapping && selectedMapping.organization ? selectedMapping.organization.name : '-'"></h5>
                        </div>
                        <span class="badge text-bg-danger fw-bold font-monospace" x-text="selectedMapping ? selectedMapping.default_service_type : ''"></span>
                    </div>
                </div>

                <div class="row g-3 fs-8">
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Mitra Ekspedisi Default:</span>
                        <span class="fw-bold text-body" x-text="selectedMapping && selectedMapping.courier ? selectedMapping.courier.name : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Kode Ekspedisi:</span>
                        <span class="font-monospace fw-bold text-body" x-text="selectedMapping && selectedMapping.courier ? selectedMapping.courier.code : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Kontak / Telepon Ekspedisi:</span>
                        <span class="text-body" x-text="selectedMapping && selectedMapping.courier ? (selectedMapping.courier.phone || '-') : '-'"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Status Pemetaan:</span>
                        <span class="badge" :class="selectedMapping && selectedMapping.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="selectedMapping && selectedMapping.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                    </div>
                    <div class="col-12">
                        <span class="text-secondary d-block">Catatan Rute / Instruksi Pengiriman:</span>
                        <span class="text-body" x-text="selectedMapping ? (selectedMapping.notes || '-') : '-'"></span>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top">
                <button type="button" @click="viewModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: HAPUS PEMETAAN (DELETE) ==================== -->
    <div x-show="deleteModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteModalOpen = false" 
             class="card shadow-2xl border border-danger-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <form :action="deleteMappingForm.delete_url" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-header bg-danger text-white d-flex align-items-center justify-content-between py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <h6 class="mb-0 fw-bold">Konfirmasi Hapus Pemetaan</h6>
                    </div>
                    <button type="button" @click="deleteModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
                </div>

                <div class="card-body p-4 text-center">
                    <div class="rounded-circle bg-danger-subtle text-danger p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                        <i class="bi bi-trash fs-3"></i>
                    </div>
                    <p class="fs-7 text-body mb-2">Apakah Anda yakin ingin menghapus konfigurasi pemetaan ekspedisi ini?</p>
                    <div class="p-3 bg-body-secondary rounded-3 border text-start fs-8 mb-3">
                        <div><strong>Cabang:</strong> <span x-text="deleteMappingForm.organization_name"></span></div>
                        <div><strong>Ekspedisi:</strong> <span x-text="deleteMappingForm.courier_name"></span></div>
                    </div>
                    <p class="fs-9 text-muted mb-0">Tindakan ini akan mengembalikan aturan pengiriman cabang ke default umum.</p>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top gap-2">
                    <button type="button" @click="deleteModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">Ya, Hapus Pemetaan</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

