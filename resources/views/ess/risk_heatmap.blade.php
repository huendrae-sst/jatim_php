@extends('layouts.app')
@section('title', 'ESS: Peta Risiko Ketahanan Logistik Jaringan')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Executive Support (ESS)</li>
    <li class="breadcrumb-item active" aria-current="page">Peta Ketahanan Jaringan</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Top Action Bar & Filters -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3 bg-body-tertiary">
            <form action="{{ route('ess.risk_heatmap') }}" method="GET" class="row g-2 align-items-center">
                <!-- Filter Kota / Wilayah -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-geo-alt"></i></span>
                        <select name="city" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $city === 'all' ? 'selected' : '' }}>Semua Kota / Wilayah</option>
                            @foreach($cities as $c)
                                <option value="{{ $c }}" {{ (string)$city === (string)$c ? 'selected' : '' }}>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Filter Tipe Gudang -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-diagram-3"></i></span>
                        <select name="type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $type === 'all' ? 'selected' : '' }}>Semua Tipe Fasilitas</option>
                            <option value="CENTRAL_LOGISTICS" {{ $type === 'CENTRAL_LOGISTICS' ? 'selected' : '' }}>Gudang Pusat Logistik</option>
                            <option value="SUB_WAREHOUSE" {{ $type === 'SUB_WAREHOUSE' ? 'selected' : '' }}>Penyimpanan Cabang</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Status Risiko -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-shield-exclamation"></i></span>
                        <select name="risk_status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $riskFilter === 'all' ? 'selected' : '' }}>Semua Status Risiko</option>
                            <option value="AMAN" {{ strtoupper($riskFilter) === 'AMAN' ? 'selected' : '' }}>Status Aman (&ge; 85%)</option>
                            <option value="WASPADA" {{ strtoupper($riskFilter) === 'WASPADA' ? 'selected' : '' }}>Status Waspada</option>
                            <option value="KRITIS" {{ strtoupper($riskFilter) === 'KRITIS' ? 'selected' : '' }}>Status Kritis (&lt; 60%)</option>
                        </select>
                    </div>
                </div>

                <!-- Action Button Export CSV -->
                <div class="col-12 col-sm-6 col-md-3 text-sm-end">
                    <a href="{{ route('ess.risk_heatmap.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fs-8 w-100 w-md-auto fw-semibold">
                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor CSV / Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive Summary Info-Boxes -->
    <div class="row g-3">
        <!-- 1. Total Fasilitas Terpantau -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-buildings"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Gudang / Cabang</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-primary">
                        {{ number_format($totalWarehouses) }} Lokasi
                    </span>
                    <span class="fs-9 text-secondary">Jaringan logistik se-Jawa Timur</span>
                </div>
            </div>
        </div>

        <!-- 2. Status Aman -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-shield-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Ketahanan Aman</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">
                        {{ $safeCount }} Cabang
                    </span>
                    <span class="fs-9 text-secondary">Buffer stok memadai &gt; 85%</span>
                </div>
            </div>
        </div>

        <!-- 3. Status Waspada -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-shield-exclamation"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Perlu Monitoring (Waspada)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-warning-emphasis">
                        {{ $warningCount }} Cabang
                    </span>
                    <span class="fs-9 text-secondary">Mendekati ambang batas ROP</span>
                </div>
            </div>
        </div>

        <!-- 4. Status Kritis -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Kritis / Rentan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-danger">
                        {{ $criticalCount }} Cabang
                    </span>
                    <span class="fs-9 text-secondary">Terdapat potensi stockout</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-body py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                <i class="bi bi-grid-3x3-gap me-2 text-danger"></i> Matriks Ketahanan Stok & Peta Risiko per Kantor Cabang
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                {{ $tableData->count() }} Fasilitas Ditampilkan
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4">Gudang / Kantor Cabang</th>
                        <th>Tipe / Kota</th>
                        <th class="text-center">Total SKU</th>
                        <th class="text-center text-success">SKU Aman</th>
                        <th class="text-center text-warning-emphasis">Reorder (ROP)</th>
                        <th class="text-center text-danger">Kritis / Kosong</th>
                        <th class="text-center">Skor Ketahanan</th>
                        <th class="text-center pe-4">Tingkat Risiko</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($tableData as $row)
                        @php
                            $wh = $row['warehouse'];
                            $statusBadge = match($row['risk_status']) {
                                'AMAN' => 'text-bg-success',
                                'WASPADA' => 'text-bg-warning text-dark',
                                default => 'text-bg-danger',
                            };
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-body">{{ $wh->name }}</div>
                                <span class="font-monospace text-secondary fs-9">[{{ $wh->code }}] &bull; {{ $wh->organization?->name ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $wh->type === 'CENTRAL_LOGISTICS' ? 'Pusat Logistik' : 'Cabang' }}</span>
                                <div class="text-secondary fs-9 mt-0.5">{{ $wh->organization?->city ?? '-' }}</div>
                            </td>
                            <td class="text-center font-monospace fw-semibold">
                                {{ $row['managed_skus'] }}
                            </td>
                            <td class="text-center font-monospace fw-semibold text-success">
                                {{ $row['safe_skus'] }}
                            </td>
                            <td class="text-center font-monospace fw-semibold text-warning-emphasis">
                                {{ $row['reorder_skus'] }}
                            </td>
                            <td class="text-center font-monospace fw-bold text-danger">
                                {{ $row['critical_skus'] }}
                            </td>
                            <td class="text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="fw-bold font-monospace fs-8 {{ $row['resilience_score'] < 70 ? 'text-danger' : ($row['resilience_score'] < 85 ? 'text-warning-emphasis' : 'text-success') }}">
                                        {{ $row['resilience_score'] }}%
                                    </span>
                                    <div class="progress w-75 mt-1" style="height: 4px;">
                                        <div class="progress-bar {{ $row['resilience_score'] < 70 ? 'bg-danger' : ($row['resilience_score'] < 85 ? 'bg-warning' : 'bg-success') }}" 
                                             role="progressbar" 
                                             style="width: {{ $row['resilience_score'] }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center pe-4">
                                <span class="badge {{ $statusBadge }} px-2.5 py-1">
                                    {{ $row['risk_status'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-secondary">
                                Tidak ada data gudang atau cabang yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
