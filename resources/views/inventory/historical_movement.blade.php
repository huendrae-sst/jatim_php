@extends('layouts.app')
@section('title', 'Inquiry Historical Movement')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Inquiry Historical Movement</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Matched Item Current Location Matrix -->
    @if($matchedItem && count($locationBalances) > 0)
        <div class="card shadow-xs border-0 border-top border-4 border-danger">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <span class="badge bg-danger bg-opacity-10 text-danger border mb-1">Pelacakan Posisi Fisik Terkini (Multi-Lokasi)</span>
                    <h5 class="card-title fw-bold mb-0">{{ $matchedItem->name }} (<span class="font-monospace text-danger">{{ $matchedItem->sku }}</span>)</h5>
                </div>
                <span class="text-secondary fs-8">Kategori: <strong>{{ $matchedItem->category?->name }}</strong> | Satuan: <strong>{{ $matchedItem->uom }}</strong></span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    @foreach($locationBalances as $locBal)
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="p-3 border rounded-3 bg-light h-100">
                                <span class="fs-9 text-uppercase text-secondary fw-bold d-block">{{ $locBal->warehouse?->name }}</span>
                                <span class="text-muted fs-9 d-block mb-2">{{ $locBal->warehouse?->type }}</span>
                                <div class="d-flex justify-content-between align-items-baseline">
                                    <span class="fs-4 fw-bold text-dark font-monospace">{{ number_format($locBal->on_hand) }}</span>
                                    <span class="badge {{ $locBal->stock_status === 'CRITICAL_LOW' ? 'bg-danger' : ($locBal->stock_status === 'WARNING' ? 'bg-warning text-dark' : 'bg-success') }}">
                                        {{ $locBal->stock_status }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between fs-9 text-secondary mt-1 border-top pt-1">
                                    <span>Tersedia: <strong>{{ number_format($locBal->available) }}</strong></span>
                                    <span>Rusak: <strong>{{ number_format($locBal->damaged) }}</strong></span>
                                    <span>Hold: <strong>{{ number_format($locBal->hold) }}</strong></span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header (Judul Form) & Action Tools -->
        <div class="card-header border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <div>
                <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-danger"></i>
                    Inquiry Historical Movement
                </h3>
            </div>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm btn-outline-success fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-file-earmark-excel"></i>
                    <span>Ekspor CSV</span>
                </a>
            </div>
        </div>

        <!-- Filter & Search Toolbar (seperti inventory/stock-balances) -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('inventory.movement_inquiry') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Gudang Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                            <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Gudang</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ (string)$warehouseId === (string)$wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Tipe Mutasi Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-filter"></i></span>
                            <select name="transaction_type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Tipe Mutasi</option>
                                @foreach($transactionTypes as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}" {{ (string)$transactionType === (string)$typeKey ? 'selected' : '' }}>
                                        {{ $typeLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Dari Tanggal -->
                    <div class="col-6 col-sm-3 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar"></i></span>
                            <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm border-start-0 fs-8" title="Dari Tanggal" onchange="this.form.submit()">
                        </div>
                    </div>

                    <!-- Sampai Tanggal -->
                    <div class="col-6 col-sm-3 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm border-start-0 fs-8" title="Sampai Tanggal" onchange="this.form.submit()">
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($warehouseId && $warehouseId !== 'ALL') || ($transactionType && $transactionType !== 'ALL') || $dateFrom || $dateTo)
                        <div class="col-auto">
                            <a href="{{ route('inventory.movement_inquiry') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   name="search" 
                                   value="{{ $search }}" 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8" 
                                   placeholder="Cari SKU, Barcode, No. Ref...">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table View -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-4 py-3">Waktu Mutasi</th>
                            <th class="py-3">Lokasi Gudang</th>
                            <th class="py-3">Barang Persediaan</th>
                            <th class="py-3">Tipe Mutasi</th>
                            <th class="py-3">No. Dokumen / Ref</th>
                            <th class="py-3 text-center text-success">Masuk (+)</th>
                            <th class="py-3 text-center text-danger">Keluar (-)</th>
                            <th class="py-3 text-center">Saldo Akhir</th>
                            <th class="py-3 pe-4">Keterangan / Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $m)
                            <tr>
                                <td class="ps-4 text-secondary font-monospace fs-8">
                                    {{ $m->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="fw-semibold text-body fs-8">{{ $m->warehouse?->name }}</td>
                                <td>
                                    <span class="font-monospace fw-bold text-dark d-block fs-8">{{ $m->item?->sku }}</span>
                                    <span class="text-secondary fs-8">{{ $m->item?->name }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border fs-9">
                                        {{ $transactionTypes[$m->transaction_type] ?? $m->transaction_type }}
                                    </span>
                                </td>
                                <td class="font-monospace fw-semibold text-primary fs-8">
                                    {{ $m->reference_number ?: '-' }}
                                </td>
                                <td class="text-center font-monospace text-success fw-bold fs-8">
                                    {{ $m->qty_in > 0 ? '+'.number_format($m->qty_in) : '-' }}
                                </td>
                                <td class="text-center font-monospace text-danger fw-bold fs-8">
                                    {{ $m->qty_out > 0 ? '-'.number_format($m->qty_out) : '-' }}
                                </td>
                                <td class="text-center font-monospace fw-bold fs-7">
                                    {{ number_format($m->balance_after) }}
                                </td>
                                <td class="pe-4 text-secondary fs-8">
                                    <div class="text-body">{{ $m->notes ?: '-' }}</div>
                                    @if($m->creator)
                                        <div class="text-muted fs-9 fst-italic">Oleh: {{ $m->creator->name }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <i class="bi bi-inbox text-secondary fs-1 mb-2"></i>
                                        <span class="text-secondary fw-medium">Belum ada mutasi persediaan pada kriteria yang dipilih</span>
                                        @if($search || ($warehouseId && $warehouseId !== 'ALL') || ($transactionType && $transactionType !== 'ALL') || $dateFrom || $dateTo)
                                            <a href="{{ route('inventory.movement_inquiry') }}" class="btn btn-sm btn-outline-danger mt-3">
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
        <x-pagination-footer :paginator="$movements" :perPage="$perPage" />
    </div>
</div>
@endsection
