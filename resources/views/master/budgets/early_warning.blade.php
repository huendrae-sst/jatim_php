@extends('layouts.app')
@section('title', 'Early Warning System (EWS) Anggaran Unit Kerja')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('finance.settlements.index') }}" class="text-decoration-none text-danger">Finance</a></li>
    <li class="breadcrumb-item active" aria-current="page">EWS Anggaran</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    selectedItem: null,
    detailModalOpen: false,
    createModalOpen: false,
    createCostCenter: '',
    editModalOpen: false,
    editItem: { id: null, allocated_amount: 0, organization_name: '', cost_center_code: '', year: {{ $year }}, notes: '' },
    deleteModalOpen: false,
    deleteItem: { id: null, organization_name: '', cost_center_code: '', year: {{ $year }}, allocated_amount: 0 },
    openDetail(item) {
        this.selectedItem = item;
        this.detailModalOpen = true;
    },
    openEditModal(item) {
        this.editItem = {
            id: item.budget_id,
            allocated_amount: item.allocated_amount,
            organization_name: item.organization_name,
            cost_center_code: item.cost_center_code,
            year: item.year,
            notes: ''
        };
        this.editModalOpen = true;
    },
    openDeleteModal(item) {
        this.deleteItem = {
            id: item.budget_id,
            organization_name: item.organization_name,
            cost_center_code: item.cost_center_code,
            year: item.year,
            allocated_amount: item.allocated_amount
        };
        this.deleteModalOpen = true;
    },
    formatRupiah(val) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
    }
}">

    <!-- Alert / Notification Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-xs border-start border-4 border-success d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- KPI Metric Widgets (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- Box 1: Total Alokasi -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Alokasi (T.A. {{ $year }})</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">Rp {{ number_format($summary['total_allocated'] ?? 0, 0, ',', '.') }}</span>
                    <span class="fs-9 text-secondary">Realisasi: Rp {{ number_format($summary['total_spent'] ?? 0, 0, ',', '.') }} ({{ $summary['overall_utilization_pct'] ?? 0 }}%)</span>
                </div>
            </div>
        </div>

        <!-- Box 2: Safe Level -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-shield-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Aman (&lt; 80%)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($summary['safe'] ?? 0) }} <span class="fs-7 fw-normal text-secondary">Unit</span></span>
                    <span class="fs-9 text-secondary">Utilisasi Terkendali</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Warning & Critical -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Waspada & Kritis</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-warning">{{ number_format(($summary['warning_80'] ?? 0) + ($summary['critical_90'] ?? 0)) }} <span class="fs-7 fw-normal text-secondary">Unit</span></span>
                    <span class="fs-9 text-secondary">Waspada: {{ $summary['warning_80'] ?? 0 }} | Kritis: {{ $summary['critical_90'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Overbudget Block -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-slash-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Overbudget (Blokir)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-danger">{{ number_format($summary['overbudget_100'] ?? 0) }} <span class="fs-7 fw-normal text-secondary">Unit</span></span>
                    <span class="fs-9 text-danger fw-semibold">Pengadaan Diblokir</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card Filter & Data Table -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header with Action Tools -->
        <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 py-3 px-4">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                <i class="bi bi-shield-exclamation text-danger"></i>
                Peringatan Dini Utilisasi Anggaran Unit Kerja
            </h3>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto flex-wrap">
                <button type="button" @click="createModalOpen = true; createCostCenter = ''" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle"></i>
                    <span>Tambah Alokasi Pagu</span>
                </button>
                <form action="{{ route('master.budgets.notify_alert') }}" method="POST" onsubmit="return confirm('Kirim notifikasi peringatan dini ke seluruh penanggung jawab unit kerja yang melebihi batas 80%, 90%, atau overbudget?');" class="d-inline">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <button type="submit" class="btn btn-sm btn-outline-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-bell-fill"></i>
                        <span>Kirim Alert ({{ $summary['action_required_count'] ?? 0 }})</span>
                    </button>
                </form>
                <a href="{{ route('master.budgets') }}" class="btn btn-sm btn-outline-secondary fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-gear"></i>
                    <span>Kelola Pagu Anggaran</span>
                </a>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('master.budgets.early_warning') }}" method="GET">
                <div class="row g-2 align-items-center">
                    <!-- Tahun Anggaran -->
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar3"></i></span>
                            <select name="year" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                @for($y = date('Y') + 1; $y >= date('Y') - 2; $y--)
                                    <option value="{{ $y }}" {{ (int)$year === $y ? 'selected' : '' }}>T.A. {{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <!-- Risk Level Filter Pills -->
                    <div class="col-12 col-md-auto">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <a href="{{ route('master.budgets.early_warning', ['year' => $year, 'search' => $search, 'risk_level' => 'ALL']) }}" 
                               class="btn {{ $riskLevel === 'ALL' || empty($riskLevel) ? 'btn-danger text-white' : 'btn-outline-secondary' }}">
                                Semua ({{ $summary['total_budgets'] ?? 0 }})
                            </a>
                            <a href="{{ route('master.budgets.early_warning', ['year' => $year, 'search' => $search, 'risk_level' => 'WARNING_80']) }}" 
                               class="btn {{ $riskLevel === 'WARNING_80' ? 'btn-warning text-dark' : 'btn-outline-secondary' }}">
                                Waspada 80% ({{ $summary['warning_80'] ?? 0 }})
                            </a>
                            <a href="{{ route('master.budgets.early_warning', ['year' => $year, 'search' => $search, 'risk_level' => 'CRITICAL_90']) }}" 
                               class="btn {{ $riskLevel === 'CRITICAL_90' ? 'btn-danger text-white' : 'btn-outline-secondary' }}">
                                Kritis 90% ({{ $summary['critical_90'] ?? 0 }})
                            </a>
                            <a href="{{ route('master.budgets.early_warning', ['year' => $year, 'search' => $search, 'risk_level' => 'OVERBUDGET_BLOCK']) }}" 
                               class="btn {{ $riskLevel === 'OVERBUDGET_BLOCK' ? 'btn-dark text-white' : 'btn-outline-secondary' }}">
                                Overbudget ({{ $summary['overbudget_100'] ?? 0 }})
                            </a>
                            <a href="{{ route('master.budgets.early_warning', ['year' => $year, 'search' => $search, 'risk_level' => 'SAFE']) }}" 
                               class="btn {{ $riskLevel === 'SAFE' ? 'btn-success text-white' : 'btn-outline-secondary' }}">
                                Aman (&lt;80%) ({{ $summary['safe'] ?? 0 }})
                            </a>
                        </div>
                    </div>

                    <!-- Search Box -->
                    <div class="col-12 col-md ms-md-auto">
                        <input type="hidden" name="risk_level" value="{{ $riskLevel }}">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   name="search" 
                                   value="{{ $search }}" 
                                   placeholder="Cari nama unit kerja, kode, cost center..." 
                                   class="form-control form-control-sm border-start-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                            @if($search || ($riskLevel && $riskLevel !== 'ALL'))
                                <a href="{{ route('master.budgets.early_warning', ['year' => $year]) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Body -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="min-width: 200px;">Unit Kerja / Cabang</th>
                            <th class="py-3 text-center" style="width: 140px;">Cost Center</th>
                            <th class="py-3 text-end" style="width: 160px;">Pagu Alokasi</th>
                            <th class="py-3 text-end" style="width: 160px;">Realisasi Terpakai</th>
                            <th class="py-3 text-end" style="width: 160px;">Sisa Pagu</th>
                            <th class="py-3" style="min-width: 180px;">Utilisasi (%)</th>
                            <th class="py-3 text-center" style="width: 160px;">Status EWS</th>
                            <th class="pe-4 py-3 text-center" style="width: 100px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($evaluations as $e)
                            <tr class="{{ $e['risk_level'] === 'OVERBUDGET_BLOCK' ? 'table-danger' : ($e['risk_level'] === 'CRITICAL_90' ? 'table-warning' : '') }}">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $e['organization_name'] }}</div>
                                    <div class="small text-muted font-monospace">{{ $e['organization_code'] }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace">
                                        {{ $e['cost_center_code'] }}
                                    </span>
                                </td>
                                <td class="text-end font-monospace fw-semibold text-secondary">
                                    Rp {{ number_format($e['allocated_amount'], 0, ',', '.') }}
                                </td>
                                <td class="text-end font-monospace fw-bold {{ $e['is_blocked'] ? 'text-danger' : 'text-dark' }}">
                                    Rp {{ number_format($e['spent_amount'], 0, ',', '.') }}
                                </td>
                                <td class="text-end font-monospace fw-semibold {{ $e['remaining_amount'] < 0 ? 'text-danger' : 'text-success' }}">
                                    Rp {{ number_format($e['remaining_amount'], 0, ',', '.') }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="small fw-bold {{ $e['is_blocked'] ? 'text-danger' : ($e['utilization_pct'] >= 90 ? 'text-danger' : ($e['utilization_pct'] >= 80 ? 'text-warning' : 'text-success')) }}">
                                            {{ number_format($e['utilization_pct'], 1) }}%
                                        </span>
                                        @if($e['is_blocked'])
                                            <span class="badge bg-danger text-white fs-9 py-0">BLOKIR</span>
                                        @endif
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar {{ $e['badge_class'] }}" 
                                             role="progressbar" 
                                             style="width: {{ min(100, $e['utilization_pct']) }}%" 
                                             aria-valuenow="{{ $e['utilization_pct'] }}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($e['risk_level'] === 'OVERBUDGET_BLOCK')
                                        <span class="badge text-bg-danger fw-bold"><i class="bi bi-x-octagon-fill me-1"></i> Overbudget (&ge;100%)</span>
                                    @elseif($e['risk_level'] === 'CRITICAL_90')
                                        <span class="badge text-bg-warning fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i> Kritis (90-99%)</span>
                                    @elseif($e['risk_level'] === 'WARNING_80')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning fw-bold"><i class="bi bi-exclamation-circle me-1"></i> Waspada (80-89%)</span>
                                    @else
                                        <span class="badge text-bg-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Aman (&lt;80%)</span>
                                    @endif
                                </td>
                                <td class="text-center pe-3 pe-md-4 py-2">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openDetail({{ json_encode($e) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail & Rekomendasi EWS">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openEditModal({{ json_encode($e) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Pagu Alokasi Anggaran">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteModal({{ json_encode($e) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Hapus Alokasi Anggaran">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    Tidak ada data anggaran yang sesuai dengan filter yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-pagination-footer :paginator="$evaluations" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL: DETAIL EVALUASI EWS ANGGARAN ==================== -->
    <div x-show="detailModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="detailModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-search fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Detail Evaluasi EWS Anggaran</h6>
                        <span class="fs-8 text-secondary" x-text="selectedItem ? selectedItem.organization_name : ''"></span>
                    </div>
                </div>
                <button type="button" @click="detailModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" x-show="selectedItem" style="max-height: 75vh; overflow-y: auto;">
                <div class="mb-3">
                    <label class="text-secondary fs-8 fw-bold text-uppercase d-block mb-1">Unit Kerja / Cabang</label>
                    <h6 class="fw-bold mb-0 text-body" x-text="selectedItem?.organization_name"></h6>
                    <span class="fs-8 text-secondary font-monospace" x-text="selectedItem?.organization_code"></span>
                </div>
                <div class="mb-3">
                    <label class="text-secondary fs-8 fw-bold text-uppercase d-block mb-1">Cost Center</label>
                    <div class="fw-bold fs-8 text-body font-monospace" x-text="(selectedItem?.cost_center_code || '') + ' - ' + (selectedItem?.cost_center_name || '')"></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="text-secondary fs-8 fw-bold text-uppercase d-block mb-1">Pagu Alokasi</label>
                        <div class="fw-bold fs-7 font-monospace text-body" x-text="formatRupiah(selectedItem?.allocated_amount)"></div>
                    </div>
                    <div class="col-6">
                        <label class="text-secondary fs-8 fw-bold text-uppercase d-block mb-1">Realisasi Terpakai</label>
                        <div class="fw-bold fs-7 font-monospace" :class="selectedItem?.is_blocked ? 'text-danger' : 'text-body'" x-text="formatRupiah(selectedItem?.spent_amount)"></div>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="text-secondary fs-8 fw-bold text-uppercase d-block mb-1">Sisa Pagu</label>
                        <div class="fw-bold fs-7 font-monospace" :class="(selectedItem?.remaining_amount || 0) < 0 ? 'text-danger' : 'text-success'" x-text="formatRupiah(selectedItem?.remaining_amount)"></div>
                    </div>
                    <div class="col-6">
                        <label class="text-secondary fs-8 fw-bold text-uppercase d-block mb-1">Persentase Utilisasi</label>
                        <div class="fw-bold fs-7 font-monospace" :class="selectedItem?.is_blocked ? 'text-danger' : 'text-body'" x-text="(selectedItem?.utilization_pct || 0) + '%'"></div>
                    </div>
                </div>
                <div class="p-3 rounded border" :class="selectedItem?.is_blocked ? 'bg-danger-subtle border-danger text-danger' : ((selectedItem?.utilization_pct || 0) >= 90 ? 'bg-warning-subtle border-warning text-dark' : 'bg-body-secondary border')">
                    <div class="fw-bold fs-8 mb-1"><i class="bi bi-info-circle-fill me-1"></i> Rekomendasi Tindakan:</div>
                    <div class="fs-8" x-text="selectedItem?.recommendation"></div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3" @click="detailModalOpen = false">Tutup</button>
                <a :href="'{{ route('master.budgets') }}?search=' + (selectedItem ? selectedItem.organization_name : '')" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                    Edit Alokasi Pagu
                </a>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: TAMBAH ALOKASI PAGU ANGGARAN ==================== -->
    <div x-show="createModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createModalOpen = false; createCostCenter = ''" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
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
                            @foreach($organizations ?? [] as $org)
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
                            <input type="number" name="year" value="{{ $currentYear ?? date('Y') }}" min="2020" max="2099" required class="form-control form-control-sm fw-bold fs-8">
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

    <!-- ==================== MODAL: EDIT PAGU ALOKASI ANGGARAN ==================== -->
    <div x-show="editModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-pencil fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Edit Pagu Alokasi Anggaran</h6>
                        <span class="fs-8 text-secondary" x-text="editItem.organization_name"></span>
                    </div>
                </div>
                <button type="button" @click="editModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/master/budgets/' + editItem.id" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Cost Center</label>
                            <input type="text" class="form-control form-control-sm fs-8 font-monospace fw-bold" :value="editItem.cost_center_code" disabled>
                        </div>
                        <div class="col-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tahun Anggaran</label>
                            <input type="text" class="form-control form-control-sm fs-8 font-monospace fw-bold" :value="editItem.year" disabled>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Pagu Alokasi Baru (Rp) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary fs-8">Rp</span>
                            <input type="number" 
                                   name="allocated_amount" 
                                   x-model="editItem.allocated_amount" 
                                   min="0" 
                                   step="1000" 
                                   required 
                                   class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <span class="fs-9 text-secondary mt-1 d-block" x-text="'Format: ' + formatRupiah(editItem.allocated_amount)"></span>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan / Keterangan Revisi</label>
                        <textarea name="notes" x-model="editItem.notes" rows="2" class="form-control form-control-sm fs-8" placeholder="Alasan penyesuaian pagu..."></textarea>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: HAPUS ALOKASI ANGGARAN ==================== -->
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
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Pagu Anggaran</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModalOpen = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/master/budgets/' + deleteItem.id" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin menghapus alokasi pagu anggaran unit kerja berikut dari sistem?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">Unit Kerja & Cost Center:</div>
                        <div class="fs-8 text-body fw-bold" x-text="deleteItem.organization_name"></div>
                        <div class="font-monospace text-danger fs-8 fw-semibold" x-text="deleteItem.cost_center_code + ' (T.A. ' + deleteItem.year + ')'"></div>
                        <div class="fs-8 text-secondary mt-1 font-monospace" x-text="'Pagu: ' + formatRupiah(deleteItem.allocated_amount)"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Menghapus pagu anggaran akan mempengaruhi kalkulasi utilisasi EWS dan blokir validasi pemesanan unit terkait.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModalOpen = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Pagu
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
