@extends('layouts.app')
@section('title', 'Gudang: Packing List')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik & Gudang</li>
    <li class="breadcrumb-item active" aria-current="page">Packing List</li>
@endsection

@section('content')
<div x-data="{
    viewModalOpen: false,
    packModalOpen: false,
    selectedOrder: null,
    packActionUrl: '',

    openViewModal(order) {
        this.selectedOrder = order;
        this.viewModalOpen = true;
    },

    openPackModal(order) {
        this.selectedOrder = order;
        this.packActionUrl = '{{ url('warehouse/packing') }}/' + order.id + '/process';
        this.packModalOpen = true;
    },

    deleteModalOpen: false,
    deleteActionUrl: '',

    openDeleteModal(order) {
        this.selectedOrder = order;
        this.deleteActionUrl = '{{ url('warehouse/packing') }}/' + order.id;
        this.deleteModalOpen = true;
    }
}" class="space-y-4">

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

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header -->
        <div class="card-header border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 py-3 px-4">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                Daftar Antrean Pengepakan Barang (Packing)
            </h3>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                    {{ $pickingOrders->total() }} Antrean Menunggu
                </span>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('warehouse.packing.queue') }}" method="GET">
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

                    <!-- Reset Button -->
                    @if(request('search') || request('organization_id'))
                        <div class="col-auto">
                            <a href="{{ route('warehouse.packing.queue') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                   placeholder="Cari no. order, barang, atau cabang..." 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Packing Queue Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 text-nowrap fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4 py-3" style="width: 170px;">No. Order & Tanggal</th>
                        <th class="py-3" style="width: 220px;">Unit Kerja Tujuan</th>
                        <th class="py-3">Item Fisik Diambil (Picked)</th>
                        <th class="py-3 text-center" style="width: 120px;">Total Qty</th>
                        <th class="pe-4 py-3 text-center" style="min-width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pickingOrders as $ord)
                        @php
                            $itemPicked = fn($it) => $it->qty_picked > 0 ? $it->qty_picked : ($it->qty_allocated > 0 ? $it->qty_allocated : ($it->qty_approved > 0 ? $it->qty_approved : $it->qty_requested));
                            $totalPicked = $ord->items->sum($itemPicked);
                            $itemsCount = $ord->items->count();
                            $firstItem = $ord->items->first()?->item;
                            $firstItemPicked = $ord->items->first() ? $itemPicked($ord->items->first()) : 0;
                            $ordData = [
                                'id' => $ord->id,
                                'order_number' => $ord->order_number,
                                'order_date' => $ord->order_date ? $ord->order_date->format('d/m/Y') : '-',
                                'priority' => $ord->priority,
                                'status' => $ord->status,
                                'requesting_organization' => $ord->requestingOrganization ? [
                                    'name' => $ord->requestingOrganization->name,
                                    'code' => $ord->requestingOrganization->code,
                                    'city' => $ord->requestingOrganization->city,
                                    'address' => $ord->requestingOrganization->address,
                                ] : null,
                                'items' => $ord->items->map(fn($it) => [
                                    'id' => $it->id,
                                    'item_name' => $it->item->name ?? '-',
                                    'sku' => $it->item->sku ?? '-',
                                    'uom' => $it->item->uom ?? 'Unit',
                                    'qty_picked' => $itemPicked($it),
                                ])->values()->all(),
                                'total_picked' => $totalPicked,
                            ];
                        @endphp
                        <tr>
                            <!-- No. Order & Tanggal -->
                            <td class="ps-4 font-monospace">
                                <span class="fw-bold text-danger d-block">{{ $ord->order_number }}</span>
                                <span class="text-secondary fs-9">{{ $ord->order_date ? $ord->order_date->format('d/m/Y') : '-' }}</span>
                            </td>

                            <!-- Unit Kerja Tujuan -->
                            <td>
                                <div class="fw-semibold text-body">{{ $ord->requestingOrganization->name ?? '-' }}</div>
                                <div class="fs-9 text-secondary">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $ord->requestingOrganization->city ?? '-' }}
                                </div>
                            </td>

                            <!-- Item Fisik Diambil -->
                            <td>
                                @if($itemsCount > 1)
                                    <div class="d-flex align-items-center gap-1 mb-1">
                                        <span class="badge bg-danger-subtle text-danger-emphasis fs-9 fw-bold">
                                            {{ $itemsCount }} Jenis Barang
                                        </span>
                                    </div>
                                    <div class="fs-9 text-secondary text-truncate" style="max-width: 300px;" title="{{ $ord->items->map(fn($i) => ($i->item->name ?? '-') . ' (' . $itemPicked($i) . ' ' . ($i->item->uom ?? '') . ')')->implode(', ') }}">
                                        {{ $ord->items->take(2)->map(fn($i) => ($i->item->name ?? '-') . ' (' . $itemPicked($i) . ' ' . ($i->item->uom ?? '') . ')')->implode(', ') }}{{ $itemsCount > 2 ? ', +' . ($itemsCount - 2) . ' lainnya' : '' }}
                                    </div>
                                @elseif($firstItem)
                                    <div class="fw-bold text-body">{{ $firstItem->name }}</div>
                                    <div class="fs-9 text-secondary font-monospace">{{ $firstItem->sku }} • {{ $firstItemPicked }} {{ $firstItem->uom }}</div>
                                @else
                                    <span class="text-secondary">-</span>
                                @endif
                            </td>

                            <!-- Total Qty -->
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-8">
                                    {{ $totalPicked }}
                                </span>
                            </td>

                            <!-- Aksi -->
                            <td class="pe-4 text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <!-- View Modal Button -->
                                    <button type="button" 
                                            @click="openViewModal({{ json_encode($ordData) }})" 
                                            class="btn btn-sm btn-outline-secondary py-0.5 px-1.5 fs-9" 
                                            title="Lihat Detail Item">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <!-- Packing Action Modal Button (Stay on this page) -->
                                    <button type="button" 
                                            @click="openPackModal({{ json_encode($ordData) }})" 
                                            class="btn-action-icon text-danger btn btn-sm btn-outline-danger py-0.5 px-1.5 fs-9 fw-bold" 
                                            title="Input Koli & Selesaikan Packing">
                                        <i class="bi bi-box-seam"></i>
                                    </button>

                                    <!-- Delete Action Modal Button -->
                                    <button type="button" 
                                            @click="openDeleteModal({{ json_encode($ordData) }})" 
                                            class="btn btn-sm btn-outline-danger py-0.5 px-1.5 fs-9" 
                                            title="Hapus Order dari Antrean">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-secondary">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                <p class="fw-bold mb-1">Tidak ada order yang menunggu pengepakan saat ini</p>
                                <p class="fs-8 text-muted mb-0">Semua order yang selesai picking telah dipacking atau belum ada alokasi baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination-footer :paginator="$pickingOrders" :perPage="$perPage" />
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: LIHAT DETAIL ORDER & ITEM                       -->
    <!-- ======================================================== -->
    <div x-show="viewModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="viewModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color); max-height: 90vh; display: flex; flex-direction: column;">
            
            <div class="card-header bg-danger text-white py-2 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-boxes fs-6"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Detail Order Siap Pengepakan</h5>
                </div>
                <button type="button" @click="viewModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <div class="card-body p-3.5 fs-8 overflow-y-auto" style="flex: 1 1 auto;">
                <div class="border rounded-2 p-3 bg-body-tertiary mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Nomor Order:</span>
                            <span class="font-monospace fw-bold text-danger fs-7" x-text="selectedOrder?.order_number"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Tanggal Order:</span>
                            <span class="fw-semibold text-body" x-text="selectedOrder?.order_date"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Unit Kerja Tujuan:</span>
                            <span class="fw-semibold text-body" x-text="selectedOrder?.requesting_organization?.name"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Status:</span>
                            <span class="badge bg-warning-subtle text-warning-emphasis font-monospace" x-text="selectedOrder?.status"></span>
                        </div>
                    </div>
                </div>

                <div class="fw-bold text-danger text-uppercase fs-9 mb-2">
                    Daftar Fisik Barang yang Telah Dipick:
                </div>
                <div class="table-responsive border rounded-2">
                    <table class="table table-sm table-striped align-middle mb-0 fs-8">
                        <thead class="table-light text-secondary text-uppercase fs-9">
                            <tr>
                                <th class="ps-3 py-2">Nama Barang</th>
                                <th class="py-2">SKU</th>
                                <th class="pe-3 py-2 text-center">Qty Picked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in (selectedOrder?.items || [])" :key="item.id">
                                <tr>
                                    <td class="ps-3 fw-semibold" x-text="item.item_name"></td>
                                    <td class="font-monospace text-secondary fs-9" x-text="item.sku"></td>
                                    <td class="pe-3 text-center">
                                        <span class="badge bg-danger-subtle text-danger-emphasis font-monospace fw-bold" x-text="item.qty_picked + ' ' + item.uom"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary border-top py-2.5 px-4 d-flex align-items-center justify-content-end gap-2 flex-shrink-0">
                <button type="button" class="btn btn-sm btn-outline-secondary fs-8" @click="viewModalOpen = false">
                    Tutup
                </button>
                <button type="button" 
                        @click="viewModalOpen = false; openPackModal(selectedOrder)" 
                        class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-box-seam"></i>
                    <span>Input Form Pengepakan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: FORM PENGEPAKAN BARANG (PACKING LIST)            -->
    <!-- ======================================================== -->
    <div x-show="packModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="packModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger text-white py-2 px-4 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam fs-6"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Form Pengepakan Barang (Packing List)</h5>
                </div>
                <button type="button" @click="packModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <form :action="packActionUrl" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    
                    <!-- Order Info Box -->
                    <div class="p-3 bg-body-tertiary rounded-2 border">
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="fs-9 text-secondary d-block">Nomor Order:</span>
                                <span class="font-monospace fw-bold text-danger fs-7" x-text="selectedOrder?.order_number"></span>
                            </div>
                            <div class="col-6">
                                <span class="fs-9 text-secondary d-block">Tujuan Cabang:</span>
                                <span class="fw-semibold text-body" x-text="selectedOrder?.requesting_organization?.name"></span>
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <span class="fs-9 text-secondary d-block">Total Qty Fisik Barang:</span>
                                <span class="fw-bold text-body font-monospace" x-text="selectedOrder?.total_picked + ' Unit'"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fs-8 fw-semibold mb-1">
                                Jumlah Koli (Box) <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   name="koli_count" 
                                   value="1" 
                                   min="1" 
                                   required 
                                   class="form-control form-control-sm text-center fw-bold font-monospace fs-8">
                            <div class="fs-9 text-secondary mt-1">Karton / koli fisik.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 fw-semibold mb-1">
                                Berat Kotor (Kg) <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   step="0.1" 
                                   name="total_weight_kg" 
                                   value="5.0" 
                                   min="0.1" 
                                   required 
                                   class="form-control form-control-sm text-center fw-bold font-monospace fs-8">
                            <div class="fs-9 text-secondary mt-1">Termasuk kardus & packaging.</div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-semibold mb-1">
                            Dimensi Paket (P x L x T cm) <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="dimensions_cm" 
                               value="40 x 30 x 25 cm" 
                               required 
                               class="form-control form-control-sm font-monospace fs-8">
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary border-top py-2.5 px-4 d-flex align-items-center justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary fs-8" @click="packModalOpen = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check2-circle"></i>
                        <span>Selesaikan Packing (Ready to Ship)</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 3: KONFIRMASI HAPUS DARI PACKING (KEMBALI KE PICKING)-->
    <!-- ======================================================== -->
    <div x-show="deleteModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="deleteModalOpen = false" 
             class="card shadow-2xl border border-danger-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger text-white py-2 px-4 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-counterclockwise fs-6"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Hapus dari Antrean Packing</h5>
                </div>
                <button type="button" @click="deleteModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <form :action="deleteActionUrl" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin membatalkan pengepakan untuk order berikut?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-9 text-secondary mb-0.5">Nomor & Unit Pemohon:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="selectedOrder?.order_number"></div>
                        <div class="fs-8 text-body fw-semibold" x-text="selectedOrder?.requesting_organization?.name"></div>
                        <div class="fs-9 text-secondary font-monospace" x-text="selectedOrder?.total_picked + ' Unit Fisik'"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-arrow-counterclockwise text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Order ini akan dibatalkan dari proses pengepakan dan dikembalikan ke <strong>antrean picking</strong> (pengambilan barang gudang).
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModalOpen = false" class="btn btn-sm btn-outline-secondary px-3 fs-8">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 fs-8 shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Kembalikan ke Picking</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
