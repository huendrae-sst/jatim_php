@extends('layouts.app')
@section('title', 'Detail Bon Produksi ' . $prodOrder->production_number)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('production.index') }}" class="text-decoration-none text-danger">Produksi & Personalisasi</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $prodOrder->production_number }}</li>
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
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-xs border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="card-title fw-bold mb-0 font-monospace text-danger">{{ $prodOrder->production_number }}</h5>
                    {!! $prodOrder->status_badge !!}
                </div>
                <p class="text-secondary fs-8 mb-0 mt-1">Diterbitkan oleh {{ $prodOrder->creator?->name }} pada {{ $prodOrder->created_at->translatedFormat('d F Y H:i') }}</p>
            </div>
            <div class="d-flex gap-2">
                @if($prodOrder->status !== 'COMPLETED' && $prodOrder->status !== 'CANCELLED')
                    <button type="button" class="btn btn-primary btn-sm px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#issueModal">
                        <i class="bi bi-box-arrow-right me-1"></i> Keluarkan Bahan Baku (Deduct Stock)
                    </button>
                @endif
                <a href="{{ route('production.manifest', $prodOrder->id) }}" target="_blank" class="btn btn-outline-dark btn-sm">
                    <i class="bi bi-file-earmark-text me-1"></i> Cetak Rekap Manifest Produksi
                </a>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Unit Cabang Tujuan</span>
                    <span class="fw-bold text-dark fs-7">{{ $prodOrder->destinationOrganization?->name }}</span>
                    <span class="text-muted fs-8 d-block">{{ $prodOrder->destinationOrganization?->address ?: 'Alamat terdaftar di master organisasi' }}</span>
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Gudang Sumber Bahan</span>
                    <span class="fw-bold text-dark fs-7">{{ $prodOrder->warehouse?->name }}</span>
                    <span class="text-muted fs-8 d-block">{{ $prodOrder->warehouse?->code }}</span>
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Referensi Tautan</span>
                    @if($prodOrder->embossFile)
                        <span class="text-dark fs-8 d-block"><i class="bi bi-file-earmark-code me-1 text-danger"></i>Emboss: {{ $prodOrder->embossFile->file_id }}</span>
                    @endif
                    @if($prodOrder->order)
                        <span class="text-dark fs-8 d-block"><i class="bi bi-bag me-1 text-primary"></i>Order: {{ $prodOrder->order->order_number }}</span>
                    @endif
                    @if(!$prodOrder->embossFile && !$prodOrder->order)
                        <span class="text-muted fs-8 fst-italic">Bon Produksi Mandiri</span>
                    @endif
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Realisasi Pengeluaran Bahan</span>
                    <span class="fw-bold fs-7 {{ $prodOrder->total_issued_qty >= $prodOrder->total_planned_qty ? 'text-success' : 'text-primary' }}">
                        {{ number_format($prodOrder->total_issued_qty) }} / {{ number_format($prodOrder->total_planned_qty) }} Unit
                    </span>
                    @if($prodOrder->issued_at)
                        <span class="text-muted fs-8 d-block">Dikeluarkan oleh: {{ $prodOrder->issuer?->name }} ({{ $prodOrder->issued_at->format('d/m/Y H:i') }})</span>
                    @endif
                </div>
            </div>

            <!-- Items Table -->
            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-credit-card me-2"></i>Rincian Bahan Blankcard / Token / KUE pada Bon</h6>
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9">
                        <tr>
                            <th class="ps-3">SKU Bahan</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th class="text-center">Kuantitas Bon (Rencana)</th>
                            <th class="text-center">Kuantitas Dikeluarkan</th>
                            <th class="text-center">Sisa Pengeluaran</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prodOrder->items as $item)
                            <tr>
                                <td class="ps-3 font-monospace fw-bold">{{ $item->item->sku }}</td>
                                <td class="fw-semibold">{{ $item->item->name }}</td>
                                <td>{{ $item->item->category?->name }}</td>
                                <td class="text-center font-monospace fw-bold">{{ number_format($item->qty_planned) }} {{ $item->item->uom }}</td>
                                <td class="text-center font-monospace fw-bold text-success">{{ number_format($item->qty_issued) }}</td>
                                <td class="text-center font-monospace fw-bold text-danger">{{ number_format(max(0, $item->qty_planned - $item->qty_issued)) }}</td>
                                <td class="text-secondary">{{ $item->notes ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="ps-3 text-uppercase">Total</td>
                            <td class="text-center font-monospace">{{ number_format($prodOrder->total_planned_qty) }}</td>
                            <td class="text-center font-monospace text-success">{{ number_format($prodOrder->total_issued_qty) }}</td>
                            <td class="text-center font-monospace text-danger">{{ number_format(max(0, $prodOrder->total_planned_qty - $prodOrder->total_issued_qty)) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Issue Stock -->
<div class="modal fade" id="issueModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('production.issue_stock', $prodOrder->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-primary"><i class="bi bi-box-arrow-right me-1"></i> Pengeluaran Bahan Baku dari Gudang (Stock Deduction)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body space-y-3">
                <div class="alert alert-info py-2 fs-8 border-0 mb-3">
                    <i class="bi bi-shield-check me-1"></i> <strong>Over-Issue Protection:</strong> Sistem akan memblokir pengeluaran jika jumlah melebihi sisa bon atau melebihi saldo on-hand fisik di {{ $prodOrder->warehouse?->name }}.
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle fs-8 mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Barang</th>
                                <th class="text-center">Bon Rencana</th>
                                <th class="text-center">Sudah Keluar</th>
                                <th class="text-center" style="width: 30%">Kuantitas Dikeluarkan Sekarang</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($prodOrder->items as $idx => $item)
                                @php $sisa = max(0, $item->qty_planned - $item->qty_issued); @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="items[{{ $idx }}][production_order_item_id]" value="{{ $item->id }}">
                                        <strong>{{ $item->item->name }}</strong>
                                        <span class="text-muted d-block font-monospace fs-9">{{ $item->item->sku }}</span>
                                    </td>
                                    <td class="text-center font-monospace">{{ $item->qty_planned }}</td>
                                    <td class="text-center font-monospace text-success">{{ $item->qty_issued }}</td>
                                    <td>
                                        <input type="number" name="items[{{ $idx }}][qty_to_issue]" value="{{ $sisa }}" class="form-control form-control-sm text-center font-monospace fw-bold" min="0" max="{{ $sisa }}" {{ $sisa == 0 ? 'disabled' : '' }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Proses Pengeluaran Bahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
