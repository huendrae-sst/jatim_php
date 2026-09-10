@extends('layouts.app')
@section('title', 'Early Warning System (EWS) - Radar Risiko Persediaan')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Early Warning System (EWS)</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Summary Metrics (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- 1. Kritis / Stockout -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-exclamation-octagon"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Kritis / Stockout</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $summary['critical_count'] > 0 ? 'text-danger' : 'text-body-emphasis' }}">
                        {{ number_format($summary['critical_count']) }} SKU
                    </span>
                    <span class="fs-9 text-secondary">Stok &le; 0 atau habis &le; 3 hr</span>
                </div>
            </div>
        </div>

        <!-- 2. Perlu Reorder Segera (ROP Breach) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-cart-plus"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Reorder (ROP)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $summary['reorder_count'] > 0 ? 'text-warning-emphasis' : 'text-body-emphasis' }}">
                        {{ number_format($summary['reorder_count']) }} SKU
                    </span>
                    <span class="fs-9 text-secondary">Di bawah ambang ROP</span>
                </div>
            </div>
        </div>

        <!-- 3. Terdapat Barang Rusak -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger-subtle text-danger"><i class="bi bi-x-octagon"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Stok Rusak</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $summary['damaged_count'] > 0 ? 'text-danger' : 'text-body-emphasis' }}">
                        {{ number_format($summary['damaged_count']) }} SKU
                    </span>
                    <span class="fs-9 text-secondary">Perlu afkir / retur vendor</span>
                </div>
            </div>
        </div>

        <!-- 4. Total Nilai Berisiko -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-dark"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Valuasi Berisiko</span>
                    <span class="info-box-number fs-6 fw-bold font-monospace text-danger">
                        Rp {{ number_format($summary['total_at_risk_valuation'], 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Aset stok dalam peringatan</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-body py-3 px-4 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                Radar Peringatan Dini Persediaan Barang
            </h3>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                    {{ $warnings->total() }} SKU Masuk Kriteria
                </span>
                <form action="{{ route('inventory.early_warning.scan') }}" method="POST" onsubmit="return confirm('Jalankan pemindaian menyeluruh EWS dan kirimkan notifikasi alert ke tim terkait?');" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger fs-8">
                        Pindai & Kirim Alert
                    </button>
                </form>
                <a href="{{ route('inventory.early_warning.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fs-8">
                    Ekspor CSV
                </a>
            </div>
        </div>

        <!-- Filter Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('inventory.early_warning') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Gudang / Cabang -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                            <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="all" {{ $selectedWarehouseId === 'all' ? 'selected' : '' }}>Semua Gudang (Konsolidasi)</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ (string)$selectedWarehouseId === (string)$wh->id ? 'selected' : '' }}>
                                        [{{ $wh->code }}] {{ $wh->name }} ({{ $wh->organization?->name ?? 'Pusat' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Kategori Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
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

                    <!-- Jenis Alert -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-bell"></i></span>
                            <select name="alert_type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Status Alert</option>
                                <option value="CRITICAL_STOCKOUT" {{ $alertType === 'CRITICAL_STOCKOUT' ? 'selected' : '' }}>Kritis / Habis (Stockout)</option>
                                <option value="HIGH_REORDER" {{ $alertType === 'HIGH_REORDER' ? 'selected' : '' }}>Butuh Reorder (ROP Breach)</option>
                                <option value="OVERSTOCK_EXCESS" {{ $alertType === 'OVERSTOCK_EXCESS' ? 'selected' : '' }}>Overstock / Kelebihan</option>
                                <option value="DEAD_STOCK" {{ $alertType === 'DEAD_STOCK' ? 'selected' : '' }}>Dead Stock (&ge; 90 hari pasif)</option>
                                <option value="DAMAGED_ALERT" {{ $alertType === 'DAMAGED_ALERT' ? 'selected' : '' }}>Terdapat Stok Rusak</option>
                                <option value="SAFE" {{ $alertType === 'SAFE' ? 'selected' : '' }}>Stok Normal / Aman</option>
                            </select>
                        </div>
                    </div>

                    <!-- Search Input -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm border-start-0 fs-8" placeholder="Cari SKU / Nama Barang...">
                            <button type="submit" class="btn btn-sm btn-danger px-3 fs-8">Cari</button>
                        </div>
                    </div>

                    <!-- Reset Filter -->
                    <div class="col-12 col-sm-6 col-md-1 text-end">
                        <a href="{{ route('inventory.early_warning') }}" class="btn btn-sm btn-outline-danger fs-8 w-100" title="Reset Filter">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table View -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4">SKU & Barang</th>
                        <th class="text-center">Kategori</th>
                        <th class="text-end">On Hand</th>
                        <th class="text-end">Bebas (Avail)</th>
                        <th class="text-center">Threshold (SS / ROP / Max)</th>
                        <th class="text-center">Laju & Daya Tahan</th>
                        <th class="text-center">Status Alert EWS</th>
                        <th>Rekomendasi Tindakan Cepat</th>
                        <th class="text-center pe-4" style="width: 105px;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    @forelse($warnings as $w)
                        <tr class="{{ $w['severity'] === 'CRITICAL' ? 'table-danger-subtle' : ($w['severity'] === 'WARNING' ? 'table-warning-subtle' : '') }}">
                            <!-- SKU & Name -->
                            <td class="ps-4">
                                <div class="fw-bold text-body-emphasis font-monospace">{{ $w['sku'] }}</div>
                                <div class="text-secondary fw-semibold">{{ $w['name'] }}</div>
                                <span class="badge bg-light text-secondary border fs-9">Satuan: {{ $w['uom'] }}</span>
                            </td>

                            <!-- Category -->
                            <td class="text-center">
                                <span class="badge bg-body-secondary text-body fs-9">{{ $w['category_name'] }}</span>
                            </td>

                            <!-- On Hand -->
                            <td class="text-end font-monospace">
                                <div class="fw-bold">{{ number_format($w['on_hand']) }}</div>
                                @if($w['damaged'] > 0)
                                    <div class="badge bg-danger-subtle text-danger fs-9" title="Stok Rusak">
                                        {{ $w['damaged'] }} Rusak
                                    </div>
                                @endif
                            </td>

                            <!-- Available -->
                            <td class="text-end font-monospace">
                                @if($w['available'] <= 0)
                                    <span class="badge bg-danger fs-8 fw-bold">0 Habis</span>
                                @elseif($w['available'] <= $w['reorder_point'])
                                    <span class="text-warning-emphasis fw-bold">{{ number_format($w['available']) }}</span>
                                @else
                                    <span class="text-success fw-bold">{{ number_format($w['available']) }}</span>
                                @endif
                            </td>

                            <!-- Thresholds -->
                            <td class="text-center font-monospace fs-9">
                                <div>SS: <strong class="text-secondary">{{ $w['safety_stock'] }}</strong> | ROP: <strong class="text-warning-emphasis">{{ $w['reorder_point'] }}</strong></div>
                                <div class="text-secondary">Max: {{ $w['max_stock'] }} | LT: {{ $w['lead_time_days'] }} hr</div>
                            </td>

                            <!-- Laju Konsumsi & Daya Tahan -->
                            <td class="text-center font-monospace">
                                <div class="fw-semibold">{{ $w['daily_demand'] }} <span class="fs-9 text-secondary">/hari</span></div>
                                <div>
                                    @if($w['days_of_supply'] <= 3)
                                        <span class="badge bg-danger fs-9">{{ $w['days_of_supply'] }} hari tersisa</span>
                                    @elseif($w['days_of_supply'] <= $w['lead_time_days'])
                                        <span class="badge bg-warning text-dark fs-9">{{ $w['days_of_supply'] }} hari (kritis LT)</span>
                                    @elseif($w['days_of_supply'] > 180)
                                        <span class="badge bg-primary-subtle text-primary fs-9">&gt; 180 hari (over)</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success fs-9">{{ $w['days_of_supply'] }} hari</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Status Alert EWS -->
                            <td class="text-center">
                                @if(empty($w['alerts']))
                                    <span class="badge bg-success-subtle text-success fs-8 px-2 py-1">
                                        <i class="bi bi-check-circle me-1"></i> AMAN
                                    </span>
                                @else
                                    <div class="d-flex flex-column gap-1 align-items-center">
                                        @foreach($w['alerts'] as $al)
                                            @if($al === 'CRITICAL_STOCKOUT')
                                                <span class="badge bg-danger fs-9"><i class="bi bi-exclamation-octagon me-1"></i> STOCKOUT</span>
                                            @elseif($al === 'HIGH_REORDER')
                                                <span class="badge bg-warning text-dark fs-9"><i class="bi bi-cart-plus me-1"></i> REORDER (ROP)</span>
                                            @elseif($al === 'OVERSTOCK_EXCESS')
                                                <span class="badge bg-primary fs-9"><i class="bi bi-box-arrow-up me-1"></i> OVERSTOCK</span>
                                            @elseif($al === 'DEAD_STOCK')
                                                <span class="badge bg-secondary fs-9"><i class="bi bi-hourglass-bottom me-1"></i> DEAD STOCK</span>
                                            @elseif($al === 'DAMAGED_ALERT')
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-9"><i class="bi bi-x-octagon me-1"></i> RUSAK</span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <!-- Rekomendasi Tindakan -->
                            <td style="min-width: 280px; max-width: 360px;">
                                <div class="fs-9 text-body-emphasis fw-medium">
                                    {{ $w['recommendation'] }}
                                </div>
                                @if($w['suggested_reorder_qty'] > 0)
                                    <div class="fs-9 text-danger fw-bold mt-1">
                                        Saran Reorder: +{{ number_format($w['suggested_reorder_qty']) }} {{ $w['uom'] }}
                                    </div>
                                @endif

                                @if(!empty($w['lead_time_comparison']))
                                    @php $comp = $w['lead_time_comparison']; @endphp
                                    <div class="mt-2 p-2 rounded border bg-body-tertiary fs-9">
                                        <div class="d-flex align-items-center justify-content-between mb-1.5 pb-1 border-bottom">
                                            <span class="fw-bold text-uppercase fs-10 text-secondary d-flex align-items-center gap-1">
                                                <i class="bi bi-stopwatch text-primary"></i> Perbandingan Kecepatan
                                            </span>
                                            @if($comp['switching_available'])
                                                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle py-0.5 px-1.5 fw-bold" style="font-size: 10px;">
                                                    <i class="bi bi-lightning-charge-fill me-0.5"></i> Switching Lebih Cepat (~{{ $comp['days_saved'] }} hr)
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary border py-0.5 px-1.5" style="font-size: 10px;">
                                                    Switching Tdk Tersedia
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Jalur 1: Switching Stock via Kurir Langsung -->
                                        <div class="mb-1.5 p-1.5 rounded {{ $comp['switching_available'] ? 'bg-success-subtle border border-success-subtle' : 'bg-body border text-muted' }}">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold {{ $comp['switching_available'] ? 'text-success-emphasis' : 'text-secondary' }}">
                                                    <i class="bi bi-truck me-1"></i> Switching Stock (Kurir)
                                                </span>
                                                <span class="badge {{ $comp['switching_available'] ? 'bg-success text-white' : 'bg-secondary-subtle text-secondary' }} font-monospace" style="font-size: 10px;">
                                                    {{ $comp['switching_days_label'] }}
                                                </span>
                                            </div>
                                            <div class="text-secondary fs-10 mt-0.5">
                                                @if($comp['switching_available'])
                                                    Sumber: <strong>{{ $comp['source_label'] }}</strong> &rarr; Kurir Langsung &rarr; Gudang Tujuan.
                                                @else
                                                    Tidak ada ketersediaan surplus stok di gudang lain (0 surplus).
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Jalur 2: Pengadaan Baru PR via Vendor -->
                                        <div class="p-1.5 rounded bg-body border">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-bold text-body">
                                                    <i class="bi bi-cart-check me-1"></i> Pengadaan PR (Vendor)
                                                </span>
                                                <span class="badge bg-secondary text-white font-monospace" style="font-size: 10px;">
                                                    ~{{ $comp['pr_days'] }} hari
                                                </span>
                                            </div>
                                            <div class="text-secondary fs-10 mt-0.5">
                                                Vendor (LT {{ $comp['vendor_lead_time'] }} hr) &rarr; Gudang Pusat &rarr; Transit ke Tujuan (~{{ $comp['transit_days'] }} hr).
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </td>

                            <!-- Aksi Cepat -->
                            <td class="text-center pe-4 py-2">
                                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                    @php
                                        $stockCardWh = ($selectedWarehouseId && $selectedWarehouseId !== 'all') 
                                            ? $selectedWarehouseId 
                                            : ($w['warehouse_id'] ?? 'ALL');
                                    @endphp
                                    <a href="{{ route('inventory.stock_card', ['itemId' => $w['item_id'], 'warehouse_id' => $stockCardWh]) }}" 
                                       class="btn-action-icon text-primary" 
                                       title="Lihat Kartu Stok">
                                        <i class="bi bi-card-list"></i>
                                    </a>
                                    @if(in_array('CRITICAL_STOCKOUT', $w['alerts']) || in_array('HIGH_REORDER', $w['alerts']))
                                        <a href="{{ route('inventory.switching.index') }}" class="btn-action-icon text-warning" title="Switching Antar-Cabang">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </a>
                                        @if(auth()->user()->canAccessModule('procurement'))
                                            <a href="{{ route('procurement.pr.index') }}" class="btn-action-icon text-danger" title="Pusat Pengadaan PR">
                                                <i class="bi bi-bag-plus"></i>
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-secondary">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="bi bi-shield-check display-5 text-success mb-2"></i>
                                    <h6 class="fw-bold text-body-emphasis">Tidak Ada Anomali Ditemukan</h6>
                                    <p class="fs-8 text-secondary mb-0">Semua item memenuhi parameter batas aman persediaan.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Standardized Pagination Footer -->
        <x-pagination-footer :paginator="$warnings" :perPage="$perPage" />
    </div>
</div>
@endsection
