@extends('layouts.app')
@section('title', 'Distribusi & Ekspedisi')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik</li>
    <li class="breadcrumb-item active" aria-current="page">Distribusi & Ekspedisi</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ dispatchModal: false, selectedOrder: null }">

    <!-- Section 1: Ready to Ship Orders -->
    @if($readyOrders->count() > 0)
        <div class="card card-outline card-danger shadow-xs mb-3">
            <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam text-danger fs-5"></i>
                    <h3 class="card-title fw-bold text-slate-800 fs-6 mb-0">
                        Order Siap Diberangkatkan (Status: READY_TO_SHIP)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $readyOrders->count() }} Paket</span>
                    </h3>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                        <tr>
                            <th class="ps-3 py-2" style="width: 170px;">No. Order & Tanggal</th>
                            <th class="py-2" style="width: 240px;">Tujuan Cabang / Unit</th>
                            <th class="py-2">Alamat Pengiriman</th>
                            <th class="py-2 text-center" style="width: 150px;">Koli & Berat Packing</th>
                            <th class="pe-3 py-2 text-center" style="width: 90px;">Dispatch</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($readyOrders as $ord)
                            @php
                                $lastPacking = $ord->packings->last();
                                $koli = $lastPacking->koli_count ?? 1;
                                $weight = $lastPacking->total_weight_kg ?? 1;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <span class="font-monospace fw-bold text-slate-900 d-block">{{ $ord->order_number }}</span>
                                    <small class="text-muted fs-8">{{ $ord->order_date ? $ord->order_date->format('d M Y') : '-' }}</small>
                                </td>
                                <td>
                                    <span class="fw-semibold text-slate-800 d-block">{{ $ord->requestingOrganization->name }}</span>
                                    <small class="text-muted fs-8">{{ $ord->requestingOrganization->city ?? '-' }}</small>
                                </td>
                                <td>
                                    <span class="text-slate-700 fs-8">{{ Str::limit($ord->requestingOrganization->address ?? '-', 60) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border font-monospace fs-8">
                                        {{ $koli }} Koli ({{ $weight }} kg)
                                    </span>
                                </td>
                                <td class="pe-3 text-center">
                                    <button type="button" 
                                            @click="selectedOrder = {{ Js::from($ord) }}; dispatchModal = true" 
                                            class="btn-action-icon text-danger" 
                                            title="Terbitkan Manifest & Dispatch Ekspedisi">
                                        <i class="bi bi-truck"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Section 2: Shipments Table -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-truck text-danger fs-5"></i>
                <h3 class="card-title fw-bold text-slate-800 fs-6 mb-0">
                    Daftar Manifest Pengiriman (Shipments)
                </h3>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
                <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                    <tr>
                        <th class="ps-3 py-2" style="width: 170px;">No. Manifest & Tanggal</th>
                        <th class="py-2" style="width: 160px;">No. Order</th>
                        <th class="py-2" style="width: 220px;">Tujuan Cabang</th>
                        <th class="py-2" style="width: 200px;">Ekspedisi & No. Resi</th>
                        <th class="py-2 text-center" style="width: 130px;">Koli & Berat</th>
                        <th class="py-2 text-center" style="width: 130px;">Status Pengiriman</th>
                        <th class="pe-3 py-2 text-center" style="width: 100px;">Cetak Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $shp)
                        <tr>
                            <td class="ps-3">
                                <span class="font-monospace fw-bold text-danger d-block">{{ $shp->manifest_number }}</span>
                                <small class="text-muted fs-8">{{ $shp->created_at ? $shp->created_at->format('d M Y H:i') : '-' }}</small>
                            </td>
                            <td>
                                <span class="font-monospace fw-semibold text-slate-800">{{ $shp->order->order_number ?? '-' }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-slate-800 d-block">{{ $shp->order->requestingOrganization->name ?? '-' }}</span>
                                <small class="text-muted fs-8">{{ $shp->order->requestingOrganization->city ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-slate-800 d-block">{{ $shp->courier->name ?? 'Kurir Internal' }}</span>
                                <small class="font-monospace text-muted fs-8">Resi: {{ $shp->tracking_number }}</small>
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-light border font-monospace fs-8">
                                    {{ $shp->koli_count }} Koli ({{ $shp->total_weight_kg }} kg)
                                </span>
                            </td>
                            <td class="text-center">
                                @php
                                    $badgeClass = match($shp->status) {
                                        'DELIVERED', 'RECEIVED' => 'text-bg-success',
                                        'IN_TRANSIT', 'OUT_FOR_DELIVERY' => 'text-bg-info',
                                        'READY_TO_SHIP', 'DISPATCHED' => 'text-bg-primary',
                                        'DELIVERY_FAILED', 'RETURNED' => 'text-bg-danger',
                                        default => 'text-bg-secondary',
                                    };
                                    $statusLabel = match($shp->status) {
                                        'DELIVERED' => 'Terkirim',
                                        'IN_TRANSIT' => 'Dalam Perjalanan',
                                        'OUT_FOR_DELIVERY' => 'Kurir Mengantar',
                                        'DISPATCHED' => 'Diberangkatkan',
                                        'READY_TO_SHIP' => 'Siap Kirim',
                                        default => str_replace('_', ' ', $shp->status),
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} fs-8">{{ $statusLabel }}</span>
                            </td>
                            <td class="pe-3 text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="{{ route('distribution.manifest.print', $shp->id) }}" 
                                       target="_blank" 
                                       class="btn-action-icon text-secondary" 
                                       title="Cetak Lembar Manifest Ekspedisi">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <a href="{{ route('distribution.label.print', $shp->id) }}" 
                                       target="_blank" 
                                       class="btn-action-icon text-primary" 
                                       title="Cetak Label Koli & QR Code">
                                        <i class="bi bi-qr-code"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-truck fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                <span class="fw-semibold">Belum ada dokumen pengiriman (manifest) yang dicatat.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top">
            <x-pagination-footer :paginator="$shipments" />
        </div>
    </div>

    <!-- Dispatch Shipment Modal (Standard Bank Jatim) -->
    <div x-show="dispatchModal" 
         x-cloak 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         style="display: none;"
         @keydown.escape.window="dispatchModal = false">
        
        <div class="bg-white rounded-3 shadow-xl max-w-lg w-full flex flex-col overflow-hidden" 
             @click.outside="dispatchModal = false">
            
            <!-- Modal Header -->
            <div class="bg-danger text-white px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-truck fs-5"></i>
                    <div>
                        <h5 class="modal-title mb-0 fs-6 fw-bold">Penerbitan Manifest & Dispatch Ekspedisi</h5>
                        <small class="text-white-50 fs-8">Penetapan kurir rekanan dan nomor tracking kiriman</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="dispatchModal = false" aria-label="Close"></button>
            </div>

            <!-- Modal Form Body -->
            <form action="{{ route('distribution.shipments.store') }}" method="POST">
                @csrf
                <input type="hidden" name="order_id" :value="selectedOrder?.id">

                <div class="p-4 space-y-3">
                    
                    <!-- Order Info Box -->
                    <div class="p-3 bg-light rounded-2 border">
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="fs-8 text-muted d-block">Nomor Order</span>
                                <span class="font-monospace fw-bold text-danger" x-text="selectedOrder?.order_number"></span>
                            </div>
                            <div class="col-6">
                                <span class="fs-8 text-muted d-block">Tujuan Cabang</span>
                                <span class="fw-semibold text-slate-800" x-text="selectedOrder?.requesting_organization?.name"></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fs-7 fw-bold text-slate-800">
                            Jasa Ekspedisi / Kurir <span class="text-danger">*</span>
                        </label>
                        <select name="courier_id" required class="form-select form-select-sm">
                            @foreach($couriers as $cr)
                                <option value="{{ $cr->id }}">{{ $cr->name }} (SLA: {{ $cr->sla_days }} hari)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fs-7 fw-bold text-slate-800">
                                Layanan Kurir <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="service_type" 
                                   value="REGULER" 
                                   required 
                                   class="form-control form-control-sm font-monospace">
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-7 fw-bold text-slate-800">
                                Nomor Resi / AWB <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   name="tracking_number" 
                                   value="BJ-EXP-{{ date('Ymd') }}-{{ rand(100, 999) }}" 
                                   required 
                                   class="form-control form-control-sm font-monospace fw-bold">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fs-7 fw-bold text-slate-800">
                                Ongkos Kirim (Rp) <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   name="shipping_cost" 
                                   value="125000" 
                                   min="0" 
                                   required 
                                   class="form-control form-control-sm font-monospace fw-bold">
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-7 fw-bold text-slate-800">
                                Estimasi Tiba (ETA) <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   name="eta_date" 
                                   value="{{ date('Y-m-d', strtotime('+2 days')) }}" 
                                   required 
                                   class="form-control form-control-sm font-monospace">
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="dispatchModal = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-send-check"></i>
                        <span>Terbitkan Manifest & Dispatch</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
