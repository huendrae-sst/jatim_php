@extends('layouts.app')
@section('title', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- KPI Metric Widgets (Adaptive by Role & Organization) -->
    <div class="row g-2 g-md-3">
        @if($isBranch)
            <x-kpi-card 
                title="Pagu Anggaran {{ $user->organization->code ?? 'Cabang' }}"
                value="Rp {{ number_format($branchBudgetAvailable / 1000000, 1) }}M"
                subtext="Pagu: Rp {{ number_format($branchBudgetPagu / 1000000, 1) }}M (Serapan {{ $branchBudgetUtilization }}%)"
                icon="bi-pie-chart-fill"
                color="warning"
                :link="route('master.budgets')"
                linkText="Detail Pagu Cabang"
            />

            <x-kpi-card 
                title="Pesanan Berjalan"
                value="{{ $branchTotalOrders }} Order"
                subtext="{{ $branchPendingOrders }} Menunggu Persetujuan"
                icon="bi-cart-check-fill"
                color="success"
                :link="route('orders.index')"
                linkText="Daftar Order Cabang"
            />

            <x-kpi-card 
                title="Distribusi & In-Transit"
                value="{{ $branchInTransit }} Paket"
                subtext="{{ $branchDelivered }} Paket Sukses Diterima"
                icon="bi-truck"
                color="info"
                :link="route('receiving.index')"
                linkText="Penerimaan Barang Cabang"
            />

            <x-kpi-card 
                title="Total Valuasi Persediaan"
                value="{{ number_format($branchOnHand) }} Unit"
                subtext="Valuasi: Rp {{ number_format($branchStockValuation / 1000000, 1) }}M ({{ $branchSkuCount }} SKU)"
                icon="bi-box-seam"
                color="danger"
                :link="route('inventory.balances')"
                linkText="Stok Gudang Cabang"
            />
        @else
            <x-kpi-card 
                title="Total Valuasi Persediaan"
                value="Rp {{ number_format($totalStockValue / 1000000, 1) }}M"
                subtext="Bebas: Rp {{ number_format($availableStockValue / 1000000, 1) }}M"
                icon="bi-currency-dollar"
                color="danger"
                :link="route('inventory.balances')"
                linkText="Rincian Stock Balances"
            />

            <x-kpi-card 
                title="Serapan Pagu Anggaran 2026"
                value="{{ $budgetUtilization }}%"
                subtext="Realisasi: Rp {{ number_format($totalBudgetRealized / 1000000, 0) }} jt"
                icon="bi-pie-chart-fill"
                color="warning"
                :link="route('master.budgets')"
                linkText="Lihat Pagu Cabang"
            />

            <x-kpi-card 
                title="Distribusi & In-Transit"
                value="{{ $inTransitShipments + $readyToShipOrders }} Paket"
                subtext="{{ $deliveredCount }} Paket Sukses Diterima"
                icon="bi-truck"
                color="info"
                :link="route('distribution.shipments.index')"
                linkText="Monitoring Manifest"
            />

            <x-kpi-card 
                title="Pesanan Berjalan"
                value="{{ $pendingOrderApprovals + $allocatedOrders }} Order"
                subtext="{{ $pendingOrderApprovals }} Butuh Persetujuan"
                icon="bi-cart-check-fill"
                color="success"
                :link="route('orders.index')"
                linkText="Daftar Semua Pesanan"
            />
        @endif
    </div>

    <!-- EWS Early Warning Banner -->
    @if(($ewsSummary['total_alerts'] ?? 0) > 0)
        <div class="alert alert-danger-subtle border border-danger-subtle d-flex align-items-center justify-content-between p-3 rounded-3 shadow-xs mb-0 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bi bi-radar fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-danger-emphasis d-flex align-items-center gap-2">
                        Early Warning System (EWS): Terdeteksi {{ $ewsSummary['total_alerts'] }} Anomali Persediaan
                        @if($ewsSummary['critical_count'] > 0)
                            <span class="badge bg-danger">{{ $ewsSummary['critical_count'] }} Kritis/Stockout</span>
                        @endif
                        @if($ewsSummary['reorder_count'] > 0)
                            <span class="badge bg-warning text-dark">{{ $ewsSummary['reorder_count'] }} Reorder (ROP)</span>
                        @endif
                    </h6>
                    <p class="fs-8 text-secondary mb-0">
                        Terdapat SKU dalam kondisi bahaya kehabisan stok, melebihi kapasitas (overstock), atau mengendap tanpa mutasi. Valuasi aset terdampak: <strong>Rp {{ number_format($ewsSummary['total_at_risk_valuation'], 0, ',', '.') }}</strong>.
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('inventory.early_warning') }}" class="btn btn-sm btn-danger fs-8">
                    <i class="bi bi-shield-exclamation me-1"></i> Buka Radar EWS
                </a>
            </div>
        </div>
    @endif

    <!-- Operational Action Center (Adaptive Info-Boxes) -->
    <div class="row g-2 g-sm-3">
        @if($isBranch)
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('orders.create') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-danger"><i class="bi bi-plus-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Order Baru</span>
                            <span class="info-box-number fs-7 text-danger fw-bold">Buat Order</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('orders.index') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-warning"><i class="bi bi-clock-history"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Order Pending</span>
                            <span class="info-box-number fs-6 text-body-emphasis">{{ $branchPendingOrders }}</span>
                        </div>
                    </div>
                </a>
            </div>

            @if($user->canAccessModule('order_approvals'))
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="{{ route('orders.approvals') }}" class="text-decoration-none">
                        <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                            <span class="info-box-icon text-bg-primary"><i class="bi bi-check2-circle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Persetujuan</span>
                                <span class="info-box-number fs-6 text-body-emphasis">{{ $branchPendingOrders }}</span>
                            </div>
                        </div>
                    </a>
                </div>
            @endif

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('receiving.index') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-info"><i class="bi bi-truck"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Penerimaan</span>
                            <span class="info-box-number fs-6 text-body-emphasis">{{ $branchInTransit }}</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('receiving.discrepancies') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-danger"><i class="bi bi-exclamation-octagon"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Selisih QC</span>
                            <span class="info-box-number fs-6 text-danger">{{ $discrepancyReports }}</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('inventory.balances') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-success"><i class="bi bi-box-seam"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Stok Cabang</span>
                            <span class="info-box-number fs-6 text-success">{{ $branchSkuCount }} SKU</span>
                        </div>
                    </div>
                </a>
            </div>
        @else
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('procurement.pr.index') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-warning"><i class="bi bi-clock-history"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">PR Pending</span>
                            <span class="info-box-number fs-6 text-body-emphasis">{{ $pendingPrCount }}</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('procurement.consolidation.index') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-primary"><i class="bi bi-collection"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">PR Pool (PO)</span>
                            <span class="info-box-number fs-6 text-body-emphasis">{{ $approvedPrPoolCount }}</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('orders.index') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-danger"><i class="bi bi-cart3"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Order Cabang</span>
                            <span class="info-box-number fs-6 text-body-emphasis">{{ $pendingOrderApprovals }}</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('warehouse.picking.queue') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-secondary"><i class="bi bi-boxes"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Gudang Pick/Pack</span>
                            <span class="info-box-number fs-6 text-body-emphasis">{{ $allocatedOrders + $readyToShipOrders }}</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('receiving.discrepancies') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-danger"><i class="bi bi-exclamation-octagon"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Discrepancy</span>
                            <span class="info-box-number fs-6 text-danger">{{ $discrepancyReports }}</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('finance.settlements.index') }}" class="text-decoration-none">
                    <div class="info-box shadow-xs mb-0 h-100 hover:shadow transition bg-body">
                        <span class="info-box-icon text-bg-success"><i class="bi bi-journal-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Settlement</span>
                            <span class="info-box-number fs-6 text-success">{{ $pendingSettlements }}</span>
                        </div>
                    </div>
                </a>
            </div>
        @endif
    </div>

    <!-- Charts Row -->
    <div class="row g-3">
        <!-- Chart 1: Category Valuation -->
        <div class="col-12 col-lg-5">
            <div class="card card-outline card-danger shadow-xs h-100">
                <div class="card-header border-bottom">
                    <h3 class="card-title fs-6 fw-bold mb-0 text-body">
                        Valuasi per Kategori Barang
                    </h3>
                    <div class="card-tools">
                        <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle fs-8">Aktif</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="position-relative" style="height: 260px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: Pagu vs Realisasi per Unit Kerja -->
        <div class="col-12 col-lg-7">
            <div class="card card-outline card-warning shadow-xs h-100">
                <div class="card-header border-bottom">
                    <h3 class="card-title fs-6 fw-bold mb-0 text-body">
                        Serapan Pagu Anggaran per Cabang
                    </h3>
                    <div class="card-tools">
                        <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-8">TA 2026</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="position-relative" style="height: 260px;">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table (Reusable & Mobile-First) -->
    <x-card :title="$isBranch ? 'Histori Permintaan Order ' . ($user->organization->name ?? 'Cabang') : 'Histori Permintaan Order Terakhir'" icon="bi bi-clock-history" :noPadding="true">
        <x-slot:actions>
            <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-danger fs-8 fw-semibold">
                {{ $isBranch ? 'Semua Pesanan Cabang' : 'Lihat Semua Pesanan' }} <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </x-slot:actions>

        <x-table>
            <thead class="border-bottom fs-8 text-uppercase text-secondary bg-body-tertiary">
                <tr>
                    <th class="ps-3">Nomor Order</th>
                    <th>Unit Peminta</th>
                    <th>Status Alur</th>
                    <th class="text-end">Nilai Estimasi</th>
                    <th>Tanggal Diajukan</th>
                    <th class="text-center pe-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $ord)
                    <tr>
                        <td class="ps-3 fw-bold font-monospace">
                            <a href="{{ route('orders.index') }}" class="text-decoration-none text-danger">
                                {{ $ord->order_number }}
                            </a>
                        </td>
                        <td>
                            <span class="fw-semibold text-body">{{ $ord->requestingOrganization->name }}</span>
                            <div class="fs-8 text-secondary font-monospace">{{ $ord->requestingOrganization->code }}</div>
                        </td>
                        <td>
                            <x-status-badge :status="$ord->status" />
                        </td>
                        <td class="text-end fw-bold font-monospace text-body-emphasis">
                            Rp {{ number_format($ord->total_estimated_value, 0, ',', '.') }}
                        </td>
                        <td class="text-secondary fs-8">{{ $ord->created_at->format('d M Y, H:i') }} WIB</td>
                        <td class="text-center pe-3">
                            <x-action-button type="view" icon="bi bi-arrow-right" :href="route('orders.index')" title="Buka Daftar Order" />
                        </td>
                    </tr>
                @empty
                    <x-empty-state colspan="6" title="Belum ada transaksi order" />
                @endforelse
            </tbody>
        </x-table>
    </x-card>

