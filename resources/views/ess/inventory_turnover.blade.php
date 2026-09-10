@extends('layouts.app')
@section('title', 'ESS: Perputaran Persediaan & Produktivitas Aset (ITO)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Executive Support (ESS)</li>
    <li class="breadcrumb-item active" aria-current="page">Perputaran Stok (ITO)</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Top Action Bar & Filters -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3 bg-body-tertiary">
            <form action="{{ route('ess.inventory_turnover') }}" method="GET" class="row g-2 align-items-center">
                <!-- Gudang / Unit -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                        <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $warehouseId === 'all' ? 'selected' : '' }}>Semua Gudang & Cabang (Konsolidasi)</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (string)$warehouseId === (string)$wh->id ? 'selected' : '' }}>
                                    [{{ $wh->code }}] {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Kategori Barang -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-tag"></i></span>
                        <select name="category_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $categoryId === 'all' ? 'selected' : '' }}>Semua Kategori</option>
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
                    <a href="{{ route('ess.inventory_turnover.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fs-8 w-100 w-md-auto fw-semibold">
                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor CSV / Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive Summary Info-Boxes -->
    <div class="row g-3">
        <!-- 1. Rasio Perputaran Persediaan Rata-Rata -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-arrow-repeat"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Inventory Turnover (ITO)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-primary">
                        {{ $overallIto }}x / Tahun
                    </span>
                    <span class="fs-9 text-secondary">Frekuensi perputaran stok 12 bln</span>
                </div>
            </div>
        </div>

        <!-- 2. Days of Inventory (DOI) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-clock-history"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Days of Inventory (DOI)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">
                        {{ $overallDoi >= 999 ? 'Pasif' : $overallDoi.' Hari' }}
                    </span>
                    <span class="fs-9 text-secondary">Rata-rata barang terserap</span>
                </div>
            </div>
        </div>

        <!-- 3. Nilai Pengeluaran Barang 12 Bulan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-box-arrow-right"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Nilai Keluar (12 Bln)</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace text-success">
                        Rp {{ number_format($totalCogsIssued, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Total penyerapan logistik</span>
                </div>
            </div>
        </div>

        <!-- 4. Modal Tertahan pada Stok Pasif -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-lock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Modal Tertahan (Slow)</span>
                    <span class="info-box-number fs-5 fw-bold font-monospace text-warning-emphasis">
                        Rp {{ number_format($totalIdleValuation, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Persediaan bergerak lambat</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-body py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                <i class="bi bi-speedometer2 me-2 text-primary"></i> Indeks Perputaran & Kecepatan Barang Logistik
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                {{ $tableData->count() }} SKU Teranalisis
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4">SKU & Nama Barang</th>
                        <th>Kategori</th>
                        <th class="text-center">On Hand</th>
                        <th class="text-end">Nilai Saldo</th>
                        <th class="text-end">Pengeluaran (12 Bln)</th>
                        <th class="text-center">Rasio ITO</th>
                        <th class="text-center">Daya Tahan</th>
                        <th class="text-center pe-4">Klasifikasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($tableData as $row)
                        @php
                            $item = $row['item'];
                            $velBadge = match($row['velocity']) {
                                'FAST_MOVING' => 'text-bg-success',
                                'MEDIUM_MOVING' => 'text-bg-info',
                                default => 'text-bg-secondary',
                            };
                            $velLabel = match($row['velocity']) {
                                'FAST_MOVING' => 'Fast Moving',
                                'MEDIUM_MOVING' => 'Medium',
                                default => 'Slow Moving',
                            };
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-body">{{ $item->name }}</div>
                                <span class="font-monospace text-secondary fs-9">{{ $item->sku }} &bull; {{ $item->uom }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $item->category?->name ?? '-' }}</span>
                            </td>
                            <td class="text-center font-monospace fw-semibold">
                                {{ number_format($row['on_hand']) }}
                            </td>
                            <td class="text-end font-monospace text-body">
                                Rp {{ number_format($row['stock_valuation'], 0, ',', '.') }}
                            </td>
                            <td class="text-end font-monospace fw-semibold text-primary">
                                Rp {{ number_format($row['annual_cogs'], 0, ',', '.') }}
                                <div class="text-secondary fs-9 font-normal">{{ number_format($row['annual_qty_out']) }} {{ $item->uom }}</div>
                            </td>
                            <td class="text-center font-monospace fw-bold fs-8">
                                {{ $row['ito'] }}x
                            </td>
                            <td class="text-center font-monospace">
                                {{ $row['doi'] >= 999 ? '> 365 hr' : $row['doi'].' hr' }}
                            </td>
                            <td class="text-center pe-4">
                                <span class="badge {{ $velBadge }} px-2 py-1">
                                    {{ $velLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-secondary">
                                Tidak ada data barang untuk kriteria terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
