@extends('layouts.app')
@section('title', 'Switching Stock Antar-Unit')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Switching Stock</li>
@endsection

@section('content')
<div x-data="{
    createModalOpen: false,
    viewModalOpen: false,
    editModalOpen: false,
    deleteModalOpen: false,

    allWarehouses: {{ Js::from($warehouses) }},
    allItems: {{ Js::from($items) }},
    allOrgs: {{ Js::from($organizations) }},

    createData: {
        destination_organization_id: '',
        destination_warehouse_id: '',
        source_organization_id: '',
        source_warehouse_id: '',
        recommendation_reason: '',
        items: [
            { item_id: '', qty_requested: 1 }
        ]
    },

    selectedSwitching: null,

    editData: {
        id: '',
        destination_organization_id: '',
        destination_warehouse_id: '',
        source_organization_id: '',
        source_warehouse_id: '',
        recommendation_reason: '',
        items: [
            { item_id: '', qty_requested: 1 }
        ]
    },
    editActionUrl: '',

    deleteData: {
        id: '',
        ref: '',
        item_name: '',
        qty: '',
        source: '',
        dest: ''
    },
    deleteActionUrl: '',

    loadingRec: false,
    recommendations: [],
    itemRecommendations: [],
    recViewMode: 'branch',
    recError: '',
    recSearched: false,
    recSelectedItem: null,

    addItemRow(formType = 'create') {
        if (formType === 'create') {
            this.createData.items.push({ item_id: '', qty_requested: 1 });
        } else {
            if (!this.editData.items) this.editData.items = [];
            this.editData.items.push({ item_id: '', qty_requested: 1 });
        }
    },

    removeItemRow(index, formType = 'create') {
        const target = formType === 'create' ? this.createData.items : this.editData.items;
        if (target && target.length > 1) {
            target.splice(index, 1);
        }
    },

    onDestOrgChange(formType = 'create') {
        const orgId = formType === 'create' ? this.createData.destination_organization_id : this.editData.destination_organization_id;
        const available = this.allWarehouses.filter(w => w.organization_id == orgId);
        if (formType === 'create') {
            this.createData.destination_warehouse_id = available.length > 0 ? available[0].id : '';
            this.recommendations = [];
            this.itemRecommendations = [];
            this.recSearched = false;
            this.recError = '';
        } else {
            this.editData.destination_warehouse_id = available.length > 0 ? available[0].id : '';
        }
    },

    onSourceOrgChange(formType = 'create') {
        const orgId = formType === 'create' ? this.createData.source_organization_id : this.editData.source_organization_id;
        const available = this.allWarehouses.filter(w => w.organization_id == orgId);
        if (formType === 'create') {
            this.createData.source_warehouse_id = available.length > 0 ? available[0].id : '';
        } else {
            this.editData.source_warehouse_id = available.length > 0 ? available[0].id : '';
        }
    },

    getFilteredWarehouses(orgId) {
        if (!orgId) return [];
        return this.allWarehouses.filter(w => w.organization_id == orgId);
    },

    async fetchSystemRecommendations() {
        if (!this.createData.destination_organization_id) {
            this.recError = 'Silakan pilih Unit Tujuan (Peminta) terlebih dahulu.';
            return;
        }

        const validItems = (this.createData.items || []).filter(it => it.item_id && Number(it.qty_requested) > 0);

        if (validItems.length === 0) {
            this.recError = 'Silakan pilih minimal 1 barang dan tentukan jumlah kebutuhan pada daftar barang.';
            return;
        }

        this.loadingRec = true;
        this.recError = '';
        this.recommendations = [];
        this.itemRecommendations = [];
        this.recSearched = false;

        try {
            const url = new URL('{{ route('inventory.switching.recommendations') }}', window.location.origin);
            url.searchParams.append('destination_organization_id', this.createData.destination_organization_id);

            validItems.forEach((it, idx) => {
                url.searchParams.append(`items[${idx}][item_id]`, it.item_id);
                url.searchParams.append(`items[${idx}][qty]`, it.qty_requested);
            });

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Gagal memuat rekomendasi.');
            }

            this.recommendations = data.recommendations || [];
            this.itemRecommendations = data.item_recommendations || [];
            this.recViewMode = this.recommendations.length > 0 ? 'branch' : 'item';
            this.recSearched = true;
        } catch (err) {
            this.recError = err.message || 'Terjadi kesalahan saat memproses rekomendasi sistem.';
        } finally {
            this.loadingRec = false;
        }
    },

    selectRecommendation(rec) {
        this.createData.source_organization_id = rec.organization_id;
        this.createData.source_warehouse_id = rec.warehouse_id;
        const fulfillStatus = rec.is_all_covered 
            ? `semua ${rec.total_items_count} barang terpenuhi` 
            : `${rec.fully_covered_count} dari ${rec.total_items_count} barang terpenuhi`;
        this.createData.recommendation_reason = `Rekomendasi sistem: Pemenuhan dari surplus cabang ${rec.organization_name} (${fulfillStatus}).`;
    },

    selectItemSource(source, itemRec) {
        this.createData.source_organization_id = source.organization_id;
        this.createData.source_warehouse_id = source.warehouse_id;
        this.createData.recommendation_reason = `Rekomendasi sistem untuk ${itemRec.item_name}: Dipenuhi dari surplus ${source.organization_name} (Gudang ${source.warehouse_name}).`;
    },

    openViewModal(item) {
        this.selectedSwitching = item;
        this.viewModalOpen = true;
    },

    openEditModal(item) {
        let rowItems = [];
        if (item.items && item.items.length > 0) {
            rowItems = item.items.map(it => ({
                item_id: it.item_id,
                qty_requested: it.qty_requested
            }));
        } else if (item.item_id) {
            rowItems = [{
                item_id: item.item_id,
                qty_requested: item.qty_requested || 1
            }];
        } else {
            rowItems = [{ item_id: '', qty_requested: 1 }];
        }

        this.editData = {
            id: item.id,
            destination_organization_id: item.destination_organization_id,
            destination_warehouse_id: item.destination_warehouse_id,
            source_organization_id: item.source_organization_id,
            source_warehouse_id: item.source_warehouse_id,
            items: rowItems,
            recommendation_reason: item.recommendation_reason || ''
        };
        this.editActionUrl = '{{ url('inventory/switching-stocks') }}/' + item.id;
        this.editModalOpen = true;
    },

    openDeleteModal(item) {
        let itemNames = '';
        if (item.items && item.items.length > 0) {
            if (item.items.length === 1) {
                itemNames = item.items[0].item ? item.items[0].item.name : '-';
            } else {
                itemNames = item.items.length + ' Jenis Barang (' + item.items.map(it => it.item ? it.item.name : '').filter(Boolean).slice(0, 3).join(', ') + (item.items.length > 3 ? '...' : '') + ')';
            }
        } else {
            itemNames = item.item ? item.item.name : '-';
        }

        let totalQty = item.total_qty || (item.items && item.items.length > 0 ? item.items.reduce((sum, it) => sum + (it.qty_requested || 0), 0) : item.qty_requested);

        this.deleteData = {
            id: item.id,
            ref: item.order ? item.order.order_number : ('MANUAL-' + item.id),
            item_name: itemNames,
            qty: totalQty + ' Unit',
            source: item.source_organization ? item.source_organization.name : '-',
            dest: item.destination_organization ? item.destination_organization.name : '-'
        };
        this.deleteActionUrl = '{{ url('inventory/switching-stocks') }}/' + item.id;
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
                <i class="bi bi-arrow-left-right text-danger me-2"></i> Daftar Switching Stock Antar-Unit
            </h3>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">{{ $switchings->total() }} Data Ditemukan</span>
                <button type="button" @click="createModalOpen = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1 ms-auto">
                    <i class="bi bi-plus-circle"></i>
                    <span>Tambah Switching Stock</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('inventory.switching.index') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Cabang / Unit Organisasi Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                            <select name="organization_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Cabang / Unit</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ (string)$orgId === (string)$org->id ? 'selected' : '' }}>
                                        [{{ $org->code }}] {{ $org->name }}
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
                                <option value="PROPOSED" {{ $status === 'PROPOSED' ? 'selected' : '' }}>PROPOSED</option>
                                <option value="APPROVED" {{ $status === 'APPROVED' ? 'selected' : '' }}>APPROVED</option>
                                <option value="RESERVED" {{ $status === 'RESERVED' ? 'selected' : '' }}>RESERVED</option>
                                <option value="COMPLETED" {{ $status === 'COMPLETED' ? 'selected' : '' }}>COMPLETED</option>
                                <option value="REJECTED" {{ $status === 'REJECTED' ? 'selected' : '' }}>REJECTED</option>
                                <option value="CANCELLED" {{ $status === 'CANCELLED' ? 'selected' : '' }}>CANCELLED</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || $status || $orgId)
                        <div class="col-auto">
                            <a href="{{ route('inventory.switching.index') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                   placeholder="Cari no. referensi, barang, cabang..." 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Switching Stocks Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 text-nowrap fs-8">
                <thead class="table-light text-secondary text-uppercase fs-9">
                    <tr>
                        <th class="ps-4 py-3">No. Referensi</th>
                        <th class="py-3">Tanggal</th>
                        <th class="py-3">Barang & SKU</th>
                        <th class="py-3">Unit Sumber (Penyedia)</th>
                        <th class="py-3">Unit Tujuan (Peminta)</th>
                        <th class="py-3 text-center">Jumlah (Qty)</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="pe-4 py-3 text-center" style="min-width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($switchings as $sw)
                        @php
                            $totalQty = $sw->total_qty;
                            $itemsCount = $sw->total_items_count;
                            $firstItem = $sw->items->first()?->item ?? $sw->item;

                            $swData = [
                                'id' => $sw->id,
                                'order_id' => $sw->order_id,
                                'order' => $sw->order ? ['order_number' => $sw->order->order_number] : null,
                                'item_id' => $sw->item_id,
                                'item' => $firstItem ? [
                                    'id' => $firstItem->id,
                                    'name' => $firstItem->name,
                                    'sku' => $firstItem->sku,
                                    'uom' => $firstItem->uom,
                                    'safety_stock' => $firstItem->safety_stock,
                                ] : null,
                                'items' => $sw->items->map(fn($it) => [
                                    'id' => $it->id,
                                    'item_id' => $it->item_id,
                                    'qty_requested' => $it->qty_requested,
                                    'item' => $it->item ? [
                                        'id' => $it->item->id,
                                        'name' => $it->item->name,
                                        'sku' => $it->item->sku,
                                        'uom' => $it->item->uom,
                                    ] : null,
                                ])->values()->all(),
                                'source_organization_id' => $sw->source_organization_id,
                                'source_organization' => $sw->sourceOrganization ? ['name' => $sw->sourceOrganization->name, 'city' => $sw->sourceOrganization->city] : null,
                                'source_warehouse_id' => $sw->source_warehouse_id,
                                'source_warehouse' => $sw->sourceWarehouse ? ['name' => $sw->sourceWarehouse->name] : null,
                                'destination_organization_id' => $sw->destination_organization_id,
                                'destination_organization' => $sw->destinationOrganization ? ['name' => $sw->destinationOrganization->name, 'city' => $sw->destinationOrganization->city] : null,
                                'destination_warehouse_id' => $sw->destination_warehouse_id,
                                'destination_warehouse' => $sw->destinationWarehouse ? ['name' => $sw->destinationWarehouse->name] : null,
                                'qty_requested' => $totalQty,
                                'total_qty' => $totalQty,
                                'items_count' => $itemsCount,
                                'status' => $sw->status,
                                'recommendation_reason' => $sw->recommendation_reason,
                                'rejection_reason' => $sw->rejection_reason,
                                'created_at' => $sw->created_at ? $sw->created_at->format('d/m/Y H:i') : '-',
                                'proposer' => $sw->proposer ? ['name' => $sw->proposer->name] : null,
                                'approver' => $sw->approver ? ['name' => $sw->approver->name] : null,
                            ];
                        @endphp
                        <tr>
                            <!-- No. Referensi -->
                            <td class="ps-4 font-monospace">
                                @if($sw->order)
                                    <a href="{{ route('orders.index') }}" class="fw-bold text-danger text-decoration-none">
                                        {{ $sw->order->order_number }}
                                    </a>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace">MANUAL-{{ $sw->id }}</span>
                                @endif
                            </td>

                            <!-- Tanggal -->
                            <td class="text-secondary fs-9">
                                {{ $sw->created_at ? $sw->created_at->format('d/m/Y H:i') : '-' }}
                            </td>

                            <!-- Barang & SKU -->
                            <td>
                                @if($sw->items->count() > 1)
                                    <div class="d-flex align-items-center gap-1.5 mb-1">
                                        <span class="badge bg-danger-subtle text-danger-emphasis fs-9 fw-bold">
                                            <i class="bi bi-boxes me-1"></i>{{ $sw->items->count() }} Jenis Barang
                                        </span>
                                    </div>
                                    <div class="fs-9 text-secondary text-truncate" style="max-width: 240px;" title="{{ $sw->items->map(fn($i) => ($i->item->name ?? '-') . ' (' . $i->qty_requested . ' ' . ($i->item->uom ?? '') . ')')->implode(', ') }}">
                                        {{ $sw->items->take(2)->map(fn($i) => $i->item->name ?? '-')->implode(', ') }}{{ $sw->items->count() > 2 ? ', +' . ($sw->items->count() - 2) . ' lainnya' : '' }}
                                    </div>
                                @else
                                    <div class="fw-bold text-body">{{ $firstItem->name ?? '-' }}</div>
                                    <div class="fs-9 text-secondary font-monospace">{{ $firstItem->sku ?? '-' }} • {{ $firstItem->uom ?? '' }}</div>
                                @endif
                            </td>

                            <!-- Unit Sumber (Penyedia) -->
                            <td>
                                <div class="fw-semibold text-body">{{ $sw->sourceOrganization->name ?? '-' }}</div>
                                <div class="fs-9 text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ $sw->sourceWarehouse->name ?? '-' }}</div>
                            </td>

                            <!-- Unit Tujuan (Peminta) -->
                            <td>
                                <div class="fw-semibold text-body">{{ $sw->destinationOrganization->name ?? '-' }}</div>
                                <div class="fs-9 text-secondary"><i class="bi bi-geo-alt me-1"></i>{{ $sw->destinationWarehouse->name ?? '-' }}</div>
                            </td>

                            <!-- Qty -->
                            <td class="text-center font-monospace fw-bold text-primary fs-7">
                                {{ number_format($totalQty, 0, ',', '.') }} 
                                <span class="fs-9 text-secondary fw-normal">Unit</span>
                                @if($sw->items->count() > 1)
                                    <div class="fs-9 text-secondary fw-normal font-sans">({{ $sw->items->count() }} item)</div>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="text-center">
                                @if($sw->status === 'APPROVED' || $sw->status === 'COMPLETED')
                                    <span class="badge bg-success-subtle text-success-emphasis fs-9 py-1 px-2">
                                        <i class="bi bi-check-circle me-1"></i>{{ $sw->status }}
                                    </span>
                                @elseif($sw->status === 'PROPOSED')
                                    <span class="badge bg-warning-subtle text-warning-emphasis fs-9 py-1 px-2">
                                        <i class="bi bi-clock me-1"></i>{{ $sw->status }}
                                    </span>
                                @elseif($sw->status === 'REJECTED' || $sw->status === 'CANCELLED')
                                    <span class="badge bg-danger-subtle text-danger-emphasis fs-9 py-1 px-2">
                                        <i class="bi bi-x-circle me-1"></i>{{ $sw->status }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis fs-9 py-1 px-2">
                                        {{ $sw->status }}
                                    </span>
                                @endif
                            </td>

                            <!-- Aksi (View, Edit, Delete, Approve) -->
                            <td class="pe-4 text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <!-- View Button -->
                                    <button type="button" @click="openViewModal({{ json_encode($swData) }})" class="btn btn-sm btn-outline-secondary py-0.5 px-1.5 fs-9" title="Lihat Detail">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    @if($sw->status === 'PROPOSED')
                                        <!-- Edit Button -->
                                        <button type="button" @click="openEditModal({{ json_encode($swData) }})" class="btn btn-sm btn-outline-primary py-0.5 px-1.5 fs-9" title="Edit Pengajuan">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button" @click="openDeleteModal({{ json_encode($swData) }})" class="btn btn-sm btn-outline-danger py-0.5 px-1.5 fs-9" title="Hapus Pengajuan">
                                            <i class="bi bi-trash"></i>
                                        </button>

                                        <!-- Approve Button (if authorized) -->
                                        @if(auth()->user()->hasRole('SUPER_ADMIN', 'SWITCHING_APPROVER', 'ORDER_APPROVER'))
                                            <form action="{{ route('inventory.switching.approve', $sw->id) }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Apakah Anda yakin menyetujui switching stock ini? Stok di gudang sumber akan direservasi.');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success py-0.5 px-1.5 fs-9 fw-bold" title="Setujui Switching">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-secondary">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                <p class="fw-bold mb-1">Tidak ada data switching stock ditemukan</p>
                                <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau sesuaikan filter cabang / status.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination-footer :paginator="$switchings" :perPage="$perPage" />
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 1: TAMBAH MANUAL SWITCHING STOCK & REKOMENDASI SISTEM -->
    <!-- ======================================================== -->
    <div x-show="createModalOpen"
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         x-cloak
         style="display: none; z-index: 1050;">
        <div @click.away="createModalOpen = false"
             class="card shadow-2xl border border-secondary-subtle max-w-4xl w-full overflow-hidden"
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color); max-height: 90vh; display: flex; flex-direction: column;">
            <form action="{{ route('inventory.switching.store') }}" method="POST" class="d-flex flex-column h-100 m-0 overflow-hidden">
                @csrf
                <div class="card-header bg-danger text-white py-2 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                    <h5 class="modal-title fs-6 fw-bold mb-0">Buat Switching Stock Antar-Unit</h5>
                    <button type="button" @click="createModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
                </div>

                <div class="card-body p-3.5 fs-8 overflow-y-auto" style="flex: 1 1 auto;">
                    <!-- Bagian 1: Unit Tujuan (Peminta) -->
                    <div class="border rounded-2 p-3 bg-body-tertiary mb-2.5">
                        <div class="fw-bold text-danger text-uppercase fs-9 mb-2">1. Unit Tujuan (Peminta)</div>
                        <div class="row g-2.5">
                            <div class="col-md-6">
                                <label class="form-label fs-9 fw-semibold mb-1">Unit Tujuan (Peminta) <span class="text-danger">*</span></label>
                                <select name="destination_organization_id" x-model="createData.destination_organization_id" @change="onDestOrgChange('create')" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Unit Kerja / Cabang --</option>
                                    <template x-for="org in allOrgs" :key="org.id">
                                        <option :value="org.id" x-text="'[' + org.code + '] ' + org.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-9 fw-semibold mb-1">Gudang Tujuan <span class="text-danger">*</span></label>
                                <select name="destination_warehouse_id" x-model="createData.destination_warehouse_id" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Gudang Tujuan --</option>
                                    <template x-for="wh in getFilteredWarehouses(createData.destination_organization_id)" :key="wh.id">
                                        <option :value="wh.id" x-text="wh.name + ' (' + wh.code + ')'"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 2: Daftar Barang yang Dibutuhkan (Multi-Item) -->
                    <div class="border rounded-2 p-3 bg-body-tertiary mb-2.5">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-bold text-danger text-uppercase fs-9">
                                2. Daftar Barang yang Dibutuhkan (<span x-text="createData.items.length"></span> Barang)
                            </div>
                            <button type="button" @click="addItemRow('create')" class="btn btn-xs btn-outline-danger fw-bold py-1 px-2 fs-9 d-inline-flex align-items-center gap-1">
                                <i class="bi bi-plus-circle"></i> Tambah Barang
                            </button>
                        </div>

                        <div class="table-responsive border rounded bg-body mb-2" style="max-height: 220px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0 fs-8">
                                <thead class="table-light text-secondary fs-9 text-uppercase sticky-top">
                                    <tr>
                                        <th class="ps-3 py-1.5">Pilih Barang / Item</th>
                                        <th style="width: 140px;" class="text-center py-1.5">Jumlah (Qty)</th>
                                        <th style="width: 45px;" class="text-center py-1.5">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, idx) in createData.items" :key="idx">
                                        <tr>
                                            <td class="ps-3 py-1.5">
                                                <select :name="'items[' + idx + '][item_id]'" x-model="row.item_id" class="form-select form-select-sm" required>
                                                    <option value="">-- Pilih Barang / Item --</option>
                                                    <template x-for="it in allItems" :key="it.id">
                                                        <option :value="it.id" x-text="it.name + ' [' + it.sku + '] (' + it.uom + ')'"></option>
                                                    </template>
                                                </select>
                                            </td>
                                            <td class="text-center py-1.5">
                                                <input type="number" :name="'items[' + idx + '][qty_requested]'" x-model.number="row.qty_requested" min="1" class="form-control form-control-sm text-center font-monospace fw-bold" required>
                                            </td>
                                            <td class="text-center py-1.5">
                                                <button type="button" @click="removeItemRow(idx, 'create')" :disabled="createData.items.length <= 1" class="btn btn-sm text-danger py-0 px-1" title="Hapus baris barang">
                                                    <i class="bi bi-trash fs-8"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-secondary fs-9 d-flex align-items-center justify-content-between">
                            <span>Satu pengajuan dapat memuat beberapa barang sekaligus.</span>
                            <span class="fw-semibold">Total Kuantitas: <span class="font-monospace text-primary" x-text="createData.items.reduce((sum, r) => sum + (Number(r.qty_requested) || 0), 0)"></span> Unit</span>
                        </div>
                    </div>

                    <!-- Bagian 3: Tombol Khusus Sistem Rekomendasi Cabang Alternatif -->
                    <div class="border border-primary-subtle rounded-2 p-3 bg-primary-subtle shadow-none mb-2.5">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2">
                            <div>
                                <div class="fw-bold text-primary text-uppercase fs-9">Rekomendasi Sistem Otomatis</div>
                            </div>
                            <button type="button" @click="fetchSystemRecommendations()" :disabled="loadingRec" class="btn btn-xs btn-primary fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5 flex-shrink-0 py-1.5 px-3" style="font-size: 11.5px;">
                                <span x-show="loadingRec" class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width: 12px; height: 12px;"></span>
                                <span x-text="loadingRec ? 'Menganalisis...' : 'Cari Rekomendasi Cabang'"></span>
                            </button>
                        </div>

                        <!-- Error Alert Rekomendasi -->
                        <div x-show="recError" x-cloak class="alert alert-warning py-1.5 px-2.5 fs-9 mb-2" role="alert">
                            <span x-text="recError"></span>
                        </div>

                        <!-- Hasil Rekomendasi -->
                        <div x-show="recSearched" x-cloak class="mt-2">
                            <!-- Jika ditemukan Cabang dengan Stok Surplus -->
                            <template x-if="recommendations.length > 0">
                                <div class="bg-body rounded border p-2.5">
                                    <!-- View Mode Switcher -->
                                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2 pb-2 border-bottom">
                                        <div class="btn-group btn-group-sm p-0.5 bg-body border rounded">
                                            <button type="button" @click="recViewMode = 'branch'" class="btn btn-xs py-0.5 px-2 rounded fw-medium" :class="recViewMode === 'branch' ? 'btn-primary shadow-xs' : 'btn-light border-0 text-secondary'" style="font-size: 11px;">
                                                Per Cabang Terpadu (<span x-text="recommendations.length"></span>)
                                            </button>
                                            <button type="button" @click="recViewMode = 'item'" class="btn btn-xs py-0.5 px-2 rounded fw-medium" :class="recViewMode === 'item' ? 'btn-primary shadow-xs' : 'btn-light border-0 text-secondary'" style="font-size: 11px;">
                                                Per Masing-Masing (<span x-text="itemRecommendations.length"></span>)
                                            </button>
                                        </div>
                                        <span class="text-secondary" style="font-size: 11px;" x-show="recViewMode === 'branch'">Klik 'Gunakan Cabang Ini' untuk mengisi Unit Sumber</span>
                                    </div>

                                    <!-- Tampilan 1: Per Cabang Terpadu -->
                                    <div x-show="recViewMode === 'branch'">
                                        <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                                            <table class="table table-sm table-bordered table-hover mb-0 fs-9 align-middle">
                                                <thead class="table-light text-secondary text-uppercase fs-9 sticky-top">
                                                    <tr>
                                                        <th>Cabang & Gudang</th>
                                                        <th>Kota</th>
                                                        <th>Status Pemenuhan Seluruh Barang</th>
                                                        <th class="text-center" style="width: 120px;">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-for="(rec, idx) in recommendations" :key="idx">
                                                        <tr :class="{'table-success': createData.source_warehouse_id == rec.warehouse_id}">
                                                            <td>
                                                                <div class="fw-bold" x-text="rec.organization_name"></div>
                                                                <div class="text-secondary fs-9" x-text="'Gudang: ' + rec.warehouse_name"></div>
                                                            </td>
                                                            <td x-text="rec.city || '-'"></td>
                                                            <td>
                                                                <div class="mb-1">
                                                                    <template x-if="rec.is_all_covered">
                                                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle py-0.5 px-1.5 fw-bold" style="font-size: 10.5px;">
                                                                            Semua Barang Terpenuhi (100%)
                                                                        </span>
                                                                    </template>
                                                                    <template x-if="!rec.is_all_covered">
                                                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-0.5 px-1.5 fw-semibold" style="font-size: 10.5px;" x-text="rec.fully_covered_count + ' dari ' + rec.total_items_count + ' Barang Terpenuhi'">
                                                                        </span>
                                                                    </template>
                                                                </div>
                                                                <!-- Breakdown per barang -->
                                                                <div class="d-flex flex-column gap-1">
                                                                    <template x-for="itemDet in rec.items" :key="itemDet.item_id">
                                                                        <div class="d-flex align-items-center justify-content-between gap-2 px-1.5 py-0.5 rounded bg-body-tertiary" style="font-size: 11px;">
                                                                            <span class="fw-semibold text-truncate" style="max-width: 170px;" x-text="itemDet.item_name"></span>
                                                                            <span class="font-monospace" :class="itemDet.is_fully_covered ? 'text-success fw-bold' : (itemDet.is_partially_covered ? 'text-warning fw-bold' : 'text-danger')" x-text="'Surplus +' + itemDet.excess_stock + ' / Butuh ' + itemDet.qty_needed + ' ' + itemDet.uom"></span>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" @click="selectRecommendation(rec)" class="btn btn-xs btn-outline-success fw-bold py-1 px-2 fs-9">
                                                                    <span x-text="createData.source_warehouse_id == rec.warehouse_id ? '✓ Dipilih' : 'Gunakan Cabang Ini'"></span>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Tampilan 2: Per Masing-Masing Barang -->
                                    <div x-show="recViewMode === 'item'">
                                        <div class="d-flex flex-column gap-2" style="max-height: 250px; overflow-y: auto;">
                                            <template x-for="itemRec in itemRecommendations" :key="itemRec.item_id">
                                                <div class="border rounded p-2 bg-body-tertiary">
                                                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-1 mb-1.5">
                                                        <div>
                                                            <span class="fw-bold text-body" x-text="itemRec.item_name"></span>
                                                            <span class="text-secondary font-monospace ms-1" style="font-size: 11px;" x-text="'[' + itemRec.sku + ']'"></span>
                                                        </div>
                                                        <div>
                                                            <span class="badge bg-secondary-subtle text-secondary-emphasis" style="font-size: 10.5px;" x-text="'Kebutuhan: ' + itemRec.qty_needed + ' ' + itemRec.uom"></span>
                                                            <span class="badge bg-body text-secondary border ms-1" style="font-size: 10.5px;" x-text="'Safety: ' + itemRec.safety_stock + ' ' + itemRec.uom"></span>
                                                        </div>
                                                    </div>

                                                    <template x-if="itemRec.has_recommendation && itemRec.sources.length > 0">
                                                        <div class="table-responsive border rounded bg-body">
                                                            <table class="table table-sm table-hover align-middle mb-0 fs-9">
                                                                <thead class="table-light text-secondary text-uppercase fs-9">
                                                                    <tr>
                                                                        <th class="py-1">Gudang & Cabang</th>
                                                                        <th class="py-1">Kota</th>
                                                                        <th class="py-1">Tersedia</th>
                                                                        <th class="py-1">Surplus Stok</th>
                                                                        <th class="text-center py-1" style="width: 105px;">Aksi</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <template x-for="src in itemRec.sources" :key="src.warehouse_id">
                                                                        <tr :class="{'table-success': createData.source_warehouse_id == src.warehouse_id}">
                                                                            <td class="py-1">
                                                                                <div class="fw-bold text-body" x-text="src.warehouse_name"></div>
                                                                                <div class="text-secondary fs-9" x-text="src.organization_name"></div>
                                                                            </td>
                                                                            <td class="py-1" x-text="src.city || '-'"></td>
                                                                            <td class="font-monospace py-1" x-text="src.available_stock + ' ' + itemRec.uom"></td>
                                                                            <td class="py-1">
                                                                                <span class="font-monospace fw-bold text-success" x-text="'+' + src.excess_stock + ' ' + itemRec.uom"></span>
                                                                                <template x-if="src.is_fully_covered">
                                                                                    <span class="badge bg-success-subtle text-success-emphasis ms-1 py-0.5 px-1" style="font-size: 10px;">Cukup</span>
                                                                                </template>
                                                                                <template x-if="!src.is_fully_covered">
                                                                                    <span class="badge bg-warning-subtle text-warning-emphasis ms-1 py-0.5 px-1" style="font-size: 10px;">Sebagian</span>
                                                                                </template>
                                                                            </td>
                                                                            <td class="text-center py-1">
                                                                                <button type="button" @click="selectItemSource(src, itemRec)" class="btn btn-xs btn-outline-success fw-bold py-0.5 px-1.5 fs-9">
                                                                                    <span x-text="createData.source_warehouse_id == src.warehouse_id ? '✓ Dipilih' : 'Pilih Gudang'"></span>
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    </template>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </template>

                                                    <template x-if="!itemRec.has_recommendation || itemRec.sources.length === 0">
                                                        <div class="py-1 px-2 rounded bg-danger-subtle border border-danger-subtle text-danger-emphasis d-flex align-items-center justify-content-between" style="font-size: 11px;">
                                                            <span class="fw-semibold">Tidak ada rekomendasi untuk barang tersebut</span>
                                                            <span class="text-secondary" style="font-size: 10.5px;">(Stok seluruh cabang <= safety stock)</span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Jika TIDAK ditemukan Cabang yang Memiliki Stok Surplus untuk Seluruh Barang -->
                            <template x-if="recommendations.length === 0">
                                <div>
                                    <div class="alert alert-secondary py-1.5 px-2.5 mb-2" style="font-size: 11.5px;">
                                        Tidak ditemukan cabang dengan stok di atas <em>safety stock</em> untuk barang-barang yang dipilih. Anda dapat memilih Unit Sumber secara manual di bawah.
                                    </div>

                                    <!-- Rekomendasi Per Barang -->
                                    <div class="bg-body rounded border p-2.5">
                                        <div class="fw-bold text-secondary text-uppercase mb-2" style="font-size: 11px;">
                                            <span>Rekomendasi Ketersediaan Stok Per Barang:</span>
                                        </div>

                                        <div class="d-flex flex-column gap-2" style="max-height: 250px; overflow-y: auto;">
                                            <template x-for="itemRec in itemRecommendations" :key="itemRec.item_id">
                                                <div class="border rounded p-2 bg-body-tertiary">
                                                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-1 mb-1.5">
                                                        <div>
                                                            <span class="fw-bold text-body" x-text="itemRec.item_name"></span>
                                                            <span class="text-secondary font-monospace ms-1" style="font-size: 11px;" x-text="'[' + itemRec.sku + ']'"></span>
                                                        </div>
                                                        <div>
                                                            <span class="badge bg-secondary-subtle text-secondary-emphasis" style="font-size: 10.5px;" x-text="'Kebutuhan: ' + itemRec.qty_needed + ' ' + itemRec.uom"></span>
                                                            <span class="badge bg-body text-secondary border ms-1" style="font-size: 10.5px;" x-text="'Safety: ' + itemRec.safety_stock + ' ' + itemRec.uom"></span>
                                                        </div>
                                                    </div>

                                                    <template x-if="itemRec.has_recommendation && itemRec.sources.length > 0">
                                                        <div class="table-responsive border rounded bg-body">
                                                            <table class="table table-sm table-hover align-middle mb-0 fs-9">
                                                                <thead class="table-light text-secondary text-uppercase fs-9">
                                                                    <tr>
                                                                        <th class="py-1">Gudang & Cabang</th>
                                                                        <th class="py-1">Kota</th>
                                                                        <th class="py-1">Tersedia</th>
                                                                        <th class="py-1">Surplus Stok</th>
                                                                        <th class="text-center py-1" style="width: 105px;">Aksi</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <template x-for="src in itemRec.sources" :key="src.warehouse_id">
                                                                        <tr :class="{'table-success': createData.source_warehouse_id == src.warehouse_id}">
                                                                            <td class="py-1">
                                                                                <div class="fw-bold text-body" x-text="src.warehouse_name"></div>
                                                                                <div class="text-secondary fs-9" x-text="src.organization_name"></div>
                                                                            </td>
                                                                            <td class="py-1" x-text="src.city || '-'"></td>
                                                                            <td class="font-monospace py-1" x-text="src.available_stock + ' ' + itemRec.uom"></td>
                                                                            <td class="py-1">
                                                                                <span class="font-monospace fw-bold text-success" x-text="'+' + src.excess_stock + ' ' + itemRec.uom"></span>
                                                                                <template x-if="src.is_fully_covered">
                                                                                    <span class="badge bg-success-subtle text-success-emphasis ms-1 py-0.5 px-1" style="font-size: 10px;">Cukup</span>
                                                                                </template>
                                                                                <template x-if="!src.is_fully_covered">
                                                                                    <span class="badge bg-warning-subtle text-warning-emphasis ms-1 py-0.5 px-1" style="font-size: 10px;">Sebagian</span>
                                                                                </template>
                                                                            </td>
                                                                            <td class="text-center py-1">
                                                                                <button type="button" @click="selectItemSource(src, itemRec)" class="btn btn-xs btn-outline-success fw-bold py-0.5 px-1.5 fs-9">
                                                                                    <span x-text="createData.source_warehouse_id == src.warehouse_id ? '✓ Dipilih' : 'Pilih Gudang'"></span>
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    </template>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </template>

                                                    <template x-if="!itemRec.has_recommendation || itemRec.sources.length === 0">
                                                        <div class="py-1 px-2 rounded bg-danger-subtle border border-danger-subtle text-danger-emphasis d-flex align-items-center justify-content-between" style="font-size: 11px;">
                                                            <span class="fw-semibold">Tidak ada rekomendasi untuk barang tersebut</span>
                                                            <span class="text-secondary" style="font-size: 10.5px;">(Stok seluruh cabang <= safety stock)</span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Bagian 4: Unit Sumber & Gudang Penyedia (Manual Selection) -->
                    <div class="border rounded-2 p-3 bg-body-tertiary mb-0">
                        <div class="fw-bold text-secondary text-uppercase fs-9 mb-2">3. Unit Sumber (Penyedia Stok)</div>
                        <div class="row g-2.5">
                            <div class="col-md-6">
                                <label class="form-label fs-9 fw-semibold mb-1">Unit Sumber (Penyedia) <span class="text-danger">*</span></label>
                                <select name="source_organization_id" x-model="createData.source_organization_id" @change="onSourceOrgChange('create')" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Unit Kerja Penyedia --</option>
                                    <template x-for="org in allOrgs" :key="org.id">
                                        <option :value="org.id" x-text="'[' + org.code + '] ' + org.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fs-9 fw-semibold mb-1">Gudang Sumber <span class="text-danger">*</span></label>
                                <select name="source_warehouse_id" x-model="createData.source_warehouse_id" class="form-select form-select-sm" required>
                                    <option value="">-- Pilih Gudang Sumber --</option>
                                    <template x-for="wh in getFilteredWarehouses(createData.source_organization_id)" :key="wh.id">
                                        <option :value="wh.id" x-text="wh.name + ' (' + wh.code + ')'"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fs-9 fw-semibold mb-1">Alasan / Catatan Pengajuan</label>
                                <textarea name="recommendation_reason" x-model="createData.recommendation_reason" rows="2" class="form-control form-control-sm" placeholder="Catatan pengajuan kebutuhan atau alasan switching stock..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary py-2.5 px-4 d-flex justify-content-end gap-2 flex-shrink-0">
                    <button type="button" @click="createModalOpen = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold shadow-xs">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: VIEW DETAIL SWITCHING STOCK                      -->
    <!-- ======================================================== -->
    <div x-show="viewModalOpen"
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         x-cloak
         style="display: none; z-index: 1050;">
        <div @click.away="viewModalOpen = false"
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden"
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color); max-height: 90vh; display: flex; flex-direction: column;">
            <div class="card-header bg-dark text-white py-2.5 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                <h5 class="modal-title fs-6 fw-bold mb-0">Rincian Switching Stock</h5>
                <button type="button" @click="viewModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="card-body p-4 fs-8 overflow-y-auto" style="flex: 1 1 auto;">
                <template x-if="selectedSwitching">
                    <div>
                        <!-- Header Info -->
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                            <div>
                                <div class="fs-9 text-secondary">Nomor Referensi:</div>
                                <div class="fs-6 fw-bold font-monospace text-danger" x-text="selectedSwitching.order ? selectedSwitching.order.order_number : ('MANUAL-' + selectedSwitching.id)"></div>
                            </div>
                            <div class="text-end">
                                <span class="badge fs-8 py-1 px-2.5"
                                    :class="{
                                        'bg-success-subtle text-success-emphasis': selectedSwitching.status === 'APPROVED' || selectedSwitching.status === 'COMPLETED',
                                        'bg-warning-subtle text-warning-emphasis': selectedSwitching.status === 'PROPOSED',
                                        'bg-danger-subtle text-danger-emphasis': selectedSwitching.status === 'REJECTED' || selectedSwitching.status === 'CANCELLED',
                                        'bg-secondary-subtle text-secondary-emphasis': selectedSwitching.status !== 'APPROVED' && selectedSwitching.status !== 'COMPLETED' && selectedSwitching.status !== 'PROPOSED' && selectedSwitching.status !== 'REJECTED' && selectedSwitching.status !== 'CANCELLED'
                                    }"
                                    x-text="selectedSwitching.status">
                                </span>
                                <div class="fs-9 text-secondary mt-1" x-text="selectedSwitching.created_at"></div>
                            </div>
                        </div>

                        <!-- Detail Barang -->
                        <div class="card bg-body-tertiary border shadow-none mb-3">
                            <div class="card-body p-3">
                                <div class="fw-bold text-secondary text-uppercase fs-9 mb-2 d-flex align-items-center justify-content-between">
                                    <span>Daftar Barang yang Diajukan</span>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis" x-text="(selectedSwitching.items && selectedSwitching.items.length > 0 ? selectedSwitching.items.length : 1) + ' Jenis Barang'"></span>
                                </div>

                                <template x-if="selectedSwitching.items && selectedSwitching.items.length > 0">
                                    <div class="table-responsive border rounded bg-body">
                                        <table class="table table-sm table-hover align-middle mb-0 fs-8">
                                            <thead class="table-light text-secondary fs-9 text-uppercase">
                                                <tr>
                                                    <th class="ps-3" style="width: 40px;">No</th>
                                                    <th>Barang & SKU</th>
                                                    <th>Satuan</th>
                                                    <th class="text-end pe-3" style="width: 120px;">Jumlah (Qty)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(row, idx) in selectedSwitching.items" :key="idx">
                                                    <tr>
                                                        <td class="ps-3 font-monospace text-secondary" x-text="idx + 1"></td>
                                                        <td>
                                                            <div class="fw-bold text-body" x-text="row.item ? row.item.name : '-'"></div>
                                                            <div class="fs-9 text-secondary font-monospace" x-text="row.item ? row.item.sku : '-'"></div>
                                                        </td>
                                                        <td class="text-secondary" x-text="row.item ? row.item.uom : '-'"></td>
                                                        <td class="text-end pe-3 font-monospace fw-bold text-primary" x-text="row.qty_requested"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                            <tfoot class="table-light fs-8 fw-bold">
                                                <tr>
                                                    <td colspan="3" class="ps-3 text-secondary text-uppercase fs-9">Total Keseluruhan</td>
                                                    <td class="text-end pe-3 font-monospace text-primary" x-text="selectedSwitching.total_qty || selectedSwitching.qty_requested"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </template>

                                <template x-if="!selectedSwitching.items || selectedSwitching.items.length === 0">
                                    <div class="row g-2">
                                        <div class="col-sm-6">
                                            <span class="text-secondary">Nama Barang:</span>
                                            <div class="fw-bold text-body" x-text="selectedSwitching.item ? selectedSwitching.item.name : '-'"></div>
                                        </div>
                                        <div class="col-sm-3">
                                            <span class="text-secondary">SKU / Kode:</span>
                                            <div class="font-monospace fw-bold" x-text="selectedSwitching.item ? selectedSwitching.item.sku : '-'"></div>
                                        </div>
                                        <div class="col-sm-3">
                                            <span class="text-secondary">Jumlah (Qty):</span>
                                            <div class="font-monospace fw-bold text-primary fs-7" x-text="selectedSwitching.qty_requested + ' ' + (selectedSwitching.item ? selectedSwitching.item.uom : '')"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Perbandingan Unit Sumber vs Unit Tujuan -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-secondary-subtle">
                                    <div class="card-header bg-secondary-subtle py-1.5 px-3 fw-bold fs-9 text-secondary-emphasis">
                                        Unit Sumber (Penyedia Stok)
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="fw-bold text-body fs-7" x-text="selectedSwitching.source_organization ? selectedSwitching.source_organization.name : '-'"></div>
                                        <div class="text-secondary fs-9 mt-1">Gudang: <span class="fw-semibold" x-text="selectedSwitching.source_warehouse ? selectedSwitching.source_warehouse.name : '-'"></span></div>
                                        <div class="text-secondary fs-9">Kota: <span x-text="selectedSwitching.source_organization ? selectedSwitching.source_organization.city : '-'"></span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-danger-subtle">
                                    <div class="card-header bg-danger-subtle py-1.5 px-3 fw-bold fs-9 text-danger-emphasis">
                                        Unit Tujuan (Peminta)
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="fw-bold text-body fs-7" x-text="selectedSwitching.destination_organization ? selectedSwitching.destination_organization.name : '-'"></div>
                                        <div class="text-secondary fs-9 mt-1">Gudang: <span class="fw-semibold" x-text="selectedSwitching.destination_warehouse ? selectedSwitching.destination_warehouse.name : '-'"></span></div>
                                        <div class="text-secondary fs-9">Kota: <span x-text="selectedSwitching.destination_organization ? selectedSwitching.destination_organization.city : '-'"></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alasan & Audit Info -->
                        <div class="card bg-body-tertiary border shadow-none mb-3">
                            <div class="card-body p-3">
                                <div class="mb-2">
                                    <span class="text-secondary fs-9 fw-bold text-uppercase">Catatan / Alasan Rekomendasi:</span>
                                    <div class="mt-1 fst-italic text-body" x-text="selectedSwitching.recommendation_reason || 'Tidak ada catatan tambahan.'"></div>
                                </div>
                                <div class="row g-2 border-top pt-2 mt-2 fs-9 text-secondary">
                                    <div class="col-sm-6">
                                        Diajukan Oleh: <span class="fw-bold text-body" x-text="selectedSwitching.proposer ? selectedSwitching.proposer.name : '-'"></span>
                                    </div>
                                    <div class="col-sm-6">
                                        Disetujui Oleh: <span class="fw-bold text-body" x-text="selectedSwitching.approver ? selectedSwitching.approver.name : 'Menunggu Approval'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="card-footer bg-body-tertiary py-2 px-4 flex-shrink-0 text-end">
                <button type="button" @click="viewModalOpen = false" class="btn btn-sm btn-secondary">Tutup</button>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 3: EDIT SWITCHING STOCK (PROPOSED ONLY)            -->
    <!-- ======================================================== -->
    <div x-show="editModalOpen"
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         x-cloak
         style="display: none; z-index: 1050;">
        <div @click.away="editModalOpen = false"
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden"
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color); max-height: 90vh; display: flex; flex-direction: column;">
            <form :action="editActionUrl" method="POST" class="d-flex flex-column h-100 m-0 overflow-hidden">
                @csrf
                @method('PUT')
                <div class="card-header bg-primary text-white py-2.5 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                    <h5 class="modal-title fs-6 fw-bold mb-0">Edit Pengajuan Switching Stock</h5>
                    <button type="button" @click="editModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
                </div>

                <div class="card-body p-4 fs-8 overflow-y-auto" style="flex: 1 1 auto;">
                    <div class="row g-3">
                        <!-- Unit Tujuan -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit Tujuan (Peminta) <span class="text-danger">*</span></label>
                            <select name="destination_organization_id" x-model="editData.destination_organization_id" @change="onDestOrgChange('edit')" class="form-select form-select-sm" required>
                                <template x-for="org in allOrgs" :key="org.id">
                                    <option :value="org.id" x-text="'[' + org.code + '] ' + org.name"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Gudang Tujuan -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gudang Tujuan <span class="text-danger">*</span></label>
                            <select name="destination_warehouse_id" x-model="editData.destination_warehouse_id" class="form-select form-select-sm" required>
                                <template x-for="wh in getFilteredWarehouses(editData.destination_organization_id)" :key="wh.id">
                                    <option :value="wh.id" x-text="wh.name + ' (' + wh.code + ')'"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Daftar Barang yang Diajukan (Multi-Item) -->
                        <div class="col-12">
                            <div class="card bg-body-tertiary border shadow-none mb-0">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="fw-bold text-primary text-uppercase fs-9">
                                            Daftar Barang yang Diajukan (<span x-text="editData.items ? editData.items.length : 0"></span> Barang)
                                        </div>
                                        <button type="button" @click="addItemRow('edit')" class="btn btn-xs btn-outline-primary fw-bold py-1 px-2 fs-9 d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-plus-circle"></i> Tambah Barang
                                        </button>
                                    </div>

                                    <div class="table-responsive border rounded bg-body mb-2">
                                        <table class="table table-sm table-hover align-middle mb-0 fs-8">
                                            <thead class="table-light text-secondary fs-9 text-uppercase">
                                                <tr>
                                                    <th class="ps-3">Pilih Barang / Item</th>
                                                    <th style="width: 140px;" class="text-center">Jumlah (Qty)</th>
                                                    <th style="width: 45px;" class="text-center">Hapus</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(row, idx) in editData.items" :key="idx">
                                                    <tr>
                                                        <td class="ps-3 py-2">
                                                            <select :name="'items[' + idx + '][item_id]'" x-model="row.item_id" class="form-select form-select-sm" required>
                                                                <option value="">-- Pilih Barang / Item --</option>
                                                                <template x-for="it in allItems" :key="it.id">
                                                                    <option :value="it.id" x-text="it.name + ' [' + it.sku + '] (' + it.uom + ')'"></option>
                                                                </template>
                                                            </select>
                                                        </td>
                                                        <td class="text-center py-2">
                                                            <input type="number" :name="'items[' + idx + '][qty_requested]'" x-model.number="row.qty_requested" min="1" class="form-control form-control-sm text-center font-monospace fw-bold" required>
                                                        </td>
                                                        <td class="text-center py-2">
                                                            <button type="button" @click="removeItemRow(idx, 'edit')" :disabled="editData.items && editData.items.length <= 1" class="btn btn-sm text-danger py-0 px-1" title="Hapus baris barang">
                                                                <i class="bi bi-trash fs-8"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-secondary fs-9 d-flex align-items-center justify-content-between">
                                        <span>Perubahan item akan memperbarui daftar barang pada pengajuan switching ini.</span>
                                        <span class="fw-semibold">Total Kuantitas: <span class="font-monospace text-primary" x-text="editData.items ? editData.items.reduce((sum, r) => sum + (Number(r.qty_requested) || 0), 0) : 0"></span> Unit</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Unit Sumber -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit Sumber (Penyedia) <span class="text-danger">*</span></label>
                            <select name="source_organization_id" x-model="editData.source_organization_id" @change="onSourceOrgChange('edit')" class="form-select form-select-sm" required>
                                <template x-for="org in allOrgs" :key="org.id">
                                    <option :value="org.id" x-text="'[' + org.code + '] ' + org.name"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Gudang Sumber -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gudang Sumber <span class="text-danger">*</span></label>
                            <select name="source_warehouse_id" x-model="editData.source_warehouse_id" class="form-select form-select-sm" required>
                                <template x-for="wh in getFilteredWarehouses(editData.source_organization_id)" :key="wh.id">
                                    <option :value="wh.id" x-text="wh.name + ' (' + wh.code + ')'"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Alasan / Catatan -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Alasan / Catatan Pengajuan</label>
                            <textarea name="recommendation_reason" x-model="editData.recommendation_reason" rows="2" class="form-control form-control-sm"></textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary py-2.5 px-4 d-flex justify-content-between flex-shrink-0">
                    <button type="button" @click="editModalOpen = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold shadow-xs">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 4: DELETE CONFIRMATION                              -->
    <!-- ======================================================== -->
    <div x-show="deleteModalOpen"
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         x-cloak
         style="display: none; z-index: 1050;">
        <div @click.away="deleteModalOpen = false"
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden"
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <form :action="deleteActionUrl" method="POST" class="m-0">
                @csrf
                @method('DELETE')
                <div class="card-header bg-danger text-white py-2.5 px-4 d-flex align-items-center justify-content-between">
                    <h5 class="modal-title fs-6 fw-bold mb-0">Konfirmasi Hapus Switching Stock</h5>
                    <button type="button" @click="deleteModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 fs-8">
                    <p class="mb-3 text-body">
                        Apakah Anda yakin ingin menghapus pengajuan switching stock berikut? Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <div class="card bg-body-tertiary border p-3">
                        <div class="row g-2">
                            <div class="col-4 text-secondary">No. Referensi:</div>
                            <div class="col-8 fw-bold font-monospace text-body" x-text="deleteData.ref"></div>

                            <div class="col-4 text-secondary">Barang:</div>
                            <div class="col-8 fw-bold text-body" x-text="deleteData.item_name"></div>

                            <div class="col-4 text-secondary">Jumlah:</div>
                            <div class="col-8 font-monospace fw-bold text-primary" x-text="deleteData.qty"></div>

                            <div class="col-4 text-secondary">Dari (Sumber):</div>
                            <div class="col-8 text-body" x-text="deleteData.source"></div>

                            <div class="col-4 text-secondary">Ke (Tujuan):</div>
                            <div class="col-8 text-body" x-text="deleteData.dest"></div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary py-2.5 px-4 d-flex justify-content-between">
                    <button type="button" @click="deleteModalOpen = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

