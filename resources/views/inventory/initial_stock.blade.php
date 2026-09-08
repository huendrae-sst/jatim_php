@extends('layouts.app')
@section('title', 'Saldo Awal Gudang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Saldo Awal Gudang</li>
@endsection

@section('content')
@php
    $importedMap = collect(session('imported_rows', []))->keyBy('item_id');
@endphp
<div class="space-y-4" x-data="{
    searchQuery: '',
    categoryFilter: 'all',
    statusFilter: 'all',
    warehouseDropdownOpen: false,
    warehouseSearch: '',
    importModalOpen: false,
    cutoffDate: '{{ $cutoffDate }}',
    warehousesList: {{ Js::from($warehouses->map(fn($w) => [
        'id' => $w->id,
        'code' => $w->code,
        'name' => $w->name,
        'type' => $w->type,
        'org_name' => $w->organization->name,
        'org_code' => $w->organization->code,
        'city' => $w->organization->city,
    ])) }},
    rows: {{ Js::from($items->map(function($it) use ($importedMap) {
        $imp = $importedMap->get($it->id);
        $qtyGood = $imp ? $imp['qty_good'] : ($it->stockBalances->first()?->on_hand ?? 0);
        $qtyDamaged = $imp ? $imp['qty_damaged'] : ($it->stockBalances->first()?->damaged ?? 0);
        $unitCost = $imp ? (float) $imp['unit_cost'] : (float) $it->estimated_unit_price;
        return [
            'item_id' => $it->id,
            'name' => $it->name,
            'sku' => $it->sku,
            'uom' => $it->uom,
            'category' => $it->category?->name ?? 'Umum',
            'unit_cost' => $unitCost,
            'qty_good' => $qtyGood,
            'qty_damaged' => $qtyDamaged,
        ];
    })) }},
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
    getTotalSKU() {
        return this.rows.length;
    },
    getFilledSKUCount() {
        return this.rows.filter(r => (parseInt(r.qty_good) || 0) > 0 || (parseInt(r.qty_damaged) || 0) > 0).length;
    },
    getTotalQty() {
        return this.rows.reduce((sum, r) => sum + (parseInt(r.qty_good) || 0) + (parseInt(r.qty_damaged) || 0), 0);
    },
    getTotalValuation() {
        return this.rows.reduce((sum, r) => {
            const qty = (parseInt(r.qty_good) || 0) + (parseInt(r.qty_damaged) || 0);
            return sum + (qty * (parseFloat(r.unit_cost) || 0));
        }, 0);
    },
    getRowSubtotal(row) {
        const qty = (parseInt(row.qty_good) || 0) + (parseInt(row.qty_damaged) || 0);
        return qty * (parseFloat(row.unit_cost) || 0);
    },
    resetAll() {
        if (confirm('Kosongkan semua isian kuantitas pada tabel?')) {
            this.rows.forEach(r => {
                r.qty_good = 0;
                r.qty_damaged = 0;
            });
        }
    }
}">

    <!-- Warehouse & Cut-off Date Selector Header Bar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-danger-subtle text-danger flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Lokasi Gudang Penempatan Saldo Awal:</div>
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

            <!-- Action Controls: Cut-off Date, Warehouse Selector, Download, Import & History Link -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Cutoff Date Selector -->
                <div class="inline-flex items-center space-x-2 bg-slate-50 border border-slate-200 text-slate-800 text-xs font-semibold px-3 py-1.5 rounded-xl shadow-xs">
                    <span class="text-slate-500 font-bold text-[11px] whitespace-nowrap">Tanggal Cut-off:</span>
                    <input type="date" 
                           x-model="cutoffDate" 
                           class="bg-white border border-slate-200 text-slate-900 font-bold text-xs rounded-lg px-2 py-1 shadow-2xs focus:ring-2 focus:ring-danger focus:outline-none" 
                           style="width: 135px;">
                </div>

                <!-- Searchable Warehouse Dropdown Button -->
                <div class="relative" @click.away="warehouseDropdownOpen = false">
                    <button @click="warehouseDropdownOpen = !warehouseDropdownOpen" type="button" class="inline-flex items-center justify-between space-x-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-xs font-bold px-3 py-2 rounded-xl transition shadow-sm">
                        <span>Ganti Lokasi Gudang</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition ms-1" :class="warehouseDropdownOpen && 'rotate-180'"></i>
                    </button>

                    <!-- Searchable Dropdown Popup -->
                    <div x-show="warehouseDropdownOpen" x-cloak class="absolute right-0 mt-2 w-72 sm:w-96 max-w-[calc(100vw-2rem)] bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden">
                        <!-- Search Input in Dropdown -->
                        <div class="p-3 border-b border-slate-100 bg-slate-50">
                            <div class="relative">
                                <input type="text" x-model="warehouseSearch" placeholder="Cari gudang atau cabang..." class="w-full bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-danger focus:outline-none">
                            </div>
                        </div>

                        <!-- Warehouse List Options -->
                        <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                            <template x-for="wh in filteredWarehouses" :key="wh.id">
                                <a :href="'{{ route('inventory.initial_stock.index') }}?warehouse_id=' + wh.id + '&cutoff_date=' + cutoffDate" class="flex items-center justify-between p-3 hover:bg-danger-subtle/40 transition" :class="wh.id == '{{ $selectedWarehouseId }}' ? 'bg-danger-subtle font-bold text-danger' : 'text-slate-700'">
                                    <div class="flex items-center space-x-2.5">
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

                <!-- Download Template Button -->
                <a :href="'{{ route('inventory.initial_stock.template') }}?warehouse_id={{ $selectedWarehouseId }}'" class="inline-flex items-center space-x-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold px-3 py-2 rounded-xl transition shadow-sm" title="Download Template Format Excel/CSV">
                    <span>Download Template</span>
                </a>

                <!-- Import Button -->
                <button type="button" @click="importModalOpen = true" class="inline-flex items-center space-x-1.5 bg-success text-white hover:bg-success-emphasis text-xs font-semibold px-3 py-2 rounded-xl transition shadow-sm">
                    <span>Import Saldo Awal</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Live Summary Metrics (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- 1. Total SKU -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-box-seam"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total SKU Terdaftar</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis" x-text="getTotalSKU()"></span>
                    <span class="fs-9 text-secondary">Katalog barang aktif di sistem</span>
                </div>
            </div>
        </div>

        <!-- 2. SKU Terisi Saldo -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">SKU Terisi Saldo</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success" x-text="getFilledSKUCount() + ' SKU'"></span>
                    <span class="fs-9 text-secondary" x-text="(getTotalSKU() - getFilledSKUCount()) + ' SKU belum ada saldo (0)'"></span>
                </div>
            </div>
        </div>

        <!-- 3. Total Kuantitas Fisik -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-layers"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Kuantitas Fisik</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-info-emphasis" x-text="getTotalQty().toLocaleString('id-ID') + ' Unit'"></span>
                    <span class="fs-9 text-secondary">Akumulasi kuantitas baik & rusak</span>
                </div>
            </div>
        </div>

        <!-- 4. Total Valuasi Saldo Awal -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Valuasi Saldo Awal</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-danger" x-text="'Rp ' + getTotalValuation().toLocaleString('id-ID')"></span>
                    <span class="fs-9 text-secondary">Kredit RAK / Ekuitas Awal (31101)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Initial Stock Form -->
    <form action="{{ route('inventory.initial_stock.store') }}" method="POST" @submit="if(getFilledSKUCount() === 0) { alert('Harap isi kuantitas minimal 1 barang sebelum menyimpan saldo awal.'); $event.preventDefault(); }">
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
        <input type="hidden" name="cutoff_date" :value="cutoffDate">

        <!-- Items Worksheet Table Card -->
        <div class="card shadow-sm border-0 rounded-3">
            <!-- Filter & Search Toolbar (seperti /inventory/stock-balances) -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <div class="row g-2 align-items-center">
                    <!-- Kategori Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-tag"></i></span>
                            <select x-model="categoryFilter" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="all">Semua Kategori</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->name }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Status Stok Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-filter"></i></span>
                            <select x-model="statusFilter" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="all">Semua Saldo</option>
                                <option value="filled">Hanya Terisi Saldo (>0)</option>
                                <option value="zero">Saldo Belum Terisi (0)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Filter Button -->
                    <div class="col-auto" x-show="searchQuery || categoryFilter !== 'all' || statusFilter !== 'all'" style="display: none;">
                        <button type="button" @click="searchQuery = ''; categoryFilter = 'all'; statusFilter = 'all';" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                            <i class="bi bi-x-circle me-1"></i> Reset
                        </button>
                    </div>

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   x-model="searchQuery" 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8" 
                                   placeholder="Cari SKU atau nama item...">
                            <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="btn btn-sm btn-outline-secondary border-start-0 border-end-0" title="Clear">
                                <i class="bi bi-x"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>

                    <!-- Batch Reset Button -->
                    <div class="col-auto">
                        <button type="button" @click="resetAll()" class="btn btn-sm btn-outline-secondary fs-8" title="Kosongkan Semua Nilai Kuantitas">
                            Reset Input
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
                                <th class="ps-4 py-3" style="min-width: 220px;">Item & SKU</th>
                                <th class="py-3" style="width: 130px;">Kategori</th>
                                <th class="py-3 text-center" style="width: 80px;">Satuan</th>
                                <th class="py-3 text-center" style="width: 170px;">Qty Kondisi Baik</th>
                                <th class="py-3 text-center" style="width: 170px;">Qty Kondisi Rusak</th>
                                <th class="py-3 text-end" style="width: 160px;">Harga Satuan (Rp)</th>
                                <th class="py-3 pe-4 text-end" style="width: 170px;">Subtotal Valuasi (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in rows" :key="row.item_id">
                                <tr :class="((parseInt(row.qty_good) || 0) > 0 || (parseInt(row.qty_damaged) || 0) > 0) ? 'table-success' : ''"
                                    x-show="(
                                        (!searchQuery.trim() || (row.name + ' ' + row.sku + ' ' + row.category).toLowerCase().includes(searchQuery.toLowerCase())) &&
                                        (categoryFilter === 'all' || row.category === categoryFilter) &&
                                        (statusFilter === 'all' || 
                                         (statusFilter === 'filled' && ((parseInt(row.qty_good) || 0) > 0 || (parseInt(row.qty_damaged) || 0) > 0)) || 
                                         (statusFilter === 'zero' && (parseInt(row.qty_good) || 0) === 0 && (parseInt(row.qty_damaged) || 0) === 0))
                                    )">
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-body" x-text="row.name"></div>
                                        <div class="text-secondary font-monospace fs-8" x-text="row.sku"></div>
                                        <input type="hidden" :name="'items[' + idx + '][item_id]'" :value="row.item_id">
                                    </td>
                                    <td class="py-3">
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8" x-text="row.category"></span>
                                    </td>
                                    <td class="py-3 text-center font-monospace text-secondary fs-8" x-text="row.uom"></td>
                                    
                                    <!-- Qty Good -->
                                    <td class="py-3 text-center">
                                        <div class="input-group input-group-sm justify-content-center mx-auto" style="max-width: 130px;">
                                            <button type="button" @click="if((parseInt(row.qty_good) || 0) > 0) row.qty_good--" class="btn btn-outline-secondary px-2">-</button>
                                            <input type="number" :name="'items[' + idx + '][qty_good]'" x-model.number="row.qty_good" min="0" required class="form-control form-control-sm text-center fw-bold font-monospace">
                                            <button type="button" @click="row.qty_good = (parseInt(row.qty_good) || 0) + 1" class="btn btn-outline-secondary px-2">+</button>
                                        </div>
                                    </td>

                                    <!-- Qty Damaged -->
                                    <td class="py-3 text-center">
                                        <div class="input-group input-group-sm justify-content-center mx-auto" style="max-width: 130px;">
                                            <button type="button" @click="if((parseInt(row.qty_damaged) || 0) > 0) row.qty_damaged--" class="btn btn-outline-secondary px-2">-</button>
                                            <input type="number" :name="'items[' + idx + '][qty_damaged]'" x-model.number="row.qty_damaged" min="0" required class="form-control form-control-sm text-center fw-bold font-monospace text-danger">
                                            <button type="button" @click="row.qty_damaged = (parseInt(row.qty_damaged) || 0) + 1" class="btn btn-outline-secondary px-2">+</button>
                                        </div>
                                    </td>

                                    <!-- Unit Cost -->
                                    <td class="py-3 text-end">
                                        <input type="number" :name="'items[' + idx + '][unit_cost]'" x-model.number="row.unit_cost" min="0" step="any" class="form-control form-control-sm text-end font-monospace ms-auto" style="max-width: 140px;">
                                    </td>

                                    <!-- Subtotal -->
                                    <td class="py-3 pe-4 text-end font-monospace fw-bold fs-8" :class="getRowSubtotal(row) > 0 ? 'text-dark' : 'text-secondary'" x-text="'Rp ' + getRowSubtotal(row).toLocaleString('id-ID')"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Notes, Accounting Explanation & Submission Footer -->
            <div class="card-footer bg-body-tertiary p-4 border-top">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-8">
                        <div class="alert alert-info py-2 px-3 mb-3 border-0 bg-info-subtle text-info-emphasis rounded-3 fs-8">
                            <strong class="text-dark">Pembeda Settlement Akuntansi:</strong> Saldo awal dibukukan sebagai transaksi <strong>STOCK_INITIAL</strong> untuk mengkapitalisasi aset persediaan awal periode cut-off (Debit CoA 11301/11302) dengan tandingan akun Rekening Antar Kantor (RAK) atau Ekuitas Saldo Awal (Kredit CoA 31101), dan tidak menimbulkan beban selisih operasional berjalan (51206) seperti pada Stock Opname fisik.
                        </div>

                        <label class="form-label fw-bold fs-8 text-secondary text-uppercase mb-1">
                            Berita Acara / Keterangan Penetapan Saldo Awal <span class="text-danger">*</span>
                        </label>
                        <textarea name="notes" rows="2" required class="form-control form-control-sm fs-8" placeholder="Contoh: Penetapan Saldo Awal Cut-Off Go-Live Gudang {{ $currentWarehouse?->name }} per {{ date('d M Y') }}..."></textarea>
                        <div class="form-text fs-9 text-secondary mt-1">
                            Kuantitas kondisi baik akan masuk sebagai stok siap pakai (on hand), sedangkan kuantitas rusak dialokasikan ke penampungan barang rusak (damaged stock).
                        </div>
                    </div>
                    <div class="col-12 col-lg-4 d-flex flex-column align-items-lg-end gap-2">
                        <div class="text-end">
                            <span class="fs-8 text-secondary">Total Valuasi: </span>
                            <span class="fs-6 fw-bold font-monospace text-danger" x-text="'Rp ' + getTotalValuation().toLocaleString('id-ID')"></span>
                        </div>
                        <button type="submit" class="btn btn-danger fw-bold shadow-xs px-4 py-2 w-100 w-lg-auto">
                            <span>Simpan & Posting Saldo Awal</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- ==================== IMPORT MODAL ==================== -->
    <div x-show="importModalOpen" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="importModalOpen = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <!-- Modal Header -->
            <div class="card-header bg-danger text-white py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fs-6 fw-bold mb-0">Import Saldo Awal Excel / CSV</h5>
                <button type="button" @click="importModalOpen = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <!-- Modal Form -->
            <form action="{{ route('inventory.initial_stock.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="cutoff_date" :value="cutoffDate">

                <div class="modal-body p-4 fs-8 space-y-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="text-secondary fs-9 fw-bold text-uppercase mb-1">Gudang Tujuan Impor</div>
                        <div class="fw-bold text-body fs-7">{{ $currentWarehouse?->name }} ({{ $currentWarehouse?->code }})</div>
                        <div class="text-secondary fs-9">{{ $currentWarehouse?->organization->name }} - {{ $currentWarehouse?->organization->city }}</div>
                    </div>

                    <div>
                        <label class="form-label fw-bold text-secondary fs-8">File CSV / Excel Format (*.csv) <span class="text-danger">*</span></label>
                        <input type="file" name="file" accept=".csv,text/csv,text/plain" required class="form-control form-control-sm fs-8">
                        <div class="form-text fs-9 text-secondary mt-1">
                            Gunakan format file template CSV Bank Jatim. Kolom mencakup SKU, Nama Barang, Qty Baik, Qty Rusak, dan Harga Satuan.
                        </div>
                    </div>

                    <div class="p-2 bg-light rounded-2xl border d-flex align-items-center justify-content-between">
                        <span class="fs-9 text-secondary">Belum memiliki template impor?</span>
                        <a :href="'{{ route('inventory.initial_stock.template') }}?warehouse_id={{ $selectedWarehouseId }}'" class="btn btn-sm btn-outline-danger fs-9 py-1 px-2">
                            <span>Download Template CSV</span>
                        </a>
                    </div>

                    <div class="form-check p-2 bg-body-tertiary rounded border">
                        <input class="form-check-input ms-1 me-2" type="checkbox" name="direct_post" value="1" id="directPostCheck">
                        <label class="form-check-label fs-8 fw-semibold text-body" for="directPostCheck">
                            Langsung Posting ke Buku Besar (Stock Ledger & Balances)
                        </label>
                        <div class="form-text fs-9 text-secondary ms-4">
                            Jika tidak dicentang, data file akan dimuat terlebih dahulu ke lembar kerja di layar untuk ditinjau sebelum disimpan.
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-bold text-secondary fs-8">Catatan Impor (Opsional)</label>
                        <input type="text" name="import_notes" class="form-control form-control-sm fs-8" placeholder="Contoh: Impor data migrasi saldo awal dari sistem lama...">
                    </div>
                </div>

                <div class="modal-footer bg-body-tertiary p-3 border-top d-flex justify-content-between">
                    <button type="button" @click="importModalOpen = false" class="btn btn-sm btn-outline-secondary fs-8 px-3">
                        <span>Batal</span>
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 px-4">
                        <span>Unggah & Proses File</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
