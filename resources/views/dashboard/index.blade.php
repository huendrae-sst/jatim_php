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
                :link="auth()->user()->canAccessModule('master_budgets') ? route('master.budgets') : null"
                :linkText="auth()->user()->canAccessModule('master_budgets') ? 'Detail Pagu Cabang' : null"
            />

            <x-kpi-card 
                title="Pesanan Berjalan"
                value="{{ $branchTotalOrders }} Order"
                subtext="{{ $branchPendingOrders }} Menunggu Persetujuan"
                icon="bi-cart-check-fill"
                color="success"
                :link="auth()->user()->canAccessModule('orders') ? route('orders.index') : null"
                :linkText="auth()->user()->canAccessModule('orders') ? 'Daftar Order Cabang' : null"
            />

            <x-kpi-card 
                title="Distribusi & In-Transit"
                value="{{ $branchInTransit }} Paket"
                subtext="{{ $branchDelivered }} Paket Sukses Diterima"
                icon="bi-truck"
                color="info"
                :link="auth()->user()->canAccessModule('receiving_branch') || auth()->user()->canAccessModule('receiving') ? route('receiving.index') : null"
                :linkText="auth()->user()->canAccessModule('receiving_branch') || auth()->user()->canAccessModule('receiving') ? 'Penerimaan Barang Cabang' : null"
            />

            <x-kpi-card 
                title="Total Valuasi Persediaan"
                value="{{ number_format($branchOnHand) }} Unit"
                subtext="Valuasi: Rp {{ number_format($branchStockValuation / 1000000, 1) }}M ({{ $branchSkuCount }} SKU)"
                icon="bi-box-seam"
                color="danger"
                :link="auth()->user()->canAccessModule('inventory') ? route('inventory.balances') : null"
                :linkText="auth()->user()->canAccessModule('inventory') ? 'Stok Gudang Cabang' : null"
            />
        @else
            <x-kpi-card 
                title="Total Valuasi Persediaan"
                value="Rp {{ number_format($totalStockValue / 1000000, 1) }}M"
                subtext="Bebas: Rp {{ number_format($availableStockValue / 1000000, 1) }}M"
                icon="bi-currency-dollar"
                color="danger"
                :link="auth()->user()->canAccessModule('inventory') ? route('inventory.balances') : null"
                :linkText="auth()->user()->canAccessModule('inventory') ? 'Rincian Stock Balances' : null"
            />

            <x-kpi-card 
                title="Serapan Pagu Anggaran 2026"
                value="{{ $budgetUtilization }}%"
                subtext="Realisasi: Rp {{ number_format($totalBudgetRealized / 1000000, 0) }} jt"
                icon="bi-pie-chart-fill"
                color="warning"
                :link="auth()->user()->canAccessModule('master_budgets') ? route('master.budgets') : null"
                :linkText="auth()->user()->canAccessModule('master_budgets') ? 'Lihat Pagu Cabang' : null"
            />

            <x-kpi-card 
                title="Distribusi & In-Transit"
                value="{{ $inTransitShipments + $readyToShipOrders }} Paket"
                subtext="{{ $deliveredCount }} Paket Sukses Diterima"
                icon="bi-truck"
                color="info"
                :link="auth()->user()->canAccessModule('distribution') ? route('distribution.shipments.index') : null"
                :linkText="auth()->user()->canAccessModule('distribution') ? 'Monitoring Manifest' : null"
            />

            <x-kpi-card 
                title="Pesanan Berjalan"
                value="{{ $pendingOrderApprovals + $allocatedOrders }} Order"
                subtext="{{ $pendingOrderApprovals }} Butuh Persetujuan"
                icon="bi-cart-check-fill"
                color="success"
                :link="auth()->user()->canAccessModule('orders') || auth()->user()->canAccessModule('order_approvals') ? route('orders.index') : null"
                :linkText="auth()->user()->canAccessModule('orders') || auth()->user()->canAccessModule('order_approvals') ? 'Daftar Semua Pesanan' : null"
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

    <!-- EWS Early Warning Anggaran Banner -->
    @if(($budgetAlertSummary['total_alerts'] ?? 0) > 0)
        <div class="alert alert-warning border-warning-subtle d-flex align-items-center justify-content-between p-3 rounded-3 shadow-xs mb-0 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning text-dark p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bi bi-cash-coin fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        Early Warning System (EWS) Anggaran: {{ $budgetAlertSummary['total_alerts'] }} Unit Kerja Terdeteksi
                        @if($budgetAlertSummary['overbudget_count'] > 0)
                            <span class="badge bg-danger"><i class="bi bi-slash-circle me-1"></i>{{ $budgetAlertSummary['overbudget_count'] }} Overbudget &ge;100%</span>
                        @endif
                        @if($budgetAlertSummary['critical_90_count'] > 0)
                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $budgetAlertSummary['critical_90_count'] }} Kritis &ge;90%</span>
                        @endif
                        @if($budgetAlertSummary['warning_80_count'] > 0)
                            <span class="badge bg-info text-dark"><i class="bi bi-exclamation-circle me-1"></i>{{ $budgetAlertSummary['warning_80_count'] }} Siaga &ge;80%</span>
                        @endif
                    </h6>
                    <p class="fs-8 text-secondary mb-0">
                        Pagu anggaran belanja mendekati atau melampaui plafon tahunan. Total serapan berjalan: <strong>{{ $budgetAlertSummary['overall_rate'] }}%</strong> dari pagu Rp {{ number_format($budgetAlertSummary['total_allocated'] / 1000000, 1) }}M.
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('master.budgets.early_warning') }}" class="btn btn-sm btn-dark fs-8">
                    <i class="bi bi-pie-chart-fill me-1"></i> Detail EWS Anggaran
                </a>
            </div>
        </div>
    @endif

    <!-- Analytics & Charts Grid (Unified 2x2 Layout) -->
    <div class="row g-3 mb-4">
        <!-- Chart 1: Category Valuation -->
        <div class="col-12 col-lg-6">
            <div class="card card-outline card-danger shadow-xs h-100">
                <div class="card-header border-bottom py-2 d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                            <i class="bi bi-pie-chart-fill text-danger"></i> Valuasi per Kategori Barang
                        </h3>
                    </div>
                    <div class="card-tools">
                        <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle fs-8">Aktif</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="position-relative" style="height: 270px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 2: Pagu vs Realisasi per Unit Kerja -->
        <div class="col-12 col-lg-6">
            <div class="card card-outline card-warning shadow-xs h-100">
                <div class="card-header border-bottom py-2 d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                            <i class="bi bi-cash-stack text-warning"></i> Serapan Pagu Anggaran per Cabang
                        </h3>
                    </div>
                    <div class="card-tools">
                        <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-8">TA 2026</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="position-relative" style="height: 270px;">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 3: Stock Movement In vs Out Velocity -->
        <div class="col-12 col-lg-6">
            <div class="card card-outline card-primary shadow-xs h-100">
                <div class="card-header border-bottom py-2 d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                            <i class="bi bi-graph-up-arrow text-primary"></i> Tren Arus Masuk vs Keluar Barang
                        </h3>
                    </div>
                    <div class="card-tools">
                        <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle fs-8">6 Bulan Terakhir</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="position-relative" style="height: 270px;">
                        <canvas id="movementChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart 4: Top 5 Fast-Moving Items -->
        <div class="col-12 col-lg-6">
            <div class="card card-outline card-success shadow-xs h-100">
                <div class="card-header border-bottom py-2 d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                            <i class="bi bi-trophy-fill text-success"></i> Top 5 Barang Paling Banyak Bergerak
                        </h3>
                    </div>
                    <div class="card-tools">
                        <span class="badge text-bg-success-subtle text-success border border-success-subtle fs-8">Fast-Moving</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="position-relative" style="height: 270px;">
                        <canvas id="fastMovingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table (Reusable & Mobile-First) -->
    @if(auth()->user()->canAccessModule('orders') || auth()->user()->canAccessModule('order_approvals'))
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
    @endif

