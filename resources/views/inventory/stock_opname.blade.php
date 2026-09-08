@extends('layouts.app')
@section('title', 'Stock Opname & Rekonsiliasi Fisik')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Stock Opname</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    searchQuery: '',
    statusFilter: 'all',
    warehouseDropdownOpen: false,
    warehouseSearch: '',
    selectedYear: {{ $selectedYear }},
    selectedMonth: {{ $selectedMonth }},
    months: {{ Js::from($months) }},
    warehousesList: {{ Js::from($warehouses->map(fn($w) => [
        'id' => $w->id,
        'code' => $w->code,
        'name' => $w->name,
        'type' => $w->type,
        'org_name' => $w->organization->name,
        'org_code' => $w->organization->code,
        'city' => $w->organization->city,
    ])) }},
    rows: {{ Js::from($items->map(fn($it) => [
        'item_id' => $it->id,
        'name' => $it->name,
        'sku' => $it->sku,
        'uom' => $it->uom,
        'category' => $it->category->name,
        'unit_price' => (float) $it->estimated_unit_price,
        'system_qty' => $it->stockBalances->first()?->on_hand ?? 0,
        'physical_qty' => $it->stockBalances->first()?->on_hand ?? 0,
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
    getVariance(row) {
        return (row.physical_qty || 0) - (row.system_qty || 0);
    },
    getVarianceValue(row) {
        return this.getVariance(row) * row.unit_price;
    },
    getDiscrepancyCount() {
        return this.rows.filter(r => (r.physical_qty || 0) != (r.system_qty || 0)).length;
    },
    getNetVarianceQty() {
        return this.rows.reduce((sum, r) => sum + this.getVariance(r), 0);
    },
    getNetVarianceValue() {
        return this.rows.reduce((sum, r) => sum + this.getVarianceValue(r), 0);
    },
    setAllMatch() {
        this.rows.forEach(r => { r.physical_qty = r.system_qty; });
    },
    setAllZero() {
        this.rows.forEach(r => { r.physical_qty = 0; });
    }
}">

    <!-- Warehouse & Period Selector Header Bar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Lokasi Gudang yang Di-Opname:</div>
                    <div class="text-sm font-black text-slate-900 flex items-center space-x-2">
                        <span>{{ $currentWarehouse?->name ?? 'Gudang Terpilih' }}</span>
                        @if($currentWarehouse)
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $currentWarehouse->type === 'CENTRAL_LOGISTICS' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $currentWarehouse->type === 'CENTRAL_LOGISTICS' ? 'Gudang Logistik Pusat' : 'Penyimpanan Cabang' }}
                            </span>
                            <span class="text-[11px] text-slate-400 font-normal font-mono">({{ $currentWarehouse->code }} • {{ $currentWarehouse->organization->city }})</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Controls: Period Selector, Warehouse Selector & History Link -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Period Month & Year Selector -->
                <div class="d-inline-flex align-items-center bg-light border border-slate-200 rounded-xl px-3 py-1.5 gap-2 shadow-xs">
                    <div class="d-flex align-items-center text-secondary fs-8 fw-bold">
                        <i class="bi bi-calendar-check me-1 text-danger"></i>
                        <span>Periode:</span>
                    </div>
                    <select x-model.number="selectedMonth" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark fs-8 py-0 ps-1 pe-4" style="width: auto;">
                        @foreach($months as $mNum => $mName)
                            <option value="{{ $mNum }}" {{ $selectedMonth === $mNum ? 'selected' : '' }}>{{ $mName }}</option>
                        @endforeach
                    </select>
                    <select x-model.number="selectedYear" class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark fs-8 py-0 ps-1 pe-4" style="width: auto;">
                        @foreach($years as $yr)
                            <option value="{{ $yr }}" {{ $selectedYear === $yr ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Searchable Warehouse Dropdown Button -->
                <div class="relative" @click.away="warehouseDropdownOpen = false">
                    <button @click="warehouseDropdownOpen = !warehouseDropdownOpen" type="button" class="inline-flex items-center justify-between space-x-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-xs font-bold px-3 py-2 rounded-xl transition shadow-sm">
                        <i class="fa-solid fa-building-circle-check text-slate-500"></i>
                        <span>Ganti Lokasi Gudang</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition ms-1" :class="warehouseDropdownOpen && 'rotate-180'"></i>
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
                            <template x-for="wh in filteredWarehouses" :key="wh.id">
                                <a :href="'{{ route('inventory.stock_opname') }}?warehouse_id=' + wh.id + '&period_year=' + selectedYear + '&period_month=' + selectedMonth" class="flex items-center justify-between p-3 hover:bg-amber-50/60 transition" :class="wh.id == '{{ $selectedWarehouseId }}' ? 'bg-amber-50 font-bold text-brand-600' : 'text-slate-700'">
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

                <!-- Link to History -->
                <a :href="'{{ route('inventory.stock_opname.history') }}?warehouse_id={{ $selectedWarehouseId }}&period_year=' + selectedYear + '&period_month=' + selectedMonth" class="inline-flex items-center space-x-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold px-3 py-2 rounded-xl transition shadow-sm">
                    <i class="bi bi-clock-history text-danger"></i>
                    <span>Riwayat Opname</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Opname Live Summary Metrics (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- 1. Total SKU -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-list-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">SKU Terhitung</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis" x-text="rows.length"></span>
                    <span class="fs-9 text-secondary">Item dalam lembar hitung</span>
                </div>
            </div>
        </div>

        <!-- 2. Discrepancy Count -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Item Berselisih</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace" :class="getDiscrepancyCount() > 0 ? 'text-danger' : 'text-success'" x-text="getDiscrepancyCount() + ' SKU'"></span>
                    <span class="fs-9 text-secondary" x-text="getDiscrepancyCount() > 0 ? 'Memerlukan penyesuaian' : 'Seluruh item cocok (Match)'"></span>
                </div>
            </div>
        </div>

        <!-- 3. Net Qty Variance -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-arrow-left-right"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Net Selisih Qty</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace" :class="getNetVarianceQty() > 0 ? 'text-success' : (getNetVarianceQty() < 0 ? 'text-danger' : 'text-body-emphasis')" x-text="(getNetVarianceQty() > 0 ? '+' : '') + getNetVarianceQty() + ' Unit'"></span>
                    <span class="fs-9 text-secondary">Total akumulasi selisih fisik</span>
                </div>
            </div>
        </div>

        <!-- 4. Net Value Impact -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Dampak Valuasi</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace" :class="getNetVarianceValue() > 0 ? 'text-success' : (getNetVarianceValue() < 0 ? 'text-danger' : 'text-body-emphasis')" x-text="(getNetVarianceValue() >= 0 ? '+' : '') + 'Rp ' + Math.abs(getNetVarianceValue()).toLocaleString('id-ID')"></span>
                    <span class="fs-9 text-secondary">Penyesuaian buku besar</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Opname Form -->
    <form action="{{ route('inventory.stock_opname.store') }}" method="POST">
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
        <input type="hidden" name="period_year" :value="selectedYear">
        <input type="hidden" name="period_month" :value="selectedMonth">

        <!-- Items Worksheet Table Card -->
        <div class="card shadow-sm border-0 rounded-3">
            <!-- Table Toolbar (Search, Filter, Batch Actions) -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <div class="row g-2 align-items-center">
                    <!-- Search Input -->
                    <div class="col-12 col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" x-model="searchQuery" class="form-control form-control-sm border-start-0 fs-8" placeholder="Cari nama item atau SKU...">
                            <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="btn btn-sm btn-outline-secondary border-start-0" title="Clear">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Status Filter Buttons -->
                    <div class="col-12 col-md-4">
                        <div class="btn-group btn-group-sm w-100" role="group">
                            <button type="button" @click="statusFilter = 'all'" class="btn btn-sm fs-8" :class="statusFilter === 'all' ? 'btn-danger fw-bold' : 'btn-outline-secondary'">
                                Semua Item
                            </button>
                            <button type="button" @click="statusFilter = 'discrepancy'" class="btn btn-sm fs-8" :class="statusFilter === 'discrepancy' ? 'btn-danger fw-bold' : 'btn-outline-secondary'">
                                Berselisih (<span x-text="getDiscrepancyCount()"></span>)
                            </button>
                            <button type="button" @click="statusFilter = 'match'" class="btn btn-sm fs-8" :class="statusFilter === 'match' ? 'btn-danger fw-bold' : 'btn-outline-secondary'">
                                Cocok (<span x-text="rows.length - getDiscrepancyCount()"></span>)
                            </button>
                        </div>
                    </div>

                    <!-- Batch Helper Buttons -->
                    <div class="col-12 col-md-4 d-flex justify-content-md-end gap-2">
                        <button type="button" @click="setAllMatch()" class="btn btn-sm btn-outline-secondary fs-8 d-inline-flex align-items-center gap-1">
                            <i class="bi bi-check2-all"></i>
                            <span>Set Semua Cocok</span>
                        </button>
                        <button type="button" @click="setAllZero()" class="btn btn-sm btn-outline-secondary fs-8 d-inline-flex align-items-center gap-1">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Reset Hitungan</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0 fs-7">
                        <thead class="bg-body-tertiary text-secondary border-bottom">
                            <tr>
                                <th class="ps-4 py-3">Item & SKU</th>
                                <th class="py-3">Kategori</th>
                                <th class="py-3 text-center" style="width: 120px;">Stok Sistem</th>
                                <th class="py-3 text-center" style="width: 180px;">Hasil Hitung Fisik</th>
                                <th class="py-3 text-center" style="width: 140px;">Selisih (+/-)</th>
                                <th class="py-3 text-end" style="width: 160px;">Estimasi Nilai (Rp)</th>
                                <th class="py-3 pe-4 text-center" style="width: 110px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in rows" :key="row.item_id">
                                <tr :class="getVariance(row) !== 0 ? 'table-warning' : ''"
                                    x-show="(
                                        (!searchQuery.trim() || (row.name + ' ' + row.sku + ' ' + row.category).toLowerCase().includes(searchQuery.toLowerCase())) &&
                                        (statusFilter === 'all' || 
                                         (statusFilter === 'discrepancy' && getVariance(row) !== 0) || 
                                         (statusFilter === 'match' && getVariance(row) === 0))
                                    )">
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-body" x-text="row.name"></div>
                                        <div class="text-secondary font-monospace fs-8" x-text="row.sku + ' • ' + row.uom"></div>
                                        <input type="hidden" :name="'counts[' + idx + '][item_id]'" :value="row.item_id">
                                        <input type="hidden" :name="'counts[' + idx + '][system_qty]'" :value="row.system_qty">
                                    </td>
                                    <td class="py-3">
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8" x-text="row.category"></span>
                                    </td>
                                    <td class="py-3 text-center fw-bold text-body font-monospace" x-text="row.system_qty + ' ' + row.uom"></td>
                                    <td class="py-3 text-center">
                                        <div class="input-group input-group-sm justify-content-center mx-auto" style="max-width: 130px;">
                                            <button type="button" @click="if(row.physical_qty > 0) row.physical_qty--" class="btn btn-outline-secondary px-2">-</button>
                                            <input type="number" :name="'counts[' + idx + '][physical_qty]'" x-model.number="row.physical_qty" min="0" required class="form-control form-control-sm text-center fw-bold font-monospace">
                                            <button type="button" @click="row.physical_qty++" class="btn btn-outline-secondary px-2">+</button>
                                        </div>
                                    </td>
                                    <td class="py-3 text-center font-monospace fw-bold">
                                        <span :class="getVariance(row) > 0 ? 'badge bg-success-subtle text-success fs-8' : (getVariance(row) < 0 ? 'badge bg-danger-subtle text-danger fs-8' : 'text-secondary fs-8')" 
                                              x-text="(getVariance(row) > 0 ? '+' : '') + getVariance(row) + ' ' + row.uom"></span>
                                    </td>
                                    <td class="py-3 text-end font-monospace fs-8 fw-semibold" :class="getVarianceValue(row) > 0 ? 'text-success' : (getVarianceValue(row) < 0 ? 'text-danger' : 'text-secondary')" 
                                        x-text="(getVarianceValue(row) >= 0 ? '+' : '') + 'Rp ' + Math.abs(getVarianceValue(row)).toLocaleString('id-ID')"></td>
                                    <td class="py-3 pe-4 text-center">
                                        <span class="badge px-2 py-1 fs-8" :class="getVariance(row) === 0 ? 'bg-success text-white' : 'bg-danger text-white'" x-text="getVariance(row) === 0 ? 'MATCH' : 'SELISIH'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notes & Submission Footer -->
            <div class="card-footer bg-body-tertiary p-4 border-top">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-8">
                        <label class="form-label fw-bold fs-8 text-secondary text-uppercase mb-1">
                            Berita Acara / Keterangan Pelaksanaan Opname <span class="text-danger">*</span>
                        </label>
                        <textarea name="opname_notes" rows="2" required class="form-control form-control-sm fs-8" placeholder="Contoh: Pelaksanaan Stock Opname Triwulan I - Ditemukan 2 unit rusak akibat kelembaban..."></textarea>
                        <div class="form-text fs-9 text-secondary mt-1">
                            <i class="bi bi-info-circle me-1"></i> Penyesuaian selisih otomatis tercatat sebagai transaksi <strong class="text-dark">STOCK_OPNAME</strong> pada buku besar stok (*Stock Ledger*) untuk <strong>{{ $currentWarehouse?->name }}</strong> periode <strong class="text-danger" x-text="months[selectedMonth] + ' ' + selectedYear"></strong>.
                        </div>
                    </div>
                    <div class="col-12 col-lg-4 d-flex justify-content-lg-end">
                        <button type="submit" class="btn btn-danger fw-bold shadow-xs px-4 py-2 d-inline-flex align-items-center gap-2">
                            <i class="bi bi-floppy"></i>
                            <span>Simpan & Posting Hasil Opname</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

