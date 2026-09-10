@extends('layouts.app')
@section('title', 'ESS: Valuasi Aset & Realisasi Anggaran Persediaan')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Executive Support (ESS)</li>
    <li class="breadcrumb-item active" aria-current="page">Valuasi & Anggaran</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Top Action Bar & Filters -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3 bg-body-tertiary">
            <form action="{{ route('ess.valuation_budget') }}" method="GET" class="row g-2 align-items-center">
                <!-- Tahun Anggaran -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar-check"></i></span>
                        <select name="period_year" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            @foreach(range(date('Y') - 2, date('Y') + 1) as $y)
                                <option value="{{ $y }}" {{ (int)$year === $y ? 'selected' : '' }}>Tahun Anggaran {{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Gudang / Unit -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                        <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $warehouseId === 'all' ? 'selected' : '' }}>Semua Gudang & Cabang</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (string)$warehouseId === (string)$wh->id ? 'selected' : '' }}>
                                    [{{ $wh->code }}] {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Kategori Barang -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-tag"></i></span>
                        <select name="category_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $categoryId === 'all' ? 'selected' : '' }}>Semua Kategori Barang</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (string)$categoryId === (string)$cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Action Button Export CSV -->
                <div class="col-12 col-sm-6 col-md-3 text-sm-end">
                    <a href="{{ route('ess.valuation_budget.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fs-8 w-100 w-md-auto fw-semibold">
                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor CSV / Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive Summary Info-Boxes -->
    <div class="row g-3">
        <!-- 1. Total Valuasi Persediaan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-box-seam"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Valuasi Stok On-Hand</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace text-primary">
                        Rp {{ number_format($totalValuation, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">{{ number_format($totalOnHandUnits) }} Total Unit Barang</span>
                </div>
            </div>
        </div>

        <!-- 2. Pagu Anggaran Pengadaan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-wallet2"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Pagu Anggaran {{ $year }}</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace text-body-emphasis">
                        Rp {{ number_format($totalBudgetAllocated, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Alokasi Plafon Belanja Logistik</span>
                </div>
            </div>
        </div>

        <!-- 3. Realisasi Belanja Pengadaan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-cart-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Realisasi Belanja</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace text-success">
                        Rp {{ number_format($totalBudgetRealized, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Belanja yang telah terealisasi</span>
                </div>
            </div>
        </div>

        <!-- 4. Tingkat Penyerapan Anggaran -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon {{ $budgetUtilizationRate > 85 ? 'text-bg-danger' : ($budgetUtilizationRate > 60 ? 'text-bg-warning' : 'text-bg-dark') }}">
                    <i class="bi bi-pie-chart"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Rasio Penyerapan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $budgetUtilizationRate > 85 ? 'text-danger' : 'text-body-emphasis' }}">
                        {{ $budgetUtilizationRate }}%
                    </span>
                    <span class="fs-9 text-secondary">Sisa: Rp {{ number_format($totalBudgetAvailable, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-body py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                <i class="bi bi-table me-2 text-primary"></i> Rekapitulasi Alokasi Anggaran & Valuasi Persediaan per Unit Kerja
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                {{ $tableData->count() }} Unit Kerja Terdata
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4">Unit Kerja / Kantor Cabang</th>
                        <th>Tipe / Kota</th>
                        <th class="text-end">Pagu Anggaran</th>
                        <th class="text-end">Realisasi Belanja</th>
                        <th class="text-end">Sisa Anggaran</th>
                        <th class="text-center">Penyerapan</th>
                        <th class="text-end pe-4">Valuasi Stok On-Hand</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($tableData as $row)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-body">{{ $row['organization']->name }}</div>
                                <span class="font-monospace text-secondary fs-9">[{{ $row['organization']->code }}]</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $row['organization']->type }}</span>
                                <div class="text-secondary fs-9 mt-0.5">{{ $row['organization']->city ?? '-' }}</div>
                            </td>
                            <td class="text-end font-monospace text-secondary">
                                Rp {{ number_format($row['allocated_amount'], 0, ',', '.') }}
                            </td>
                            <td class="text-end font-monospace fw-semibold text-body">
                                Rp {{ number_format($row['realized_amount'], 0, ',', '.') }}
                            </td>
                            <td class="text-end font-monospace text-success">
                                Rp {{ number_format($row['remaining_budget'], 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="fw-bold font-monospace fs-8 {{ $row['utilization_rate'] > 85 ? 'text-danger' : 'text-body' }}">
                                        {{ $row['utilization_rate'] }}%
                                    </span>
                                    <div class="progress w-75 mt-1" style="height: 4px;">
                                        <div class="progress-bar {{ $row['utilization_rate'] > 85 ? 'bg-danger' : ($row['utilization_rate'] > 50 ? 'bg-warning' : 'bg-primary') }}" 
                                             role="progressbar" 
                                             style="width: {{ min(100, $row['utilization_rate']) }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end pe-4 font-monospace fw-bold text-primary">
                                Rp {{ number_format($row['stock_valuation'], 0, ',', '.') }}
                                <div class="text-secondary fs-9 font-normal">{{ number_format($row['on_hand_units']) }} unit &bull; {{ $row['sku_count'] }} SKU</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-secondary">
                                Tidak ada data anggaran atau persediaan untuk filter terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