</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const isDark = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const getTextColor = () => isDark() ? '#94a3b8' : '#64748b';
        const getGridColor = () => isDark() ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

        // 1. Category Chart
        const catData = {{ Js::from($categoryValuations) }};
        const ctxCat = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: catData.map(c => c.name),
                datasets: [{
                    data: catData.map(c => c.value),
                    backgroundColor: [
                        '#D9252A', // Bank Jatim Red
                        '#EA580C', // Orange
                        '#4F46E5', // Indigo
                        '#10B981', // Emerald
                        '#0EA5E9', // Sky
                    ],
                    borderWidth: 2,
                    borderColor: isDark() ? '#1a1d21' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            boxWidth: 10, 
                            font: { size: 11 },
                            color: getTextColor()
                        } 
                    }
                }
            }
        });

        // 2. Budget Utilization Bar Chart
        const budgetData = {{ Js::from($branchBudgets) }};
        const ctxBudget = document.getElementById('budgetChart').getContext('2d');
        const budgetChart = new Chart(ctxBudget, {
            type: 'bar',
            data: {
                labels: budgetData.map(b => b.name),
                datasets: [
                    {
                        label: 'Pagu Alokasi',
                        data: budgetData.map(b => b.allocated),
                        backgroundColor: isDark() ? '#374151' : '#CBD5E1',
                        borderRadius: 6,
                        barThickness: 16,
                    },
                    {
                        label: 'Realisasi',
                        data: budgetData.map(b => b.realized),
                        backgroundColor: '#D9252A',
                        borderRadius: 6,
                        barThickness: 16,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            boxWidth: 10, 
                            font: { size: 11 },
                            color: getTextColor()
                        } 
                    }
                },
                scales: {
                    y: {
                        grid: { color: getGridColor() },
                        ticks: {
                            color: getTextColor(),
                            callback: function(value) {
                                return 'Rp ' + (value / 1000000).toLocaleString('id-ID') + ' jt';
                            },
                            font: { size: 10 }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { 
                            color: getTextColor(),
                            font: { size: 10, weight: 'bold' } 
                        }
                    }
                }
            }
        });

        // Watch theme changes to dynamically update charts
        const observer = new MutationObserver(function() {
            const dark = isDark();
            const textColor = getTextColor();
            const gridColor = getGridColor();

            // Update Category Chart
            categoryChart.data.datasets[0].borderColor = dark ? '#1a1d21' : '#ffffff';
            categoryChart.options.plugins.legend.labels.color = textColor;
            categoryChart.update();

            // Update Budget Chart
            budgetChart.data.datasets[0].backgroundColor = dark ? '#374151' : '#CBD5E1';
            budgetChart.options.plugins.legend.labels.color = textColor;
            budgetChart.options.scales.y.ticks.color = textColor;
            budgetChart.options.scales.y.grid.color = gridColor;
            budgetChart.options.scales.x.ticks.color = textColor;
            budgetChart.update();
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-bs-theme']
        });
    });
</script>
@endsection
