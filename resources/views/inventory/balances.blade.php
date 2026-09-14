@extends('layouts.app')
@section('title', 'Stock Balances & Lokasi Gudang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Persediaan</li>
    <li class="breadcrumb-item active" aria-current="page">Stock Balances</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    warehouseDropdownOpen: false,
    warehouseSearch: '',
    viewModal: false,
    selectedBalance: {
        id: null,
        item_id: null,
        item_name: '',
        item_sku: '',
        item_uom: '',
        category_name: '',
        warehouse_id: null,
        warehouse_name: '',
        warehouse_code: '',
        organization_name: '',
        on_hand: 0,
        reserved: 0,
        damaged: 0,
        hold: 0,
        allocated: 0,
        in_transit: 0,
        available: 0,
        unit_price: 0,
        total_valuation: 0,
        stock_card_url: '#'
    },
    warehousesList: {{ Js::from($warehouses->map(fn($w) => [
        'id' => $w->id,
        'code' => $w->code,
        'name' => $w->name,
        'type' => $w->type,
        'org_name' => $w->organization->name,
        'org_code' => $w->organization->code,
        'city' => $w->organization->city,
    ])) }},
    get filteredWarehouses() {
        if (!this.warehouseSearch.trim()) return this.warehousesList;
        const q = this.warehouseSearch.toLowerCase();
        return this.warehousesList.filter(w => 
            w.name.toLowerCase().includes(q) || 
            w.code.toLowerCase().includes(q) || 
            w.org_name.toLowerCase().includes(q) ||
            w.city.toLowerCase().includes(q)
        );
    },
    openViewModal(data) {
        this.selectedBalance = data;
        this.viewModal = true;
    }
}">

    <!-- Warehouse Selector Header Bar (Compact & Searchable - Solves Infinite Scroll with 12+ Warehouses) -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Lokasi Gudang / Unit Terpilih:</div>
                    <div class="text-sm font-black text-slate-900 flex items-center space-x-2">
                        <span>
                            @if($selectedWarehouseId === 'all')
                                Seluruh Gudang & Cabang (Konsolidasi Total Bank Jatim)
                            @else
                                {{ $currentWarehouse?->name ?? 'Gudang Utama' }}
                            @endif
                        </span>
                        @if($currentWarehouse)
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $currentWarehouse->type === 'CENTRAL_LOGISTICS' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $currentWarehouse->type === 'CENTRAL_LOGISTICS' ? 'Gudang Logistik Pusat' : 'Penyimpanan Cabang' }}
                            </span>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                                {{ $warehouses->count() }} Lokasi Tergabung
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Searchable Dropdown Button -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="relative" @click.away="warehouseDropdownOpen = false">
                    <button @click="warehouseDropdownOpen = !warehouseDropdownOpen" type="button" class="w-full md:w-auto inline-flex items-center justify-between space-x-3 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-sm">
                        <div class="flex items-center space-x-2">
                            <i class="fa-solid fa-building-circle-check text-slate-500"></i>
                            <span>Ganti Lokasi Gudang</span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition" :class="warehouseDropdownOpen && 'rotate-180'"></i>
                    </button>

                    <!-- Searchable Dropdown Popup -->
                    <div x-show="warehouseDropdownOpen" x-cloak class="absolute right-0 mt-2 w-72 sm:w-96 max-w-[calc(100vw-2rem)] bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden">
                        <!-- Search Input in Dropdown -->
                        <div class="p-3 border-b border-slate-100 bg-slate-50">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                                <input type="text" x-model="warehouseSearch" class="w-full bg-white border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-jatim-700 focus:outline-none">
                            </div>
                        </div>

                        <!-- Warehouse List Options -->
                        <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                            <!-- Option All Warehouses -->
                            <a href="{{ route('inventory.balances', ['warehouse_id' => 'all']) }}" class="flex items-center justify-between p-3 hover:bg-amber-50/60 transition {{ $selectedWarehouseId === 'all' ? 'bg-amber-50 font-bold text-brand-600' : 'text-slate-700' }}">
                                <div class="flex items-center space-x-2.5">
                                    <i class="fa-solid fa-network-wired text-xs {{ $selectedWarehouseId === 'all' ? 'text-brand-500' : 'text-slate-400' }}"></i>
                                    <div>
                                        <div class="text-xs">Konsolidasi Seluruh Gudang</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Agregasi saldo total se-Bank Jatim</div>
                                    </div>
                                </div>
                                @if($selectedWarehouseId === 'all')
                                    <i class="fa-solid fa-check text-xs text-brand-600"></i>
                                @endif
                            </a>

                            <!-- Individual Warehouses List Filtered by Search -->
                            <template x-for="wh in filteredWarehouses" :key="wh.id">
                                <a :href="'{{ route('inventory.balances') }}?warehouse_id=' + wh.id" class="flex items-center justify-between p-3 hover:bg-amber-50/60 transition" :class="wh.id == '{{ $selectedWarehouseId }}' ? 'bg-amber-50 font-bold text-brand-600' : 'text-slate-700'">
                                    <div class="flex items-center space-x-2.5">
                                        <i class="fa-solid fa-warehouse text-xs" :class="wh.type === 'CENTRAL_LOGISTICS' ? 'text-amber-600' : 'text-slate-400'"></i>
                                        <div>
                                            <div class="text-xs" x-text="wh.name"></div>
                                            <div class="text-[10px] text-slate-400 font-normal" x-text="wh.code + ' • ' + wh.city"></div>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded" :class="wh.type === 'CENTRAL_LOGISTICS' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'" x-text="wh.type === 'CENTRAL_LOGISTICS' ? 'PUSAT' : 'CABANG'"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Metric Info-Boxes -->
    <div class="row g-3">
        <!-- 1. Total SKU -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">SKU Terdaftar</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($totalSkuCount) }}</span>
                    <span class="fs-9 text-secondary">Jenis item persediaan</span>
                </div>
            </div>
        </div>

        <!-- 2. Fisik On Hand -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-box-seam"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Saldo On Hand</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($totalOnHand) }} <span class="fs-8 text-secondary fw-normal">Unit</span></span>
                    <span class="fs-9 text-warning-emphasis">Reserved: {{ number_format($totalReserved) }} • Rusak: {{ number_format($totalDamaged) }}</span>
                </div>
            </div>
        </div>

        <!-- 3. Stok Bebas (Available) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Stok Bebas (Available)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($totalAvailable) }} <span class="fs-8 text-success-emphasis fw-normal">Unit</span></span>
                    <span class="fs-9 text-success-emphasis">Siap dialokasikan ke cabang</span>
                </div>
            </div>
        </div>

        <!-- 4. Total Valuasi -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Valuasi Persediaan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">Rp {{ number_format($totalValuation, 0, ',', '.') }}</span>
                    <span class="fs-9 text-secondary">Nilai perolehan rata-rata</span>
                </div>
            </div>
        </div>
    </div>

    @if(isset($pendingAdjustments) && $pendingAdjustments->isNotEmpty())
        <!-- Antrean Persetujuan Penyesuaian Stok (Maker-Checker) -->
        <div class="card border border-warning shadow-sm rounded-3 overflow-hidden mb-4">
            <div class="card-header bg-warning-subtle text-warning-emphasis py-2.5 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-exclamation fs-5 text-warning"></i>
                    <h6 class="fw-bold mb-0">Antrean Persetujuan Koreksi Stok (Maker-Checker)</h6>
                    <span class="badge bg-warning text-dark font-monospace">{{ $pendingAdjustments->where('status', 'PENDING_APPROVAL')->count() }} Menunggu Review</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 fs-8">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">No. Pengajuan</th>
                            <th>Item & SKU</th>
                            <th>Gudang / Lokasi</th>
                            <th class="text-center">Sebelum</th>
                            <th class="text-center">Koreksi</th>
                            <th class="text-center">Sesudah</th>
                            <th>Alasan / Catatan</th>
                            <th>Diajukan Oleh (Maker)</th>
                            <th class="pe-4 text-center">Tindakan Checker</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingAdjustments as $adj)
                            <tr>
                                <td class="ps-4 font-monospace fw-bold text-danger">{{ $adj->adjustment_number }}</td>
                                <td>
                                    <div class="fw-bold text-body">{{ $adj->item->name }}</div>
                                    <small class="text-muted font-monospace">{{ $adj->item->sku }}</small>
                                </td>
                                <td>{{ $adj->warehouse->name }}</td>
                                <td class="text-center font-monospace">{{ number_format($adj->qty_before) }}</td>
                                <td class="text-center font-monospace fw-bold {{ $adj->qty_diff > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $adj->qty_diff > 0 ? '+'.$adj->qty_diff : $adj->qty_diff }} {{ $adj->item->uom }}
                                </td>
                                <td class="text-center font-monospace fw-bold">{{ number_format($adj->qty_after) }}</td>
                                <td class="text-wrap" style="max-width: 250px;">{{ $adj->notes }}</td>
                                <td>{{ $adj->creator->name }} <br><small class="text-muted">{{ $adj->created_at->diffForHumans() }}</small></td>
                                <td class="pe-4 text-center">
                                    @if($adj->status === 'PENDING_APPROVAL')
                                        <div class="d-inline-flex gap-1">
                                            <form action="{{ route('inventory.adjustments.approve', $adj->id) }}" method="POST" onsubmit="return confirm('Setujui penyesuaian stok ini? Saldo fisik akan langsung diperbarui.')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success fw-bold px-2 py-1" style="font-size: 11px;">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                            <form action="{{ route('inventory.adjustments.reject', $adj->id) }}" method="POST" onsubmit="var r = prompt('Masukkan alasan penolakan koreksi stok:'); if(!r) return false; this.reason.value = r; return true;">
                                                @csrf
                                                <input type="hidden" name="reason" value="">
                                                <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1" style="font-size: 11px;">
                                                    <i class="bi bi-x-lg"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="badge {{ $adj->status === 'APPROVED' ? 'bg-success' : 'bg-danger' }}">{{ $adj->status }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Table Container Card (seperti master/items) -->
    <div class="card shadow-sm border-0 rounded-3">
        <!-- Filter & Search Toolbar (seperti master/items) -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('inventory.balances') }}" method="GET">
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Kategori Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-tag"></i></span>
                            <select name="category_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Kategori</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (string)$categoryId === (string)$cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Status Stok Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-filter"></i></span>
                            <select name="stock_filter" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Saldo</option>
                                <option value="available" {{ $stockFilter === 'available' ? 'selected' : '' }}>Hanya Tersedia (>0)</option>
                                <option value="damaged" {{ $stockFilter === 'damaged' ? 'selected' : '' }}>Ada Rusak / Damaged</option>
                                <option value="reserved" {{ $stockFilter === 'reserved' ? 'selected' : '' }}>Ada Reserved Order</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($categoryId && $categoryId !== 'ALL') || $stockFilter)
                        <div class="col-auto">
                            <a href="{{ route('inventory.balances', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                   placeholder="Cari SKU atau nama item...">
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
                            <th class="ps-4 py-3">Item & SKU</th>
                            <th class="py-3">Kategori</th>
                            @if($selectedWarehouseId === 'all')
                                <th class="py-3">Lokasi Gudang</th>
                            @endif
                            <th class="py-3 text-center">On Hand</th>
                            <th class="py-3 text-center">Reserved</th>
                            <th class="py-3 text-center">Damaged</th>
                            <th class="py-3 text-center">Available</th>
                            <th class="py-3 text-center">Status / Pagu Min</th>
                            <th class="py-3 text-end">Harga Satuan</th>
                            <th class="py-3 text-end">Total Valuasi</th>
                            <th class="py-3 pe-4 text-center" style="width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($balances as $sb)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-body">{{ $sb->item->name }}</div>
                                    <div class="text-secondary fs-8 font-monospace">{{ $sb->item->sku }} • {{ $sb->item->uom }}</div>
                                </td>
                                <td class="py-3">
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis fs-9">
                                        {{ $sb->item->category->name ?? '-' }}
                                    </span>
                                </td>
                                @if($selectedWarehouseId === 'all')
                                    <td class="py-3">
                                        <div class="fw-semibold text-body fs-8">{{ $sb->warehouse->name ?? '-' }}</div>
                                        <div class="text-secondary fs-9 font-monospace">{{ $sb->warehouse->organization->code ?? '-' }}</div>
                                    </td>
                                @endif
                                <td class="py-3 text-center font-monospace fw-bold text-body">
                                    {{ number_format($sb->on_hand) }}
                                </td>
                                <td class="py-3 text-center font-monospace fw-bold text-warning-emphasis">
                                    {{ number_format($sb->reserved) }}
                                </td>
                                <td class="py-3 text-center font-monospace fw-bold text-danger">
                                    {{ number_format($sb->damaged) }}
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge {{ $sb->available > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' }} font-monospace fs-8 px-2 py-1">
                                        {{ number_format($sb->available) }} {{ $sb->item->uom }}
                                    </span>
                                </td>
                                <td class="py-3 text-center">
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        @if($sb->stock_status === 'CRITICAL_LOW')
                                            <span class="badge bg-danger fs-9"><i class="bi bi-exclamation-octagon me-1"></i>KRITIS (≤ {{ $sb->effective_min_stock }})</span>
                                        @elseif($sb->stock_status === 'WARNING')
                                            <span class="badge bg-warning text-dark fs-9"><i class="bi bi-exclamation-triangle me-1"></i>MENDEKATI MIN</span>
                                        @else
                                            <span class="badge bg-success bg-opacity-10 text-success border fs-9">AMAN</span>
                                        @endif
                                        <button type="button" class="btn btn-link p-0 text-secondary fs-9 text-decoration-none" data-bs-toggle="modal" data-bs-target="#limitModal{{ $sb->id }}" title="Atur Pagu Gudang Ini">
                                            <i class="bi bi-sliders me-1"></i>Pagu Min: {{ $sb->effective_min_stock }}
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3 text-end font-monospace text-secondary fs-8">
                                    Rp {{ number_format($sb->item->estimated_unit_price, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end font-monospace fw-bold text-body fs-8">
                                    Rp {{ number_format($sb->on_hand * $sb->item->estimated_unit_price, 0, ',', '.') }}
                                </td>
                                <td class="py-3 pe-4 text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <!-- View Button (Bentuk Icon) -->
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from([
                                                    'id' => $sb->id,
                                                    'item_id' => $sb->item_id,
                                                    'item_name' => $sb->item->name,
                                                    'item_sku' => $sb->item->sku,
                                                    'item_uom' => $sb->item->uom,
                                                    'category_name' => $sb->item->category?->name ?? '-',
                                                    'warehouse_id' => $sb->warehouse_id,
                                                    'warehouse_name' => $sb->warehouse?->name ?? '-',
                                                    'warehouse_code' => $sb->warehouse?->code ?? '-',
                                                    'organization_name' => $sb->warehouse?->organization?->name ?? '-',
                                                    'on_hand' => $sb->on_hand,
                                                    'reserved' => $sb->reserved,
                                                    'damaged' => $sb->damaged,
                                                    'hold' => $sb->hold,
                                                    'allocated' => $sb->allocated,
                                                    'in_transit' => $sb->in_transit,
                                                    'available' => $sb->available,
                                                    'unit_price' => $sb->item->estimated_unit_price,
                                                    'total_valuation' => $sb->on_hand * $sb->item->estimated_unit_price,
                                                    'stock_card_url' => route('inventory.stock_card', ['itemId' => $sb->item_id, 'warehouse_id' => $sb->warehouse_id]),
                                                ]) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Saldo">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Kartu Stok Button (Bentuk Icon) -->
                                        <a href="{{ route('inventory.stock_card', ['itemId' => $sb->item_id, 'warehouse_id' => $sb->warehouse_id]) }}" 
                                           class="btn-action-icon text-primary" 
                                           title="Lihat Kartu Stok">
                                            <i class="bi bi-card-list"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $selectedWarehouseId === 'all' ? 10 : 9 }}" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <i class="bi bi-inbox text-secondary fs-1 mb-2"></i>
                                        <span class="text-secondary fw-medium">Belum ada saldo barang pada kriteria yang dipilih</span>
                                        @if($search || ($categoryId && $categoryId !== 'ALL') || $stockFilter)
                                            <a href="{{ route('inventory.balances', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-danger mt-3">
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
        <x-pagination-footer :paginator="$balances" :perPage="$perPage" />
    </div>

    <!-- ==================== VIEW DETAIL MODAL ==================== -->
    <div x-show="viewModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="viewModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger text-white py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-boxes fs-5"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Rincian Saldo Barang</h5>
                </div>
                <button type="button" @click="viewModal = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 fs-8">
                <!-- Info Header Barang -->
                <div class="p-3 bg-body-tertiary rounded-3 border mb-3">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h6 class="fw-bold mb-1 text-body" x-text="selectedBalance.item_name"></h6>
                            <div class="text-secondary font-monospace fs-9">
                                <span x-text="selectedBalance.item_sku"></span> • 
                                <span x-text="selectedBalance.category_name"></span> • 
                                Satuan: <span x-text="selectedBalance.item_uom"></span>
                            </div>
                        </div>
                        <span class="badge bg-danger-subtle text-danger font-monospace" x-text="'Saldo ID: ' + selectedBalance.id"></span>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- Lokasi Gudang -->
                    <div class="col-12">
                        <div class="card bg-body-tertiary border p-3">
                            <div class="fw-bold text-secondary text-uppercase fs-9 mb-2">Lokasi Penyimpanan Gudang</div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <span class="text-secondary">Gudang:</span>
                                    <div class="fw-bold text-body" x-text="selectedBalance.warehouse_name + ' (' + selectedBalance.warehouse_code + ')'"></div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-secondary">Unit Kerja:</span>
                                    <div class="fw-bold text-body" x-text="selectedBalance.organization_name"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Saldo Fisik & Status -->
                    <div class="col-12">
                        <div class="card bg-body border p-3">
                            <div class="fw-bold text-secondary text-uppercase fs-9 mb-2">Rincian Fisik & Komitmen Saldo</div>
                            <div class="row g-2 text-center">
                                <div class="col-6 col-sm-3">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <div class="text-secondary fs-9">On Hand (Fisik)</div>
                                        <div class="fs-6 fw-bold font-monospace text-body" x-text="selectedBalance.on_hand"></div>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <div class="text-secondary fs-9">Available (Bebas)</div>
                                        <div class="fs-6 fw-bold font-monospace text-success" x-text="selectedBalance.available"></div>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <div class="text-secondary fs-9">Reserved (Order)</div>
                                        <div class="fs-6 fw-bold font-monospace text-warning-emphasis" x-text="selectedBalance.reserved"></div>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <div class="text-secondary fs-9">Damaged (Rusak)</div>
                                        <div class="fs-6 fw-bold font-monospace text-danger" x-text="selectedBalance.damaged"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Valuasi -->
                    <div class="col-12">
                        <div class="card bg-body-tertiary border p-3">
                            <div class="fw-bold text-secondary text-uppercase fs-9 mb-2">Estimasi Nilai Valuasi</div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <span class="text-secondary">Estimasi Harga Satuan:</span>
                                    <div class="fw-bold font-monospace text-body" x-text="'Rp ' + Number(selectedBalance.unit_price || 0).toLocaleString('id-ID')"></div>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-secondary">Total Nilai Saldo:</span>
                                    <div class="fw-bold font-monospace text-primary" x-text="'Rp ' + Number(selectedBalance.total_valuation || 0).toLocaleString('id-ID')"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-body-tertiary py-2.5 px-4 d-flex justify-content-between align-items-center">
                <a :href="selectedBalance.stock_card_url" class="btn btn-sm btn-outline-primary fw-bold">
                    <i class="bi bi-card-list me-1"></i> Buka Kartu Stok
                </a>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-secondary">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Update Limit Pagu Stok Cabang -->
@foreach($balances as $sb)
<div class="modal fade" id="limitModal{{ $sb->id }}" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form action="{{ route('inventory.balances.limits', $sb->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold fs-8">Atur Pagu Stok Cabang</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="fw-bold text-dark fs-8 mb-1">{{ $sb->item->name }}</div>
                <div class="text-secondary fs-9 mb-3">{{ $sb->warehouse->name }}</div>
                <div class="mb-3">
                    <label class="form-label fs-9 fw-semibold">Pagu Stok Minimum (Min Stock)</label>
                    <input type="number" name="min_stock" value="{{ $sb->min_stock !== null ? $sb->min_stock : '' }}" class="form-control form-control-sm text-center font-monospace" placeholder="Default: {{ $sb->item->min_stock }}" min="0">
                    <span class="text-muted fs-9">Kosongkan untuk mengikuti default master item ({{ $sb->item->min_stock }}).</span>
                </div>
                <div class="mb-2">
                    <label class="form-label fs-9 fw-semibold">Pagu Stok Maksimum (Max Stock)</label>
                    <input type="number" name="max_stock" value="{{ $sb->max_stock !== null ? $sb->max_stock : '' }}" class="form-control form-control-sm text-center font-monospace" placeholder="Default: 200" min="0">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger btn-sm">Simpan Pagu</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
