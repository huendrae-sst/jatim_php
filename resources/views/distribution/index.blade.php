@extends('layouts.app')
@section('title', 'Distribusi & Ekspedisi')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik & Distribusi</li>
    <li class="breadcrumb-item active" aria-current="page">Distribusi & Ekspedisi</li>
@endsection

@section('content')
<div x-data="{
    dispatchModalOpen: false,
    viewModalOpen: false,
    dispatchType: 'order',
    deliveryMethod: 'COURIER',
    selectedOrder: null,
    selectedSwitching: null,
    selectedShipment: null,
    selectedCourierId: '',
    selectedServiceType: 'REGULER',
    isAutoMapped: false,
    readyOrdersList: {{ Js::from($readyOrders) }},
    readySwitchingsList: {{ Js::from($readySwitchings) }},
    allCouriers: {{ Js::from($couriers) }},

    updateDefaultCourier() {
        let mapping = null;
        if (this.dispatchType === 'order' && this.selectedOrder) {
            mapping = this.selectedOrder.requesting_organization?.default_expedition_mapping;
        } else if (this.dispatchType === 'switching' && this.selectedSwitching) {
            mapping = this.selectedSwitching.destination_organization?.default_expedition_mapping;
        }

        if (mapping && mapping.courier_id) {
            this.selectedCourierId = mapping.courier_id;
            this.selectedServiceType = mapping.default_service_type || 'REGULER';
            this.isAutoMapped = true;
        } else {
            this.selectedCourierId = this.allCouriers.length > 0 ? this.allCouriers[0].id : '';
            this.selectedServiceType = 'REGULER';
            this.isAutoMapped = false;
        }
    },

    openCreateManifestModal(order = null) {
        this.dispatchType = 'order';
        if (order) {
            this.selectedOrder = order;
        } else if (this.readyOrdersList.length > 0 && !this.selectedOrder) {
            this.selectedOrder = this.readyOrdersList[0];
        }
        this.updateDefaultCourier();
        this.dispatchModalOpen = true;
    },

    openCreateSwitchingManifestModal(switching = null) {
        this.dispatchType = 'switching';
        if (switching) {
            this.selectedSwitching = switching;
        } else if (this.readySwitchingsList.length > 0 && !this.selectedSwitching) {
            this.selectedSwitching = this.readySwitchingsList[0];
        }
        this.updateDefaultCourier();
        this.dispatchModalOpen = true;
    },

    openViewShipmentModal(shp) {
        this.selectedShipment = shp;
        this.viewModalOpen = true;
    },

    onOrderSelect(event) {
        const orderId = event.target.value;
        this.selectedOrder = this.readyOrdersList.find(o => o.id == orderId) || null;
        this.updateDefaultCourier();
    },

    onSwitchingSelect(event) {
        const swId = event.target.value;
        this.selectedSwitching = this.readySwitchingsList.find(s => s.id == swId) || null;
        this.updateDefaultCourier();
    }
}" 
x-init="
    @if(request('switching_id'))
        const targetSw = readySwitchingsList.find(s => s.id == {{ (int) request('switching_id') }});
        if (targetSw) {
            openCreateSwitchingManifestModal(targetSw);
        }
    @elseif(request('order_id'))
        const targetOrd = readyOrdersList.find(o => o.id == {{ (int) request('order_id') }});
        if (targetOrd) {
            openCreateManifestModal(targetOrd);
        }
    @endif
