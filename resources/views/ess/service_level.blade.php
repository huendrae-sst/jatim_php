@extends('layouts.app')
@section('title', 'ESS: Kinerja Layanan & SLA Distribusi')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Executive Support (ESS)</li>
    <li class="breadcrumb-item active" aria-current="page">Kinerja Layanan (SLA)</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Top Action Bar & Filters -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3 bg-body-tertiary">
            <form action="{{ route('ess.service_level') }}" method="GET" class="row g-2 align-items-center">
                <!-- Filter Tahun -->
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

                <!-- Filter Cabang Pemohon -->
                <div class="col-12 col-sm-6 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                        <select name="organization_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                            <option value="all" {{ $orgId === 'all' ? 'selected' : '' }}>Semua Cabang Pemohon</option>
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}" {{ (string)$orgId === (string)$org->id ? 'selected' : '' }}>
                                    [{{ $org->code }}] {{ $org->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Action Button Export CSV -->
                <div class="col-12 col-sm-6 col-md-4 text-sm-end">
                    <a href="{{ route('ess.service_level.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fs-8 w-100 w-md-auto fw-semibold">
                        <i class="bi bi-file-earmark-excel me-1"></i> Ekspor CSV / Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Executive Summary Info-Boxes -->
    <div class="row g-3">
        <!-- 1. Order Fill Rate -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-all"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Order Fill Rate</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">
                        {{ $overallFillRate }}%
                    </span>
                    <span class="fs-9 text-secondary">Rasio pesanan tuntas terpenuhi</span>
                </div>
            </div>
        </div>

        <!-- 2. On-Time Delivery SLA -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-clock-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Ketepatan Waktu (SLA)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-primary">
                        {{ $onTimeDeliveryRate }}%
                    </span>
                    <span class="fs-9 text-secondary">Terkirim sebelum/sesuai tenggat</span>
                </div>
            </div>
        </div>

        <!-- 3. Rata-rata Durasi Siklus Pemenuhan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-truck"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Rata-rata Durasi Siklus</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">
                        {{ $avgFulfillmentDays }} Hari
                    </span>
                    <span class="fs-9 text-secondary">Dari pengajuan hingga diterima</span>
                </div>
            </div>
        </div>

        <!-- 4. Total Order Masuk -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-dark"><i class="bi bi-inboxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-9 text-secondary fw-bold text-uppercase">Total Pesanan Logistik</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">
                        {{ number_format($totalOrders) }} Order
                    </span>
                    <span class="fs-9 text-secondary">{{ $completedOrders }} Order Selesai</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-body py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                <i class="bi bi-clipboard-check me-2 text-primary"></i> Rincian Pesanan & Kepatuhan SLA per Transaksi Permintaan
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                {{ $tableData->count() }} Dokumen Pesanan
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4">No. Order & Tanggal</th>
                        <th>Cabang Pemohon</th>
                        <th class="text-center">Permintaan vs Terpenuhi</th>
                        <th class="text-center">Fill Rate</th>
                        <th class="text-center">Durasi Layanan</th>
                        <th class="text-center">Kepatuhan SLA</th>
                        <th class="text-center pe-4">Status Pesanan</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($tableData as $row)
                        @php
                            $order = $row['order'];
                            $statusBadge = match($order->status) {
                                'COMPLETED', 'DELIVERED', 'RECEIVED' => 'text-bg-success',
                                'APPROVED', 'PACKED', 'SHIPPED' => 'text-bg-primary',
                                'WAITING_APPROVAL', 'PENDING' => 'text-bg-warning text-dark',
                                'CANCELLED', 'REJECTED' => 'text-bg-danger',
                                default => 'text-bg-secondary',
                            };
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <span class="font-monospace fw-bold text-body">{{ $order->order_number }}</span>
                                <div class="text-secondary fs-9">{{ $order->submitted_at?->format('d M Y') ?? $order->created_at?->format('d M Y') }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold text-body">{{ $order->requestingOrganization?->name ?? '-' }}</div>
                                <span class="text-secondary fs-9 font-monospace">Target: {{ $order->required_date?->format('d M Y') ?? 'Segera' }}</span>
                            </td>
                            <td class="text-center font-monospace">
                                <span class="text-success fw-bold">{{ number_format($row['total_fulfilled']) }}</span> / 
                                <span class="text-secondary">{{ number_format($row['total_requested']) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold font-monospace fs-8 {{ $row['fill_rate'] < 100 ? 'text-warning-emphasis' : 'text-success' }}">
                                    {{ $row['fill_rate'] }}%
                                </span>
                            </td>
                            <td class="text-center font-monospace">
                                {{ $row['duration_days'] ? $row['duration_days'].' Hari' : '-' }}
                            </td>
                            <td class="text-center">
                                @if($row['is_on_time'])
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                                        <i class="bi bi-check-circle me-1"></i> On Time
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="bi bi-exclamation-circle me-1"></i> Terlambat
                                    </span>
                                @endif
                            </td>
                            <td class="text-center pe-4">
                                <span class="badge {{ $statusBadge }} px-2 py-1">
                                    {{ $order->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-secondary">
                                Tidak ada data pesanan logistik pada periode terpilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
