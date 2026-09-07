@extends('layouts.app')
@section('title', 'Stock Balances & Lokasi Gudang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Persediaan</li>
    <li class="breadcrumb-item active" aria-current="page">Stock Balances</li>
@endsection

@section('content')
<div class="space-y-3 select-none" x-data="{
    searchQuery: '',
    selectedCategory: 'all',
    stockFilter: 'all',
    balanceCurrentPage: 1,
    balancePerPage: 15,
    warehouseDropdownOpen: false,
    warehouseSearch: '',
    rawBalances: {{ Js::from($balances->map(fn($sb) => [
        'id' => $sb->id,
        'item_name' => $sb->item->name,
        'item_sku' => $sb->item->sku,
        'item_category' => $sb->item->category->name,
        'available' => $sb->available,
        'damaged' => $sb->damaged,
        'reserved' => $sb->reserved,
    ])) }},
    get filteredBalances() {
        const q = this.searchQuery.toLowerCase().trim();
        return this.rawBalances.filter(b => {
            const matchesSearch = !q || (b.item_name + ' ' + b.item_sku + ' ' + b.item_category).toLowerCase().includes(q);
            const matchesFilter = this.stockFilter === 'all' ||
                (this.stockFilter === 'available' && b.available > 0) ||
                (this.stockFilter === 'damaged' && b.damaged > 0) ||
                (this.stockFilter === 'reserved' && b.reserved > 0);
            return matchesSearch && matchesFilter;
        });
    },
    get balanceTotalPages() {
        return Math.max(1, Math.ceil(this.filteredBalances.length / this.balancePerPage));
    },
    get balanceFirstItem() {
        if (this.filteredBalances.length === 0) return 0;
        return (this.balanceCurrentPage - 1) * this.balancePerPage + 1;
    },
    get balanceLastItem() {
        return Math.min(this.filteredBalances.length, this.balanceCurrentPage * this.balancePerPage);
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
    }
}">

    <!-- Action Bar -->
    <div class="d-flex flex-column flex-sm-row justify-content-end align-items-stretch align-items-sm-center gap-2">
        <a href="{{ route('inventory.stock_opname', ['warehouse_id' => $selectedWarehouseId !== 'all' ? $selectedWarehouseId : 1]) }}" class="btn btn-sm btn-light border fw-bold text-slate-700 shadow-xs d-inline-flex align-items-center justify-content-center">
            <i class="bi bi-boxes me-1"></i> Stock Opname
        </a>
        <a href="{{ route('inventory.forecasting') }}" class="btn btn-sm btn-warning text-white fw-bold shadow-xs d-inline-flex align-items-center justify-content-center">
            <i class="bi bi-graph-up me-1"></i> Forecasting & ROP
        </a>
    </div>

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

    <!-- KPI Summary Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Total SKU -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-xs font-semibold text-slate-500">SKU Terdaftar</span>
                <i class="fa-solid fa-boxes-stacked text-xs text-slate-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-900">{{ number_format($totalSkuCount) }}</div>
                <div class="text-[11px] text-slate-400 mt-0.5">Jenis item persediaan</div>
            </div>
        </div>

        <!-- 2. Fisik On Hand -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-xs font-semibold text-slate-500">Saldo On Hand</span>
                <i class="fa-solid fa-cubes text-xs text-slate-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-900">{{ number_format($totalOnHand) }} <span class="text-xs text-slate-400 font-normal">Unit</span></div>
                <div class="text-[11px] text-amber-600 mt-0.5 font-medium">Reserved: {{ number_format($totalReserved) }} • Rusak: {{ number_format($totalDamaged) }}</div>
            </div>
        </div>

        <!-- 3. Stok Bebas (Available) -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-xs font-semibold text-slate-500">Stok Bebas (Available)</span>
                <i class="fa-solid fa-circle-check text-xs text-emerald-500"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-emerald-700">{{ number_format($totalAvailable) }} <span class="text-xs text-emerald-600 font-normal">Unit</span></div>
                <div class="text-[11px] text-emerald-600 mt-0.5 font-medium">Siap dialokasikan ke cabang</div>
            </div>
        </div>

        <!-- 4. Total Valuasi -->
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-slate-500">
                <span class="text-xs font-semibold text-slate-500">Valuasi Persediaan</span>
                <i class="fa-solid fa-vault text-xs text-slate-400"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-900">Rp {{ number_format($totalValuation, 0, ',', '.') }}</div>
                <div class="text-[11px] text-slate-400 mt-0.5">Nilai perolehan rata-rata</div>
            </div>
        </div>
    </div>

    <!-- Stock Formula Legend Bar -->
    <div class="bg-white px-4 py-2.5 rounded-xl border border-slate-200/80 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs text-slate-600">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-calculator text-slate-400 text-xs"></i>
            <span class="font-bold text-slate-800 text-[11px]">Formula Persediaan JIMS:</span>
        </div>
        <div class="flex items-center space-x-2 font-mono text-[11px] flex-wrap">
            <span class="bg-emerald-50 text-emerald-800 px-2 py-0.5 rounded font-bold border border-emerald-200">Available</span>
            <span class="text-slate-400">=</span>
            <span class="bg-slate-100 text-slate-800 px-2 py-0.5 rounded border border-slate-200">On Hand</span>
            <span class="text-slate-400">-</span>
            <span class="bg-amber-50 text-amber-800 px-2 py-0.5 rounded border border-amber-200">Reserved</span>
            <span class="text-slate-400">-</span>
            <span class="bg-rose-50 text-rose-800 px-2 py-0.5 rounded border border-rose-200">Damaged</span>
            <span class="text-slate-400">-</span>
            <span class="bg-slate-100 text-slate-800 px-2 py-0.5 rounded border border-slate-200">Hold</span>
        </div>
    </div>

    <!-- Table Container Card (Clean SaaS Styling) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        
        <!-- Table Search & Filter Bar -->
        <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="relative flex-1 max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="text" x-model="searchQuery" @input="balanceCurrentPage = 1" class="w-full h-9 box-border bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-jatim-700">
            </div>

            <div class="flex items-center space-x-2">
                <span class="text-xs text-slate-400 font-semibold">Filter Stok:</span>
                <select x-model="stockFilter" @change="balanceCurrentPage = 1" class="h-9 box-border bg-slate-50 border border-slate-200 rounded-lg px-3 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-jatim-700 cursor-pointer">
                    <option value="all">Semua Saldo</option>
                    <option value="available">Hanya Stok Tersedia (>0)</option>
                    <option value="damaged">Ada Rusak / Damaged</option>
                    <option value="reserved">Ada Reserved Order</option>
                </select>
            </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4">Item & SKU</th>
                        <th class="py-3 px-4">Kategori</th>
                        @if($selectedWarehouseId === 'all')
                            <th class="py-3 px-4">Lokasi Gudang</th>
                        @endif
                        <th class="py-3 px-4 text-center">On Hand</th>
                        <th class="py-3 px-4 text-center">Reserved</th>
                        <th class="py-3 px-4 text-center">Damaged</th>
                        <th class="py-3 px-4 text-center">Available</th>
                        <th class="py-3 px-4 text-right">Harga Satuan</th>
                        <th class="py-3 px-4 text-right">Total Valuasi</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($balances as $sb)
                        <tr class="hover:bg-slate-50/80 transition"
                            x-show="(
                                (!searchQuery.trim() || '{{ strtolower($sb->item->name) }} {{ strtolower($sb->item->sku) }} {{ strtolower($sb->item->category->name) }}'.includes(searchQuery.toLowerCase())) &&
                                (stockFilter === 'all' || 
                                 (stockFilter === 'available' && {{ $sb->available }} > 0) || 
                                 (stockFilter === 'damaged' && {{ $sb->damaged }} > 0) || 
                                 (stockFilter === 'reserved' && {{ $sb->reserved }} > 0))
                            ) && (
                                filteredBalances.findIndex(b => b.id === {{ $sb->id }}) >= (balanceCurrentPage - 1) * balancePerPage &&
                                filteredBalances.findIndex(b => b.id === {{ $sb->id }}) < balanceCurrentPage * balancePerPage
                            )">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900">{{ $sb->item->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $sb->item->sku }} • {{ $sb->item->uom }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 font-medium">
                                <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[10px] font-semibold">
                                    {{ $sb->item->category->name }}
                                </span>
                            </td>
                            @if($selectedWarehouseId === 'all')
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800">{{ $sb->warehouse->name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ $sb->warehouse->organization->code }}</div>
                                </td>
                            @endif
                            <td class="py-3.5 px-4 text-center font-bold text-slate-800 text-sm">
                                {{ number_format($sb->on_hand) }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-amber-600">
                                {{ number_format($sb->reserved) }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-rose-600">
                                {{ number_format($sb->damaged) }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-black {{ $sb->available > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' }}">
                                    {{ number_format($sb->available) }} {{ $sb->item->uom }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right text-slate-600 font-mono">
                                Rp {{ number_format($sb->item->estimated_unit_price, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-slate-900 font-mono">
                                Rp {{ number_format($sb->on_hand * $sb->item->estimated_unit_price, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <a href="{{ route('inventory.stock_card', ['itemId' => $sb->item_id, 'warehouse_id' => $sb->warehouse_id]) }}" class="inline-flex items-center space-x-1 bg-slate-100 hover:bg-jatim-700 hover:text-white text-slate-700 px-2.5 py-1 rounded-lg text-xs font-bold transition">
                                    <i class="fa-solid fa-list-check text-[10px]"></i>
                                    <span>Kartu Stok</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <i class="fa-solid fa-box-open text-2xl text-slate-300"></i>
                                    <span>Belum ada saldo barang pada gudang yang dipilih.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div class="px-5 py-3.5 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500 bg-white">
            <div>
                Menampilkan
                <span class="font-bold text-slate-800" x-text="balanceFirstItem"></span>
                sampai
                <span class="font-bold text-slate-800" x-text="balanceLastItem"></span>
                dari
                <span class="font-bold text-slate-800" x-text="filteredBalances.length"></span>
                data
            </div>

            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-1.5">
                    <span class="text-slate-400 font-medium">Per page:</span>
                    <select x-model.number="balancePerPage" @change="balanceCurrentPage = 1" class="h-8 bg-slate-50 border border-slate-200 rounded-lg px-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-1 focus:ring-jatim-700 cursor-pointer">
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                <div class="flex items-center space-x-1" x-show="balanceTotalPages > 1">
                    <button type="button" @click="balanceCurrentPage = 1" :disabled="balanceCurrentPage === 1" class="px-2 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none transition cursor-pointer" title="Halaman Pertama">
                        <i class="fa-solid fa-angles-left text-[10px]"></i>
                    </button>
                    <button type="button" @click="balanceCurrentPage = Math.max(1, balanceCurrentPage - 1)" :disabled="balanceCurrentPage === 1" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none transition cursor-pointer" title="Sebelumnya">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    </button>
                    
                    <template x-for="p in balanceTotalPages" :key="p">
                        <button type="button" @click="balanceCurrentPage = p" class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer" :class="balanceCurrentPage === p ? 'bg-jatim-700 text-white shadow-xs border border-jatim-700' : 'border border-slate-200 text-slate-700 hover:bg-slate-50'" x-text="p"></button>
                    </template>

                    <button type="button" @click="balanceCurrentPage = Math.min(balanceTotalPages, balanceCurrentPage + 1)" :disabled="balanceCurrentPage === balanceTotalPages" class="px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none transition cursor-pointer" title="Selanjutnya">
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                    <button type="button" @click="balanceCurrentPage = balanceTotalPages" :disabled="balanceCurrentPage === balanceTotalPages" class="px-2 py-1 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none transition cursor-pointer" title="Halaman Terakhir">
                        <i class="fa-solid fa-angles-right text-[10px]"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
