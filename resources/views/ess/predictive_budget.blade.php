@extends('layouts.app')
@section('title', 'ESS: Proyeksi Anggaran & Prediktif Pengadaan')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Executive Support (ESS)</li>
    <li class="breadcrumb-item active" aria-current="page">Proyeksi Belanja Logistik</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Top Action Bar & Filters -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3 bg-body-tertiary">
            <form action="{{ route('ess.predictive_budget') }}" method="GET" class="row g-2 align-items-center">
                <!-- Horizon Waktu Proyeksi -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-hourglass-split"></i></span>
                        <select name="horizon_months" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="3" {{ (int)$horizonMonths === 3 ? 'selected' : '' }}>Horizon 3 Bulan ke Depan (Triwulan)</option>
                            <option value="6" {{ (int)$horizonMonths === 6 ? 'selected' : '' }}>Horizon 6 Bulan ke Depan (Semester)</option>
                            <option value="12" {{ (int)$horizonMonths === 12 ? 'selected' : '' }}>Horizon 12 Bulan ke Depan (Tahunan / RKAP)</option>
                        </select>
                    </div>
                </div>

                <!-- Kategori Barang -->
                <div class="col-12 col-sm-6 col-md-4">
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
                <div class="col-12 col-sm-6 col-md-4 text-sm-end">
                    <a href="{{ route('ess.predictive_budget.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fs-8 w-100 w-md-auto fw-semibold">
                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor CSV / Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive Summary Info-Boxes -->
    <div class="row g-3">
        <!-- 1. Estimasi Anggaran Pengadaan Dibutuhkan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cash-coin"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Proyeksi Anggaran ({{ $horizonMonths }} Bln)</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace text-danger">
                        Rp {{ number_format($totalProjectedBudget, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Estimasi belanja logistik dibutuhkan</span>
                </div>
            </div>
        </div>

        <!-- 2. Jumlah SKU Butuh Reorder / Pengadaan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-cart-plus"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">SKU Perlu Pengadaan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-warning-emphasis">
                        {{ number_format($itemsRequiringProcurement) }} SKU
                    </span>
                    <span class="fs-9 text-secondary">dari total {{ $totalItemsEvaluated }} SKU teranalisis</span>
                </div>
            </div>
        </div>

        <!-- 3. Kategori Alokasi Belanja Terbesar -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-pie-chart-fill"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Kategori Terbesar</span>
                    <span class="info-box-number fs-6 fw-bold text-truncate text-primary" title="{{ $topCategoryName }}">
                        {{ $topCategoryName }}
                    </span>
                    <span class="fs-9 text-secondary font-monospace">Rp {{ number_format($topCategoryAmount, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- 4. Horizon Proyeksi Aktif -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-dark"><i class="bi bi-graph-up-arrow"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Metode Prediksi</span>
                    <span class="info-box-number fs-6 fw-bold text-body-emphasis">
                        Moving Demand ({{ $horizonMonths }} Bln)
                    </span>
                    <span class="fs-9 text-secondary">Konsumsi riil + buffer safety stock</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-body py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                <i class="bi bi-cpu me-2 text-danger"></i> Rincian Proyeksi Kebutuhan & Rekomendasi Pagu Anggaran Pengadaan
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                {{ $tableData->count() }} SKU Terprediksi
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4">SKU & Nama Barang</th>
                        <th>Kategori</th>
                        <th class="text-center">Stok Bebas</th>
                        <th class="text-center">Laju / Hari</th>
                        <th class="text-center">Kebutuhan ({{ $horizonMonths }} Bln)</th>
                        <th class="text-center text-primary">Saran Beli</th>
                        <th class="text-end">Estimasi Pagu (Rp)</th>
                        <th class="text-center pe-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($tableData as $row)
                        @php
                            $item = $row['item'];
                            $needsProcurement = $row['recommended_qty'] > 0;
                        @endphp
                        <tr class="{{ $needsProcurement ? 'bg-danger-subtle/10' : '' }}">
                            <td class="ps-4">
                                <div class="fw-bold text-body">{{ $item->name }}</div>
                                <span class="font-monospace text-secondary fs-9">{{ $item->sku }} &bull; {{ $item->uom }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $item->category?->name ?? '-' }}</span>
                            </td>
                            <td class="text-center font-monospace fw-semibold">
                                {{ number_format($row['available_stock']) }}
                            </td>
                            <td class="text-center font-monospace text-secondary">
                                {{ $row['daily_demand'] }}
                            </td>
                            <td class="text-center font-monospace">
                                {{ number_format($row['projected_demand']) }} {{ $item->uom }}
                            </td>
                            <td class="text-center font-monospace fw-bold {{ $needsProcurement ? 'text-danger' : 'text-success' }}">
                                {{ number_format($row['recommended_qty']) }}
                            </td>
                            <td class="text-end font-monospace fw-bold {{ $needsProcurement ? 'text-danger' : 'text-secondary' }}">
                                Rp {{ number_format($row['estimated_budget'], 0, ',', '.') }}
                                <div class="text-secondary fs-9 font-normal">@ Rp {{ number_format($item->estimated_unit_price, 0, ',', '.') }}</div>
                            </td>
                            <td class="text-center pe-4">
                                @if($needsProcurement)
                                    <span class="badge text-bg-danger px-2.5 py-1">
                                        Perlu Belanja
                                    </span>
                                @else
                                    <span class="badge text-bg-success px-2.5 py-1">
                                        Stok Cukup
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-secondary">
                                Tidak ada data proyeksi kebutuhan barang untuk filter terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
