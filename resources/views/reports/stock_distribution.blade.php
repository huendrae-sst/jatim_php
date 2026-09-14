@extends('layouts.app')
@section('title', 'Laporan Matriks Sebaran Stok Wilayah & Cabang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none text-danger">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Sebaran Stok Wilayah</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Header Action -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2">
        <div>
            <h4 class="fw-bold mb-0 text-dark">Matriks Sebaran & Mutasi Stok Wilayah</h4>
            <p class="text-muted small mb-0">Konsolidasi stok per SKU di seluruh gudang pusat dan cabang (Saldo Awal, Inflow, Outflow, Pemusnahan, dan Valuasi)</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('reports.stock_distribution.csv', request()->query()) }}" class="btn btn-sm btn-outline-success fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                <i class="bi bi-file-earmark-excel"></i>
                <span>Export CSV Matrix</span>
            </a>
            <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                <i class="bi bi-printer"></i>
                <span>Cetak</span>
            </button>
        </div>
    </div>

    <!-- Summary KPI Banner -->
    <div class="bg-gradient-to-r from-navy-900 to-slate-800 text-white p-4 sm:p-5 rounded-2xl border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm">
        <div>
            <div class="text-xs uppercase font-bold text-slate-400">Total Valuasi Finansial Terdistribusi:</div>
            <div class="text-2xl sm:text-3xl font-black text-rose-300 mt-1">Rp {{ number_format($totalValuationAll, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-300 mt-1">
                Periode Perhitungan: <strong class="text-white">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}</strong> s/d <strong class="text-white">{{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</strong>
            </div>
        </div>
        <div class="sm:text-right">
            <div class="text-xs uppercase font-bold text-slate-400">Akumulasi Kuantitas On-Hand:</div>
            <div class="text-2xl font-black text-emerald-400 mt-1">{{ number_format($totalQtyAll, 0, ',', '.') }} <span class="text-sm font-normal text-slate-300">Unit Fisik</span></div>
            <div class="text-xs text-slate-300 mt-1">{{ count($matrixData) }} SKU Terdata</div>
        </div>
    </div>

    <!-- Filter Card Toolbar -->
    <div class="card shadow-xs border-0">
        <div class="card-body p-3 bg-body-tertiary rounded">
            <form action="{{ route('reports.stock_distribution') }}" method="GET">
                <div class="row g-2 align-items-center">
                    <!-- Warehouse Filter -->
                    <div class="col-12 col-md-3">
                        <label class="form-label fs-8 text-muted mb-1">Gudang / Cabang:</label>
                        <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm fs-8">
                            <option value="ALL">Semua Lokasi Gudang</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (string)$warehouseId === (string)$wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }} ({{ $wh->organization->code ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Category Filter -->
                    <div class="col-12 col-md-2">
                        <label class="form-label fs-8 text-muted mb-1">Kategori:</label>
                        <select name="category_id" onchange="this.form.submit()" class="form-select form-select-sm fs-8">
                            <option value="ALL">Semua Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ (string)$categoryId === (string)$cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div class="col-6 col-md-2">
                        <label class="form-label fs-8 text-muted mb-1">Dari Tanggal:</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm fs-8">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fs-8 text-muted mb-1">Sampai Tanggal:</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm fs-8">
                    </div>

                    <!-- Search & Submit -->
                    <div class="col-12 col-md-3">
                        <label class="form-label fs-8 text-muted mb-1">Cari SKU / Nama:</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" value="{{ $search }}" placeholder="Ketik kata kunci..." class="form-control form-control-sm fs-8">
                            <button type="submit" class="btn btn-danger btn-sm">Filter</button>
                            @if($search || ($warehouseId && $warehouseId !== 'ALL') || ($categoryId && $categoryId !== 'ALL'))
                                <a href="{{ route('reports.stock_distribution') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                                    <i class="bi bi-x"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Matrix Table -->
    <div class="card shadow-xs border-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-8 border">
                <thead class="bg-light text-secondary border-bottom">
                    <tr>
                        <th class="ps-3 py-3" style="min-width: 220px;">Barang & SKU</th>
                        <th class="py-3" style="min-width: 170px;">Lokasi Gudang</th>
                        <th class="py-3 text-center" style="width: 100px;" title="Saldo Awal Periode">Saldo Awal</th>
                        <th class="py-3 text-center text-success" style="width: 100px;" title="Penerimaan PO / Transfer Masuk / Retur Masuk">Total Masuk</th>
                        <th class="py-3 text-center text-danger" style="width: 100px;" title="Distribusi / Transfer Keluar / Retur Keluar">Total Keluar</th>
                        <th class="py-3 text-center text-rose-600" style="width: 100px;" title="Pemusnahan Barang Rusak / Kadaluwarsa">Dimusnahkan</th>
                        <th class="py-3 text-center fw-bold text-dark" style="width: 100px;" title="Stok Fisik Tersedia di Gudang">Saldo Fisik</th>
                        <th class="py-3 text-center" style="width: 110px;">Status Stok</th>
                        <th class="py-3 text-end" style="width: 120px;">Harga Satuan</th>
                        <th class="pe-3 py-3 text-end" style="width: 140px;">Valuasi (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($matrixData as $row)
                        @php
                            $item = $row['item'];
                            $whCount = count($row['warehouses']);
                        @endphp

                        @if($whCount === 0)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ $item->name }}</div>
                                    <div class="text-muted font-monospace fs-9">{{ $item->sku }} • {{ $item->uom }}</div>
                                    <span class="badge bg-light text-secondary border fs-9">{{ $item->category->name ?? '-' }}</span>
                                </td>
                                <td colspan="9" class="text-center text-muted py-3">
                                    <em>Belum ada catatan saldo/mutasi pada lokasi gudang yang difilter</em>
                                </td>
                            </tr>
                        @else
                            @foreach($row['warehouses'] as $index => $w)
                                <tr>
                                    @if($index === 0)
                                        <td rowspan="{{ $whCount }}" class="ps-3 align-top bg-white border-end">
                                            <div class="fw-bold text-dark">{{ $item->name }}</div>
                                            <div class="text-muted font-monospace fs-9">{{ $item->sku }} • {{ $item->uom }}</div>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-9">{{ $item->category->name ?? '-' }}</span>
                                            <div class="mt-2 pt-2 border-top">
                                                <div class="text-muted fs-9">Total On Hand Seluruh Gudang:</div>
                                                <div class="fw-bold fs-7 text-dark">{{ number_format($row['total_on_hand']) }} {{ $item->uom }}</div>
                                                <div class="fw-bold fs-8 text-primary">Rp {{ number_format($row['total_valuation'], 0, ',', '.') }}</div>
                                            </div>
                                        </td>
                                    @endif
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $w['warehouse']->name }}</div>
                                        <div class="text-muted fs-9">{{ $w['warehouse']->organization->name ?? 'Gudang Pusat' }}</div>
                                    </td>
                                    <td class="text-center font-monospace">{{ number_format($w['initial']) }}</td>
                                    <td class="text-center font-monospace text-success fw-semibold">+{{ number_format($w['total_in']) }}</td>
                                    <td class="text-center font-monospace text-danger fw-semibold">-{{ number_format($w['total_out']) }}</td>
                                    <td class="text-center font-monospace text-rose-600">{{ number_format($w['destroyed']) }}</td>
                                    <td class="text-center font-monospace fw-bold fs-7 bg-light">{{ number_format($w['on_hand']) }}</td>
                                    <td class="text-center">
                                        @if($w['stock_status'] === 'NORMAL')
                                            <span class="badge bg-success-subtle text-success border border-success fs-9">NORMAL</span>
                                        @elseif($w['stock_status'] === 'LOW')
                                            <span class="badge bg-warning-subtle text-warning border border-warning fs-9">MENIPIS</span>
                                        @elseif($w['stock_status'] === 'OUT_OF_STOCK')
                                            <span class="badge bg-danger text-white fs-9">HABIS</span>
                                        @else
                                            <span class="badge bg-info-subtle text-info border border-info fs-9">BERLEBIH</span>
                                        @endif
                                    </td>
                                    <td class="text-end font-monospace text-muted">
                                        Rp {{ number_format($item->estimated_unit_price, 0, ',', '.') }}
                                    </td>
                                    <td class="pe-3 text-end font-monospace fw-bold text-dark">
                                        Rp {{ number_format($w['valuation'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Tidak ditemukan data pergerakan/sebaran persediaan pada kriteria filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
