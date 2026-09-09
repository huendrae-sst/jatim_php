@extends('layouts.app')
@section('title', 'Gudang: Picking List')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik & Gudang</li>
    <li class="breadcrumb-item active" aria-current="page">Picking List</li>
@endsection

@section('content')
<div x-data="{
    viewModalOpen: false,
    confirmModalOpen: false,
    selectedOrder: null,
    confirmActionUrl: '',

    openViewModal(order) {
        this.selectedOrder = order;
        this.viewModalOpen = true;
    },

    openConfirmModal(order) {
        this.selectedOrder = order;
        this.confirmActionUrl = '{{ url('warehouse/picking') }}/' + order.id + '/process';
        this.confirmModalOpen = true;
    },

    deleteModalOpen: false,
    deleteActionUrl: '',

    openDeleteModal(order) {
        this.selectedOrder = order;
        this.deleteActionUrl = '{{ url('warehouse/picking') }}/' + order.id;
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
                Daftar Antrean Picking Barang
            </h3>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">
                    {{ $allocatedOrders->total() }} Antrean Menunggu
                </span>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('warehouse.picking.queue') }}" method="GET">
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

                    <!-- Prioritas Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-flag"></i></span>
                            <select name="priority" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Prioritas</option>
                                <option value="URGENT" {{ request('priority') === 'URGENT' ? 'selected' : '' }}>URGENT</option>
                                <option value="HIGH" {{ request('priority') === 'HIGH' ? 'selected' : '' }}>HIGH</option>
                                <option value="NORMAL" {{ request('priority') === 'NORMAL' ? 'selected' : '' }}>NORMAL</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if(request('search') || request('priority') || request('organization_id'))
                        <div class="col-auto">
                            <a href="{{ route('warehouse.picking.queue') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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

        <!-- Picking Queue Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 text-nowrap fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4 py-3" style="width: 170px;">No. Order & Tanggal</th>
                        <th class="py-3" style="width: 220px;">Unit Kerja Tujuan</th>
                        <th class="py-3">Item yang Diambil (Picking)</th>
                        <th class="py-3 text-center" style="width: 120px;">Total Qty</th>
                        <th class="py-3 text-center" style="width: 120px;">Prioritas</th>
                        <th class="pe-4 py-3 text-center" style="min-width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allocatedOrders as $ord)
                        @php
                            $itemQty = fn($it) => $it->qty_allocated > 0 ? $it->qty_allocated : ($it->qty_approved > 0 ? $it->qty_approved : $it->qty_requested);
                            $totalAllocated = $ord->items->sum($itemQty);
                            $itemsCount = $ord->items->count();
                            $firstItem = $ord->items->first()?->item;
                            $firstItemQty = $ord->items->first() ? $itemQty($ord->items->first()) : 0;
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
                                    'qty_requested' => $it->qty_requested,
                                    'qty_allocated' => $itemQty($it),
                                ])->values()->all(),
                                'total_allocated' => $totalAllocated,
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

                            <!-- Item yang Diambil -->
                            <td>
                                @if($itemsCount > 1)
                                    <div class="d-flex align-items-center gap-1 mb-1">
                                        <span class="badge bg-danger-subtle text-danger-emphasis fs-9 fw-bold">
                                            {{ $itemsCount }} Jenis Barang
                                        </span>
                                    </div>
                                    <div class="fs-9 text-secondary text-truncate" style="max-width: 280px;" title="{{ $ord->items->map(fn($i) => ($i->item->name ?? '-') . ' (' . $itemQty($i) . ' ' . ($i->item->uom ?? '') . ')')->implode(', ') }}">
                                        {{ $ord->items->take(2)->map(fn($i) => ($i->item->name ?? '-') . ' (' . $itemQty($i) . ' ' . ($i->item->uom ?? '') . ')')->implode(', ') }}{{ $itemsCount > 2 ? ', +' . ($itemsCount - 2) . ' lainnya' : '' }}
                                    </div>
                                @elseif($firstItem)
                                    <div class="fw-bold text-body">{{ $firstItem->name }}</div>
                                    <div class="fs-9 text-secondary font-monospace">{{ $firstItem->sku }} • {{ $firstItemQty }} {{ $firstItem->uom }}</div>
                                @else
                                    <span class="text-secondary">-</span>
                                @endif
                            </td>

                            <!-- Total Qty -->
                            <td class="text-center">
                                <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-8">
                                    {{ $totalAllocated }}
                                </span>
                            </td>

                            <!-- Prioritas -->
                            <td class="text-center">
                                @if($ord->priority === 'URGENT')
                                    <span class="badge bg-danger-subtle text-danger-emphasis fs-9 py-1 px-2">
                                        URGENT
                                    </span>
                                @elseif($ord->priority === 'HIGH')
                                    <span class="badge bg-warning-subtle text-warning-emphasis fs-9 py-1 px-2">
                                        HIGH
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis fs-9 py-1 px-2">
                                        NORMAL
                                    </span>
                                @endif
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

                                    <!-- Konfirmasi Picking Button (Stay on this page) -->
                                    <button type="button" 
                                            @click="openConfirmModal({{ json_encode($ordData) }})" 
                                            class="btn-action-icon text-danger btn btn-sm btn-outline-danger py-0.5 px-1.5 fs-9 fw-bold" 
                                            title="Konfirmasi Selesai Picking">
                                        <i class="bi bi-check2-circle"></i>
                                    </button>

                                    <!-- Delete Action Modal Button -->
                                    <button type="button" 
                                            @click="openDeleteModal({{ json_encode($ordData) }})" 
                                            class="btn btn-sm btn-outline-danger py-0.5 px-1.5 fs-9" 
                                            title="Hapus Order dari Antrean Picking (Kembalikan ke /orders)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-secondary">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                <p class="fw-bold mb-1">Tidak ada order yang menunggu picking saat ini</p>
                                <p class="fs-8 text-muted mb-0">Semua order yang dialokasikan telah selesai dipicking atau belum ada alokasi baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination-footer :paginator="$allocatedOrders" :perPage="$perPage" />
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: LIHAT DETAIL ORDER & DAFTAR ITEM PICKING       -->
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
                    <i class="bi bi-box-seam fs-6"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Detail Antrean Picking</h5>
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
                            <span class="fs-9 text-secondary d-block">Unit Kerja / Cabang:</span>
                            <span class="fw-semibold text-body" x-text="selectedOrder?.requesting_organization?.name"></span>
                        </div>
                        <div class="col-6">
                            <span class="fs-9 text-secondary d-block">Prioritas:</span>
                            <span class="badge bg-danger-subtle text-danger-emphasis font-monospace" x-text="selectedOrder?.priority"></span>
                        </div>
                    </div>
                </div>

                <div class="fw-bold text-danger text-uppercase fs-9 mb-2">
                    Daftar Fisik Barang yang Harus Diambil:
                </div>
                <div class="table-responsive border rounded-2">
                    <table class="table table-sm table-striped align-middle mb-0 fs-8">
                        <thead class="table-light text-secondary text-uppercase fs-9">
                            <tr>
                                <th class="ps-3 py-2">Nama Barang</th>
                                <th class="py-2">SKU</th>
                                <th class="py-2 text-center">Qty Diminta</th>
                                <th class="pe-3 py-2 text-center">Qty Alokasi (Pick)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in (selectedOrder?.items || [])" :key="item.id">
                                <tr>
                                    <td class="ps-3 fw-semibold" x-text="item.item_name"></td>
                                    <td class="font-monospace text-secondary fs-9" x-text="item.sku"></td>
                                    <td class="text-center" x-text="item.qty_requested + ' ' + item.uom"></td>
                                    <td class="pe-3 text-center">
                                        <span class="badge bg-danger-subtle text-danger-emphasis font-monospace fw-bold" x-text="item.qty_allocated + ' ' + item.uom"></span>
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
                <form :action="'{{ url('warehouse/picking') }}/' + selectedOrder?.id + '/process'" method="POST" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check2-circle"></i>
                        <span>Konfirmasi Selesai Picking</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: KONFIRMASI AKSI PICKING SELESAI                  -->
    <!-- ======================================================== -->
    <div x-show="confirmModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="confirmModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger text-white py-2 px-4 d-flex align-items-center justify-content-between">
                <h5 class="modal-title fs-6 fw-bold mb-0">Konfirmasi Picking Fisik</h5>
                <button type="button" @click="confirmModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <form :action="confirmActionUrl" method="POST">
                @csrf
                <div class="card-body p-4 text-center">
                    <i class="bi bi-question-circle text-danger fs-1 d-block mb-3"></i>
                    <h6 class="fw-bold mb-2">Konfirmasi Pengambilan Fisik Barang?</h6>
                    <p class="fs-8 text-secondary mb-3">
                        Apakah Anda yakin seluruh item fisik untuk order 
                        <strong class="font-monospace text-danger" x-text="selectedOrder?.order_number"></strong> 
                        telah selesai diambil dari rak gudang dan siap masuk tahap pengepakan (packing)?
                    </p>
                    <div class="bg-body-tertiary border rounded-2 p-2.5 text-start fs-8">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Tujuan:</span>
                            <span class="fw-semibold" x-text="selectedOrder?.requesting_organization?.name"></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Total Qty Pick:</span>
                            <span class="fw-bold text-danger font-monospace" x-text="selectedOrder?.total_allocated + ' Unit'"></span>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary border-top py-2.5 px-4 d-flex align-items-center justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary fs-8" @click="confirmModalOpen = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check-lg"></i>
                        <span>Ya, Konfirmasi Picking</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 3: KONFIRMASI HAPUS DARI PICKING (KEMBALI KE ORDERS) -->
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
                    <h5 class="modal-title fs-6 fw-bold mb-0">Hapus dari Antrean Picking</h5>
                </div>
                <button type="button" @click="deleteModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <form :action="deleteActionUrl" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin membatalkan antrean picking untuk order berikut?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-9 text-secondary mb-0.5">Nomor & Unit Pemohon:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="selectedOrder?.order_number"></div>
                        <div class="fs-8 text-body fw-semibold" x-text="selectedOrder?.requesting_organization?.name"></div>
                        <div class="fs-9 text-secondary font-monospace" x-text="(selectedOrder?.total_allocated || 0) + ' Unit Alokasi'"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-arrow-counterclockwise text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Order ini akan dibatalkan dari antrean picking gudang, reservasi stok dilepaskan, dan data dikembalikan ke <strong>antrean pesanan (/orders)</strong>.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModalOpen = false" class="btn btn-sm btn-outline-secondary px-3 fs-8">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 fs-8 shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <span>Kembalikan ke /orders</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
