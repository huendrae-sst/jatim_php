@extends('layouts.app')
@section('title', 'Pagu Anggaran Persediaan')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('master.budgets') }}" class="text-decoration-none text-danger">Master Data</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pagu Anggaran</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    createModalOpen: false,
    createCostCenter: '',
    viewModalOpen: false,
    selectedBudget: null,

    openViewModal(b) {
        this.selectedBudget = b;
        this.viewModalOpen = true;
    },

    formatRupiah(num) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }
}">

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header -->
        <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 py-3 px-4">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                Pagu Anggaran Persediaan Unit Kerja
            </h3>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <span class="badge text-bg-danger fs-8 fw-bold">T.A. {{ $currentYear }}</span>
                <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">{{ $budgets->total() }} Unit Terdaftar</span>
                <button type="button" @click="createModalOpen = true; createCostCenter = ''" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1 ms-auto">
                    <i class="bi bi-plus-circle"></i>
                    <span>Tambah Alokasi Pagu</span>
                </button>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold shadow-xs d-inline-flex align-items-center gap-1" title="Cetak Halaman Ini">
                    <i class="bi bi-printer"></i>
                    <span>Cetak</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('master.budgets') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Unit Kerja Filter -->
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
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

                    <!-- Reset Button -->
                    @if($search || ($orgId && $orgId !== 'ALL'))
                        <div class="col-auto">
                            <a href="{{ route('master.budgets') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                            <th class="ps-4 py-3" style="min-width: 220px;">Unit Kerja</th>
                            <th class="py-3 text-center" style="width: 140px;">Cost Center</th>
                            <th class="py-3 text-end" style="width: 160px;">Pagu Alokasi (Rp)</th>
                            <th class="py-3 text-end" style="width: 150px;">Komitmen (Rp)</th>
                            <th class="py-3 text-end" style="width: 150px;">Realisasi (Rp)</th>
                            <th class="py-3 text-end" style="width: 160px;">Sisa Anggaran (Rp)</th>
                            <th class="py-3 text-center" style="width: 160px;">Tingkat Serapan</th>
                            <th class="pe-4 py-3 text-center" style="width: 80px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($budgets as $b)
                            @php
                                $bData = [
                                    'id' => $b->id,
                                    'org_name' => $b->organization ? $b->organization->name : 'Unit Tidak Diketahui',
                                    'org_code' => $b->organization ? $b->organization->code : '-',
                                    'org_city' => $b->organization ? $b->organization->city : '-',
                                    'cost_center_code' => $b->cost_center_code,
                                    'year' => $b->year,
                                    'allocated_amount' => (float) $b->allocated_amount,
                                    'committed_amount' => (float) $b->committed_amount,
                                    'realized_amount' => (float) $b->realized_amount,
                                    'available_amount' => (float) $b->available_amount,
                                    'utilization_percentage' => (float) $b->utilization_percentage,
                                ];
                            @endphp
                            <tr>
                                <!-- Unit Kerja -->
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fw-bold">{{ $b->organization->code ?? '-' }}</span>
                                        <span class="fw-bold text-body">{{ $b->organization->name ?? 'Unit Tidak Diketahui' }}</span>
                                    </div>
                                    @if($b->organization && $b->organization->city)
                                        <div class="fs-9 text-secondary mt-0.5"><i class="bi bi-geo-alt me-1"></i>{{ $b->organization->city }}</div>
                                    @endif
                                </td>

                                <!-- Cost Center -->
                                <td class="text-center">
                                    <span class="badge bg-body-secondary text-secondary-emphasis font-monospace border">{{ $b->cost_center_code ?? '-' }}</span>
                                </td>

                                <!-- Pagu Alokasi -->
                                <td class="text-end font-monospace fw-bold text-body">Rp {{ number_format($b->allocated_amount, 0, ',', '.') }}</td>

                                <!-- Komitmen -->
                                <td class="text-end font-monospace fw-semibold text-warning-emphasis">Rp {{ number_format($b->committed_amount, 0, ',', '.') }}</td>

                                <!-- Realisasi -->
                                <td class="text-end font-monospace fw-semibold text-info-emphasis">Rp {{ number_format($b->realized_amount, 0, ',', '.') }}</td>

                                <!-- Sisa Anggaran -->
                                <td class="text-end font-monospace fw-bold text-success">Rp {{ number_format($b->available_amount, 0, ',', '.') }}</td>

                                <!-- Tingkat Serapan -->
                                <td class="text-center">
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <span class="badge fs-9 py-1 px-2 fw-bold
                                            @if($b->utilization_percentage < 50) bg-success-subtle text-success border border-success-subtle
                                            @elseif($b->utilization_percentage <= 80) bg-warning-subtle text-warning-emphasis border border-warning-subtle
                                            @else bg-danger-subtle text-danger border border-danger-subtle
                                            @endif">
                                            {{ $b->utilization_percentage }}%
                                        </span>
                                        <div class="progress w-100" style="height: 5px; max-width: 120px;">
                                            <div class="progress-bar
                                                @if($b->utilization_percentage < 50) bg-success
                                                @elseif($b->utilization_percentage <= 80) bg-warning
                                                @else bg-danger
                                                @endif" 
                                                 style="width: {{ min($b->utilization_percentage, 100) }}%">
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Aksi -->
                                <td class="pe-4 text-center">
                                    <button type="button" @click="openViewModal({{ Js::from($bData) }})" class="btn-action-icon text-secondary" title="Detail Pagu Anggaran">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-secondary">
                                    <i class="bi bi-wallet2 fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada data pagu anggaran ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau sesuaikan filter.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card Footer / Pagination -->
        <x-pagination-footer :paginator="$budgets" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL: DETAIL PAGU ANGGARAN ==================== -->
    <div x-show="viewModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-wallet2 fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Detail Pagu Anggaran Persediaan</h6>
                        <span class="fs-8 text-secondary" x-text="selectedBudget ? selectedBudget.org_name + ' (' + selectedBudget.org_code + ')' : ''"></span>
                    </div>
                </div>
                <button type="button" @click="viewModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-4" style="max-height: 75vh; overflow-y: auto;">
                <!-- Header Info -->
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <span class="fs-8 text-secondary d-block">Cost Center:</span>
                            <span class="font-monospace fw-bold fs-7 text-body" x-text="selectedBudget ? selectedBudget.cost_center_code : '-'"></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fs-8 text-secondary d-block">Tahun Anggaran:</span>
                            <span class="badge text-bg-danger fs-8 fw-bold" x-text="selectedBudget ? selectedBudget.year : '-'"></span>
                        </div>
                    </div>
                </div>

                <!-- 4 Financial Cards -->
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-3 border bg-body text-center">
                            <div class="fs-9 text-secondary text-uppercase fw-semibold mb-1">Pagu Alokasi</div>
                            <div class="fs-7 fw-bold font-monospace text-body" x-text="selectedBudget ? formatRupiah(selectedBudget.allocated_amount) : 'Rp 0'"></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-3 border bg-body text-center">
                            <div class="fs-9 text-warning-emphasis text-uppercase fw-semibold mb-1">Komitmen</div>
                            <div class="fs-7 fw-bold font-monospace text-warning-emphasis" x-text="selectedBudget ? formatRupiah(selectedBudget.committed_amount) : 'Rp 0'"></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-3 border bg-body text-center">
                            <div class="fs-9 text-info-emphasis text-uppercase fw-semibold mb-1">Realisasi</div>
                            <div class="fs-7 fw-bold font-monospace text-info-emphasis" x-text="selectedBudget ? formatRupiah(selectedBudget.realized_amount) : 'Rp 0'"></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-3 rounded-3 border bg-body text-center">
                            <div class="fs-9 text-success text-uppercase fw-semibold mb-1">Sisa Tersedia</div>
                            <div class="fs-7 fw-bold font-monospace text-success" x-text="selectedBudget ? formatRupiah(selectedBudget.available_amount) : 'Rp 0'"></div>
                        </div>
                    </div>
                </div>

                <!-- Progress & Utilization Breakdown -->
                <div class="p-3 rounded-3 border bg-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-8 fw-bold text-secondary">Tingkat Serapan Anggaran:</span>
                        <span class="fs-7 fw-bold" 
                              :class="{
                                  'text-success': selectedBudget && selectedBudget.utilization_percentage < 50,
                                  'text-warning-emphasis': selectedBudget && selectedBudget.utilization_percentage >= 50 && selectedBudget.utilization_percentage <= 80,
                                  'text-danger': selectedBudget && selectedBudget.utilization_percentage > 80
                              }"
                              x-text="selectedBudget ? selectedBudget.utilization_percentage + '%' : '0%'">
                        </span>
                    </div>

                    <div class="progress" style="height: 12px;">
                        <div class="progress-bar bg-info" 
                             :style="'width: ' + (selectedBudget && selectedBudget.allocated_amount > 0 ? (selectedBudget.realized_amount / selectedBudget.allocated_amount * 100) : 0) + '%'"
                             title="Realisasi"></div>
                        <div class="progress-bar bg-warning" 
                             :style="'width: ' + (selectedBudget && selectedBudget.allocated_amount > 0 ? (selectedBudget.committed_amount / selectedBudget.allocated_amount * 100) : 0) + '%'"
                             title="Komitmen"></div>
                    </div>

                    <div class="d-flex justify-content-between mt-2 fs-9 text-secondary">
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-info p-1 rounded-circle" style="width: 8px; height: 8px;"></span>
                            <span>Realisasi (<span x-text="selectedBudget && selectedBudget.allocated_amount > 0 ? Math.round(selectedBudget.realized_amount / selectedBudget.allocated_amount * 100) : 0"></span>%)</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-warning p-1 rounded-circle" style="width: 8px; height: 8px;"></span>
                            <span>Komitmen (<span x-text="selectedBudget && selectedBudget.allocated_amount > 0 ? Math.round(selectedBudget.committed_amount / selectedBudget.allocated_amount * 100) : 0"></span>%)</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-success p-1 rounded-circle" style="width: 8px; height: 8px;"></span>
                            <span>Sisa (<span x-text="selectedBudget && selectedBudget.allocated_amount > 0 ? Math.round(selectedBudget.available_amount / selectedBudget.allocated_amount * 100) : 0"></span>%)</span>
                        </div>
                    </div>
                </div>

                <!-- Alert Notice -->
                <div class="alert alert-info py-2 px-3 fs-8 mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill text-info fs-6 flex-shrink-0"></i>
                    <div>
                        Pagu anggaran ini otomatis dipotong saat pesanan disetujui (Komitmen) dan dipindah ke Realisasi saat penerimaan barang (GRN/Settlement).
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

    <!-- CREATE BUDGET MODAL (AdminLTE 4 & Dark/Light Aware) -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createModalOpen = false; createCostCenter = ''" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-plus-circle fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Alokasi Pagu Anggaran</h6>
                        <span class="fs-8 text-secondary">Registrasi pagu belanja persediaan per unit kerja</span>
                    </div>
                </div>
                <button type="button" @click="createModalOpen = false; createCostCenter = ''" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form action="{{ route('master.budgets.store') }}" method="POST">
                @csrf

                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Organization -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja <span class="text-danger">*</span></label>
                        <select name="organization_id" 
                                required 
                                class="form-select form-select-sm fs-8"
                                @change="
                                    let sel = $event.target.selectedOptions[0];
                                    createCostCenter = (sel && sel.dataset.costCenter) ? sel.dataset.costCenter : '';
                                ">
                            <option value="" disabled selected>Pilih Unit Kerja...</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}" data-cost-center="{{ $org->cost_center_code ?? ('CC-' . $org->code) }}">
                                    {{ $org->name }} ({{ $org->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Row: Cost Center Code & Year -->
                    <div class="row g-3">
                        <div class="col-12 col-md-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Cost Center <span class="text-danger">*</span></label>
                            <input type="text" name="cost_center_code" x-model="createCostCenter" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tahun Anggaran <span class="text-danger">*</span></label>
                            <input type="number" name="year" value="{{ $currentYear }}" min="2020" max="2099" required class="form-control form-control-sm fw-bold fs-8">
                        </div>
                    </div>

                    <!-- Nominal Alokasi -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nominal Pagu Alokasi (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary fs-8">Rp</span>
                            <input type="number" name="allocated_amount" min="0" step="1000" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <span class="fs-9 text-secondary mt-1 d-block">Masukkan nominal angka (contoh: 150000000 untuk 150 juta rupiah).</span>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan / Keterangan</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createModalOpen = false; createCostCenter = ''" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Alokasi Pagu
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
