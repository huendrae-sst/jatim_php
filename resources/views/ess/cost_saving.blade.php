@extends('layouts.app')
@section('title', 'ESS: Efisiensi Biaya & Penghematan Switching Stock')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Executive Support (ESS)</li>
    <li class="breadcrumb-item active" aria-current="page">Efisiensi Biaya Switching</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Top Action Bar & Filters -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3 bg-body-tertiary">
            <form action="{{ route('ess.cost_saving') }}" method="GET" class="row g-2 align-items-center">
                <!-- Tahun Transaksi -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar"></i></span>
                        <select name="period_year" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            @foreach(range(date('Y') - 2, date('Y') + 1) as $y)
                                <option value="{{ $y }}" {{ (int)$year === $y ? 'selected' : '' }}>Tahun {{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Gudang Sumber -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-box-arrow-up-right"></i></span>
                        <select name="source_warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $sourceWhId === 'all' ? 'selected' : '' }}>Semua Gudang Asal (Sumber)</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (string)$sourceWhId === (string)$wh->id ? 'selected' : '' }}>
                                    [{{ $wh->code }}] {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Gudang Tujuan -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-box-arrow-in-down-left"></i></span>
                        <select name="destination_warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $destinationWhId === 'all' ? 'selected' : '' }}>Semua Gudang Tujuan</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (string)$destinationWhId === (string)$wh->id ? 'selected' : '' }}>
                                    [{{ $wh->code }}] {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Action Button Export CSV -->
                <div class="col-12 col-sm-6 col-md-3 text-sm-end">
                    <a href="{{ route('ess.cost_saving.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fs-8 w-100 w-md-auto fw-semibold">
                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor CSV / Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive Summary Info-Boxes -->
    <div class="row g-3">
        <!-- 1. Total Penghematan Anggaran -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-piggy-bank"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Total Cost Savings</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace text-success">
                        Rp {{ number_format($totalCostSaved, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Penghematan kas tanpa beli vendor</span>
                </div>
            </div>
        </div>

        <!-- 2. Total Barang Ditransfer -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-arrow-left-right"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Barang Terdistribusi</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-primary">
                        {{ number_format($totalUnitsSwitched) }} Unit
                    </span>
                    <span class="fs-9 text-secondary">Redistribusi persediaan antar-cabang</span>
                </div>
            </div>
        </div>

        <!-- 3. Rata-rata Lead Time Switching -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-stopwatch"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Kecepatan Pemenuhan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">
                        {{ $avgLeadTimeDays }} Hari
                    </span>
                    <span class="fs-9 text-secondary">vs Benchmark Vendor: {{ $vendorBenchmarkDays }} Hari</span>
                </div>
            </div>
        </div>

        <!-- 4. Total Transaksi Selesai -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-dark"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Tingkat Penyelesaian</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">
                        {{ $totalCompleted }} / {{ $totalTransactions }}
                    </span>
                    <span class="fs-9 text-secondary">Sesi switching tuntas diterima</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-body py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                <i class="bi bi-arrow-left-right me-2 text-success"></i> Rincian Realisasi Penghematan Anggaran via Switching Stock
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                {{ $tableData->count() }} Aktivitas Switching
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4">No. Referensi</th>
                        <th>Gudang Asal (Sumber)</th>
                        <th>Gudang Tujuan</th>
                        <th class="text-center">Kuantitas</th>
                        <th class="text-end">Nilai Penghematan</th>
                        <th class="text-center">Lead Time</th>
                        <th class="text-center pe-4">Status Alur</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($tableData as $row)
                        @php
                            $sw = $row['switching'];
                            $badgeClass = match($sw->status) {
                                'RECEIVED' => 'text-bg-success',
                                'DISPATCHED' => 'text-bg-primary',
                                'APPROVED' => 'text-bg-info',
                                'PROPOSED' => 'text-bg-warning text-dark',
                                'REJECTED' => 'text-bg-danger',
                                default => 'text-bg-secondary',
                            };
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <span class="font-monospace fw-bold text-body">{{ $sw->reference_number }}</span>
                                <div class="text-secondary fs-9">{{ $sw->created_at?->format('d M Y') ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-body">{{ $sw->sourceWarehouse?->name ?? '-' }}</div>
                                <span class="text-secondary fs-9">{{ $sw->sourceOrganization?->name ?? 'Logistik' }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-body">{{ $sw->destinationWarehouse?->name ?? '-' }}</div>
                                <span class="text-secondary fs-9">{{ $sw->destinationOrganization?->name ?? 'Cabang' }}</span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold font-monospace">{{ number_format($row['units_count']) }}</span>
                                <span class="text-secondary fs-9">({{ $row['items_count'] }} SKU)</span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-success">
                                Rp {{ number_format($row['savings_amount'], 0, ',', '.') }}
                            </td>
                            <td class="text-center">
                                @if($row['duration_days'])
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle font-monospace">
                                        {{ $row['duration_days'] }} Hari
                                    </span>
                                @else
                                    <span class="text-secondary fs-9">Dalam Pengiriman</span>
                                @endif
                            </td>
                            <td class="text-center pe-4">
                                <span class="badge {{ $badgeClass }} px-2 py-1">
                                    {{ $sw->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-secondary">
                                Tidak ada aktivitas switching stock pada filter terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
