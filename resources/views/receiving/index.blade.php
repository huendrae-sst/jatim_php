@extends('layouts.app')
@section('title', 'Penerimaan Barang Cabang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Penerimaan & QC</li>
    <li class="breadcrumb-item active" aria-current="page">Penerimaan Barang Cabang</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Section 1: Incoming In-Transit Shipments -->
    <div class="card card-outline card-warning shadow-xs mb-3">
        <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-truck text-warning fs-5"></i>
                <h3 class="card-title fw-bold text-slate-800 fs-6 mb-0">
                    Pengiriman Menuju Cabang (In-Transit)
                    @if($incomingShipments->count() > 0)
                        <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $incomingShipments->count() }} Paket</span>
                    @endif
                </h3>
            </div>
            <div>
                <a href="{{ route('receiving.discrepancies') }}" class="btn btn-sm btn-outline-danger shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Daftar Discrepancy / Klaim</span>
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
                <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                    <tr>
                        <th class="ps-3 py-2" style="width: 170px;">No. Manifest</th>
                        <th class="py-2" style="width: 160px;">No. Order</th>
                        <th class="py-2" style="width: 220px;">Cabang Tujuan</th>
                        <th class="py-2" style="width: 220px;">Ekspedisi & Resi Tracking</th>
                        <th class="py-2 text-center" style="width: 130px;">Status Paket</th>
                        <th class="pe-3 py-2 text-center" style="width: 100px;">Konfirmasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incomingShipments as $shp)
                        <tr>
                            <td class="ps-3">
                                <span class="font-monospace fw-bold text-slate-900 d-block">{{ $shp->manifest_number }}</span>
                                <small class="text-muted fs-8">{{ $shp->created_at ? $shp->created_at->format('d M Y') : '-' }}</small>
                            </td>
                            <td>
                                <span class="font-monospace fw-semibold text-slate-800">{{ $shp->order->order_number ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-slate-800 d-block">{{ $shp->order->requestingOrganization->name ?? '-' }}</span>
                                <small class="text-muted fs-8">{{ $shp->order->requestingOrganization->city ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-slate-800 d-block">{{ $shp->courier->name ?? 'Kurir' }}</span>
                                <small class="font-monospace text-muted fs-8">Resi: {{ $shp->tracking_number }}</small>
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-warning text-dark fs-8">IN_TRANSIT</span>
                            </td>
                            <td class="pe-3 text-center">
                                <a href="{{ route('receiving.confirm.form', $shp->id) }}" 
                                   class="btn-action-icon text-success" 
                                   title="Konfirmasi Terima Barang di Cabang">
                                    <i class="bi bi-box-arrow-in-down"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-truck fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                <span class="fw-semibold">Tidak ada pengiriman dalam perjalanan (in-transit) saat ini.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 2: Receivings History -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-danger fs-5"></i>
                <h3 class="card-title fw-bold text-slate-800 fs-6 mb-0">
                    Histori Penerimaan Barang di Cabang
                </h3>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
                <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                    <tr>
                        <th class="ps-3 py-2" style="width: 170px;">No. Penerimaan</th>
                        <th class="py-2" style="width: 170px;">No. Manifest</th>
                        <th class="py-2" style="width: 220px;">Cabang Penerima</th>
                        <th class="py-2" style="width: 180px;">Petugas Penerima</th>
                        <th class="py-2" style="width: 140px;">Tanggal Terima</th>
                        <th class="pe-3 py-2 text-center" style="width: 140px;">Status Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receivings as $rcv)
                        <tr>
                            <td class="ps-3">
                                <span class="font-monospace fw-bold text-danger d-block">{{ $rcv->receiving_number }}</span>
                            </td>
                            <td>
                                <span class="font-monospace fw-semibold text-slate-800">{{ $rcv->shipment->manifest_number ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-slate-800 d-block">{{ $rcv->order->requestingOrganization->name ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="text-slate-800">{{ $rcv->receiver->name ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="text-slate-700">{{ $rcv->receipt_date ? $rcv->receipt_date->format('d M Y') : '-' }}</span>
                            </td>
                            <td class="pe-3 text-center">
                                @php
                                    $badgeClass = match($rcv->status) {
                                        'RECEIVED_FULL' => 'text-bg-success',
                                        'DISCREPANCY' => 'text-bg-danger',
                                        default => 'text-bg-warning',
                                    };
                                    $label = match($rcv->status) {
                                        'RECEIVED_FULL' => 'Lengkap & Sesuai',
                                        'DISCREPANCY' => 'Ada Selisih',
                                        default => str_replace('_', ' ', $rcv->status),
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} fs-8">{{ $label }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                <span class="fw-semibold">Belum ada histori penerimaan barang tercatat di cabang.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top">
            <x-pagination-footer :paginator="$receivings" />
        </div>
    </div>

</div>
@endsection
