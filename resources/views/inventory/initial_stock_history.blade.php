@extends('layouts.app')
@section('title', 'Riwayat Saldo Awal Gudang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.initial_stock.index') }}" class="text-decoration-none text-danger">Saldo Awal Gudang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Riwayat</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
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

    <!-- Warehouse Selector Header Bar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-danger-subtle text-danger flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Lokasi Gudang / Unit Terpilih:</div>
                    <div class="text-sm font-black text-slate-900 flex items-center space-x-2">
                        <span>
                            @if($selectedWarehouseId === 'all')
                                Seluruh Gudang & Cabang (Semua Lokasi)
                            @else
                                {{ $warehouses->firstWhere('id', $selectedWarehouseId)?->name ?? 'Gudang Terpilih' }}
                            @endif
                        </span>
                        @if($selectedWarehouseId !== 'all' && ($currentWh = $warehouses->firstWhere('id', $selectedWarehouseId)))
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $currentWh->type === 'CENTRAL_LOGISTICS' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $currentWh->type === 'CENTRAL_LOGISTICS' ? 'Gudang Logistik Pusat' : 'Penyimpanan Cabang' }}
                            </span>
                            <span class="text-[11px] text-slate-400 font-normal font-mono">({{ $currentWh->code }} • {{ $currentWh->organization->city }})</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Controls: Warehouse Selector & New Input Button -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Searchable Dropdown Button -->
                <div class="relative" @click.away="warehouseDropdownOpen = false">
                    <button @click="warehouseDropdownOpen = !warehouseDropdownOpen" type="button" class="w-full md:w-auto inline-flex items-center justify-between space-x-3 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-800 text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-sm">
                        <span>Ganti Lokasi Gudang</span>
                        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition" :class="warehouseDropdownOpen && 'rotate-180'"></i>
                    </button>

                    <!-- Searchable Dropdown Popup -->
                    <div x-show="warehouseDropdownOpen" x-cloak class="absolute right-0 mt-2 w-72 sm:w-96 max-w-[calc(100vw-2rem)] bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden">
                        <div class="p-3 border-b border-slate-100 bg-slate-50">
                            <div class="relative">
                                <input type="text" x-model="warehouseSearch" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-danger focus:outline-none" placeholder="Cari gudang / cabang...">
                            </div>
                        </div>

                        <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                            <!-- Option All Warehouses -->
                            <a href="{{ route('inventory.initial_stock.history', ['warehouse_id' => 'all', 'search' => $search]) }}" class="flex items-center justify-between p-3 hover:bg-danger-subtle/40 transition {{ $selectedWarehouseId === 'all' ? 'bg-danger-subtle font-bold text-danger' : 'text-slate-700' }}">
                                <div>
                                    <div class="text-xs">Semua Gudang & Cabang</div>
                                    <div class="text-[10px] text-slate-400">Lihat seluruh riwayat saldo awal</div>
                                </div>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-700">SEMUA</span>
                            </a>

                            <template x-for="wh in filteredWarehouses" :key="wh.id">
                                <a :href="'{{ route('inventory.initial_stock.history') }}?warehouse_id=' + wh.id + '&search={{ urlencode($search ?? '') }}'" class="flex items-center justify-between p-3 hover:bg-danger-subtle/40 transition" :class="wh.id == '{{ $selectedWarehouseId }}' ? 'bg-danger-subtle font-bold text-danger' : 'text-slate-700'">
                                    <div>
                                        <div class="text-xs" x-text="wh.name"></div>
                                        <div class="text-[10px] text-slate-400 font-normal" x-text="wh.code + ' • ' + wh.city"></div>
                                    </div>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded" :class="wh.type === 'CENTRAL_LOGISTICS' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'" x-text="wh.type === 'CENTRAL_LOGISTICS' ? 'PUSAT' : 'CABANG'"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Input Saldo Awal Button -->
                <a href="{{ route('inventory.initial_stock.index', ['warehouse_id' => $selectedWarehouseId !== 'all' ? $selectedWarehouseId : ($warehouses->first()?->id ?? 1)]) }}" class="inline-flex items-center space-x-1.5 bg-danger text-white hover:bg-danger-emphasis text-xs font-semibold px-4 py-2.5 rounded-xl transition shadow-sm">
                    <span>Input Saldo Awal</span>
                </a>
            </div>
        </div>
    </div>

    <!-- History Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <!-- Table Toolbar & Filters -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form method="GET" action="{{ route('inventory.initial_stock.history') }}" class="row g-2 align-items-center">
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm border-start-0 fs-8" placeholder="Cari nomor referensi, nama barang, atau catatan...">
                        @if($search)
                            <a href="{{ route('inventory.initial_stock.history', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-secondary border-start-0" title="Clear">
                                <i class="bi bi-x"></i>
                            </a>
                        @endif
                        <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8">Cari</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table View -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-8">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="min-width: 170px;">Tanggal & No. Ref</th>
                            <th class="py-3" style="min-width: 180px;">Lokasi Gudang</th>
                            <th class="py-3" style="min-width: 200px;">Barang (Item & SKU)</th>
                            <th class="py-3 text-center" style="min-width: 100px;">Qty Masuk</th>
                            <th class="py-3 text-center" style="min-width: 100px;">Saldo Akhir</th>
                            <th class="py-3 text-end" style="min-width: 130px;">Harga Satuan</th>
                            <th class="py-3 text-end" style="min-width: 140px;">Total Valuasi</th>
                            <th class="py-3" style="min-width: 130px;">Petugas</th>
                            <th class="pe-4 py-3" style="min-width: 180px;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historyLedgers as $row)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold font-monospace text-dark">{{ $row->reference_number }}</div>
                                    <div class="text-secondary fs-9">{{ $row->created_at->format('d/m/Y H:i') }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-semibold text-body">{{ $row->warehouse->name }}</div>
                                    <div class="text-secondary fs-9">{{ $row->warehouse->code }} • {{ $row->warehouse->organization->city }}</div>
                                </td>
                                <td class="py-3">
                                    <div class="fw-semibold text-body">{{ $row->item->name }}</div>
                                    <div class="text-secondary font-monospace fs-9">{{ $row->item->sku }} • {{ $row->item->category?->name ?? '-' }}</div>
                                </td>
                                <td class="py-3 text-center font-monospace fw-bold text-success">
                                    +{{ number_format($row->qty_in) }} {{ $row->item->uom }}
                                </td>
                                <td class="py-3 text-center font-monospace fw-semibold text-body">
                                    {{ number_format($row->balance_after) }} {{ $row->item->uom }}
                                </td>
                                <td class="py-3 text-end font-monospace">
                                    Rp {{ number_format($row->unit_cost, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end font-monospace fw-bold text-dark">
                                    Rp {{ number_format($row->total_value, 0, ',', '.') }}
                                </td>
                                <td class="py-3">
                                    <div class="fw-semibold text-body fs-9">{{ $row->creator?->name ?? 'Sistem' }}</div>
                                </td>
                                <td class="pe-4 py-3 text-secondary fs-9">
                                    {{ $row->notes ?: '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <i class="bi bi-inbox text-secondary fs-1 mb-2"></i>
                                        <span class="text-secondary fw-medium">Belum ada riwayat saldo awal pada filter yang dipilih</span>
                                        @if($search)
                                            <a href="{{ route('inventory.initial_stock.history', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-danger mt-3">
                                                <span>Reset Pencarian</span>
                                            </a>
                                        @else
                                            <a href="{{ route('inventory.initial_stock.index', ['warehouse_id' => $selectedWarehouseId !== 'all' ? $selectedWarehouseId : ($warehouses->first()?->id ?? 1)]) }}" class="btn btn-sm btn-danger mt-3">
                                                <span>Input Saldo Awal Sekarang</span>
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
        <x-pagination-footer :paginator="$historyLedgers" :perPage="$perPage" />
    </div>

</div>
@endsection