</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const isDark = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const getTextColor = () => isDark() ? '#94a3b8' : '#64748b';
        const getGridColor = () => isDark() ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

        // Helper function for Rupiah formatting
        const formatRupiah = (val) => 'Rp ' + Number(val).toLocaleString('id-ID');

        // 1. Category Chart (Doughnut)
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
                        '#8B5CF6', // Purple
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
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, family: 'system-ui, sans-serif' },
                            color: getTextColor(),
                            padding: 12
                        } 
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const val = context.parsed;
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return ` ${context.label}: ${formatRupiah(val)} (${pct}%)`;
                            }
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
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, family: 'system-ui, sans-serif' },
                            color: getTextColor(),
                            padding: 12
                        } 
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ` ${context.dataset.label}: ${formatRupiah(context.parsed.y)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
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

        // 3. Movement Chart (Line with area fill)
        const movementData = {{ Js::from($monthlyMovements) }};
        const ctxMovement = document.getElementById('movementChart').getContext('2d');
        const movementChart = new Chart(ctxMovement, {
            type: 'line',
            data: {
                labels: movementData.map(m => m.month),
                datasets: [
                    {
                        label: 'Barang Masuk (Inbound)',
                        data: movementData.map(m => m.in),
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: 'Barang Keluar (Outbound)',
                        data: movementData.map(m => m.out),
                        borderColor: '#D9252A',
                        backgroundColor: 'rgba(217, 37, 42, 0.12)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            boxWidth: 8,
                            boxHeight: 8,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, family: 'system-ui, sans-serif' },
                            color: getTextColor(),
                            padding: 12
                        } 
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ` ${context.dataset.label}: ${context.parsed.y.toLocaleString('id-ID')} unit`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: getGridColor() },
                        ticks: {
                            color: getTextColor(),
                            callback: function(value) {
                                return value.toLocaleString('id-ID');
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

        // 4. Top 5 Fast-Moving Items (Horizontal Bar Chart)
        const fastMovingData = {{ Js::from($topFastMoving) }};
        const ctxFastMoving = document.getElementById('fastMovingChart').getContext('2d');
        const fastMovingChart = new Chart(ctxFastMoving, {
            type: 'bar',
            data: {
                labels: fastMovingData.map(f => f.name),
                datasets: [{
                    label: 'Total Kuantitas',
                    data: fastMovingData.map(f => f.qty),
                    backgroundColor: [
                        '#0284C7',
                        '#0EA5E9',
                        '#38BDF8',
                        '#7DD3FC',
                        '#BAE6FD',
                    ],
                    borderRadius: 6,
                    barThickness: 16,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const item = fastMovingData[context.dataIndex];
                                const uom = item && item.uom ? ' ' + item.uom : ' unit';
                                return ` Jumlah Keluar: ${context.parsed.x.toLocaleString('id-ID')}${uom}`;
                            },
                            afterLabel: function(context) {
                                const item = fastMovingData[context.dataIndex];
                                return item && item.sku ? `SKU: ${item.sku}` : '';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: getGridColor() },
                        ticks: {
                            color: getTextColor(),
                            callback: function(value) {
                                return value.toLocaleString('id-ID');
                            },
                            font: { size: 10 }
                        }
                    },
                    y: {
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

            // Update Movement Chart
            movementChart.options.plugins.legend.labels.color = textColor;
            movementChart.options.scales.y.ticks.color = textColor;
            movementChart.options.scales.y.grid.color = gridColor;
            movementChart.options.scales.x.ticks.color = textColor;
            movementChart.update();

            // Update Fast Moving Chart
            fastMovingChart.options.scales.x.ticks.color = textColor;
            fastMovingChart.options.scales.x.grid.color = gridColor;
            fastMovingChart.options.scales.y.ticks.color = textColor;
            fastMovingChart.update();
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-bs-theme']
        });
    });
</script>
@endsection
