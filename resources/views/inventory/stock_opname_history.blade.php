@extends('layouts.app')
@section('title', 'History Stock Opname')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">History Stock Opname</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    warehouseDropdownOpen: false,
    warehouseSearch: '',
    detailModalOpen: false,
    selectedOpname: null,
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
    openDetail(data) {
        this.selectedOpname = data;
        this.detailModalOpen = true;
    }
}">

    <!-- Warehouse Selector Header Bar -->
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
                            <span class="text-[11px] text-slate-400 font-normal font-mono">({{ $currentWarehouse->code }} • {{ $currentWarehouse->organization->city }})</span>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                                {{ $warehouses->count() }} Lokasi Tergabung
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Controls: Warehouse Selector & New Opname Button -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Searchable Dropdown Button -->
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
                                <input type="text" x-model="warehouseSearch" class="w-full bg-white border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-jatim-700 focus:outline-none" placeholder="Cari gudang / cabang...">
                            </div>
                        </div>

                        <!-- Warehouse List Options -->
                        <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                            <!-- Option All Warehouses -->
                            <a href="{{ route('inventory.stock_opname.history', ['warehouse_id' => 'all', 'period_year' => $selectedYear, 'period_month' => $selectedMonth]) }}" class="flex items-center justify-between p-3 hover:bg-amber-50/60 transition {{ $selectedWarehouseId === 'all' ? 'bg-amber-50 font-bold text-brand-600' : 'text-slate-700' }}">
                                <div class="flex items-center space-x-2.5">
                                    <i class="fa-solid fa-network-wired text-xs {{ $selectedWarehouseId === 'all' ? 'text-brand-500' : 'text-slate-400' }}"></i>
                                    <div>
                                        <div class="text-xs">Konsolidasi Seluruh Gudang</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Riwayat opname seluruh cabang Bank Jatim</div>
                                    </div>
                                </div>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">SEMUA</span>
                            </a>

                            <!-- Individual Warehouses List Filtered by Search -->
                            <template x-for="wh in filteredWarehouses" :key="wh.id">
                                <a :href="'{{ route('inventory.stock_opname.history') }}?warehouse_id=' + wh.id + '&period_year={{ $selectedYear }}&period_month={{ $selectedMonth }}'" class="flex items-center justify-between p-3 hover:bg-amber-50/60 transition" :class="wh.id == '{{ $selectedWarehouseId }}' ? 'bg-amber-50 font-bold text-brand-600' : 'text-slate-700'">
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

    <!-- Summary Metrics (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- 1. Total Sesi Opname -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-journal-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Sesi Opname</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($totalSessions) }} Sesi</span>
                    <span class="fs-9 text-secondary">Berdasarkan filter periode aktif</span>
                </div>
            </div>
        </div>

        <!-- 2. Total SKU Diaudit -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-secondary"><i class="bi bi-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Item Dihitung</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($totalItemsAudited) }} SKU</span>
                    <span class="fs-9 text-secondary">Akumulasi seluruh item diverifikasi</span>
                </div>
            </div>
        </div>

        <!-- 3. Total Item Berselisih -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Item Berselisih</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $totalDiscrepancies > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($totalDiscrepancies) }} SKU</span>
                    <span class="fs-9 text-secondary">Item yang memerlukan penyesuaian buku</span>
                </div>
            </div>
        </div>

        <!-- 4. Net Dampak Valuasi -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Net Dampak Valuasi</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $netVarianceVal > 0 ? 'text-success' : ($netVarianceVal < 0 ? 'text-danger' : 'text-body-emphasis') }}">
                        {{ ($netVarianceVal > 0 ? '+' : '') . 'Rp ' . number_format($netVarianceVal, 0, ',', '.') }}
                    </span>
                    <span class="fs-9 text-secondary">Akumulasi rekonsiliasi nilai buku</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Opname History Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('inventory.stock_opname.history') }}" method="GET">
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                
                <div class="row g-2 align-items-center">
                    <!-- Filter Periode Tahun -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar-event"></i></span>
                            <select name="period_year" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="all" {{ $selectedYear === 'all' ? 'selected' : '' }}>Semua Tahun</option>
                                @foreach($years as $yr)
                                    <option value="{{ $yr }}" {{ (string)$selectedYear === (string)$yr ? 'selected' : '' }}>
                                        Tahun {{ $yr }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Filter Periode Bulan -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-calendar-month"></i></span>
                            <select name="period_month" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="" {{ empty($selectedMonth) || $selectedMonth === 'all' ? 'selected' : '' }}>Semua Bulan</option>
                                @foreach($months as $mNum => $mName)
                                    <option value="{{ $mNum }}" {{ (string)$selectedMonth === (string)$mNum ? 'selected' : '' }}>
                                        {{ $mName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($selectedYear && $selectedYear !== 'all') || (!empty($selectedMonth) && $selectedMonth !== 'all'))
                        <div class="col-auto">
                            <a href="{{ route('inventory.stock_opname.history', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                   placeholder="Cari nomor opname, berita acara, atau pelaksana...">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table View -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9 border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="min-width: 170px;">No. Opname & Tanggal</th>
                            <th class="py-3" style="min-width: 130px;">Periode</th>
                            <th class="py-3" style="min-width: 180px;">Lokasi Gudang</th>
                            <th class="py-3" style="min-width: 150px;">Auditor / Pelaksana</th>
                            <th class="py-3 text-center" style="min-width: 90px;">Total SKU</th>
                            <th class="py-3 text-center" style="min-width: 110px;">Status Selisih</th>
                            <th class="py-3 text-center" style="min-width: 100px;">Net Selisih</th>
                            <th class="py-3 text-end" style="min-width: 130px;">Dampak Valuasi</th>
                            <th class="py-3 text-center" style="min-width: 80px;">Status</th>
                            <th class="pe-4 py-3 text-end" style="min-width: 70px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($opnames as $opn)
                            <tr>
                                <!-- No Opname & Tanggal -->
                                <td class="ps-4 py-3">
                                    <div class="fw-bold font-monospace text-body">{{ $opn->opname_number }}</div>
                                    <div class="text-secondary fs-9">
                                        <i class="bi bi-calendar3 me-1"></i>{{ $opn->opname_date->format('d/m/Y') }}
                                    </div>
                                </td>

                                <!-- Periode -->
                                <td class="py-3">
                                    <span class="badge bg-primary-subtle text-primary-emphasis font-monospace fs-8">
                                        <i class="bi bi-calendar-check me-1"></i>{{ $opn->period_formatted }}
                                    </span>
                                </td>

                                <!-- Lokasi Gudang -->
                                <td class="py-3">
                                    <div class="fw-bold text-body">{{ $opn->warehouse->name }}</div>
                                    <div class="text-secondary fs-9">
                                        {{ $opn->warehouse->code }} • {{ $opn->warehouse->organization->name }}
                                    </div>
                                </td>

                                <!-- Auditor / Pelaksana -->
                                <td class="py-3">
                                    <div class="fw-semibold text-body">{{ $opn->user?->name ?? 'Sistem' }}</div>
                                    <div class="text-secondary fs-9">{{ $opn->user?->email ?? '-' }}</div>
                                </td>

                                <!-- Total SKU -->
                                <td class="py-3 text-center font-monospace fw-semibold text-body">
                                    {{ number_format($opn->total_items) }} Item
                                </td>

                                <!-- Status Selisih -->
                                <td class="py-3 text-center">
                                    @if($opn->discrepancy_items_count > 0)
                                        <span class="badge bg-danger-subtle text-danger fs-8">
                                            <i class="bi bi-exclamation-triangle me-1"></i>{{ $opn->discrepancy_items_count }} Berselisih
                                        </span>
                                    @else
                                        <span class="badge bg-success-subtle text-success fs-8">
                                            <i class="bi bi-check-circle me-1"></i>Match (0)
                                        </span>
                                    @endif
                                </td>

                                <!-- Net Selisih Qty -->
                                <td class="py-3 text-center font-monospace fw-bold">
                                    @if($opn->net_variance_qty > 0)
                                        <span class="text-success">+{{ number_format($opn->net_variance_qty) }} Unit</span>
                                    @elseif($opn->net_variance_qty < 0)
                                        <span class="text-danger">{{ number_format($opn->net_variance_qty) }} Unit</span>
                                    @else
                                        <span class="text-secondary">0 Unit</span>
                                    @endif
                                </td>

                                <!-- Dampak Valuasi -->
                                <td class="py-3 text-end font-monospace fw-semibold fs-8 {{ $opn->net_variance_value > 0 ? 'text-success' : ($opn->net_variance_value < 0 ? 'text-danger' : 'text-secondary') }}">
                                    {{ ($opn->net_variance_value > 0 ? '+' : '') . 'Rp ' . number_format($opn->net_variance_value, 0, ',', '.') }}
                                </td>

                                <!-- Status -->
                                <td class="py-3 text-center">
                                    <span class="badge bg-success text-white fs-9 px-2 py-1">POSTED</span>
                                </td>

                                <!-- Aksi -->
                                <td class="pe-4 py-3 text-end">
                                    @php
                                        $opnJson = [
                                            'id' => $opn->id,
                                            'opname_number' => $opn->opname_number,
                                            'period_formatted' => $opn->period_formatted,
                                            'opname_date' => $opn->opname_date->format('d/m/Y'),
                                            'warehouse_name' => $opn->warehouse->name,
                                            'warehouse_code' => $opn->warehouse->code,
                                            'org_name' => $opn->warehouse->organization->name,
                                            'user_name' => $opn->user?->name ?? 'Sistem',
                                            'total_items' => $opn->total_items,
                                            'discrepancy_items_count' => $opn->discrepancy_items_count,
                                            'net_variance_qty' => $opn->net_variance_qty,
                                            'net_variance_value' => (float) $opn->net_variance_value,
                                            'notes' => $opn->notes,
                                            'items' => $opn->items->map(fn($itemRow) => [
                                                'id' => $itemRow->id,
                                                'sku' => $itemRow->item->sku,
                                                'name' => $itemRow->item->name,
                                                'uom' => $itemRow->item->uom,
                                                'category' => $itemRow->item->category?->name ?? '-',
                                                'system_qty' => $itemRow->system_qty,
                                                'physical_qty' => $itemRow->physical_qty,
                                                'variance_qty' => $itemRow->variance_qty,
                                                'unit_price' => (float) $itemRow->unit_price,
                                                'variance_value' => (float) $itemRow->variance_value,
                                            ]),
                                        ];
                                    @endphp
                                    <button type="button" 
                                            @click="openDetail({{ Js::from($opnJson) }})" 
                                            class="btn-action-icon text-primary" 
                                            title="Lihat Detail Opname">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <i class="bi bi-inbox text-secondary fs-1 mb-2"></i>
                                        <span class="text-secondary fw-medium">Belum ada riwayat stock opname pada kriteria yang dipilih</span>
                                        @if($search || ($selectedYear && $selectedYear !== 'all') || (!empty($selectedMonth) && $selectedMonth !== 'all'))
                                            <a href="{{ route('inventory.stock_opname.history', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-danger mt-3">
                                                <i class="bi bi-x-circle me-1"></i> Reset Filter
                                            </a>
                                        @else
                                            <a href="{{ route('inventory.stock_opname', ['warehouse_id' => $selectedWarehouseId !== 'all' ? $selectedWarehouseId : ($warehouses->first()?->id ?? 1)]) }}" class="btn btn-sm btn-danger mt-3">
                                                <i class="bi bi-plus-lg me-1"></i> Lakukan Stock Opname Sekarang
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
        <x-pagination-footer :paginator="$opnames" :perPage="$perPage" />
    </div>

    <!-- ==================== VIEW DETAIL MODAL ==================== -->
    <div x-show="detailModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="detailModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-4xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color); max-height: 90vh;">
            <!-- Modal Header -->
            <div class="card-header bg-danger text-white py-3 px-4 d-flex justify-content-between align-items-center flex-shrink-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text fs-5"></i>
                    <div>
                        <h5 class="modal-title fs-6 fw-bold mb-0">Rincian Hasil Stock Opname</h5>
                        <div class="text-white-50 fs-9 font-monospace" x-text="selectedOpname ? selectedOpname.opname_number + ' • Periode ' + selectedOpname.period_formatted : ''"></div>
                    </div>
                </div>
                <button type="button" @click="detailModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="modal-body p-4 fs-8 overflow-y-auto" style="max-height: calc(90vh - 130px);">
                <template x-if="selectedOpname">
                    <div>
                        <!-- Info Header Summary Cards -->
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-6">
                                <div class="p-3 bg-body-tertiary rounded-3 border h-100">
                                    <div class="text-secondary fs-9 fw-bold text-uppercase mb-1">Informasi Lokasi & Pelaksana</div>
                                    <div class="fw-bold text-body fs-7" x-text="selectedOpname.warehouse_name + ' (' + selectedOpname.warehouse_code + ')'"></div>
                                    <div class="text-secondary fs-9" x-text="selectedOpname.org_name"></div>
                                    <div class="mt-2 pt-2 border-top d-flex justify-content-between text-secondary fs-9">
                                        <span>Auditor: <strong class="text-body" x-text="selectedOpname.user_name"></strong></span>
                                        <span>Tanggal: <strong class="text-body" x-text="selectedOpname.opname_date"></strong></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-3 bg-body-tertiary rounded-3 border h-100">
                                    <div class="text-secondary fs-9 fw-bold text-uppercase mb-1">Ringkasan Rekonsiliasi</div>
                                    <div class="row g-2 text-center mt-1">
                                        <div class="col-4">
                                            <div class="bg-body border rounded p-2">
                                                <div class="fs-9 text-secondary">Total SKU</div>
                                                <div class="fw-bold font-monospace fs-7 text-body" x-text="selectedOpname.total_items"></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-body border rounded p-2">
                                                <div class="fs-9 text-secondary">Selisih</div>
                                                <div class="fw-bold font-monospace fs-7" :class="selectedOpname.discrepancy_items_count > 0 ? 'text-danger' : 'text-success'" x-text="selectedOpname.discrepancy_items_count + ' SKU'"></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-body border rounded p-2">
                                                <div class="fs-9 text-secondary">Dampak Nilai</div>
                                                <div class="fw-bold font-monospace fs-8" :class="selectedOpname.net_variance_value >= 0 ? 'text-success' : 'text-danger'" x-text="(selectedOpname.net_variance_value >= 0 ? '+' : '') + 'Rp ' + Math.abs(selectedOpname.net_variance_value).toLocaleString('id-ID')"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Berita Acara / Keterangan -->
                        <div class="p-3 bg-body-tertiary rounded-3 border mb-3">
                            <div class="text-secondary fs-9 fw-bold text-uppercase mb-1">Berita Acara / Keterangan Pelaksanaan</div>
                            <div class="text-body fst-italic" x-text="selectedOpname.notes || 'Tidak ada catatan pelaksanaan khusus.'"></div>
                        </div>

                        <!-- Detail Table of Items -->
                        <div class="card border rounded-3 overflow-hidden">
                            <div class="card-header bg-light py-2 px-3 fw-bold text-secondary fs-8">
                                <i class="bi bi-list-ul me-1"></i> Rincian Seluruh Barang yang Diperiksa
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 fs-8">
                                    <thead class="table-light text-secondary text-uppercase fs-9">
                                        <tr>
                                            <th class="ps-3 py-2">Barang</th>
                                            <th class="py-2">Kategori</th>
                                            <th class="py-2 text-center">Sistem</th>
                                            <th class="py-2 text-center">Fisik</th>
                                            <th class="py-2 text-center">Selisih</th>
                                            <th class="py-2 text-end">Harga Satuan</th>
                                            <th class="py-2 text-end">Nilai Selisih</th>
                                            <th class="pe-3 py-2 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="row in selectedOpname.items" :key="row.id">
                                            <tr>
                                                <td class="ps-3 py-2">
                                                    <div class="fw-bold text-body" x-text="row.name"></div>
                                                    <div class="text-secondary fs-9 font-monospace" x-text="row.sku + ' • ' + row.uom"></div>
                                                </td>
                                                <td class="py-2">
                                                    <span class="badge bg-secondary-subtle text-secondary-emphasis fs-9" x-text="row.category"></span>
                                                </td>
                                                <td class="py-2 text-center font-monospace" x-text="row.system_qty"></td>
                                                <td class="py-2 text-center font-monospace fw-bold" x-text="row.physical_qty"></td>
                                                <td class="py-2 text-center font-monospace fw-bold">
                                                    <span :class="row.variance_qty > 0 ? 'text-success' : (row.variance_qty < 0 ? 'text-danger' : 'text-secondary')" 
                                                          x-text="(row.variance_qty > 0 ? '+' : '') + row.variance_qty"></span>
                                                </td>
                                                <td class="py-2 text-end font-monospace fs-9 text-secondary" x-text="'Rp ' + row.unit_price.toLocaleString('id-ID')"></td>
                                                <td class="py-2 text-end font-monospace fs-9 fw-semibold" 
                                                    :class="row.variance_value > 0 ? 'text-success' : (row.variance_value < 0 ? 'text-danger' : 'text-secondary')" 
                                                    x-text="(row.variance_value >= 0 ? '+' : '') + 'Rp ' + Math.abs(row.variance_value).toLocaleString('id-ID')"></td>
                                                <td class="pe-3 py-2 text-center">
                                                    <span class="badge px-2 py-0.5 fs-9" :class="row.variance_qty === 0 ? 'bg-success text-white' : 'bg-danger text-white'" x-text="row.variance_qty === 0 ? 'MATCH' : 'SELISIH'"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="card-footer bg-body-tertiary p-3 border-top d-flex justify-content-end">
                <button type="button" @click="detailModalOpen = false" class="btn btn-sm btn-secondary px-4 fw-bold">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
