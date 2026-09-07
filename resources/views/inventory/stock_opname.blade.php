@extends('layouts.app')
@section('title', 'Stock Opname & Rekonsiliasi Fisik')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Stock Opname</li>
@endsection

@section('content')
<div class="space-y-3 select-none" x-data="{
    searchQuery: '',
    statusFilter: 'all',
    warehouseDropdownOpen: false,
    warehouseSearch: '',
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

    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ route('inventory.balances', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-light border fw-bold text-slate-700 shadow-xs">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Saldo Stok
        </a>
    </div>

    <!-- Warehouse Selector Header Bar (Compact & Searchable - Solves Infinite Scroll with 12+ Warehouses) -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Lokasi Gudang yang Di-Opname:</div>
                    <div class="text-sm font-black text-slate-900 flex items-center space-x-2">
                        <span>{{ $currentWarehouse?->name ?? 'Gudang Terpilih' }}</span>
                        @if($currentWarehouse)
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $currentWarehouse->type === 'CENTRAL_LOGISTICS' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $currentWarehouse->type === 'CENTRAL_LOGISTICS' ? 'Gudang Logistik Pusat' : 'Gudang Cabang' }}
                            </span>
                            <span class="text-[11px] text-slate-400 font-normal font-mono">({{ $currentWarehouse->code }} • {{ $currentWarehouse->organization->city }})</span>
                        @endif
                    </div>
                </div>
            </div>

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
                <div x-show="warehouseDropdownOpen" x-cloak class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden">
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
                            <a :href="'{{ route('inventory.stock_opname') }}?warehouse_id=' + wh.id" class="flex items-center justify-between p-3 hover:bg-purple-50/60 transition" :class="wh.id == '{{ $selectedWarehouseId }}' ? 'bg-purple-50 font-bold text-purple-700' : 'text-slate-700'">
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

    <!-- Opname Live Summary Metrics -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total SKU -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-xs font-semibold text-slate-500">SKU Terhitung</span>
                <i class="fa-solid fa-list-check text-xs text-slate-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-900" x-text="rows.length"></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Item dalam lembar hitung</div>
            </div>
        </div>

        <!-- 2. Discrepancy Count -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-xs font-semibold text-slate-500">Item Berselisih</span>
                <i class="fa-solid fa-triangle-exclamation text-xs text-amber-500"></i>
            </div>
            <div>
                <div class="text-xl font-bold" :class="getDiscrepancyCount() > 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="getDiscrepancyCount() + ' SKU'"></div>
                <div class="text-[11px] text-slate-400 mt-0.5" x-text="getDiscrepancyCount() > 0 ? 'Memerlukan penyesuaian' : 'Seluruh item cocok (Match)'"></div>
            </div>
        </div>

        <!-- 3. Net Qty Variance -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-xs font-semibold text-slate-500">Net Selisih Qty</span>
                <i class="fa-solid fa-scale-balanced text-xs text-slate-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold" :class="getNetVarianceQty() > 0 ? 'text-emerald-600' : (getNetVarianceQty() < 0 ? 'text-rose-600' : 'text-slate-900')" x-text="(getNetVarianceQty() > 0 ? '+' : '') + getNetVarianceQty() + ' Unit'"></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Total akumulasi selisih fisik</div>
            </div>
        </div>

        <!-- 4. Net Value Impact -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-xs font-semibold text-slate-500">Dampak Nilai Valuasi</span>
                <i class="fa-solid fa-money-bill-transfer text-xs text-slate-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold" :class="getNetVarianceValue() > 0 ? 'text-emerald-600' : (getNetVarianceValue() < 0 ? 'text-rose-600' : 'text-slate-900')" x-text="(getNetVarianceValue() >= 0 ? '+' : '') + 'Rp ' + Math.abs(getNetVarianceValue()).toLocaleString('id-ID')"></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Penyesuaian buku besar</div>
            </div>
        </div>
    </div>

    <!-- Opname Form -->
    <form action="{{ route('inventory.stock_opname.store') }}" method="POST" class="space-y-3">
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">

        <!-- Items Worksheet Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            
            <!-- Table Toolbar (Search, Filter, Batch Actions) -->
            <div class="p-4 border-b border-slate-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="flex items-center space-x-3 flex-1">
                    <div class="relative flex-1 max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" x-model="searchQuery" class="w-full h-9 box-border bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-jatim-700">
                    </div>

                    <!-- Status Filter Tabs -->
                    <div class="flex items-center bg-slate-100 p-0.5 rounded-lg text-xs font-semibold text-slate-600 h-9 box-border">
                        <button type="button" @click="statusFilter = 'all'" class="h-full px-2.5 rounded-md transition" :class="statusFilter === 'all' ? 'bg-white text-slate-900 shadow-xs font-bold' : 'hover:text-slate-900'">Semua</button>
                        <button type="button" @click="statusFilter = 'discrepancy'" class="h-full px-2.5 rounded-md transition" :class="statusFilter === 'discrepancy' ? 'bg-white text-rose-700 shadow-xs font-bold' : 'hover:text-slate-900'">Berselisih</button>
                        <button type="button" @click="statusFilter = 'match'" class="h-full px-2.5 rounded-md transition" :class="statusFilter === 'match' ? 'bg-white text-emerald-700 shadow-xs font-bold' : 'hover:text-slate-900'">Cocok</button>
                    </div>
                </div>

                <!-- Batch Helper Buttons -->
                <div class="flex items-center space-x-2">
                    <button type="button" @click="setAllMatch()" class="h-9 box-border inline-flex items-center space-x-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 rounded-lg transition">
                        <i class="fa-solid fa-equals text-[10px]"></i>
                        <span>Set Semua Cocok</span>
                    </button>
                    <button type="button" @click="setAllZero()" class="h-9 box-border inline-flex items-center space-x-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 rounded-lg transition">
                        <i class="fa-solid fa-rotate-left text-[10px]"></i>
                        <span>Reset Hitungan</span>
                    </button>
                </div>
            </div>

            <!-- Table View -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/80 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Item & SKU</th>
                            <th class="py-3 px-4">Kategori</th>
                            <th class="py-3 px-4 text-center w-28">Stok Sistem</th>
                            <th class="py-3 px-4 text-center w-40">Hasil Hitung Fisik</th>
                            <th class="py-3 px-4 text-center w-36">Selisih (+/-)</th>
                            <th class="py-3 px-4 text-right w-36">Estimasi Selisih (Rp)</th>
                            <th class="py-3 px-4 text-center w-28">Status Audit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, idx) in rows" :key="row.item_id">
                            <tr :class="getVariance(row) !== 0 ? 'bg-amber-50/40 hover:bg-amber-50/70' : 'hover:bg-slate-50/80'"
                                x-show="(
                                    (!searchQuery.trim() || (row.name + ' ' + row.sku + ' ' + row.category).toLowerCase().includes(searchQuery.toLowerCase())) &&
                                    (statusFilter === 'all' || 
                                     (statusFilter === 'discrepancy' && getVariance(row) !== 0) || 
                                     (statusFilter === 'match' && getVariance(row) === 0))
                                )"
                                class="transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900" x-text="row.name"></div>
                                    <div class="text-[10px] text-slate-400 font-mono" x-text="row.sku + ' • ' + row.uom"></div>
                                    <input type="hidden" :name="'counts[' + idx + '][item_id]'" :value="row.item_id">
                                    <input type="hidden" :name="'counts[' + idx + '][system_qty]'" :value="row.system_qty">
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px] font-semibold" x-text="row.category"></span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-bold text-slate-800 text-sm" x-text="row.system_qty + ' ' + row.uom"></td>
                                <td class="py-3.5 px-4 text-center">
                                    <div class="inline-flex items-center space-x-1">
                                        <button type="button" @click="if(row.physical_qty > 0) row.physical_qty--" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs">-</button>
                                        <input type="number" :name="'counts[' + idx + '][physical_qty]'" x-model.number="row.physical_qty" min="0" required class="w-20 bg-white border border-slate-300 rounded-lg py-1 px-2 text-center text-xs font-black text-slate-900 focus:ring-2 focus:ring-jatim-700 focus:outline-none">
                                        <button type="button" @click="row.physical_qty++" class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs">+</button>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 text-center font-black text-xs">
                                    <span :class="getVariance(row) > 0 ? 'text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200' : (getVariance(row) < 0 ? 'text-rose-600 bg-rose-50 px-2 py-0.5 rounded border border-rose-200' : 'text-slate-400')" x-text="(getVariance(row) > 0 ? '+' : '') + getVariance(row) + ' ' + row.uom"></span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono text-xs" :class="getVarianceValue(row) > 0 ? 'text-emerald-600 font-bold' : (getVarianceValue(row) < 0 ? 'text-rose-600 font-bold' : 'text-slate-400')" x-text="(getVarianceValue(row) >= 0 ? '+' : '') + 'Rp ' + Math.abs(getVarianceValue(row)).toLocaleString('id-ID')"></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full inline-block" :class="getVariance(row) === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'" x-text="getVariance(row) === 0 ? 'MATCH' : 'SELISIH'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Notes & Submission Footer -->
            <div class="bg-slate-50 p-6 border-t border-slate-200 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Berita Acara / Keterangan Pelaksanaan Opname</label>
                    <textarea name="opname_notes" rows="2" required class="w-full bg-white border border-slate-200 rounded-xl p-3 text-xs focus:ring-2 focus:ring-jatim-700 focus:outline-none"></textarea>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2">
                    <div class="text-xs text-slate-500">
                        Penyesuaian selisih otomatis tercatat sebagai transaksi <strong class="text-slate-800">STOCK_OPNAME</strong> pada buku besar stok (*Stock Ledger*).
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-jatim-700 hover:bg-jatim-800 text-white rounded-xl text-xs font-bold shadow-md transition inline-flex items-center space-x-2 flex-shrink-0">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Simpan & Posting Hasil Opname</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
