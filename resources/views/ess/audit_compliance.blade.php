@extends('layouts.app')
@section('title', 'ESS: Akuntabilitas, Kerugian Aset & Kepatuhan Audit')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Executive Support (ESS)</li>
    <li class="breadcrumb-item active" aria-current="page">Akuntabilitas & Audit</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Top Action Bar & Filters -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3 bg-body-tertiary">
            <form action="{{ route('ess.audit_compliance') }}" method="GET" class="row g-2 align-items-center">
                <!-- Filter Tahun Periode -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar"></i></span>
                        <select name="period_year" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            @foreach(range(date('Y') - 2, date('Y') + 1) as $y)
                                <option value="{{ $y }}" {{ (int)$year === $y ? 'selected' : '' }}>Tahun {{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Filter Gudang -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                        <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $warehouseId === 'all' ? 'selected' : '' }}>Semua Fasilitas Gudang / Cabang</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (string)$warehouseId === (string)$wh->id ? 'selected' : '' }}>
                                    [{{ $wh->code }}] {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Action Button Export CSV -->
                <div class="col-12 col-sm-6 col-md-4 text-sm-end">
                    <a href="{{ route('ess.audit_compliance.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fs-8 w-100 w-md-auto fw-semibold">
                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor CSV / Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive Summary Info-Boxes -->
    <div class="row g-3">
        <!-- 1. Kepatuhan Jadwal Opname -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-clipboard2-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Kepatuhan Opname</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">
                        {{ $complianceRate }}%
                    </span>
                    <span class="fs-9 text-secondary">{{ $postedSessions }} dari {{ $totalSessions }} sesi ter-POSTED</span>
                </div>
            </div>
        </div>

        <!-- 2. Akurasi Fisik vs Sistem -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-shield-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Akurasi Saldo Fisik</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-primary">
                        {{ $accuracyRate }}%
                    </span>
                    <span class="fs-9 text-secondary">Tingkat kesesuaian sensus stok</span>
                </div>
            </div>
        </div>

        <!-- 3. Nilai Kerugian Selisih / Discrepancy -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-calculator"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Nilai Selisih Bersih</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace {{ $totalNetVarianceValue < 0 ? 'text-danger' : 'text-body-emphasis' }}">
                        Rp {{ number_format($totalNetVarianceValue, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Net variance hasil stock opname</span>
                </div>
            </div>
        </div>

        <!-- 4. Valuasi Barang Rusak (Damaged) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-x-octagon"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Valuasi Stok Rusak</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace text-danger">
                        Rp {{ number_format($totalDamagedValuation, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Potensi write-off / klaim retur</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-body py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                <i class="bi bi-journal-check me-2 text-danger"></i> Hasil Audit Stock Opname & Kepatuhan Pelaporan Cabang
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                {{ $tableData->count() }} Sesi Opname Terdata
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4">No. Opname & Periode</th>
                        <th>Gudang / Fasilitas</th>
                        <th>Petugas Pelaksana</th>
                        <th class="text-center">Item Dihitung</th>
                        <th class="text-center">Item Selisih</th>
                        <th class="text-end">Nilai Selisih (Rp)</th>
                        <th class="text-center pe-4">Status Audit</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($tableData as $opn)
                        @php
                            $statusBadge = match($opn->status) {
                                'POSTED' => 'text-bg-success',
                                'REVIEW' => 'text-bg-warning text-dark',
                                'CANCELLED' => 'text-bg-danger',
                                default => 'text-bg-secondary',
                            };
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <span class="font-monospace fw-bold text-body">{{ $opn->opname_number }}</span>
                                <div class="text-secondary fs-9">{{ $opn->period_formatted }} &bull; Sensus: {{ $opn->opname_date?->format('d M Y') ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-body">{{ $opn->warehouse?->name ?? '-' }}</div>
                                <span class="text-secondary fs-9">{{ $opn->warehouse?->organization?->name ?? '-' }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-body">{{ $opn->user?->name ?? '-' }}</div>
                                <span class="text-secondary fs-9">{{ $opn->user?->role ?? '-' }}</span>
                            </td>
                            <td class="text-center font-monospace fw-semibold">
                                {{ number_format($opn->total_items) }}
                            </td>
                            <td class="text-center font-monospace">
                                @if($opn->discrepancy_items_count > 0)
                                    <span class="text-danger fw-bold">{{ $opn->discrepancy_items_count }} SKU</span>
                                @else
                                    <span class="text-success font-monospace">0 (Sesuai)</span>
                                @endif
                            </td>
                            <td class="text-end font-monospace fw-bold {{ $opn->net_variance_value < 0 ? 'text-danger' : ($opn->net_variance_value > 0 ? 'text-success' : 'text-body') }}">
                                Rp {{ number_format($opn->net_variance_value, 0, ',', '.') }}
                            </td>
                            <td class="text-center pe-4">
                                <span class="badge {{ $statusBadge }} px-2.5 py-1">
                                    {{ $opn->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-secondary">
                                Tidak ada data stock opname pada periode terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
