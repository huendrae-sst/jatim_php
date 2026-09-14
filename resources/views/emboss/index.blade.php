@extends('layouts.app')
@section('title', 'Integrasi File Emboss & Personalisasi Kartu')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">Integrasi Emboss</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ 
    createModal: false,
    viewModal: false,
    viewItem: null,
    editModal: false,
    editItem: { id: null, file_id: '', file_name: '', source: '', notes: '' },
    deleteModal: false,
    deleteItem: { id: null, file_id: '', file_name: '', total_records: 0 },
    openViewModal(item) {
        this.viewItem = item;
        this.viewModal = true;
    },
    openEditModal(item) {
        this.editItem = {
            id: item.id,
            file_id: item.file_id,
            file_name: item.file_name,
            source: item.source,
            notes: item.notes || ''
        };
        this.editModal = true;
    },
    openDeleteModal(item) {
        this.deleteItem = {
            id: item.id,
            file_id: item.file_id,
            file_name: item.file_name,
            total_records: item.total_records || 0
        };
        this.deleteModal = true;
    }
}">

    <!-- Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- KPI Metric Widgets (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- Box 1: Total Berkas -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-file-earmark-text"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Berkas Diproses</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($metrics['total_files']) }}</span>
                    <span class="fs-9 text-secondary">Semua Berkas Unggahan</span>
                </div>
            </div>
        </div>

        <!-- Box 2: Total Records -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-credit-card-2-front"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Record Kartu</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($metrics['total_records']) }}</span>
                    <span class="fs-9 text-secondary">Seluruh Baris Nasabah</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Sukses Valid -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Record Sukses (Valid)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($metrics['total_success']) }}</span>
                    <span class="fs-9 text-secondary">Siap Generate Order</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Reject & Duplikat -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-exclamation-octagon"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Reject & Duplikat</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-warning">{{ number_format($metrics['total_reject'] + $metrics['total_duplicate']) }}</span>
                    <span class="fs-9 text-secondary">Memerlukan Koreksi</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <div class="card-header border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <div>
                <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                    <i class="bi bi-credit-card-2-front text-danger"></i>
                    Riwayat Unggahan Berkas Emboss
                </h3>
            </div>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <a href="{{ route('emboss.template') }}" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-download me-1"></i> Unduh Template CSV
                </a>
                <button type="button" @click="createModal = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Unggah Berkas Baru</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('emboss.index') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Source Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-hdd-network"></i></span>
                            <select name="source" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Sumber Data</option>
                                <option value="CARD_CORE_SYSTEM" {{ $source === 'CARD_CORE_SYSTEM' ? 'selected' : '' }}>Core Banking Card Center</option>
                                <option value="ATM_CENTER" {{ $source === 'ATM_CENTER' ? 'selected' : '' }}>ATM Switching & Terminal</option>
                                <option value="BRANCH_PORTAL" {{ $source === 'BRANCH_PORTAL' ? 'selected' : '' }}>Portal Cabang Bank Jatim</option>
                                <option value="MANUAL_UPLOAD" {{ $source === 'MANUAL_UPLOAD' ? 'selected' : '' }}>Unggahan Manual</option>
                            </select>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Status</option>
                                <option value="COMPLETED" {{ $status === 'COMPLETED' ? 'selected' : '' }}>Selesai (Completed)</option>
                                <option value="PARTIAL_SUCCESS" {{ $status === 'PARTIAL_SUCCESS' ? 'selected' : '' }}>Parsial (Ada Reject)</option>
                                <option value="FAILED" {{ $status === 'FAILED' ? 'selected' : '' }}>Gagal (Failed)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($source && $source !== 'ALL') || ($status && $status !== 'ALL'))
                        <div class="col-auto">
                            <a href="{{ route('emboss.index') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm border-start-0 border-end-0 fs-8" placeholder="Cari File ID, Nama Berkas, Catatan...">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Files Table -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary fs-9 text-uppercase">
                        <tr>
                            <th class="ps-3 py-2">File ID / Berkas</th>
                            <th class="py-2">Sumber Data</th>
                            <th class="text-center py-2">Waktu & Durasi</th>
                            <th class="text-center py-2">Total Record</th>
                            <th class="text-center py-2">Sukses</th>
                            <th class="text-center py-2">Reject</th>
                            <th class="text-center py-2">Duplikat</th>
                            <th class="text-center py-2">Status</th>
                            <th class="text-center py-2" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($files as $f)
                            <tr>
                                <td class="ps-3 py-2">
                                    <a href="{{ route('emboss.show', $f->id) }}" class="fw-bold text-decoration-none text-dark d-block">
                                        {{ $f->file_id }}
                                    </a>
                                    <span class="fs-9 text-muted text-truncate d-inline-block" style="max-width: 200px;" title="{{ $f->file_name }}">
                                        <i class="bi bi-filetype-csv me-1"></i>{{ $f->file_name }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <span class="badge bg-light text-dark border fs-9">
                                        {{ str_replace('_', ' ', $f->source) }}
                                    </span>
                                    <span class="fs-9 text-muted d-block mt-0.5">Oleh: {{ $f->uploader?->name ?? 'System' }}</span>
                                </td>
                                <td class="text-center py-2">
                                    <span class="font-monospace fw-semibold">{{ $f->created_at->format('d/m/Y H:i') }}</span>
                                    <span class="fs-9 text-muted d-block font-monospace">
                                        <i class="bi bi-stopwatch me-0.5"></i>{{ $f->duration_seconds ? $f->duration_seconds.'s' : '-' }}
                                    </span>
                                </td>
                                <td class="text-center py-2 font-monospace fw-bold fs-7">
                                    {{ number_format($f->total_records) }}
                                </td>
                                <td class="text-center py-2">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success font-monospace fs-8">
                                        {{ number_format($f->success_records) }}
                                    </span>
                                </td>
                                <td class="text-center py-2">
                                    @if($f->reject_records > 0)
                                        <a href="{{ route('emboss.reject_queue', $f->id) }}" class="badge bg-danger bg-opacity-10 text-danger border border-danger font-monospace fs-8 text-decoration-none">
                                            {{ number_format($f->reject_records) }} <i class="bi bi-arrow-right-short"></i>
                                        </a>
                                    @else
                                        <span class="badge bg-light text-muted border font-monospace fs-8">0</span>
                                    @endif
                                </td>
                                <td class="text-center py-2">
                                    @if($f->duplicate_records > 0)
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning font-monospace fs-8" title="Record duplikat dicegah idempotensi">
                                            {{ number_format($f->duplicate_records) }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border font-monospace fs-8">0</span>
                                    @endif
                                </td>
                                <td class="text-center py-2">
                                    @if($f->status === 'COMPLETED')
                                        <span class="badge bg-success">COMPLETED</span>
                                    @elseif($f->status === 'PARTIAL_SUCCESS')
                                        <span class="badge bg-warning text-dark">PARTIAL SUCCESS</span>
                                    @elseif($f->status === 'PROCESSING')
                                        <span class="badge bg-primary"><i class="spinner-border spinner-border-sm me-1"></i> PROCESSING</span>
                                    @else
                                        <span class="badge bg-danger">FAILED</span>
                                    @endif
                                </td>
                                <td class="text-center pe-3 pe-md-4 py-2">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from($f) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Berkas Emboss">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="{{ route('emboss.show', $f->id) }}" 
                                           class="btn-action-icon text-dark" 
                                           title="Buka Data Mapping Lengkap">
                                            <i class="bi bi-arrow-up-right-square"></i>
                                        </a>
                                        @if($f->reject_records > 0)
                                            <a href="{{ route('emboss.reject_queue', $f->id) }}" 
                                               class="btn-action-icon text-warning" 
                                               title="Kelola Reject Queue ({{ $f->reject_records }})">
                                                <i class="bi bi-exclamation-triangle"></i>
                                            </a>
                                        @endif
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($f) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Berkas Emboss">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($f) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Hapus Berkas Emboss">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    Belum ada riwayat berkas emboss yang diproses. Silakan unggah berkas baru.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Standardized Bank Jatim Pagination Footer -->
        <x-pagination-footer :paginator="$files" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL: UNGGAH BERKAS BARU ==================== -->
    <div x-show="createModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-cloud-arrow-up fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Unggah Berkas Batch Personalisasi Kartu</h6>
                        <span class="fs-8 text-secondary">Proses intake berkas batch CSV/text untuk dipetakan ke sistem</span>
                    </div>
                </div>
                <button type="button" @click="createModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('emboss.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Sistem Sumber Data (Source) <span class="text-danger">*</span>
                        </label>
                        <select name="source" class="form-select form-select-sm fs-8" required>
                            <option value="CARD_CORE_SYSTEM">Core Banking Card Center (Bank Jatim Host)</option>
                            <option value="ATM_CENTER">ATM Switching & Terminal Management System</option>
                            <option value="BRANCH_PORTAL">Portal Permintaan Personalisasi Cabang</option>
                            <option value="MANUAL_UPLOAD">Unggahan Manual Operator Logistik</option>
                        </select>
                        <span class="fs-9 text-muted mt-1 d-block">Identifikasi sumber data akan dicatat pada log audit dan penomoran batch.</span>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Pilih Berkas CSV / Text (.csv, .txt) <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="emboss_file" class="form-control form-control-sm fs-8" accept=".csv,.txt" required>
                        <span class="fs-9 text-muted mt-1 d-block">Maksimal ukuran file 50 MB. Mendukung batch besar hingga 100.000 records.</span>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Catatan / Keterangan Batch
                        </label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm fs-8" placeholder="Contoh: Batch personalisasi kartu debit chip GPN KC Surabaya"></textarea>
                    </div>

                    <div class="alert alert-info border border-info bg-info bg-opacity-10 py-2 px-3 fs-8 mb-0">
                        <i class="bi bi-shield-check text-info me-1"></i>
                        <strong>Proteksi Idempotensi Aktif:</strong> SHA-256 hash dan ID referensi otomatis diverifikasi untuk mencegah duplikasi order.
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Mulai Proses Intake Batch
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: LIHAT DETAIL EMBOSS ==================== -->
    <div x-show="viewModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-file-earmark-text fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Detail Berkas Emboss</h6>
                        <span class="fs-8 font-monospace text-danger" x-text="viewItem?.file_id"></span>
                    </div>
                </div>
                <button type="button" @click="viewModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <div class="p-3 rounded-3 bg-body-tertiary border border-secondary-subtle">
                    <div class="row g-2 fs-8">
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Nama Berkas:</span>
                            <strong class="text-body" x-text="viewItem?.file_name"></strong>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Sumber Data:</span>
                            <span class="badge bg-light text-dark border" x-text="viewItem?.source?.replace(/_/g, ' ')"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Waktu Unggah:</span>
                            <span class="text-body font-monospace" x-text="viewItem?.created_at ? new Date(viewItem.created_at).toLocaleString('id-ID') : '-'"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Status Pemrosesan:</span>
                            <span class="badge" :class="viewItem?.status === 'COMPLETED' ? 'bg-success' : (viewItem?.status === 'PARTIAL_SUCCESS' ? 'bg-warning text-dark' : 'bg-danger')" x-text="viewItem?.status"></span>
                        </div>
                    </div>
                </div>

                <!-- Record Statistics Grid -->
                <div class="row g-2 text-center">
                    <div class="col-3">
                        <div class="p-2 border rounded bg-body">
                            <span class="fs-9 text-secondary d-block">Total Records</span>
                            <span class="fs-6 fw-bold font-monospace text-body" x-text="viewItem?.total_records"></span>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-success-subtle border-success-subtle">
                            <span class="fs-9 text-success d-block">Sukses</span>
                            <span class="fs-6 fw-bold font-monospace text-success" x-text="viewItem?.success_records"></span>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-danger-subtle border-danger-subtle">
                            <span class="fs-9 text-danger d-block">Reject</span>
                            <span class="fs-6 fw-bold font-monospace text-danger" x-text="viewItem?.reject_records"></span>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 border rounded bg-warning-subtle border-warning-subtle">
                            <span class="fs-9 text-warning-emphasis d-block">Duplikat</span>
                            <span class="fs-6 fw-bold font-monospace text-warning-emphasis" x-text="viewItem?.duplicate_records"></span>
                        </div>
                    </div>
                </div>

                <template x-if="viewItem?.notes">
                    <div class="p-2.5 rounded bg-body-secondary border border-secondary-subtle fs-8">
                        <span class="text-secondary fw-semibold">Catatan:</span>
                        <p class="mb-0 text-body mt-1" x-text="viewItem.notes"></p>
                    </div>
                </template>
            </div>

            <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-top">
                <div class="d-flex gap-2">
                    <a :href="'/emboss/' + viewItem?.id" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                        <i class="bi bi-arrow-up-right-square"></i>
                        <span>Buka Data Mapping</span>
                    </a>
                    <template x-if="viewItem?.reject_records > 0">
                        <a :href="'/emboss/' + viewItem?.id + '/reject-queue'" class="btn btn-sm btn-outline-warning text-dark d-inline-flex align-items-center gap-1">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span>Reject Queue (<span x-text="viewItem?.reject_records"></span>)</span>
                        </a>
                    </template>
                </div>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: EDIT BERKAS EMBOSS ==================== -->
    <div x-show="editModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-pencil fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Edit Informasi Berkas Emboss</h6>
                        <span class="fs-8 font-monospace text-primary" x-text="editItem.file_id"></span>
                    </div>
                </div>
                <button type="button" @click="editModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/emboss/' + editItem.id" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Berkas</label>
                        <input type="text" class="form-control form-control-sm fs-8" :value="editItem.file_name" disabled>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Sistem Sumber Data (Source) <span class="text-danger">*</span>
                        </label>
                        <select name="source" x-model="editItem.source" class="form-select form-select-sm fs-8" required>
                            <option value="CARD_CORE_SYSTEM">Core Banking Card Center (Bank Jatim Host)</option>
                            <option value="ATM_CENTER">ATM Switching & Terminal Management System</option>
                            <option value="BRANCH_PORTAL">Portal Permintaan Personalisasi Cabang</option>
                            <option value="MANUAL_UPLOAD">Unggahan Manual Operator Logistik</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan / Keterangan</label>
                        <textarea name="notes" x-model="editItem.notes" rows="3" class="form-control form-control-sm fs-8" placeholder="Catatan tambahan batch..."></textarea>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: HAPUS BERKAS EMBOSS ==================== -->
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
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Berkas Emboss</h6>
                        <span class="fs-8 text-secondary">Tindakan ini permanen</span>
                    </div>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/emboss/' + deleteItem.id" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin menghapus berkas emboss ini beserta seluruh record data di dalamnya?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">File ID & Berkas:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deleteItem.file_id"></div>
                        <div class="fs-8 text-body fw-semibold text-truncate" x-text="deleteItem.file_name"></div>
                        <div class="fs-8 text-secondary font-monospace mt-1">
                            Total Record: <span class="fw-bold" x-text="deleteItem.total_records"></span> baris
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Seluruh record nasabah dan log pemetaan dalam berkas ini akan dihapus dari sistem.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Berkas
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
