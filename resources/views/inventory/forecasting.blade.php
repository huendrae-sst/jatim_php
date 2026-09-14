@extends('layouts.app')
@section('title', 'Forecasting & Reorder Recommendation')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Forecasting & ROP</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Summary Metrics (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- 1. Total SKU Dianalisis -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total SKU Dianalisis</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($totalSkuCount) }} SKU</span>
                    <span class="fs-9 text-secondary">Katalog barang yang dimonitor</span>
                </div>
            </div>
        </div>

        <!-- 2. Kritis / Stockout -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-exclamation-octagon"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Kritis / Stockout</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $criticalCount > 0 ? 'text-danger' : 'text-body-emphasis' }}">{{ number_format($criticalCount) }} SKU</span>
                    <span class="fs-9 text-secondary">Stok habis (0 unit bebas)</span>
                </div>
            </div>
        </div>

        <!-- 3. Perlu Reorder Segera -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-cart-plus"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Perlu Reorder Segera</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $reorderCount > 0 ? 'text-warning-emphasis' : 'text-body-emphasis' }}">{{ number_format($reorderCount) }} SKU</span>
                    <span class="fs-9 text-secondary">Di bawah titik Reorder (ROP)</span>
                </div>
            </div>
        </div>

        <!-- 4. Kondisi Stok Aman -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-shield-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Kondisi Stok Aman</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($safeCount) }} SKU</span>
                    <span class="fs-9 text-secondary">Safety stock & buffer terpenuhi</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Container -->
    <div class="card shadow-sm border-0 rounded-3">
        <!-- Card Header -->
        <div class="card-header bg-body py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                Analisis Kebutuhan Stok per SKU
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">{{ $forecasts->total() }} SKU Teranalisis</span>
        </div>

        <!-- Filter & Search Toolbar (seperti master/items) -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('inventory.forecasting') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <input type="hidden" name="horizon" value="{{ $horizon }}">
                <div class="row g-2 align-items-center">
                    <!-- Horizon Selector Pills -->
                    <div class="col-12 col-xl-auto mb-1 mb-xl-0">
                        <label class="form-label fs-9 text-muted d-block mb-1 fw-bold text-uppercase">Horizon Perencanaan:</label>
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="{{ route('inventory.forecasting', array_merge(request()->query(), ['horizon' => 1])) }}" 
                               class="btn {{ $horizon === 1 ? 'btn-danger text-white fw-bold' : 'btn-outline-secondary' }}">
                                1 Bulan
                            </a>
                            <a href="{{ route('inventory.forecasting', array_merge(request()->query(), ['horizon' => 3])) }}" 
                               class="btn {{ $horizon === 3 ? 'btn-danger text-white fw-bold' : 'btn-outline-secondary' }}">
                                3 Bulan (Triwulan)
                            </a>
                            <a href="{{ route('inventory.forecasting', array_merge(request()->query(), ['horizon' => 6])) }}" 
                               class="btn {{ $horizon === 6 ? 'btn-danger text-white fw-bold' : 'btn-outline-secondary' }}">
                                6 Bulan (Semester)
                            </a>
                            <a href="{{ route('inventory.forecasting', array_merge(request()->query(), ['horizon' => 12])) }}" 
                               class="btn {{ $horizon === 12 ? 'btn-danger text-white fw-bold' : 'btn-outline-secondary' }}">
                                12 Bulan (Tahunan)
                            </a>
                        </div>
                    </div>

                    <!-- Kategori Filter -->
                    <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                        <label class="form-label fs-9 text-muted d-block mb-1 fw-bold text-uppercase">Kategori:</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-tag"></i></span>
                            <select name="category_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Kategori</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (string)$categoryId === (string)$cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Status Risiko Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label fs-9 text-muted d-block mb-1 fw-bold text-uppercase">Status Risiko:</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-shield-exclamation"></i></span>
                            <select name="risk_level" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Risiko</option>
                                <option value="CRITICAL_STOCKOUT" {{ $riskLevel === 'CRITICAL_STOCKOUT' ? 'selected' : '' }}>Stockout</option>
                                <option value="HIGH_REORDER" {{ $riskLevel === 'HIGH_REORDER' ? 'selected' : '' }}>Reorder Now</option>
                                <option value="SAFE" {{ $riskLevel === 'SAFE' ? 'selected' : '' }}>Aman</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($categoryId && $categoryId !== 'ALL') || $riskLevel || $horizon !== 6)
                        <div class="col-auto align-self-end">
                            <a href="{{ route('inventory.forecasting') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto align-self-end">
                        <label class="form-label fs-9 text-muted d-block mb-1 fw-bold text-uppercase">Pencarian:</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   name="search" 
                                   value="{{ $search }}" 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8" 
                                   placeholder="Cari SKU atau nama item...">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
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
                            <th class="ps-4 py-3">Item & SKU</th>
                            <th class="py-3 text-center">Permintaan / Bln</th>
                            <th class="py-3 text-center">Tren</th>
                            <th class="py-3 text-center bg-primary-subtle text-primary-emphasis">Proyeksi ({{ $horizon }} Bln)</th>
                            <th class="py-3 text-center">Safety Stock</th>
                            <th class="py-3 text-center">Reorder Point (ROP)</th>
                            <th class="py-3 text-center">Stok Tersedia</th>
                            <th class="py-3 text-center bg-danger-subtle text-danger-emphasis">Rekomendasi Beli</th>
                            <th class="py-3 pe-4 text-center">Status Risiko</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($forecasts as $f)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-body">{{ $f['name'] }}</div>
                                    <div class="text-secondary fs-8 font-monospace">{{ $f['sku'] }} • {{ $f['category_name'] ?? '' }}{{ !empty($f['category_name']) && $f['category_name'] !== '-' ? ' • ' : '' }}{{ $f['uom'] }}</div>
                                </td>
                                <td class="py-3 text-center fw-bold text-body font-monospace">{{ $f['avg_monthly_demand'] }} {{ $f['uom'] }}</td>
                                <td class="py-3 text-center">
                                    @if(($f['trend'] ?? 'STABLE') === 'UP')
                                        <span class="badge bg-danger-subtle text-danger fs-9" title="Tren Permintaan Meningkat"><i class="bi bi-graph-up-arrow me-1"></i> NAIK</span>
                                    @elseif(($f['trend'] ?? 'STABLE') === 'DOWN')
                                        <span class="badge bg-success-subtle text-success fs-9" title="Tren Permintaan Menurun"><i class="bi bi-graph-down-arrow me-1"></i> TURUN</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis fs-9" title="Tren Stabil"><i class="bi bi-dash me-1"></i> STABIL</span>
                                    @endif
                                </td>
                                <td class="py-3 text-center fw-bold text-primary font-monospace bg-primary-subtle bg-opacity-25">
                                    {{ $f['projected_horizon_demand'] ?? ($f['avg_monthly_demand'] * $horizon) }} {{ $f['uom'] }}
                                </td>
                                <td class="py-3 text-center font-monospace text-secondary">{{ $f['recommended_safety_stock'] }} {{ $f['uom'] }}</td>
                                <td class="py-3 text-center fw-bold text-warning-emphasis font-monospace">{{ $f['reorder_point'] }} {{ $f['uom'] }}</td>
                                <td class="py-3 text-center fw-bold font-monospace text-body">{{ $f['current_available'] }} {{ $f['uom'] }}</td>
                                <td class="py-3 text-center bg-danger-subtle bg-opacity-25">
                                    @if($f['suggested_reorder_qty'] > 0)
                                        <span class="badge bg-danger-subtle text-danger font-monospace fw-bold fs-8 px-2 py-1">
                                            +{{ $f['suggested_reorder_qty'] }} {{ $f['uom'] }}
                                        </span>
                                    @else
                                        <span class="text-secondary fs-8">Cukup</span>
                                    @endif
                                </td>
                                <td class="py-3 pe-4 text-center">
                                    @if($f['risk_level'] === 'CRITICAL_STOCKOUT')
                                        <span class="badge bg-danger text-white fs-8 px-2 py-1">STOCKOUT</span>
                                    @elseif($f['risk_level'] === 'HIGH_REORDER')
                                        <span class="badge bg-warning text-dark fs-8 px-2 py-1">REORDER NOW</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success fs-8 px-2 py-1">AMAN</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <i class="bi bi-inbox text-secondary fs-1 mb-2"></i>
                                        <span class="text-secondary fw-medium">Tidak ada data analisis forecasting yang ditemukan</span>
                                        @if($search || ($categoryId && $categoryId !== 'ALL') || $riskLevel)
                                            <a href="{{ route('inventory.forecasting') }}" class="btn btn-sm btn-outline-danger mt-3">
                                                <i class="bi bi-x-circle me-1"></i> Reset Pencarian
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <x-pagination-footer :paginator="$forecasts" :perPage="$perPage" />
    </div>
</div>
@endsection