"
class="space-y-4">

    <!-- Flash Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center py-2 px-3 fs-7 mb-3 shadow-xs" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-6"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center py-2 px-3 fs-7 mb-3 shadow-xs" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-6"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2 px-3 fs-7 mb-3 shadow-xs" role="alert">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-circle-fill me-1"></i> Terdapat kesalahan pengisian:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Section 1: Order Siap Diberangkatkan (READY_TO_SHIP) -->
    @if($readyOrders->count() > 0)
        <div class="card card-outline card-warning shadow-xs mb-3">
            <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 py-2.5 px-4 bg-warning-subtle">
                <h4 class="card-title fs-7 fw-bold mb-0 text-warning-emphasis d-flex align-items-center">
                    Order Siap Diberangkatkan (Status: READY_TO_SHIP)
                </h4>
                <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                    <span class="badge bg-warning text-dark font-monospace fs-8">
                        {{ $readyOrders->count() }} Paket Siap Kirim
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 text-nowrap fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9">
                        <tr>
                            <th class="ps-4 py-2.5" style="width: 170px;">No. Order & Tanggal</th>
                            <th class="py-2.5" style="width: 220px;">Tujuan Cabang / Unit</th>
                            <th class="py-2.5">Alamat Pengiriman</th>
                            <th class="py-2.5 text-center" style="width: 150px;">Koli & Berat</th>
                            <th class="pe-4 py-2.5 text-center" style="min-width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($readyOrders as $ord)
                            @php
                                $lastPacking = $ord->packings->last();
                                $koli = $lastPacking->koli_count ?? 1;
                                $weight = $lastPacking->total_weight_kg ?? 1;
                                $dim = $lastPacking->dimensions_cm ?? '-';
                                $ordData = [
                                    'id' => $ord->id,
                                    'order_number' => $ord->order_number,
                                    'order_date' => $ord->order_date ? $ord->order_date->format('d/m/Y') : '-',
                                    'requesting_organization' => [
                                        'name' => $ord->requestingOrganization->name ?? '-',
                                        'city' => $ord->requestingOrganization->city ?? '-',
                                        'address' => $ord->requestingOrganization->address ?? '-',
                                    ],
                                    'koli_count' => $koli,
                                    'total_weight_kg' => $weight,
                                    'dimensions_cm' => $dim,
                                ];
                            @endphp
                            <tr>
                                <td class="ps-4 font-monospace">
                                    <span class="fw-bold text-danger d-block">{{ $ord->order_number }}</span>
                                    <span class="text-secondary fs-9">{{ $ord->order_date ? $ord->order_date->format('d/m/Y') : '-' }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-body d-block">{{ $ord->requestingOrganization->name }}</span>
                                    <span class="text-secondary fs-9"><i class="bi bi-geo-alt me-1"></i>{{ $ord->requestingOrganization->city ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="text-secondary fs-9 text-truncate d-inline-block" style="max-width: 320px;" title="{{ $ord->requestingOrganization->address ?? '-' }}">
                                        {{ Str::limit($ord->requestingOrganization->address ?? '-', 65) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-8">
                                        {{ $koli }} Koli ({{ $weight }} kg)
                                    </span>
                                </td>
                                <td class="pe-4 text-center">
                                    <button type="button" 
                                            @click="openCreateManifestModal({{ json_encode($ordData) }})" 
                                            class="btn-action-icon text-danger btn btn-sm btn-outline-danger py-0.5 px-1.5 fs-9 fw-bold" 
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

    <!-- Section 1.B: Switching Stock Siap Diberangkatkan (APPROVED / RESERVED) -->
    @if($readySwitchings->count() > 0)
        <div class="card card-outline card-info shadow-xs mb-3">
            <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 py-2.5 px-4 bg-info-subtle">
                <h4 class="card-title fs-7 fw-bold mb-0 text-info-emphasis d-flex align-items-center">
                    <i class="bi bi-arrow-left-right me-2 text-info"></i>
                    Transfer Switching Stock Siap Dikirim (Status: APPROVED / RESERVED)
                </h4>
                <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                    <span class="badge bg-info text-dark font-monospace fs-8">
                        {{ $readySwitchings->count() }} Transfer Siap Kirim
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 text-nowrap fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9">
                        <tr>
                            <th class="ps-4 py-2.5" style="width: 170px;">No. Transfer & Tanggal</th>
                            <th class="py-2.5" style="width: 220px;">Gudang Sumber (Asal)</th>
                            <th class="py-2.5" style="width: 220px;">Cabang & Gudang Tujuan</th>
                            <th class="py-2.5">Rincian Barang</th>
                            <th class="py-2.5 text-center" style="width: 120px;">Total Qty</th>
                            <th class="pe-4 py-2.5 text-center" style="min-width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($readySwitchings as $sw)
                            @php
                                $swData = [
                                    'id' => $sw->id,
                                    'transfer_number' => $sw->transfer_number ?? $sw->id,
                                    'created_at' => $sw->created_at ? $sw->created_at->format('d/m/Y H:i') : '-',
                                    'source_organization' => [
                                        'name' => $sw->sourceOrganization->name ?? '-',
                                        'city' => $sw->sourceOrganization->city ?? '-',
                                    ],
                                    'source_warehouse' => [
                                        'name' => $sw->sourceWarehouse->name ?? '-',
                                        'code' => $sw->sourceWarehouse->code ?? '-',
                                    ],
                                    'destination_organization' => [
                                        'name' => $sw->destinationOrganization->name ?? '-',
                                        'city' => $sw->destinationOrganization->city ?? '-',
                                        'address' => $sw->destinationOrganization->address ?? '-',
                                    ],
                                    'destination_warehouse' => [
                                        'name' => $sw->destinationWarehouse->name ?? '-',
                                        'code' => $sw->destinationWarehouse->code ?? '-',
                                    ],
                                    'total_qty' => $sw->total_qty,
                                    'total_items_count' => $sw->total_items_count,
                                    'items' => $sw->items->map(fn($it) => [
                                        'name' => $it->item->name ?? 'Item',
                                        'sku' => $it->item->sku ?? '-',
                                        'uom' => $it->item->uom ?? 'Unit',
                                        'qty' => $it->qty_requested,
                                    ])->values(),
                                ];
                            @endphp
                            <tr>
                                <td class="ps-4 font-monospace">
                                    <span class="fw-bold text-info-emphasis d-block">Switching #{{ $sw->id }}</span>
                                    <span class="text-secondary fs-9">{{ $sw->created_at ? $sw->created_at->format('d/m/Y H:i') : '-' }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-body d-block">{{ $sw->sourceWarehouse->name ?? '-' }}</span>
                                    <span class="text-secondary fs-9"><i class="bi bi-building me-1"></i>{{ $sw->sourceOrganization->name ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-body d-block">{{ $sw->destinationOrganization->name ?? '-' }}</span>
                                    <span class="text-secondary fs-9"><i class="bi bi-geo-alt me-1"></i>Gudang: {{ $sw->destinationWarehouse->name ?? '-' }} ({{ $sw->destinationOrganization->city ?? '-' }})</span>
                                </td>
                                <td>
                                    <span class="text-body fs-9">
                                        @if($sw->items->count() > 1)
                                            {{ $sw->items->first()?->item?->name ?? 'Barang' }} (+{{ $sw->items->count() - 1 }} item lainnya)
                                        @else
                                            {{ $sw->items->first()?->item?->name ?? ($sw->item?->name ?? 'Barang') }}
                                        @endif
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-8">
                                        {{ $sw->total_qty }} Unit ({{ $sw->total_items_count }} SKU)
                                    </span>
                                </td>
                                <td class="pe-4 text-center">
                                    <button type="button" 
                                            @click="openCreateSwitchingManifestModal({{ json_encode($swData) }})" 
                                            class="btn-action-icon text-info btn btn-sm btn-outline-info py-0.5 px-1.5 fs-9 fw-bold" 
                                            title="Terbitkan Manifest & Dispatch Switching Stock">
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

    <!-- Section 2: Main Shipments Card -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header -->
        <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 py-3 px-4">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                Daftar Manifest Pengiriman (Shipments)
            </h3>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                    {{ $shipments->total() }} Manifest Diterbitkan
                </span>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('distribution.shipments.index') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Cabang / Unit Kerja Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                            <select name="organization_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Cabang / Unit</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ request('organization_id') == $org->id ? 'selected' : '' }}>
                                        [{{ $org->code }}] {{ $org->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Ekspedisi / Kurir Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-truck"></i></span>
                            <select name="courier_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Ekspedisi</option>
                                @foreach($couriers as $cr)
                                    <option value="{{ $cr->id }}" {{ request('courier_id') == $cr->id ? 'selected' : '' }}>
                                        {{ $cr->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Status</option>
                                <option value="READY_TO_SHIP" {{ request('status') === 'READY_TO_SHIP' ? 'selected' : '' }}>READY_TO_SHIP</option>
                                <option value="DISPATCHED" {{ request('status') === 'DISPATCHED' ? 'selected' : '' }}>DISPATCHED</option>
                                <option value="IN_TRANSIT" {{ request('status') === 'IN_TRANSIT' ? 'selected' : '' }}>IN_TRANSIT</option>
                                <option value="DELIVERED" {{ request('status') === 'DELIVERED' ? 'selected' : '' }}>DELIVERED</option>
                                <option value="RECEIVED" {{ request('status') === 'RECEIVED' ? 'selected' : '' }}>RECEIVED</option>
                                <option value="RETURNED" {{ request('status') === 'RETURNED' ? 'selected' : '' }}>RETURNED</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if(request('search') || request('status') || request('courier_id') || request('organization_id'))
                        <div class="col-auto">
                            <a href="{{ route('distribution.shipments.index') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                   value="{{ request('search') }}" 
                                   placeholder="Cari no. manifest, resi, order, cabang..." 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Shipments Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 text-nowrap fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4 py-3" style="width: 170px;">No. Manifest & Tanggal</th>
                        <th class="py-3" style="width: 160px;">No. Order</th>
                        <th class="py-3" style="width: 200px;">Tujuan Cabang</th>
                        <th class="py-3" style="width: 200px;">Ekspedisi & No. Resi</th>
                        <th class="py-3 text-center" style="width: 120px;">Koli & Berat</th>
                        <th class="py-3 text-center" style="width: 130px;">Status</th>
                        <th class="pe-4 py-3 text-center" style="min-width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $shp)
                        @php
                            $isSwitching = (bool) $shp->switching_stock_id;
                            $destName = $shp->order->requestingOrganization->name ?? ($shp->switchingStock->destinationOrganization->name ?? ($shp->destinationOrganization->name ?? '-'));
                            $destCity = $shp->order->requestingOrganization->city ?? ($shp->switchingStock->destinationOrganization->city ?? ($shp->destinationOrganization->city ?? '-'));
                            $srcWhName = $shp->switchingStock->sourceWarehouse->name ?? ($shp->originWarehouse->name ?? 'Gudang Pengirim');
                            $shpData = [
                                'id' => $shp->id,
                                'manifest_number' => $shp->manifest_number,
                                'tracking_number' => $shp->tracking_number,
                                'service_type' => $shp->service_type,
                                'status' => $shp->status,
                                'shipping_cost' => $shp->shipping_cost,
                                'eta_date' => $shp->eta_date ? $shp->eta_date->format('d/m/Y') : '-',
                                'created_at' => $shp->created_at ? $shp->created_at->format('d/m/Y H:i') : '-',
                                'is_switching' => $isSwitching,
                                'order_number' => $isSwitching ? ('Switching #' . ($shp->switchingStock->transfer_number ?? $shp->switching_stock_id)) : ($shp->order->order_number ?? '-'),
                                'branch_name' => $destName,
                                'branch_city' => $destCity,
                                'origin_warehouse' => $srcWhName,
                                'courier_name' => $shp->courier->name ?? 'Kurir Internal',
                                'dispatcher_name' => $shp->dispatcher->name ?? '-',
                                'koli_count' => $shp->koli_count,
                                'total_weight_kg' => $shp->total_weight_kg,
                            ];
                        @endphp
                        <tr>
                            <!-- No. Manifest & Tanggal -->
                            <td class="ps-4 font-monospace">
                                <span class="fw-bold text-danger d-block">{{ $shp->manifest_number }}</span>
                                <span class="text-secondary fs-9">{{ $shp->created_at ? $shp->created_at->format('d/m/Y H:i') : '-' }}</span>
                            </td>

                            <!-- No. Order / Ref -->
                            <td class="font-monospace">
                                @if($isSwitching)
                                    <span class="badge bg-info-subtle text-info font-monospace fs-9 mb-0.5">TRANSFER SWITCHING</span>
                                    <span class="fw-semibold text-body d-block">#{{ $shp->switchingStock->transfer_number ?? $shp->switching_stock_id }}</span>
                                @else
                                    <span class="fw-semibold text-body">{{ $shp->order->order_number ?? '-' }}</span>
                                @endif
                            </td>

                            <!-- Tujuan Cabang -->
                            <td>
                                <div class="fw-semibold text-body">{{ $destName }}</div>
                                <div class="fs-9 text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ $destCity }}</div>
                                @if($isSwitching)
                                    <div class="fs-9 text-secondary font-monospace">Dari: {{ $srcWhName }}</div>
                                @endif
                            </td>

                            <!-- Ekspedisi & No. Resi -->
                            <td>
                                <div class="fw-semibold text-body">{{ $shp->courier->name ?? 'Kurir Internal' }}</div>
                                <div class="fs-9 text-secondary font-monospace">Resi: {{ $shp->tracking_number }}</div>
                            </td>

                            <!-- Koli & Berat -->
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-8">
                                    {{ $shp->koli_count }} Koli ({{ $shp->total_weight_kg }} kg)
                                </span>
                            </td>

                            <!-- Status Pengiriman -->
                            <td class="text-center">
                                @if(in_array($shp->status, ['DELIVERED', 'RECEIVED']))
                                    <span class="badge bg-success-subtle text-success-emphasis fs-9 py-1 px-2">
                                        {{ $shp->status }}
                                    </span>
                                @elseif(in_array($shp->status, ['IN_TRANSIT', 'OUT_FOR_DELIVERY']))
                                    <span class="badge bg-info-subtle text-info-emphasis fs-9 py-1 px-2">
                                        {{ $shp->status }}
                                    </span>
                                @elseif(in_array($shp->status, ['READY_TO_SHIP', 'DISPATCHED']))
                                    <span class="badge bg-primary-subtle text-primary-emphasis fs-9 py-1 px-2">
                                        {{ $shp->status }}
                                    </span>
                                @elseif(in_array($shp->status, ['DELIVERY_FAILED', 'RETURNED']))
                                    <span class="badge bg-danger-subtle text-danger-emphasis fs-9 py-1 px-2">
                                        {{ $shp->status }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis fs-9 py-1 px-2">
                                        {{ $shp->status }}
                                    </span>
                                @endif
                            </td>

                            <!-- Aksi (Detail, Cetak Manifest, Cetak Label) -->
                            <td class="pe-4 text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <!-- View Detail Modal Button -->
                                    <button type="button" 
                                            @click="openViewShipmentModal({{ json_encode($shpData) }})" 
                                            class="btn btn-sm btn-outline-secondary py-0.5 px-1.5 fs-9" 
                                            title="Lihat Detail Manifest">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <!-- Print Manifest -->
                                    <a href="{{ route('distribution.manifest.print', $shp->id) }}" 
                                       target="_blank" 
                                       class="btn-action-icon btn btn-sm btn-outline-secondary py-0.5 px-1.5 fs-9" 
                                       title="Cetak Lembar Manifest Ekspedisi">
                                        <i class="bi bi-printer"></i>
                                    </a>

                                    <!-- Print Label -->
                                    <a href="{{ route('distribution.label.print', $shp->id) }}" 
                                       target="_blank" 
                                       class="btn-action-icon btn btn-sm btn-outline-primary py-0.5 px-1.5 fs-9" 
                                       title="Cetak Label Koli & QR Code">
                                        <i class="bi bi-qr-code"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-secondary">
                                <i class="bi bi-truck fs-1 d-block mb-2 text-secondary-subtle"></i>
                                <p class="fw-bold mb-1">Belum ada dokumen manifest pengiriman</p>
                                <p class="fs-8 text-muted mb-0">Order yang telah selesai dipacking akan muncul pada antrean siap kirim untuk diterbitkan manifest.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination-footer :paginator="$shipments" :perPage="$perPage" />
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: TERBITKAN MANIFEST & DISPATCH EKSPEDISI         -->
    <!-- ======================================================== -->
    <div x-show="dispatchModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="dispatchModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color); max-height: 90vh; display: flex; flex-direction: column;">
            
            <div class="card-header bg-danger text-white py-2 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-truck fs-6"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Penerbitan Manifest & Dispatch Ekspedisi</h5>
                </div>
                <button type="button" @click="dispatchModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <form action="{{ route('distribution.shipments.store') }}" method="POST" class="d-flex flex-column h-100 m-0 overflow-hidden">
                @csrf
                <div class="card-body p-3.5 fs-8 overflow-y-auto space-y-3" style="flex: 1 1 auto;">
                    
                    <!-- Dispatch Type Selector -->
                    <div class="d-flex gap-2 mb-1">
                        <button type="button" 
                                @click="dispatchType = 'order'; if(readyOrdersList.length > 0 && !selectedOrder) selectedOrder = readyOrdersList[0]" 
                                :class="dispatchType === 'order' ? 'btn-danger' : 'btn-outline-secondary'" 
                                class="btn btn-sm flex-fill fw-bold fs-8">
                            <i class="bi bi-box-seam me-1"></i> Order Cabang
                            <span class="badge bg-white text-danger ms-1" x-text="readyOrdersList.length"></span>
                        </button>
                        <button type="button" 
                                @click="dispatchType = 'switching'; if(readySwitchingsList.length > 0 && !selectedSwitching) selectedSwitching = readySwitchingsList[0]" 
                                :class="dispatchType === 'switching' ? 'btn-danger' : 'btn-outline-secondary'" 
                                class="btn btn-sm flex-fill fw-bold fs-8">
                            <i class="bi bi-arrow-left-right me-1"></i> Transfer Switching
                            <span class="badge bg-white text-danger ms-1" x-text="readySwitchingsList.length"></span>
                        </button>
                    </div>

                    <!-- Section 1: Order / Switching Selection & Info Box -->
                    <div class="border rounded-2 p-3 bg-body-tertiary">
                        <!-- ORDER FORM -->
                        <template x-if="dispatchType === 'order'">
                            <div>
                                <div class="fw-bold text-danger text-uppercase fs-9 mb-2">1. Paket Order yang Diberangkatkan</div>
                                
                                <template x-if="readyOrdersList.length === 0">
                                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0" role="alert">
                                        <i class="bi bi-exclamation-circle me-1"></i> Tidak ada order dengan status READY_TO_SHIP yang siap diterbitkan manifest.
                                    </div>
                                </template>

                                <template x-if="readyOrdersList.length > 0">
                                    <div>
                                        <label class="form-label fs-8 fw-semibold mb-1">Pilih Nomor Order <span class="text-danger">*</span></label>
                                        <select name="order_id" :required="dispatchType === 'order'" @change="onOrderSelect($event)" class="form-select form-select-sm fs-8 mb-2">
                                            <template x-for="ord in readyOrdersList" :key="ord.id">
                                                <option :value="ord.id" 
                                                        :selected="selectedOrder && selectedOrder.id == ord.id"
                                                        x-text="ord.order_number + ' - ' + (ord.requesting_organization?.name || '-')">
                                                </option>
                                            </template>
                                        </select>

                                        <div class="bg-body p-2.5 rounded border fs-8">
                                            <div class="row g-1.5">
                                                <div class="col-6">
                                                    <span class="fs-9 text-secondary d-block">Tujuan Cabang:</span>
                                                    <span class="fw-semibold text-body" x-text="selectedOrder?.requesting_organization?.name || '-'"></span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="fs-9 text-secondary d-block">Kota Tujuan:</span>
                                                    <span class="fw-semibold text-body" x-text="selectedOrder?.requesting_organization?.city || '-'"></span>
                                                </div>
                                                <div class="col-12 mt-1 pt-1 border-top">
                                                    <span class="fs-9 text-secondary d-block">Alamat Pengiriman:</span>
                                                    <span class="text-body fs-9" x-text="selectedOrder?.requesting_organization?.address || '-'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- SWITCHING STOCK FORM -->
                        <template x-if="dispatchType === 'switching'">
                            <div>
                                <div class="fw-bold text-info-emphasis text-uppercase fs-9 mb-2">1. Transfer Switching Stock yang Diberangkatkan</div>
                                
                                <template x-if="readySwitchingsList.length === 0">
                                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0" role="alert">
                                        <i class="bi bi-exclamation-circle me-1"></i> Tidak ada pengajuan switching stock (APPROVED/RESERVED) yang siap dikirim.
                                    </div>
                                </template>

                                <template x-if="readySwitchingsList.length > 0">
                                    <div>
                                        <label class="form-label fs-8 fw-semibold mb-1">Pilih Switching Stock <span class="text-danger">*</span></label>
                                        <select name="switching_stock_id" :required="dispatchType === 'switching'" @change="onSwitchingSelect($event)" class="form-select form-select-sm fs-8 mb-2">
                                            <template x-for="sw in readySwitchingsList" :key="sw.id">
                                                <option :value="sw.id" 
                                                        :selected="selectedSwitching && selectedSwitching.id == sw.id"
                                                        x-text="'Switching #' + sw.id + ' (' + (sw.source_warehouse?.name || 'Gudang') + ' -> ' + (sw.destination_organization?.name || '-') + ')'">
                                                </option>
                                            </template>
                                        </select>

                                        <div class="bg-body p-2.5 rounded border fs-8">
                                            <div class="row g-1.5">
                                                <div class="col-6">
                                                    <span class="fs-9 text-secondary d-block">Gudang Pengirim (Asal):</span>
                                                    <span class="fw-semibold text-body" x-text="(selectedSwitching?.source_warehouse?.name || '-') + ' (' + (selectedSwitching?.source_organization?.name || '-') + ')'"></span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="fs-9 text-secondary d-block">Unit & Gudang Penerima:</span>
                                                    <span class="fw-semibold text-body" x-text="(selectedSwitching?.destination_organization?.name || '-') + ' - ' + (selectedSwitching?.destination_warehouse?.name || '-')"></span>
                                                </div>
                                                <div class="col-6 mt-1 pt-1 border-top">
                                                    <span class="fs-9 text-secondary d-block">Kota Tujuan:</span>
                                                    <span class="text-body fs-9" x-text="selectedSwitching?.destination_organization?.city || '-'"></span>
                                                </div>
                                                <div class="col-6 mt-1 pt-1 border-top">
                                                    <span class="fs-9 text-secondary d-block">Total Muatan:</span>
                                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace" x-text="(selectedSwitching?.total_qty || 0) + ' Unit (' + (selectedSwitching?.total_items_count || 0) + ' SKU)'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <!-- Section 2: Jasa Ekspedisi & Detail Pengiriman / Ambil di KP -->
                    <div class="border rounded-2 p-3 bg-body-tertiary">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-bold text-danger text-uppercase fs-9">2. Metode Distribusi & Pengiriman</div>
                        </div>

                        <!-- Delivery Method Selector for Orders -->
                        <template x-if="dispatchType === 'order'">
                            <div class="mb-3 border-bottom pb-2">
                                <label class="form-label fs-8 fw-semibold mb-1">Pilih Metode Pengiriman <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="delivery_method" id="distMethodCourier" value="COURIER" x-model="deliveryMethod">
                                        <label class="form-check-label fs-8 fw-semibold" for="distMethodCourier">
                                            <i class="bi bi-truck me-1"></i> Ekspedisi Kurir
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="delivery_method" id="distMethodPickup" value="PICKUP_KP" x-model="deliveryMethod">
                                        <label class="form-check-label fs-8 fw-semibold text-danger" for="distMethodPickup">
                                            <i class="bi bi-building me-1"></i> Ambil di Kantor Pusat (KP)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Courier Expedition Fields -->
                        <div x-show="deliveryMethod === 'COURIER' || dispatchType === 'switching'" x-transition>
                            <template x-if="isAutoMapped">
                                <div class="alert alert-success py-1.5 px-2.5 fs-8 mb-2 d-flex align-items-center gap-2">
                                    <i class="bi bi-magic text-success fs-7"></i>
                                    <span><strong>Auto-mapped:</strong> Ekspedisi default kantor cabang otomatis terpilih.</span>
                                </div>
                            </template>

                            <div class="mb-2.5">
                                <label class="form-label fs-8 fw-semibold mb-1">Jasa Ekspedisi / Kurir <span class="text-danger">*</span></label>
                                <select name="courier_id" x-model="selectedCourierId" :required="deliveryMethod === 'COURIER' || dispatchType === 'switching'" class="form-select form-select-sm fs-8">
                                    @foreach($couriers as $cr)
                                        <option value="{{ $cr->id }}">{{ $cr->name }} (SLA: {{ $cr->sla_days }} hari)</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-2.5 mb-2.5">
                                <div class="col-6">
                                    <label class="form-label fs-8 fw-semibold mb-1">Layanan Kurir <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="service_type" 
                                           x-model="selectedServiceType" 
                                           :required="deliveryMethod === 'COURIER' || dispatchType === 'switching'" 
                                           class="form-control form-control-sm font-monospace fs-8">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-8 fw-semibold mb-1">Nomor Resi / AWB <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           name="tracking_number" 
                                           value="BJ-EXP-{{ date('Ymd') }}-{{ rand(100, 999) }}" 
                                           :required="deliveryMethod === 'COURIER' || dispatchType === 'switching'" 
                                           class="form-control form-control-sm font-monospace fw-bold fs-8">
                                </div>
                            </div>

                            <div class="row g-2.5">
                                <div class="col-6">
                                    <label class="form-label fs-8 fw-semibold mb-1">Ongkos Kirim (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" 
                                           name="shipping_cost" 
                                           value="125000" 
                                           min="0" 
                                           :required="deliveryMethod === 'COURIER' || dispatchType === 'switching'" 
                                           class="form-control form-control-sm font-monospace fw-bold fs-8">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-8 fw-semibold mb-1">Estimasi Tiba (ETA) <span class="text-danger">*</span></label>
                                    <input type="date" 
                                           name="eta_date" 
                                           value="{{ date('Y-m-d', strtotime('+2 days')) }}" 
                                           :required="deliveryMethod === 'COURIER' || dispatchType === 'switching'" 
                                           class="form-control form-control-sm font-monospace fs-8">
                                </div>
                            </div>
                        </div>

                        <!-- Pickup at KP Fields -->
                        <div x-show="deliveryMethod === 'PICKUP_KP' && dispatchType === 'order'" x-transition class="space-y-2">
                            <div class="alert alert-info py-1.5 px-2 fs-9 mb-2">
                                <i class="bi bi-info-circle me-1"></i> Pengambilan mandiri di KP. Wajib mencantumkan data identitas PIC unit peminta.
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label fs-9 fw-semibold mb-0">NIP PIC Pengambil <span class="text-danger">*</span></label>
                                    <input type="text" name="pickup_pic_nip" class="form-control form-control-sm" placeholder="Contoh: 198501102010121001" :required="deliveryMethod === 'PICKUP_KP' && dispatchType === 'order'">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-9 fw-semibold mb-0">Nama Lengkap PIC <span class="text-danger">*</span></label>
                                    <input type="text" name="pickup_pic_name" class="form-control form-control-sm" placeholder="Contoh: Budi Santoso" :required="deliveryMethod === 'PICKUP_KP' && dispatchType === 'order'">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fs-9 fw-semibold mb-0">Jabatan / Unit Kerja <span class="text-danger">*</span></label>
                                    <input type="text" name="pickup_pic_position" class="form-control form-control-sm" placeholder="Contoh: Staff Operasional Cabang" :required="deliveryMethod === 'PICKUP_KP' && dispatchType === 'order'">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary border-top py-2.5 px-4 d-flex align-items-center justify-content-end gap-2 flex-shrink-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary fs-8" @click="dispatchModalOpen = false">
                        Batal
                    </button>
                    <button type="submit" 
                            :disabled="(dispatchType === 'order' && readyOrdersList.length === 0) || (dispatchType === 'switching' && readySwitchingsList.length === 0)"
                            class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-send-check"></i>
                        <span>Terbitkan Manifest & Dispatch</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: LIHAT DETAIL MANIFEST PENGIRIMAN                -->
    <!-- ======================================================== -->
    <div x-show="viewModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="viewModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color); max-height: 90vh; display: flex; flex-direction: column;">
            
            <div class="card-header bg-danger text-white py-2 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-truck fs-6"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Detail Manifest Pengiriman</h5>
                </div>
                <button type="button" @click="viewModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <div class="card-body p-3.5 fs-8 overflow-y-auto space-y-3" style="flex: 1 1 auto;">
                <div class="border rounded-2 p-3 bg-body-tertiary">
                    <div class="row g-2">
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Nomor Manifest:</span>
                            <span class="font-monospace fw-bold text-danger fs-7" x-text="selectedShipment?.manifest_number"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Tanggal Terbit:</span>
                            <span class="fw-semibold text-body" x-text="selectedShipment?.created_at"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Nomor Order / Ref:</span>
                            <span class="font-monospace fw-semibold text-body" x-text="selectedShipment?.order_number"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Tujuan Cabang:</span>
                            <span class="fw-semibold text-body" x-text="selectedShipment?.branch_name"></span>
                        </div>
                        <div class="col-6" x-show="selectedShipment?.is_switching">
                            <span class="fs-9 text-secondary d-block">Gudang Pengirim (Asal):</span>
                            <span class="fw-semibold text-body" x-text="selectedShipment?.origin_warehouse"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Ekspedisi / Kurir:</span>
                            <span class="fw-semibold text-body" x-text="selectedShipment?.courier_name"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Nomor Resi / AWB:</span>
                            <span class="font-monospace fw-bold text-body" x-text="selectedShipment?.tracking_number"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Paket Fisik:</span>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace" x-text="selectedShipment?.koli_count + ' Koli (' + selectedShipment?.total_weight_kg + ' kg)'"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Estimasi Tiba (ETA):</span>
                            <span class="font-monospace text-body" x-text="selectedShipment?.eta_date"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Petugas Dispatch:</span>
                            <span class="fw-semibold text-body" x-text="selectedShipment?.dispatcher_name"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Status Kiriman:</span>
                            <span class="badge bg-primary-subtle text-primary-emphasis font-monospace" x-text="selectedShipment?.status"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary border-top py-2.5 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                <button type="button" class="btn btn-sm btn-outline-secondary fs-8" @click="viewModalOpen = false">
                    Tutup
                </button>
                <div class="d-flex align-items-center gap-2">
                    <a :href="'{{ url('distribution/manifest') }}/' + selectedShipment?.id + '/print'" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-secondary fs-8 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-printer"></i>
                        <span>Cetak Manifest</span>
                    </a>
                    <a :href="'{{ url('distribution/label') }}/' + selectedShipment?.id + '/print'" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-primary fs-8 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-qr-code"></i>
                        <span>Cetak Label</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
