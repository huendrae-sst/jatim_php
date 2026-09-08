@extends('layouts.app')
@section('title', 'Penerimaan Barang Cabang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Penerimaan & QC</li>
    <li class="breadcrumb-item active" aria-current="page">Penerimaan Barang Cabang</li>
@endsection

@section('content')
<div class="space-y-4" x-data="branchReceivingManager()">

    <!-- Main Card with Tabs -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <div class="card-header p-0 pt-1 border-bottom d-flex flex-wrap justify-content-between align-items-center">
            <ul class="nav nav-tabs px-3 border-bottom-0" id="receivingTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link fw-bold {{ $tab === 'incoming' ? 'active text-danger border-bottom-0' : 'text-secondary' }}" 
                       href="{{ route('receiving.index', array_merge(request()->except(['tab', 'shipments_page', 'receivings_page']), ['tab' => 'incoming'])) }}">
                        <i class="bi bi-truck me-1"></i> Pengiriman Menuju Cabang (In-Transit)
                        @if($incomingShipments->total() > 0)
                            <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $incomingShipments->total() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link fw-bold {{ $tab === 'history' ? 'active text-danger border-bottom-0' : 'text-secondary' }}" 
                       href="{{ route('receiving.index', array_merge(request()->except(['tab', 'shipments_page', 'receivings_page']), ['tab' => 'history'])) }}">
                        <i class="bi bi-clock-history me-1"></i> Histori Penerimaan Cabang
                        @if($receivings->total() > 0)
                            <span class="badge bg-secondary rounded-pill ms-1">{{ $receivings->total() }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            @if($tab === 'incoming')
                <!-- TAB 1: Pengiriman Masuk (In-Transit) -->
                <!-- Filter Bar -->
                <div class="p-3 bg-body-tertiary border-bottom">
                    <form method="GET" action="{{ route('receiving.index') }}" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="incoming">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">

                        <!-- Courier Filter -->
                        <div class="col-12 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-muted"><i class="bi bi-truck"></i></span>
                                <select name="courier_id" class="form-select" onchange="this.form.submit()">
                                    <option value="ALL">Semua Ekspedisi Kurir</option>
                                    @foreach($couriers as $cr)
                                        <option value="{{ $cr->id }}" {{ ((string)$courierId === (string)$cr->id) ? 'selected' : '' }}>
                                            {{ $cr->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Organization / Cabang Filter (Admin Only) -->
                        @if(!$isBranch)
                            <div class="col-12 col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-body text-muted"><i class="bi bi-building"></i></span>
                                    <select name="organization_id" class="form-select" onchange="this.form.submit()">
                                        <option value="ALL">Semua Cabang Tujuan</option>
                                        @foreach($organizations as $org)
                                            <option value="{{ $org->id }}" {{ ((string)$organizationId === (string)$org->id) ? 'selected' : '' }}>
                                                [{{ $org->code }}] {{ $org->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        <!-- Status Filter -->
                        <div class="col-12 col-md-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-muted"><i class="bi bi-toggle-on"></i></span>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="ALL">Semua Status Pengiriman</option>
                                    <option value="IN_TRANSIT" {{ $status === 'IN_TRANSIT' ? 'selected' : '' }}>Dalam Perjalanan (In-Transit)</option>
                                    <option value="DISPATCHED" {{ $status === 'DISPATCHED' ? 'selected' : '' }}>Diberangkatkan</option>
                                    <option value="OUT_FOR_DELIVERY" {{ $status === 'OUT_FOR_DELIVERY' ? 'selected' : '' }}>Kurir Mengantar</option>
                                </select>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="col-12 col-md ms-md-auto">
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Cari No. Manifest, resi, order, atau cabang..." value="{{ $search ?? '' }}">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-search"></i> Cari
                                </button>
                                @if(!empty($search) || (!empty($status) && $status !== 'ALL') || (!empty($courierId) && $courierId !== 'ALL') || (!empty($organizationId) && $organizationId !== 'ALL'))
                                    <a href="{{ route('receiving.index', ['tab' => 'incoming']) }}" class="btn btn-outline-secondary" title="Reset Pencarian">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Table Pengiriman Masuk -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 fs-7">
                        <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                            <tr>
                                <th class="ps-3 py-2" style="width: 170px;">No. Manifest</th>
                                <th class="py-2" style="width: 170px;">No. Order</th>
                                <th class="py-2" style="width: 220px;">Cabang Tujuan</th>
                                <th class="py-2" style="width: 220px;">Ekspedisi & Tracking</th>
                                <th class="py-2">Gudang Asal & Muatan</th>
                                <th class="py-2 text-center" style="width: 130px;">Status Paket</th>
                                <th class="pe-3 py-2 text-center" style="width: 110px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($incomingShipments as $shp)
                                @php
                                    $itemCount = $shp->order ? $shp->order->items->count() : 0;
                                    $totalQty = $shp->order ? $shp->order->items->sum('qty_requested') : 0;
                                    $shpJson = [
                                        'id' => $shp->id,
                                        'manifest_number' => $shp->manifest_number,
                                        'tracking_number' => $shp->tracking_number,
                                        'created_at' => $shp->created_at ? $shp->created_at->format('d M Y, H:i') : '-',
                                        'order_number' => $shp->order->order_number ?? '-',
                                        'branch_name' => $shp->order->requestingOrganization->name ?? '-',
                                        'branch_city' => $shp->order->requestingOrganization->city ?? '-',
                                        'courier_name' => $shp->courier->name ?? 'Kurir',
                                        'origin_warehouse' => $shp->originWarehouse->name ?? 'Gudang Pusat',
                                        'weight' => $shp->total_weight_kg ?? '1.0',
                                        'status' => $shp->status,
                                        'items' => $shp->order ? $shp->order->items->map(fn($it) => [
                                            'name' => $it->item->name ?? 'Item',
                                            'sku' => $it->item->sku ?? '-',
                                            'category' => $it->item->category->name ?? '-',
                                            'uom' => $it->item->uom ?? 'PCS',
                                            'qty_ordered' => $it->qty_requested ?? $it->qty_approved ?? 0,
                                        ])->values() : [],
                                    ];
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <span class="font-monospace fw-bold text-slate-900 d-block">{{ $shp->manifest_number }}</span>
                                        <small class="text-muted fs-8">{{ $shp->created_at ? $shp->created_at->format('d M Y') : '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="font-monospace fw-semibold text-slate-800 d-block">{{ $shp->order->order_number ?? '-' }}</span>
                                        <small class="text-muted fs-8">Tgl Order: {{ $shp->order && $shp->order->order_date ? $shp->order->order_date->format('d/m/Y') : '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-slate-800 d-block">{{ $shp->order->requestingOrganization->name ?? '-' }}</span>
                                        <small class="text-muted fs-8">{{ $shp->order->requestingOrganization->city ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-slate-800 d-block">{{ $shp->courier->name ?? 'Kurir' }}</span>
                                        <span class="badge text-bg-light border text-slate-700 font-monospace fs-9">Resi: {{ $shp->tracking_number }}</span>
                                    </td>
                                    <td>
                                        <span class="text-slate-800 fw-semibold d-block">{{ $shp->originWarehouse->name ?? 'Gudang Pusat' }}</span>
                                        <small class="text-muted fs-8">
                                            {{ $itemCount }} SKU &bull; {{ $totalQty }} unit ({{ $shp->total_weight_kg ?? 1 }} kg)
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $badgeClass = match($shp->status) {
                                                'IN_TRANSIT' => 'text-bg-warning text-dark',
                                                'OUT_FOR_DELIVERY' => 'text-bg-info',
                                                'DISPATCHED' => 'text-bg-secondary',
                                                default => 'text-bg-primary',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} fs-8">{{ str_replace('_', ' ', $shp->status) }}</span>
                                    </td>
                                    <td class="pe-3 text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <button type="button" 
                                                    class="btn-action-icon text-secondary" 
                                                    @click="openManifestModal({{ json_encode($shpJson) }})"
                                                    title="Lihat Detail Manifest">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="{{ route('receiving.confirm.form', $shp->id) }}" 
                                               class="btn-action-icon text-success" 
                                               title="Konfirmasi Terima Barang di Cabang">
                                                <i class="bi bi-box-arrow-in-down"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-truck fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                        <span class="fw-semibold">Tidak ada pengiriman dalam perjalanan (in-transit) saat ini.</span>
                                        <p class="fs-8 text-muted mb-0">Semua paket pengiriman telah berhasil diterima atau belum diberangkatkan oleh tim distribusi.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Standardized Pagination Footer -->
                <x-pagination-footer :paginator="$incomingShipments" :perPage="$perPage" tab="incoming" pageName="shipments_page" />

            @else
                <!-- TAB 2: Riwayat Penerimaan Cabang -->
                <!-- Filter Bar -->
                <div class="p-3 bg-body-tertiary border-bottom">
                    <form method="GET" action="{{ route('receiving.index') }}" class="row g-2 align-items-center">
                        <input type="hidden" name="tab" value="history">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">

                        <!-- Organization / Cabang Filter (Admin Only) -->
                        @if(!$isBranch)
                            <div class="col-12 col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-body text-muted"><i class="bi bi-building"></i></span>
                                    <select name="organization_id" class="form-select" onchange="this.form.submit()">
                                        <option value="ALL">Semua Cabang Penerima</option>
                                        @foreach($organizations as $org)
                                            <option value="{{ $org->id }}" {{ ((string)$organizationId === (string)$org->id) ? 'selected' : '' }}>
                                                [{{ $org->code }}] {{ $org->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        <!-- Status Filter -->
                        <div class="col-12 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-muted"><i class="bi bi-check2-circle"></i></span>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="ALL">Semua Status Kondisi</option>
                                    <option value="RECEIVED_FULL" {{ $status === 'RECEIVED_FULL' ? 'selected' : '' }}>Lengkap & Sesuai</option>
                                    <option value="DISCREPANCY" {{ $status === 'DISCREPANCY' ? 'selected' : '' }}>Ada Selisih (Discrepancy)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="col-12 col-md ms-md-auto">
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Cari No. Penerimaan, manifest, petugas, atau cabang..." value="{{ $search ?? '' }}">
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-search"></i> Cari
                                </button>
                                @if(!empty($search) || (!empty($status) && $status !== 'ALL') || (!empty($organizationId) && $organizationId !== 'ALL'))
                                    <a href="{{ route('receiving.index', ['tab' => 'history']) }}" class="btn btn-outline-secondary" title="Reset Pencarian">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Table Histori Penerimaan -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 fs-7">
                        <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                            <tr>
                                <th class="ps-3 py-2" style="width: 170px;">No. Penerimaan</th>
                                <th class="py-2" style="width: 170px;">No. Manifest</th>
                                <th class="py-2" style="width: 220px;">Cabang Penerima</th>
                                <th class="py-2" style="width: 180px;">Petugas Penerima</th>
                                <th class="py-2" style="width: 140px;">Tanggal Terima</th>
                                <th class="py-2 text-center" style="width: 140px;">Status Kondisi</th>
                                <th class="pe-3 py-2 text-center" style="width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receivings as $rcv)
                                @php
                                    $hasDiscrepancy = $rcv->discrepancies->count() > 0 || $rcv->status === 'DISCREPANCY';
                                    $rcvJson = [
                                        'id' => $rcv->id,
                                        'receiving_number' => $rcv->receiving_number,
                                        'manifest_number' => $rcv->shipment->manifest_number ?? '-',
                                        'tracking_number' => $rcv->shipment->tracking_number ?? '-',
                                        'order_number' => $rcv->order->order_number ?? '-',
                                        'branch_name' => $rcv->order->requestingOrganization->name ?? '-',
                                        'receiver_name' => $rcv->receiver->name ?? '-',
                                        'receipt_date' => $rcv->receipt_date ? $rcv->receipt_date->format('d M Y') : '-',
                                        'status' => $rcv->status,
                                        'notes' => $rcv->notes ?? '-',
                                        'items' => $rcv->order ? $rcv->order->items->map(fn($it) => [
                                            'name' => $it->item->name ?? 'Item',
                                            'sku' => $it->item->sku ?? '-',
                                            'category' => $it->item->category->name ?? '-',
                                            'uom' => $it->item->uom ?? 'PCS',
                                            'qty_ordered' => $it->qty_requested ?? $it->qty_approved ?? 0,
                                        ])->values() : [],
                                        'discrepancies' => $rcv->discrepancies->map(fn($d) => [
                                            'item_name' => $d->item->name ?? '-',
                                            'type' => $d->discrepancy_type,
                                            'qty_damaged' => $d->qty_damaged,
                                            'status' => $d->resolution_status,
                                        ])->values(),
                                    ];
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        <span class="font-monospace fw-bold text-danger d-block">{{ $rcv->receiving_number }}</span>
                                    </td>
                                    <td>
                                        <span class="font-monospace fw-semibold text-slate-800 d-block">{{ $rcv->shipment->manifest_number ?? '-' }}</span>
                                        <small class="text-muted font-monospace fs-8">Order: {{ $rcv->order->order_number ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-slate-800 d-block">{{ $rcv->order->requestingOrganization->name ?? '-' }}</span>
                                        <small class="text-muted fs-8">{{ $rcv->order->requestingOrganization->city ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="text-slate-800 fw-semibold d-block">{{ $rcv->receiver->name ?? '-' }}</span>
                                        <small class="text-muted fs-8">Petugas Cabang</small>
                                    </td>
                                    <td>
                                        <span class="text-slate-700">{{ $rcv->receipt_date ? $rcv->receipt_date->format('d M Y') : '-' }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if($hasDiscrepancy)
                                            <span class="badge text-bg-danger fs-8">
                                                <i class="bi bi-exclamation-triangle me-1"></i> Ada Selisih
                                            </span>
                                        @else
                                            <span class="badge text-bg-success fs-8">
                                                <i class="bi bi-check2-circle me-1"></i> Lengkap & Sesuai
                                            </span>
                                        @endif
                                    </td>
                                    <td class="pe-3 text-center">
                                        <button type="button" 
                                                class="btn-action-icon text-secondary" 
                                                @click="openReceivingModal({{ json_encode($rcvJson) }})"
                                                title="Lihat Detail Penerimaan">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                        <span class="fw-semibold">Belum ada histori penerimaan barang tercatat di cabang.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Standardized Pagination Footer -->
                <x-pagination-footer :paginator="$receivings" :perPage="$perPage" tab="history" pageName="receivings_page" />
            @endif
        </div>
    </div>

    <!-- MODAL 1: DETAIL MANIFEST PENGIRIMAN -->
    <div x-show="viewManifestModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         style="display: none;"
         @keydown.escape.window="viewManifestModalOpen = false">
        
        <div class="bg-white rounded-3 shadow-xl max-w-3xl w-full flex flex-col max-h-[90vh] overflow-hidden" 
             @click.outside="viewManifestModalOpen = false">
            
            <div class="bg-secondary text-white px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-truck fs-5"></i>
                    <div>
                        <h5 class="modal-title mb-0 fs-6 fw-bold">Rincian Manifest Pengiriman</h5>
                        <small class="text-white-50 fs-8 font-monospace" x-text="viewManifestData.manifest_number"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="viewManifestModalOpen = false"></button>
            </div>

            <div class="p-4 overflow-y-auto flex-grow-1 space-y-3">
                <div class="row g-3 p-3 bg-light rounded-2 border">
                    <div class="col-12 col-sm-6">
                        <span class="fs-8 text-muted d-block">Cabang Tujuan</span>
                        <span class="fw-bold text-slate-800" x-text="`${viewManifestData.branch_name} (${viewManifestData.branch_city})`"></span>
                    </div>
                    <div class="col-12 col-sm-6">
                        <span class="fs-8 text-muted d-block">Gudang Asal Logistik</span>
                        <span class="fw-bold text-slate-800" x-text="viewManifestData.origin_warehouse"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="fs-8 text-muted d-block">No. Order</span>
                        <span class="fw-semibold text-slate-700 font-monospace" x-text="viewManifestData.order_number"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="fs-8 text-muted d-block">Ekspedisi / Kurir</span>
                        <span class="fw-semibold text-slate-700" x-text="viewManifestData.courier_name"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="fs-8 text-muted d-block">No. Resi Tracking</span>
                        <span class="badge text-bg-light border font-monospace fs-8" x-text="viewManifestData.tracking_number"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="fs-8 text-muted d-block">Status Pengiriman</span>
                        <span class="badge text-bg-warning text-dark fs-8" x-text="viewManifestData.status"></span>
                    </div>
                </div>

                <div>
                    <h6 class="fw-bold text-slate-800 fs-7 mb-2">Daftar Barang Dikirim</h6>
                    <div class="table-responsive border rounded-2">
                        <table class="table table-sm table-striped align-middle mb-0 fs-7">
                            <thead class="table-light fs-8 text-uppercase">
                                <tr>
                                    <th class="ps-3 py-2">Barang</th>
                                    <th class="py-2 text-center">Kategori</th>
                                    <th class="pe-3 py-2 text-center">Jumlah Dikirim</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in viewManifestData.items" :key="item.sku">
                                    <tr>
                                        <td class="ps-3">
                                            <span class="fw-bold text-slate-800 d-block" x-text="item.name"></span>
                                            <small class="text-muted font-monospace fs-8" x-text="item.sku"></small>
                                        </td>
                                        <td class="text-center" x-text="item.category"></td>
                                        <td class="pe-3 text-center fw-bold text-slate-900" x-text="`${item.qty_ordered} ${item.uom}`"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-secondary" @click="viewManifestModalOpen = false">Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL 2: DETAIL PENERIMAAN CABANG -->
    <div x-show="viewReceivingModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         style="display: none;"
         @keydown.escape.window="viewReceivingModalOpen = false">
        
        <div class="bg-white rounded-3 shadow-xl max-w-3xl w-full flex flex-col max-h-[90vh] overflow-hidden" 
             @click.outside="viewReceivingModalOpen = false">
            
            <div class="bg-danger text-white px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check fs-5"></i>
                    <div>
                        <h5 class="modal-title mb-0 fs-6 fw-bold">Rincian Dokumen Penerimaan Barang</h5>
                        <small class="text-white-50 fs-8 font-monospace" x-text="viewReceivingData.receiving_number"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="viewReceivingModalOpen = false"></button>
            </div>

            <div class="p-4 overflow-y-auto flex-grow-1 space-y-3">
                <div class="row g-3 p-3 bg-light rounded-2 border">
                    <div class="col-12 col-sm-6">
                        <span class="fs-8 text-muted d-block">Cabang Penerima</span>
                        <span class="fw-bold text-slate-800" x-text="viewReceivingData.branch_name"></span>
                    </div>
                    <div class="col-12 col-sm-6">
                        <span class="fs-8 text-muted d-block">Petugas Penerima</span>
                        <span class="fw-bold text-slate-800" x-text="viewReceivingData.receiver_name"></span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="fs-8 text-muted d-block">Tanggal Terima</span>
                        <span class="fw-semibold text-slate-700" x-text="viewReceivingData.receipt_date"></span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="fs-8 text-muted d-block">No. Manifest</span>
                        <span class="fw-semibold text-slate-700 font-monospace" x-text="viewReceivingData.manifest_number"></span>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="fs-8 text-muted d-block">Status Kondisi</span>
                        <span class="badge fs-8" :class="viewReceivingData.status === 'RECEIVED_FULL' ? 'text-bg-success' : 'text-bg-danger'" x-text="viewReceivingData.status"></span>
                    </div>
                </div>

                <div x-show="viewReceivingData.discrepancies && viewReceivingData.discrepancies.length > 0">
                    <h6 class="fw-bold text-danger fs-7 mb-2">Berita Acara Selisih (Discrepancy)</h6>
                    <div class="table-responsive border rounded-2 border-danger-subtle">
                        <table class="table table-sm table-striped align-middle mb-0 fs-7">
                            <thead class="table-danger fs-8 text-uppercase">
                                <tr>
                                    <th class="ps-3 py-2">Item</th>
                                    <th class="py-2 text-center">Jenis Selisih</th>
                                    <th class="py-2 text-center">Qty Rusak</th>
                                    <th class="pe-3 py-2 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="d in viewReceivingData.discrepancies" :key="d.item_name">
                                    <tr>
                                        <td class="ps-3 fw-bold text-danger" x-text="d.item_name"></td>
                                        <td class="text-center" x-text="d.type"></td>
                                        <td class="text-center fw-bold" x-text="d.qty_damaged"></td>
                                        <td class="pe-3 text-center">
                                            <span class="badge text-bg-warning fs-8" x-text="d.status"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h6 class="fw-bold text-slate-800 fs-7 mb-2">Item Barang Dipesan & Diterima</h6>
                    <div class="table-responsive border rounded-2">
                        <table class="table table-sm table-striped align-middle mb-0 fs-7">
                            <thead class="table-light fs-8 text-uppercase">
                                <tr>
                                    <th class="ps-3 py-2">Barang</th>
                                    <th class="py-2 text-center">Kategori</th>
                                    <th class="pe-3 py-2 text-center">Kuantitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in viewReceivingData.items" :key="item.sku">
                                    <tr>
                                        <td class="ps-3">
                                            <span class="fw-bold text-slate-800 d-block" x-text="item.name"></span>
                                            <small class="text-muted font-monospace fs-8" x-text="item.sku"></small>
                                        </td>
                                        <td class="text-center" x-text="item.category"></td>
                                        <td class="pe-3 text-center fw-bold text-success" x-text="`${item.qty_ordered} ${item.uom}`"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div x-show="viewReceivingData.notes && viewReceivingData.notes !== '-'" class="p-3 bg-light rounded-2 border">
                    <span class="fs-8 text-muted d-block">Catatan Penerimaan:</span>
                    <p class="fs-8 text-slate-800 mb-0 font-italic" x-text="viewReceivingData.notes"></p>
                </div>
            </div>

            <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-secondary" @click="viewReceivingModalOpen = false">Tutup</button>
            </div>
        </div>
    </div>

</div>

<script>
function branchReceivingManager() {
    return {
        viewManifestModalOpen: false,
        viewManifestData: {},
        viewReceivingModalOpen: false,
        viewReceivingData: {},

        openManifestModal(data) {
            this.viewManifestData = data;
            this.viewManifestModalOpen = true;
        },

        openReceivingModal(data) {
            this.viewReceivingData = data;
            this.viewReceivingModalOpen = true;
        }
    };
}
</script>
@endsection
