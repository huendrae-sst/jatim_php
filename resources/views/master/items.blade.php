@extends('layouts.app')
@section('title', 'Master Data Barang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('master.items') }}" class="text-decoration-none text-danger">Master Data</a></li>
    <li class="breadcrumb-item active" aria-current="page">Master Barang</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    activeMainTab: (new URLSearchParams(window.location.search)).get('tab') || window.location.hash.replace('#', '') || '{{ $tab ?? 'items' }}',

    init() {
        const validTabs = ['items', 'categories', 'uoms', 'conversions'];
        const urlParams = new URLSearchParams(window.location.search);
        let tabFromUrl = urlParams.get('tab') || window.location.hash.replace('#', '');
        if (!tabFromUrl && sessionStorage.getItem('jatim_tab_' + window.location.pathname)) {
            tabFromUrl = sessionStorage.getItem('jatim_tab_' + window.location.pathname);
        }
        if (tabFromUrl && validTabs.includes(tabFromUrl)) {
            this.activeMainTab = tabFromUrl;
        }
        this.$watch('activeMainTab', (val) => {
            if (validTabs.includes(val)) {
                sessionStorage.setItem('jatim_tab_' + window.location.pathname, val);
                const url = new URL(window.location);
                url.searchParams.set('tab', val);
                window.history.replaceState({}, '', url);
            }
        });
        window.addEventListener('popstate', () => {
            const p = new URLSearchParams(window.location.search);
            const t = p.get('tab') || window.location.hash.replace('#', '');
            if (t && validTabs.includes(t)) {
                this.activeMainTab = t;
            }
        });
    },

    createModal: false,
    createUom: 'PCS',
    allItemsCatalog: {{ Js::from($allItems->map(fn($it) => ['id' => $it->id, 'sku' => $it->sku, 'name' => $it->name, 'uom' => $it->uom])) }},
    viewModal: false,
    editModal: false,
    deleteModal: false,
    createCategoryModal: false,
    viewCategoryModal: false,
    editCategoryModal: false,
    deleteCategoryModal: false,
    selectedCategory: {
        id: null,
        code: '',
        name: '',
        description: '',
        items_count: 0
    },
    categoryUpdateUrl: '',
    categoryDeleteUrl: '',
    createUomModal: false,
    viewUomModal: false,
    editUomModal: false,
    deleteUomModal: false,
    selectedUom: {
        code: '',
        name: '',
        type: '',
        description: '',
        items_count: 0
    },
    uomUpdateUrl: '',
    uomDeleteUrl: '',
    createConversionModal: false,
    viewConversionModal: false,
    editConversionModal: false,
    deleteConversionModal: false,
    selectedConversion: {
        id: null,
        item_id: '',
        item_sku: '',
        item_name: '',
        from_uom: '',
        conversion_factor: 1,
        to_uom: '',
        description: '',
        is_active: 1
    },
    conversionUpdateUrl: '',
    conversionDeleteUrl: '',
    selectedItem: {
        id: null,
        category_id: '',
        category_name: '',
        sku: '',
        barcode: '',
        name: '',
        uom: 'PCS',
        specification: '',
        estimated_unit_price: 0,
        min_stock: 10,
        max_stock: 500,
        safety_stock: 20,
        reorder_point: 30,
        lead_time_days: 5,
        is_active: 1,
        stock_balances: []
    },
    updateUrl: '',
    deleteUrl: '',
    openViewModal(item) {
        this.selectedItem = {
            id: item.id,
            category_id: item.category_id,
            category_name: item.category ? item.category.name : '-',
            sku: item.sku || '-',
            barcode: item.barcode || '-',
            name: item.name || '-',
            uom: item.uom || '-',
            specification: item.specification || '-',
            estimated_unit_price: item.estimated_unit_price || 0,
            min_stock: item.min_stock || 0,
            max_stock: item.max_stock || 0,
            safety_stock: item.safety_stock || 0,
            reorder_point: item.reorder_point || 0,
            lead_time_days: item.lead_time_days || 0,
            is_active: item.is_active ? 1 : 0,
            stock_balances: item.stock_balances || []
        };
        this.viewModal = true;
    },
    openEditModal(item) {
        this.selectedItem = {
            id: item.id,
            category_id: item.category_id,
            category_name: item.category ? item.category.name : '-',
            sku: item.sku || '',
            barcode: item.barcode || '',
            name: item.name || '',
            uom: item.uom || 'PCS',
            specification: item.specification || '',
            estimated_unit_price: item.estimated_unit_price || 0,
            min_stock: item.min_stock ?? 10,
            max_stock: item.max_stock ?? 500,
            safety_stock: item.safety_stock ?? 20,
            reorder_point: item.reorder_point ?? 30,
            lead_time_days: item.lead_time_days ?? 5,
            is_active: item.is_active ? 1 : 0,
            stock_balances: item.stock_balances || []
        };
        this.updateUrl = '/master/items/' + item.id;
        this.viewModal = false;
        this.editModal = true;
    },
    openDeleteModal(item) {
        this.selectedItem = {
            id: item.id,
            sku: item.sku,
            name: item.name
        };
        this.deleteUrl = '/master/items/' + item.id;
        this.deleteModal = true;
    },
    openViewCategoryModal(cat) {
        this.selectedCategory = {
            id: cat.id,
            code: cat.code || '-',
            name: cat.name || '-',
            description: cat.description || '-',
            items_count: cat.items_count || 0
        };
        this.viewCategoryModal = true;
    },
    openEditCategoryModal(cat) {
        this.selectedCategory = {
            id: cat.id,
            code: cat.code || '',
            name: cat.name || '',
            description: cat.description || ''
        };
        this.categoryUpdateUrl = '/master/categories/' + cat.id;
        this.editCategoryModal = true;
    },
    openDeleteCategoryModal(cat) {
        this.selectedCategory = {
            id: cat.id,
            code: cat.code,
            name: cat.name,
            items_count: cat.items_count || 0
        };
        this.categoryDeleteUrl = '/master/categories/' + cat.id;
        this.deleteCategoryModal = true;
    },
    openViewUomModal(uom) {
        this.selectedUom = {
            code: uom.code || '-',
            name: uom.name || '-',
            type: uom.type || 'Kuantitas',
            description: uom.description || '-',
            items_count: uom.items_count || 0
        };
        this.viewUomModal = true;
    },
    openEditUomModal(uom) {
        this.selectedUom = {
            code: uom.code || '',
            name: uom.name || '',
            type: uom.type || 'Kuantitas',
            description: uom.description || ''
        };
        this.uomUpdateUrl = '/master/uoms/' + encodeURIComponent(uom.code);
        this.editUomModal = true;
    },
    openDeleteUomModal(uom) {
        this.selectedUom = {
            code: uom.code,
            name: uom.name,
            items_count: uom.items_count || 0
        };
        this.uomDeleteUrl = '/master/uoms/' + encodeURIComponent(uom.code);
        this.deleteUomModal = true;
    },
    openViewConversionModal(conv) {
        this.selectedConversion = {
            id: conv.id,
            item_id: conv.item_id || '',
            item_sku: conv.item ? conv.item.sku : '',
            item_name: conv.item ? conv.item.name : 'Semua Barang (Global)',
            from_uom: conv.from_uom || '',
            conversion_factor: parseFloat(conv.conversion_factor) || 1,
            to_uom: conv.to_uom || '',
            description: conv.description || '-',
            is_active: conv.is_active ? 1 : 0
        };
        this.viewConversionModal = true;
    },
    openEditConversionModal(conv) {
        this.selectedConversion = {
            id: conv.id,
            item_id: conv.item_id || '',
            item_sku: conv.item ? conv.item.sku : '',
            item_name: conv.item ? conv.item.name : 'Semua Barang (Global)',
            from_uom: conv.from_uom || '',
            conversion_factor: parseFloat(conv.conversion_factor) || 1,
            to_uom: conv.to_uom || '',
            description: conv.description || '',
            is_active: conv.is_active ? 1 : 0
        };
        this.conversionUpdateUrl = '/master/conversions/' + conv.id;
        this.editConversionModal = true;
    },
    openDeleteConversionModal(conv) {
        this.selectedConversion = {
            id: conv.id,
            item_id: conv.item_id || '',
            item_sku: conv.item ? conv.item.sku : '',
            item_name: conv.item ? conv.item.name : 'Semua Barang (Global)',
            from_uom: conv.from_uom || '',
            conversion_factor: parseFloat(conv.conversion_factor) || 1,
            to_uom: conv.to_uom || '',
            description: conv.description || '',
            is_active: conv.is_active ? 1 : 0
        };
        this.conversionDeleteUrl = '/master/conversions/' + conv.id;
        this.deleteConversionModal = true;
    },
    formatRupiah(num) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num || 0);
    }
}">

    <!-- Main Card -->
    <div class="card card-outline card-danger shadow-xs">
        <!-- Card Header with Navigation Tabs (Mobile-first scrollable) -->
        <div class="card-header bg-body p-2 px-3 border-bottom d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2">
            <ul class="nav nav-pills card-header-pills nav-pills-scroll m-0">
                <li class="nav-item">
                    <button type="button" 
                            @click="activeMainTab = 'items'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeMainTab === 'items' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-boxes"></i>
                        <span>Master Barang</span>
                        <span class="badge" :class="activeMainTab === 'items' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $totalItemsCount }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" 
                            @click="activeMainTab = 'categories'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeMainTab === 'categories' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-tags"></i>
                        <span>Kategori Barang</span>
                        <span class="badge" :class="activeMainTab === 'categories' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $categories->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" 
                            @click="activeMainTab = 'uoms'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeMainTab === 'uoms' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-rulers"></i>
                        <span>Satuan Unit (UOM)</span>
                        <span class="badge" :class="activeMainTab === 'uoms' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ count($uomList) }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" 
                            @click="activeMainTab = 'conversions'" 
                            class="nav-link py-1.5 px-3 fs-8 fw-bold d-inline-flex align-items-center gap-2 text-nowrap"
                            :class="activeMainTab === 'conversions' ? 'active bg-danger text-white' : 'text-body-secondary'">
                        <i class="bi bi-arrow-left-right"></i>
                        <span>Konversi Satuan</span>
                        <span class="badge" :class="activeMainTab === 'conversions' ? 'text-bg-light text-danger' : 'bg-secondary-subtle text-secondary'">{{ $conversions->total() }}</span>
                    </button>
                </li>
            </ul>
            <div class="card-tools ms-md-auto">
                <template x-if="activeMainTab === 'items'">
                    <button type="button" @click="createModal = true; createUom = 'PCS'" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Master Barang</span>
                    </button>
                </template>
                <template x-if="activeMainTab === 'categories'">
                    <button type="button" @click="createCategoryModal = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Kategori Baru</span>
                    </button>
                </template>
                <template x-if="activeMainTab === 'uoms'">
                    <button type="button" @click="createUomModal = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Satuan Baru</span>
                    </button>
                </template>
                <template x-if="activeMainTab === 'conversions'">
                    <button type="button" @click="createConversionModal = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-plus-circle"></i>
                        <span>Tambah Konversi Satuan</span>
                    </button>
                </template>
            </div>
        </div>

        <!-- ==================== TAB 1: MASTER BARANG ==================== -->
        <div x-show="activeMainTab === 'items'">
            <!-- Filter & Search Toolbar -->
            <div class="card-body p-3 bg-body-tertiary border-bottom">
                <form action="{{ route('master.items') }}" method="GET">
                    <input type="hidden" name="tab" value="items">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <div class="row g-2 align-items-center">
                        <!-- Category Filter -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-tag"></i></span>
                                <select name="category_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                    <option value="ALL">Semua Kategori</option>
                                    @foreach($allCategories ?? $categories as $cat)
                                        <option value="{{ $cat->id }}" {{ (string)$categoryId === (string)$cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
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
                                    <option value="ACTIVE" {{ $status === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                    <option value="INACTIVE" {{ $status === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                                </select>
                            </div>
                        </div>

                        <!-- Reset Button -->
                        @if($search || ($categoryId && $categoryId !== 'ALL') || $status)
                            <div class="col-auto">
                                <a href="{{ route('master.items', ['tab' => 'items']) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
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
                                       class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                                <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        <!-- Table -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-3 py-3" style="width: 150px;">SKU / Barcode</th>
                            <th class="py-3" style="min-width: 220px;">Nama Barang</th>
                            <th class="py-3" style="width: 140px;">Kategori</th>
                            <th class="text-center py-3" style="width: 80px;">Satuan</th>
                            <th class="text-center py-3" style="width: 100px;">Safety Stock</th>
                            <th class="text-center py-3" style="width: 125px;" title="Reorder Point (Titik Pemesanan Ulang)">Reorder Point (ROP)</th>
                            <th class="text-center py-3" style="width: 90px;">Lead Time</th>
                            <th class="text-end py-3" style="min-width: 130px;">Est. Harga Satuan</th>
                            <th class="text-center py-3" style="width: 90px;">Status</th>
                            <th class="text-center pe-3 py-3" style="width: 110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $it)
                            <tr>
                                <!-- SKU / Barcode -->
                                <td class="ps-3">
                                    <div class="font-monospace fw-bold text-danger">{{ $it->sku }}</div>
                                    @if($it->barcode)
                                        <div class="font-monospace text-secondary fs-9"><i class="bi bi-upc me-1"></i>{{ $it->barcode }}</div>
                                    @endif
                                </td>

                                <!-- Nama Barang -->
                                <td>
                                    <div class="fw-bold text-body">{{ $it->name }}</div>
                                    @if($it->specification)
                                        <div class="text-secondary fs-8 text-truncate" style="max-width: 280px;" title="{{ $it->specification }}">
                                            {{ $it->specification }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Kategori -->
                                <td>
                                    <span class="badge text-bg-light border text-secondary fs-8 fw-semibold">
                                        {{ $it->category ? $it->category->name : '-' }}
                                    </span>
                                </td>

                                <!-- Satuan -->
                                <td class="text-center">
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fw-bold">
                                        {{ $it->uom }}
                                    </span>
                                </td>

                                <!-- Safety Stock -->
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary fw-bold">
                                        {{ number_format($it->safety_stock, 0, ',', '.') }}
                                    </span>
                                </td>

                                <!-- Reorder Point -->
                                <td class="text-center">
                                    <span class="badge bg-warning-subtle text-warning-emphasis fw-bold">
                                        {{ number_format($it->reorder_point, 0, ',', '.') }}
                                    </span>
                                </td>

                                <!-- Lead Time -->
                                <td class="text-center text-secondary fs-8">
                                    {{ $it->lead_time_days }} hari
                                </td>

                                <!-- Est. Harga Satuan -->
                                <td class="text-end font-monospace fw-bold text-body">
                                    Rp {{ number_format($it->estimated_unit_price, 0, ',', '.') }}
                                </td>

                                <!-- Status -->
                                <td class="text-center">
                                    @if($it->is_active)
                                        <span class="badge text-bg-success fs-9">Aktif</span>
                                    @else
                                        <span class="badge text-bg-secondary fs-9">Non-Aktif</span>
                                    @endif
                                </td>

                                <!-- Action Buttons: View, Edit, Delete -->
                                <td class="text-center pe-3">
                                    <div class="d-inline-flex align-items-center gap-1" aria-label="Aksi Master Barang">
                                        <!-- View Button -->
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from($it) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Barang">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Edit Button -->
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($it) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Master Barang">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($it) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Hapus Master Barang">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-secondary">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada data master barang ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau sesuaikan filter kategori / status.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card Footer / Pagination -->
        <x-pagination-footer :paginator="$items" :perPage="$perPage" tab="items" />
    </div>
    <!-- ==================== END TAB 1: MASTER BARANG ==================== -->

    <!-- ==================== TAB 2: KATEGORI BARANG ==================== -->
    <div x-show="activeMainTab === 'categories'">
        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('master.items') }}" method="GET">
                <input type="hidden" name="tab" value="categories">
                <div class="row g-2 align-items-center">
                    <!-- Status / Relasi Barang Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-box-seam"></i></span>
                            <select name="cat_status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Kategori</option>
                                <option value="WITH_ITEMS" {{ $catStatus === 'WITH_ITEMS' ? 'selected' : '' }}>Memiliki Barang Persediaan</option>
                                <option value="NO_ITEMS" {{ $catStatus === 'NO_ITEMS' ? 'selected' : '' }}>Belum Ada Barang</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($catSearch || $catStatus)
                        <div class="col-auto">
                            <a href="{{ route('master.items', ['tab' => 'categories']) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   name="cat_search" 
                                   value="{{ $catSearch }}" 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="width: 160px;">Kode Kategori</th>
                            <th class="py-3" style="width: 260px;">Nama Kategori</th>
                            <th class="py-3" style="min-width: 250px;">Deskripsi Pengelompokan</th>
                            <th class="text-center py-3" style="width: 150px;">Barang Terkait</th>
                            <th class="text-center py-3" style="width: 100px;">Status</th>
                            <th class="text-center pe-4 py-3" style="width: 100px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td class="ps-4">
                                    <span class="font-monospace fw-bold text-danger">{{ $cat->code }}</span>
                                </td>
                                <td>
                                    <span class="fw-bold text-body">{{ $cat->name }}</span>
                                </td>
                                <td class="text-secondary fs-8">
                                    {{ $cat->description ?? '-' }}
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('master.items', ['category_id' => $cat->id, 'tab' => 'items']) }}" 
                                       @click="activeMainTab = 'items'" 
                                       class="badge bg-secondary-subtle text-secondary-emphasis font-monospace link-underline-opacity-0 link-underline-opacity-50-hover" 
                                       title="Filter barang dalam kategori ini">
                                        <i class="bi bi-box-seam me-1"></i> {{ $cat->items_count ?? 0 }} Barang
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-success">Aktif</span>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewCategoryModal({{ Js::from($cat) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Kategori">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openEditCategoryModal({{ Js::from($cat) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Kategori">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteCategoryModal({{ Js::from($cat) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Hapus Kategori">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada kategori yang ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau sesuaikan filter status kategori.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card Footer -->
        <div class="card-footer bg-body border-top py-3 px-4">
            <div class="fs-8 text-secondary">
                Menampilkan {{ $categories->count() }} total kategori barang terdaftar di sistem
            </div>
        </div>
    </div>
    <!-- ==================== END TAB 2: KATEGORI BARANG ==================== -->

    <!-- ==================== TAB 3: SATUAN UKURAN (UOM) ==================== -->
    <div x-show="activeMainTab === 'uoms'">
        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('master.items') }}" method="GET">
                <input type="hidden" name="tab" value="uoms">
                <div class="row g-2 align-items-center">
                    <!-- Klasifikasi Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-tags"></i></span>
                            <select name="uom_type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Klasifikasi</option>
                                @foreach($allUomTypes as $utype)
                                    <option value="{{ $utype }}" {{ $uomType === $utype ? 'selected' : '' }}>{{ $utype }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Penggunaan Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-box-seam"></i></span>
                            <select name="uom_usage" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="">Semua Penggunaan</option>
                                <option value="USED" {{ $uomUsage === 'USED' ? 'selected' : '' }}>Digunakan Barang</option>
                                <option value="UNUSED" {{ $uomUsage === 'UNUSED' ? 'selected' : '' }}>Belum Digunakan</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($uomSearch || ($uomType && $uomType !== 'ALL') || $uomUsage)
                        <div class="col-auto">
                            <a href="{{ route('master.items', ['tab' => 'uoms']) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   name="uom_search" 
                                   value="{{ $uomSearch }}" 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="width: 140px;">Kode Satuan (UOM)</th>
                            <th class="py-3" style="width: 220px;">Nama Satuan</th>
                            <th class="py-3" style="width: 140px;">Klasifikasi</th>
                            <th class="py-3" style="min-width: 260px;">Deskripsi & Contoh Penggunaan</th>
                            <th class="text-center py-3" style="width: 140px;">Jumlah Barang</th>
                            <th class="text-center pe-4 py-3" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($uomList as $uom)
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-7 px-2.5 py-1.5 border border-secondary-subtle">
                                        {{ $uom['code'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-body">{{ $uom['name'] }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-8">
                                        {{ $uom['type'] ?? 'Kuantitas' }}
                                    </span>
                                </td>
                                <td class="text-secondary fs-8">
                                    {{ $uom['description'] ?? '-' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border font-monospace">
                                        <i class="bi bi-box me-1 text-danger"></i> {{ $uom['items_count'] ?? 0 }} Barang
                                    </span>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewUomModal({{ Js::from($uom) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Satuan">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openEditUomModal({{ Js::from($uom) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Satuan">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteUomModal({{ Js::from($uom) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Hapus Satuan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada satuan unit yang ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau sesuaikan filter klasifikasi / penggunaan.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card Footer -->
        <div class="card-footer bg-body border-top py-3 px-4">
            <div class="fs-8 text-secondary">
                Menampilkan {{ count($uomList) }} total satuan unit ukuran (UOM) terkonfigurasi di sistem
            </div>
        </div>
    </div>
    <!-- ==================== END TAB 3: SATUAN UKURAN (UOM) ==================== -->

    <!-- ==================== TAB 4: KONVERSI SATUAN BARANG ==================== -->
    <div x-show="activeMainTab === 'conversions'">
        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('master.items') }}" method="GET">
                <input type="hidden" name="tab" value="conversions">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Target Barang Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="position-relative" x-data="{
                            open: false,
                            search: '',
                            selectedVal: '{{ $convItemId ?? '' }}',
                            get selectedItem() {
                                return allItemsCatalog.find(i => String(i.id) === String(this.selectedVal)) || null;
                            },
                            get filteredItems() {
                                if (!this.search.trim()) return allItemsCatalog;
                                let q = this.search.toLowerCase();
                                return allItemsCatalog.filter(i => 
                                    (i.name && i.name.toLowerCase().includes(q)) || 
                                    (i.sku && i.sku.toLowerCase().includes(q))
                                );
                            },
                            select(val) {
                                this.selectedVal = val;
                                this.open = false;
                                this.search = '';
                                $nextTick(() => { $refs.convFilterInput.form.submit(); });
                            }
                        }" :style="open ? 'z-index: 1060;' : 'z-index: 1;'" @click.outside="open = false" @keydown.escape.window="open = false">
                            <input type="hidden" name="conv_item_id" :value="selectedVal" x-ref="convFilterInput">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-box-seam"></i></span>
                                <button type="button" 
                                        @click="open = !open; if(open) { $nextTick(() => $refs.sConvFilterInput?.focus()); }" 
                                        class="form-select form-select-sm border-start-0 fs-8 text-start d-flex align-items-center justify-content-between text-truncate bg-body">
                                    <span class="text-truncate">
                                        <template x-if="!selectedVal"><span>Semua Target Barang</span></template>
                                        <template x-if="selectedVal === 'global'"><span>Konversi Standar (Global)</span></template>
                                        <template x-if="selectedVal && selectedVal !== 'global'">
                                            <span x-text="selectedItem ? ('[' + selectedItem.sku + '] ' + selectedItem.name) : 'Semua Target Barang'"></span>
                                        </template>
                                    </span>
                                </button>
                            </div>
                            <div x-show="open" 
                                 x-cloak 
                                 class="position-absolute start-0 mt-1 w-100 bg-body border border-secondary-subtle rounded-3 shadow-lg p-2" 
                                 style="z-index: 1060; min-width: 280px;">
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-body text-secondary border-end-0 py-1 px-2"><i class="bi bi-search"></i></span>
                                    <input type="text" x-ref="sConvFilterInput" x-model="search" class="form-control form-control-sm border-start-0 fs-8 py-1" autocomplete="off" @keydown.enter.prevent="if (filteredItems.length > 0) select(filteredItems[0].id)">
                                    <button type="button" x-show="search" @click="search = ''; $refs.sConvFilterInput.focus()" class="btn btn-sm btn-outline-secondary border-start-0 py-0 px-2 fs-9"><i class="bi bi-x"></i></button>
                                </div>
                                <div class="overflow-y-auto" style="max-height: 200px;">
                                    <div @click="select('')" class="p-2 rounded-2 cursor-pointer border-bottom border-light-subtle d-flex flex-column hover-bg-body-secondary" :class="!selectedVal ? 'bg-danger-subtle text-danger-emphasis' : ''" role="button">
                                        <span class="fw-semibold fs-8">Semua Target Barang</span>
                                    </div>
                                    <div @click="select('global')" class="p-2 rounded-2 cursor-pointer border-bottom border-light-subtle d-flex flex-column hover-bg-body-secondary" :class="selectedVal === 'global' ? 'bg-danger-subtle text-danger-emphasis' : ''" role="button">
                                        <span class="fw-semibold fs-8">Konversi Standar (Global / Umum)</span>
                                    </div>
                                    <template x-for="cat in filteredItems" :key="cat.id">
                                        <div @click="select(cat.id)" class="p-2 rounded-2 searchable-item-option border-bottom border-light-subtle d-flex flex-column gap-0.5" :class="String(cat.id) === String(selectedVal) ? 'bg-danger-subtle text-danger-emphasis' : ''" role="button">
                                            <div class="d-flex align-items-center justify-content-between gap-1">
                                                <span class="fw-semibold fs-8 text-truncate" x-text="cat.name"></span>
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-9" x-text="cat.sku"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="filteredItems.length === 0" class="text-center py-3 text-secondary fs-8">
                                        <i class="bi bi-inbox me-1"></i> Tidak ada barang yang cocok
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-12 col-sm-6 col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                            <select name="conv_status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Status</option>
                                <option value="ACTIVE" {{ $convStatus === 'ACTIVE' ? 'selected' : '' }}>Aktif</option>
                                <option value="INACTIVE" {{ $convStatus === 'INACTIVE' ? 'selected' : '' }}>Non-Aktif</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($convSearch || $convItemId || ($convStatus && $convStatus !== 'ALL'))
                        <div class="col-auto">
                            <a href="{{ route('master.items', ['tab' => 'conversions']) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   name="conv_search" 
                                   value="{{ $convSearch }}" 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="min-width: 220px;">Target Barang</th>
                            <th class="py-3" style="width: 130px;">Satuan Asal</th>
                            <th class="py-3" style="min-width: 220px;">Formula & Rasio Konversi</th>
                            <th class="py-3" style="width: 130px;">Satuan Tujuan</th>
                            <th class="py-3" style="min-width: 220px;">Deskripsi / Catatan</th>
                            <th class="text-center py-3" style="width: 100px;">Status</th>
                            <th class="text-center pe-4 py-3" style="width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($conversions as $conv)
                            <tr>
                                <td class="ps-4">
                                    @if($conv->item)
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-body fs-8">{{ $conv->item->name }}</span>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-9 align-self-start mt-0.5">
                                                {{ $conv->item->sku }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fs-8 px-2 py-1">
                                            <i class="bi bi-globe2 me-1"></i> Standar Global (Semua Barang)
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-danger-subtle text-danger font-monospace fs-8 border border-danger-subtle">
                                        {{ $conv->from_uom }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-inline-flex align-items-center gap-1.5 py-1 px-2 rounded-2 bg-body-secondary border border-secondary-subtle fs-8">
                                        <span class="font-monospace fw-bold text-body">1 {{ $conv->from_uom }}</span>
                                        <i class="bi bi-arrow-right text-danger"></i>
                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle font-monospace fw-bold fs-8">
                                            {{ (float) $conv->conversion_factor }}
                                        </span>
                                        <span class="font-monospace fw-bold text-body">{{ $conv->to_uom }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary font-monospace fs-8 border border-primary-subtle">
                                        {{ $conv->to_uom }}
                                    </span>
                                </td>
                                <td class="text-secondary fs-8">
                                    {{ $conv->description ?: '-' }}
                                </td>
                                <td class="text-center">
                                    @if($conv->is_active)
                                        <span class="badge text-bg-success">Aktif</span>
                                    @else
                                        <span class="badge text-bg-secondary">Non-Aktif</span>
                                    @endif
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewConversionModal({{ Js::from($conv) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Konversi">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openEditConversionModal({{ Js::from($conv) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Konversi">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteConversionModal({{ Js::from($conv) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Hapus Konversi">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary-subtle"></i>
                                    <p class="fw-bold mb-1">Tidak ada aturan konversi satuan yang ditemukan</p>
                                    <p class="fs-8 text-muted mb-0">Coba ubah kata kunci pencarian atau sesuaikan filter barang target / status.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Card Footer with Pagination -->
        <x-pagination-footer :paginator="$conversions" :perPage="$perPage" tab="conversions" />
    </div>
    <!-- ==================== END TAB 4: KONVERSI SATUAN BARANG ==================== -->
</div>

    <!-- 1. VIEW MODAL (AdminLTE 4 & Dark/Light Aware) -->
    <div x-show="viewModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-eye fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Detail Master Barang: <span class="font-monospace text-danger" x-text="selectedItem.sku"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Informasi spesifikasi teknis dan parameter persediaan</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge" :class="selectedItem.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="selectedItem.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                    <button type="button" @click="viewModal = false" class="btn-close ms-2" aria-label="Close"></button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="card-body p-4 space-y-4" style="max-height: 75vh; overflow-y: auto;">
                <!-- Main Info Card -->
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="row g-3 fs-8">
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Nama Barang:</span>
                            <span class="fw-bold text-body fs-7" x-text="selectedItem.name"></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-secondary d-block">Kategori:</span>
                            <span class="fw-bold text-body" x-text="selectedItem.category_name"></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-secondary d-block">Satuan (UOM):</span>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace" x-text="selectedItem.uom"></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Kode SKU:</span>
                            <span class="font-monospace fw-bold text-danger" x-text="selectedItem.sku"></span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-secondary d-block">Barcode:</span>
                            <span class="font-monospace fw-bold text-body" x-text="selectedItem.barcode"></span>
                        </div>
                        <div class="col-12 col-md-4">
                            <span class="text-secondary d-block">Est. Harga Satuan:</span>
                            <span class="font-monospace fw-bold text-success fs-7" x-text="formatRupiah(selectedItem.estimated_unit_price)"></span>
                        </div>
                        <div class="col-12">
                            <span class="text-secondary d-block">Spesifikasi / Keterangan:</span>
                            <span class="text-body" x-text="selectedItem.specification || '-'"></span>
                        </div>
                    </div>
                </div>

                <!-- Parameter Kontrol Persediaan -->
                <div class="border rounded-2 overflow-hidden">
                    <div class="bg-body-secondary py-1.5 px-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fs-8 fw-bold text-uppercase text-secondary">
                            <i class="bi bi-sliders text-danger me-1"></i> Parameter Kontrol Persediaan
                        </span>
                    </div>
                    <div class="table-responsive mb-0">
                        <table class="table table-sm table-bordered text-center align-middle mb-0 fs-8">
                            <thead class="bg-body-tertiary text-secondary">
                                <tr>
                                    <th class="py-1.5" style="width: 20%;">Min Stock</th>
                                    <th class="py-1.5 text-primary" style="width: 20%;">Safety Stock</th>
                                    <th class="py-1.5 text-warning-emphasis" style="width: 20%;">Reorder Point (ROP)</th>
                                    <th class="py-1.5" style="width: 20%;">Max Stock</th>
                                    <th class="py-1.5" style="width: 20%;">Lead Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="fw-bold font-monospace fs-7">
                                    <td class="py-2">
                                        <span x-text="selectedItem.min_stock"></span>
                                        <span class="fs-9 text-muted fw-normal ms-0.5" x-text="selectedItem.uom"></span>
                                    </td>
                                    <td class="py-2 text-primary bg-primary-subtle/30">
                                        <span x-text="selectedItem.safety_stock"></span>
                                        <span class="fs-9 text-primary fw-normal ms-0.5" x-text="selectedItem.uom"></span>
                                    </td>
                                    <td class="py-2 text-warning-emphasis bg-warning-subtle/30">
                                        <span x-text="selectedItem.reorder_point"></span>
                                        <span class="fs-9 text-warning-emphasis fw-normal ms-0.5" x-text="selectedItem.uom"></span>
                                    </td>
                                    <td class="py-2">
                                        <span x-text="selectedItem.max_stock"></span>
                                        <span class="fs-9 text-muted fw-normal ms-0.5" x-text="selectedItem.uom"></span>
                                    </td>
                                    <td class="py-2">
                                        <span x-text="selectedItem.lead_time_days"></span>
                                        <span class="fs-9 text-muted fw-normal ms-0.5">Hari</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Stock Balances Across Warehouses -->
                <div>
                    <h6 class="fs-8 text-secondary text-uppercase fw-bold mb-2">Posisi Stok di Gudang</h6>
                    <template x-if="selectedItem.stock_balances && selectedItem.stock_balances.length > 0">
                        <div class="table-responsive rounded-2 border">
                            <table class="table table-sm table-striped table-hover mb-0 fs-8">
                                <thead class="bg-body-tertiary text-secondary">
                                    <tr>
                                        <th class="ps-3 py-2">Gudang Penyimpanan</th>
                                        <th class="text-center py-2">On Hand (Fisik)</th>
                                        <th class="text-center py-2">Reserved (Dialokasi)</th>
                                        <th class="text-center pe-3 py-2">Available (Tersedia)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="bal in selectedItem.stock_balances" :key="bal.id">
                                        <tr>
                                            <td class="ps-3 py-2 fw-semibold text-body" x-text="bal.warehouse ? bal.warehouse.name : 'Gudang #' + bal.warehouse_id"></td>
                                            <td class="text-center py-2 font-monospace fw-bold" x-text="bal.qty_on_hand"></td>
                                            <td class="text-center py-2 font-monospace text-warning-emphasis" x-text="bal.qty_reserved"></td>
                                            <td class="text-center pe-3 py-2 font-monospace fw-bold text-success" x-text="bal.qty_available"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                    <template x-if="!selectedItem.stock_balances || selectedItem.stock_balances.length === 0">
                        <div class="p-3 text-center rounded-2 border bg-body-tertiary text-secondary fs-8">
                            <i class="bi bi-box-seam me-1"></i> Belum ada catatan saldo persediaan di gudang untuk barang ini.
                        </div>
                    </template>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="card-footer bg-body-tertiary d-flex justify-content-between align-items-center py-3 px-4 border-top">
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
                <button type="button" @click="openEditModal(selectedItem)" class="btn btn-sm btn-primary px-3">
                    <i class="bi bi-pencil-square me-1"></i> Edit Data Barang
                </button>
            </div>
        </div>
    </div>

    <!-- 2. EDIT MODAL (AdminLTE 4 & Dark/Light Aware) -->
    <div x-show="editModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-pencil-square fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Edit Master Barang: <span class="font-monospace text-danger" x-text="selectedItem.sku"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Perbarui parameter katalog, harga, atau batas kuota stok</span>
                    </div>
                </div>
                <button type="button" @click="editModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form :action="updateUrl" method="POST">
                @csrf
                @method('PUT')

                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Row 1: Kategori & Status -->
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kategori Barang <span class="text-danger">*</span></label>
                            <select name="category_id" x-model="selectedItem.category_id" required class="form-select form-select-sm fs-8">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Status Barang <span class="text-danger">*</span></label>
                            <select name="is_active" x-model="selectedItem.is_active" class="form-select form-select-sm fs-8">
                                <option value="1">Aktif</option>
                                <option value="0">Non-Aktif</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: SKU & Barcode -->
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode SKU <span class="text-danger">*</span></label>
                            <input type="text" name="sku" x-model="selectedItem.sku" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Barcode (Opsional)</label>
                            <input type="text" name="barcode" x-model="selectedItem.barcode" class="form-control form-control-sm font-monospace fs-8">
                        </div>
                    </div>

                    <!-- Row 3: Nama Barang -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" name="name" x-model="selectedItem.name" required class="form-control form-control-sm fs-8">
                    </div>

                    <!-- Row 4: UOM & Estimasi Harga -->
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Satuan (UOM) <span class="text-danger">*</span></label>
                            <select name="uom" x-model="selectedItem.uom" required class="form-select form-select-sm fs-8">
                                @foreach($uomList as $u)
                                    <option value="{{ $u['code'] }}">{{ $u['code'] }} - {{ $u['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Estimasi Harga Satuan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="estimated_unit_price" x-model="selectedItem.estimated_unit_price" required min="0" step="100" class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                    </div>

                    <!-- Row 5: Spesifikasi -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Spesifikasi / Keterangan</label>
                        <textarea name="specification" x-model="selectedItem.specification" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>

                    <!-- Row 6: Parameter Persediaan -->
                    <div class="border rounded-2 p-2.5 bg-body-tertiary">
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom border-secondary-subtle">
                            <span class="fs-8 fw-bold text-uppercase text-secondary d-flex align-items-center gap-1.5">
                                <i class="bi bi-sliders text-danger"></i> Parameter Kontrol Persediaan
                            </span>
                        </div>
                        <div class="row g-2">
                            <!-- Min Stock -->
                            <div class="col-6 col-md-4">
                                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                                    Min Stock <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="min_stock" x-model="selectedItem.min_stock" required min="0" class="form-control text-center font-monospace fw-bold fs-8">
                                    <span class="input-group-text fs-9 text-secondary px-2" x-text="selectedItem.uom || 'UOM'"></span>
                                </div>
                            </div>

                            <!-- Safety Stock -->
                            <div class="col-6 col-md-4">
                                <label class="form-label fs-8 fw-semibold text-primary mb-1">
                                    Safety Stock <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="safety_stock" x-model="selectedItem.safety_stock" required min="0" class="form-control text-center font-monospace fw-bold fs-8 text-primary border-primary-subtle">
                                    <span class="input-group-text fs-9 text-primary border-primary-subtle bg-primary-subtle px-2" x-text="selectedItem.uom || 'UOM'"></span>
                                </div>
                            </div>

                            <!-- Max Stock -->
                            <div class="col-6 col-md-4">
                                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                                    Max Stock
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="max_stock" x-model="selectedItem.max_stock" min="0" class="form-control text-center font-monospace fw-bold fs-8">
                                    <span class="input-group-text fs-9 text-secondary px-2" x-text="selectedItem.uom || 'UOM'"></span>
                                </div>
                            </div>

                            <!-- Reorder Point (ROP) -->
                            <div class="col-6 col-md-6">
                                <label class="form-label fs-8 fw-semibold text-warning-emphasis mb-1">
                                    Reorder Point (ROP) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="reorder_point" x-model="selectedItem.reorder_point" required min="0" class="form-control text-center font-monospace fw-bold fs-8 text-warning-emphasis border-warning-subtle">
                                    <span class="input-group-text fs-9 text-warning-emphasis border-warning-subtle bg-warning-subtle px-2" x-text="selectedItem.uom || 'UOM'"></span>
                                </div>
                            </div>

                            <!-- Lead Time -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                                    Lead Time Pengadaan
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="lead_time_days" x-model="selectedItem.lead_time_days" min="0" class="form-control text-center font-monospace fw-bold fs-8">
                                    <span class="input-group-text fs-9 text-secondary px-2">Hari</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="editModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. DELETE CONFIRMATION MODAL (AdminLTE 4 & Dark/Light Aware) -->
    <div x-show="deleteModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteModal = false" 
             class="card shadow-2xl border border-danger-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-danger-subtle d-flex align-items-center justify-content-between py-3 px-4 border-bottom border-danger-subtle">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-trash-fill fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Master Barang</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form :action="deleteUrl" method="POST">
                @csrf
                @method('DELETE')

                <div class="card-body p-4 space-y-3">
                    <p class="text-body mb-2 fs-7">
                        Apakah Anda yakin ingin menghapus master barang berikut secara permanen?
                    </p>

                    <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-1">Kode SKU:</div>
                        <div class="font-monospace fw-bold text-danger fs-6 mb-2" x-text="selectedItem.sku"></div>
                        <div class="fs-8 text-secondary mb-1">Nama Barang:</div>
                        <div class="fw-bold text-body fs-7" x-text="selectedItem.name"></div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-shield-exclamation text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <strong>Perlindungan Integritas Data:</strong> Barang yang sudah pernah tercatat dalam order atau memiliki alokasi persediaan di gudang tidak dapat dihapus. Anda dapat mengubah status barang menjadi <strong>Non-Aktif</strong> agar tidak dapat dipesan lagi.
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Barang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. CREATE MODAL (AdminLTE 4 & Dark/Light Aware) -->
    <div x-show="createModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createModal = false; createUom = 'PCS'" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <!-- Modal Header -->
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-plus-circle fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Master Barang Baru</h6>
                        <span class="fs-8 text-secondary">Registrasi item barang ke dalam sistem persediaan</span>
                    </div>
                </div>
                <button type="button" @click="createModal = false; createUom = 'PCS'" class="btn-close" aria-label="Close"></button>
            </div>

            <!-- Form -->
            <form action="{{ route('master.items.store') }}" method="POST">
                @csrf

                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Row 1: Kategori & SKU -->
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kategori Barang <span class="text-danger">*</span></label>
                            <select name="category_id" required class="form-select form-select-sm fs-8">
                                <option value="" disabled selected>Pilih Kategori...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode SKU Unik <span class="text-danger">*</span></label>
                            <input type="text" name="sku" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                    </div>

                    <!-- Row 2: Barcode & Nama -->
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Barcode (Opsional)</label>
                            <input type="text" name="barcode" class="form-control form-control-sm font-monospace fs-8">
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Barang <span class="text-danger">*</span></label>
                            <input type="text" name="name" required class="form-control form-control-sm fs-8">
                        </div>
                    </div>

                    <!-- Row 3: UOM & Estimasi Harga -->
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Satuan (UOM) <span class="text-danger">*</span></label>
                            <select name="uom" x-model="createUom" required class="form-select form-select-sm fs-8">
                                @foreach($uomList as $u)
                                    <option value="{{ $u['code'] }}">{{ $u['code'] }} - {{ $u['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Estimasi Harga Satuan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="estimated_unit_price" required min="0" step="100" class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                    </div>

                    <!-- Row 4: Spesifikasi -->
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Spesifikasi / Keterangan</label>
                        <textarea name="specification" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>

                    <!-- Row 5: Parameter Persediaan -->
                    <div class="border rounded-2 p-2.5 bg-body-tertiary">
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom border-secondary-subtle">
                            <span class="fs-8 fw-bold text-uppercase text-secondary d-flex align-items-center gap-1.5">
                                <i class="bi bi-sliders text-danger"></i> Parameter Kontrol Persediaan
                            </span>
                        </div>
                        <div class="row g-2">
                            <!-- Min Stock -->
                            <div class="col-6 col-md-4">
                                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                                    Min Stock <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="min_stock" value="10" required min="0" class="form-control text-center font-monospace fw-bold fs-8">
                                    <span class="input-group-text fs-9 text-secondary px-2" x-text="createUom || 'PCS'"></span>
                                </div>
                            </div>

                            <!-- Safety Stock -->
                            <div class="col-6 col-md-4">
                                <label class="form-label fs-8 fw-semibold text-primary mb-1">
                                    Safety Stock <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="safety_stock" value="20" required min="0" class="form-control text-center font-monospace fw-bold fs-8 text-primary border-primary-subtle">
                                    <span class="input-group-text fs-9 text-primary border-primary-subtle bg-primary-subtle px-2" x-text="createUom || 'PCS'"></span>
                                </div>
                            </div>

                            <!-- Max Stock -->
                            <div class="col-6 col-md-4">
                                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                                    Max Stock
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="max_stock" value="500" min="0" class="form-control text-center font-monospace fw-bold fs-8">
                                    <span class="input-group-text fs-9 text-secondary px-2" x-text="createUom || 'PCS'"></span>
                                </div>
                            </div>

                            <!-- Reorder Point (ROP) -->
                            <div class="col-6 col-md-6">
                                <label class="form-label fs-8 fw-semibold text-warning-emphasis mb-1">
                                    Reorder Point (ROP) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="reorder_point" value="30" required min="0" class="form-control text-center font-monospace fw-bold fs-8 text-warning-emphasis border-warning-subtle">
                                    <span class="input-group-text fs-9 text-warning-emphasis border-warning-subtle bg-warning-subtle px-2" x-text="createUom || 'PCS'"></span>
                                </div>
                            </div>

                            <!-- Lead Time -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fs-8 fw-semibold text-secondary mb-1">
                                    Lead Time Pengadaan
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="lead_time_days" value="5" min="0" class="form-control text-center font-monospace fw-bold fs-8">
                                    <span class="input-group-text fs-9 text-secondary px-2">Hari</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createModal = false; createUom = 'PCS'" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Master Barang
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- CATEGORY MODALS -->
    <!-- ========================================================================= -->

    <!-- CREATE CATEGORY MODAL -->
    <div x-show="createCategoryModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createCategoryModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-tags fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Kategori Barang Baru</h6>
                        <span class="fs-8 text-secondary">Klasifikasi pengelompokan jenis barang persediaan</span>
                    </div>
                </div>
                <button type="button" @click="createCategoryModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form action="{{ route('master.categories.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="code" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        <span class="fs-9 text-secondary mt-0.5 d-block">Kode unik kategori, otomatis disimpan dalam format huruf kapital.</span>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" required class="form-control form-control-sm fw-bold fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Deskripsi Pengelompokan</label>
                        <textarea name="description" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createCategoryModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW CATEGORY MODAL -->
    <div x-show="viewCategoryModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewCategoryModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-tag fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Detail Kategori: <span class="font-monospace text-danger" x-text="selectedCategory.code"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Informasi klasifikasi barang persediaan</span>
                    </div>
                </div>
                <button type="button" @click="viewCategoryModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="card-body p-4 space-y-3">
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle">
                    <div class="fs-8 text-secondary mb-0.5">Nama Kategori:</div>
                    <h5 class="fw-bold text-body mb-0" x-text="selectedCategory.name"></h5>
                </div>
                <div class="row g-3 fs-8">
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Kode Kategori:</span>
                        <span class="font-monospace fw-bold text-danger" x-text="selectedCategory.code"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Jumlah Barang Terdaftar:</span>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis" x-text="selectedCategory.items_count + ' SKU Barang'"></span>
                    </div>
                    <div class="col-12">
                        <span class="text-secondary d-block mb-1">Deskripsi:</span>
                        <div class="p-2.5 rounded-2 bg-body border text-body" x-text="selectedCategory.description || '- Tidak ada deskripsi -'"></div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top">
                <button type="button" @click="viewCategoryModal = false" class="btn btn-sm btn-outline-secondary px-3">Tutup</button>
            </div>
        </div>
    </div>

    <!-- EDIT CATEGORY MODAL -->
    <div x-show="editCategoryModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editCategoryModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-pencil-square fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Edit Kategori Barang</h6>
                        <span class="fs-8 text-secondary">Perbarui informasi kode atau nama kategori</span>
                    </div>
                </div>
                <button type="button" @click="editCategoryModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form :action="categoryUpdateUrl" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="code" x-model="selectedCategory.code" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" x-model="selectedCategory.name" required class="form-control form-control-sm fw-bold fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Deskripsi</label>
                        <textarea name="description" x-model="selectedCategory.description" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="editCategoryModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CATEGORY MODAL -->
    <div x-show="deleteCategoryModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteCategoryModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger-subtle text-danger d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <h6 class="mb-0 fw-bold">Konfirmasi Hapus Kategori</h6>
                </div>
                <button type="button" @click="deleteCategoryModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form :action="categoryDeleteUrl" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-4 text-center">
                    <p class="mb-2 fs-7 text-body">Apakah Anda yakin ingin menghapus kategori:</p>
                    <div class="fw-bold fs-6 text-danger mb-2 font-monospace" x-text="'[' + selectedCategory.code + '] ' + selectedCategory.name"></div>
                    <template x-if="selectedCategory.items_count > 0">
                        <div class="alert alert-warning py-2 px-3 fs-8 text-start mb-0">
                            <i class="bi bi-exclamation-circle-fill me-1"></i>
                            Kategori ini masih digunakan oleh <strong x-text="selectedCategory.items_count"></strong> barang. Anda tidak dapat menghapus kategori yang masih memiliki relasi barang aktif.
                        </div>
                    </template>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="deleteCategoryModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" 
                            :disabled="selectedCategory.items_count > 0" 
                            class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- UOM (SATUAN) MODALS -->
    <!-- ========================================================================= -->

    <!-- CREATE UOM MODAL -->
    <div x-show="createUomModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createUomModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-rulers fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Satuan Ukuran (UOM) Baru</h6>
                        <span class="fs-8 text-secondary">Registrasi unit satuan persediaan katalog barang</span>
                    </div>
                </div>
                <button type="button" @click="createUomModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form action="{{ route('master.uoms.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Satuan <span class="text-danger">*</span></label>
                            <input type="text" name="code" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Klasifikasi Tipe <span class="text-danger">*</span></label>
                            <select name="type" required class="form-select form-select-sm fs-8">
                                <option value="Kuantitas">Kuantitas (Eceran / Jumlah)</option>
                                <option value="Kemasan">Kemasan (Box / Pack / Dus)</option>
                                <option value="Kertas">Kertas (Rim / Lembar)</option>
                                <option value="Perangkat">Perangkat / Mesin</option>
                                <option value="Paket">Paket / Set / Pasang</option>
                                <option value="Gulungan">Gulungan / Roll</option>
                                <option value="Cairan">Cairan (Botol / Liter)</option>
                                <option value="Panjang">Panjang (Meter / Yard)</option>
                                <option value="Khusus">Satuan Khusus Lainnya</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Satuan Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" required class="form-control form-control-sm fw-bold fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Deskripsi & Contoh Penggunaan</label>
                        <textarea name="description" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createUomModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Satuan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW UOM MODAL -->
    <div x-show="viewUomModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewUomModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-rulers fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">
                            Detail Satuan: <span class="font-monospace text-danger" x-text="selectedUom.code"></span>
                        </h6>
                        <span class="fs-8 text-secondary">Unit ukuran persediaan logistik</span>
                    </div>
                </div>
                <button type="button" @click="viewUomModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="card-body p-4 space-y-3">
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle text-center">
                    <div class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-5 px-3 py-1.5 border border-secondary-subtle mb-1" x-text="selectedUom.code"></div>
                    <div class="fw-bold text-body fs-6" x-text="selectedUom.name"></div>
                </div>
                <div class="row g-3 fs-8">
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Klasifikasi:</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle" x-text="selectedUom.type"></span>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-secondary d-block">Jumlah Barang:</span>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis" x-text="selectedUom.items_count + ' Barang Terkait'"></span>
                    </div>
                    <div class="col-12">
                        <span class="text-secondary d-block mb-1">Deskripsi & Penggunaan:</span>
                        <div class="p-2.5 rounded-2 bg-body border text-body" x-text="selectedUom.description || '-'"></div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top">
                <button type="button" @click="viewUomModal = false" class="btn btn-sm btn-outline-secondary px-3">Tutup</button>
            </div>
        </div>
    </div>

    <!-- EDIT UOM MODAL -->
    <div x-show="editUomModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editUomModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-pencil-square fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Edit Satuan Ukuran (UOM)</h6>
                        <span class="fs-8 text-secondary">Perbarui informasi data satuan unit</span>
                    </div>
                </div>
                <button type="button" @click="editUomModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form :action="uomUpdateUrl" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Kode Satuan <span class="text-danger">*</span></label>
                            <input type="text" name="code" x-model="selectedUom.code" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <div class="col-12 col-md-7">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Klasifikasi Tipe <span class="text-danger">*</span></label>
                            <select name="type" x-model="selectedUom.type" required class="form-select form-select-sm fs-8">
                                <option value="Kuantitas">Kuantitas (Eceran / Jumlah)</option>
                                <option value="Kemasan">Kemasan (Box / Pack / Dus)</option>
                                <option value="Kertas">Kertas (Rim / Lembar)</option>
                                <option value="Perangkat">Perangkat / Mesin</option>
                                <option value="Paket">Paket / Set / Pasang</option>
                                <option value="Gulungan">Gulungan / Roll</option>
                                <option value="Cairan">Cairan (Botol / Liter)</option>
                                <option value="Panjang">Panjang (Meter / Yard)</option>
                                <option value="Khusus">Satuan Khusus Lainnya</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Satuan Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" x-model="selectedUom.name" required class="form-control form-control-sm fw-bold fs-8">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Deskripsi & Contoh Penggunaan</label>
                        <textarea name="description" x-model="selectedUom.description" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="editUomModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE UOM MODAL -->
    <div x-show="deleteUomModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteUomModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger-subtle text-danger d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <h6 class="mb-0 fw-bold">Konfirmasi Hapus Satuan</h6>
                </div>
                <button type="button" @click="deleteUomModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form :action="uomDeleteUrl" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-4 text-center">
                    <p class="mb-2 fs-7 text-body">Apakah Anda yakin ingin menghapus satuan ukuran:</p>
                    <div class="fw-bold fs-6 text-danger mb-2 font-monospace" x-text="'[' + selectedUom.code + '] ' + selectedUom.name"></div>
                    <template x-if="selectedUom.items_count > 0">
                        <div class="alert alert-warning py-2 px-3 fs-8 text-start mb-0">
                            <i class="bi bi-exclamation-circle-fill me-1"></i>
                            Satuan ini masih digunakan oleh <strong x-text="selectedUom.items_count"></strong> barang. Anda tidak dapat menghapus satuan yang masih memiliki relasi barang aktif.
                        </div>
                    </template>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="deleteUomModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" 
                            :disabled="selectedUom.items_count > 0" 
                            class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Satuan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- ITEM CONVERSION MODALS -->
    <!-- ========================================================================= -->

    <!-- CREATE CONVERSION MODAL -->
    <div x-show="createConversionModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createConversionModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-arrow-left-right fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Tambah Aturan Konversi Satuan</h6>
                        <span class="fs-8 text-secondary">Definisikan rasio konversi satuan barang persediaan</span>
                    </div>
                </div>
                <button type="button" @click="createConversionModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form action="{{ route('master.conversions.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    <div class="position-relative" x-data="{
                        open: false,
                        search: '',
                        selectedId: '',
                        get selectedItem() {
                            return allItemsCatalog.find(i => String(i.id) === String(this.selectedId)) || null;
                        },
                        get filteredItems() {
                            if (!this.search.trim()) return allItemsCatalog;
                            let q = this.search.toLowerCase();
                            return allItemsCatalog.filter(i => 
                                (i.name && i.name.toLowerCase().includes(q)) || 
                                (i.sku && i.sku.toLowerCase().includes(q)) ||
                                (i.uom && i.uom.toLowerCase().includes(q))
                            );
                        },
                        select(item) {
                            this.selectedId = item ? item.id : '';
                            this.open = false;
                            this.search = '';
                        }
                    }" :style="open ? 'z-index: 1060;' : 'z-index: 1;'" @click.outside="open = false" @keydown.escape.window="open = false">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Target Barang (Opsional)</label>
                        <input type="hidden" name="item_id" :value="selectedId">
                        <button type="button" 
                                @click="open = !open; if(open) { $nextTick(() => $refs.sAddConvInput?.focus()); }" 
                                class="form-select form-select-sm fs-8 text-start d-flex align-items-center justify-content-between text-truncate bg-body">
                            <span class="text-truncate" x-text="selectedItem ? ('[' + selectedItem.sku + '] ' + selectedItem.name + ' (Satuan Dasar: ' + selectedItem.uom + ')') : '-- Standar Global (Berlaku untuk Semua Barang) --'"></span>
                        </button>
                        <div x-show="open" 
                             x-cloak 
                             class="position-absolute start-0 mt-1 w-100 bg-body border border-secondary-subtle rounded-3 shadow-lg p-2" 
                             style="z-index: 1060; min-width: 280px;">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-body text-secondary border-end-0 py-1 px-2"><i class="bi bi-search"></i></span>
                                <input type="text" x-ref="sAddConvInput" x-model="search" class="form-control form-control-sm border-start-0 fs-8 py-1" autocomplete="off" @keydown.enter.prevent="if (filteredItems.length > 0) select(filteredItems[0])">
                                <button type="button" x-show="search" @click="search = ''; $refs.sAddConvInput.focus()" class="btn btn-sm btn-outline-secondary border-start-0 py-0 px-2 fs-9"><i class="bi bi-x"></i></button>
                            </div>
                            <div class="overflow-y-auto" style="max-height: 200px;">
                                <div @click="select(null)" 
                                     class="p-2 rounded-2 cursor-pointer border-bottom border-light-subtle d-flex flex-column hover-bg-body-secondary"
                                     :class="!selectedId ? 'bg-danger-subtle text-danger-emphasis' : ''"
                                     role="button">
                                    <span class="fw-semibold fs-8">-- Standar Global (Berlaku untuk Semua Barang) --</span>
                                </div>
                                <template x-for="cat in filteredItems" :key="cat.id">
                                    <div @click="select(cat)" 
                                         class="p-2 rounded-2 searchable-item-option border-bottom border-light-subtle d-flex flex-column gap-0.5"
                                         :class="String(cat.id) === String(selectedId) ? 'bg-danger-subtle text-danger-emphasis' : ''"
                                         role="button">
                                        <div class="d-flex align-items-center justify-content-between gap-1">
                                            <span class="fw-semibold fs-8 text-truncate" x-text="cat.name"></span>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-9" x-text="cat.sku"></span>
                                        </div>
                                        <div class="text-secondary fs-9" x-text="'Satuan Dasar: ' + cat.uom"></div>
                                    </div>
                                </template>
                                <div x-show="filteredItems.length === 0" class="text-center py-3 text-secondary fs-8">
                                    <i class="bi bi-inbox me-1"></i> Tidak ada barang yang cocok
                                </div>
                            </div>
                        </div>
                        <div class="form-text fs-9">Pilih barang tertentu atau biarkan kosong untuk konversi umum/global.</div>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-sm-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">1 Satuan Asal <span class="text-danger">*</span></label>
                            <select name="from_uom" required class="form-select form-select-sm font-monospace fw-bold fs-8 text-uppercase">
                                <option value="" disabled selected>-- Pilih Satuan Asal --</option>
                                @foreach($allUoms as $u)
                                    <option value="{{ $u['code'] }}">{{ $u['code'] }} - {{ $u['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Setara Dengan (=) <span class="text-danger">*</span></label>
                            <input type="number" name="conversion_factor" step="any" min="0.0001" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Satuan Tujuan <span class="text-danger">*</span></label>
                            <select name="to_uom" required class="form-select form-select-sm font-monospace fw-bold fs-8 text-uppercase">
                                <option value="" disabled selected>-- Pilih Satuan Tujuan --</option>
                                @foreach($allUoms as $u)
                                    <option value="{{ $u['code'] }}">{{ $u['code'] }} - {{ $u['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Deskripsi / Keterangan Formula</label>
                        <textarea name="description" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>

                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="create_conv_active" checked>
                        <label class="form-check-label fs-8 fw-semibold text-body" for="create_conv_active">Status Konversi Aktif</label>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createConversionModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Konversi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW CONVERSION MODAL -->
    <div x-show="viewConversionModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewConversionModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-arrow-left-right fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Detail Aturan Konversi Satuan</h6>
                        <span class="fs-8 text-secondary">Formula rasio konversi unit</span>
                    </div>
                </div>
                <button type="button" @click="viewConversionModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="card-body p-4 space-y-3">
                <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle text-center">
                    <span class="text-secondary fs-9 text-uppercase fw-bold d-block mb-1">Formula Rasio Konversi</span>
                    <div class="d-flex align-items-center justify-content-center gap-2 font-monospace fs-5 fw-bold text-body">
                        <span class="badge bg-danger text-white px-2.5 py-1.5" x-text="'1 ' + selectedConversion.from_uom"></span>
                        <i class="bi bi-arrow-right text-danger"></i>
                        <span class="badge bg-success text-white px-2.5 py-1.5" x-text="selectedConversion.conversion_factor + ' ' + selectedConversion.to_uom"></span>
                    </div>
                </div>
                <div class="row g-3 fs-8">
                    <div class="col-12">
                        <span class="text-secondary d-block">Target Barang:</span>
                        <div class="fw-bold text-body mt-0.5">
                            <span x-show="selectedConversion.item_sku" class="badge bg-secondary-subtle text-secondary-emphasis font-monospace me-1" x-text="selectedConversion.item_sku"></span>
                            <span x-text="selectedConversion.item_name"></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <span class="text-secondary d-block">Satuan Asal:</span>
                        <span class="badge bg-danger-subtle text-danger font-monospace fs-8" x-text="selectedConversion.from_uom"></span>
                    </div>
                    <div class="col-6">
                        <span class="text-secondary d-block">Satuan Tujuan:</span>
                        <span class="badge bg-primary-subtle text-primary font-monospace fs-8" x-text="selectedConversion.to_uom"></span>
                    </div>
                    <div class="col-6">
                        <span class="text-secondary d-block">Faktor Pengali:</span>
                        <span class="fw-bold font-monospace text-body fs-7" x-text="selectedConversion.conversion_factor"></span>
                    </div>
                    <div class="col-6">
                        <span class="text-secondary d-block">Status:</span>
                        <span class="badge" :class="selectedConversion.is_active ? 'text-bg-success' : 'text-bg-secondary'" x-text="selectedConversion.is_active ? 'Aktif' : 'Non-Aktif'"></span>
                    </div>
                    <div class="col-12">
                        <span class="text-secondary d-block mb-1">Keterangan / Catatan:</span>
                        <div class="p-2.5 rounded-2 bg-body border text-body" x-text="selectedConversion.description || '-'"></div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-body-tertiary d-flex justify-content-end py-3 px-4 border-top">
                <button type="button" @click="viewConversionModal = false" class="btn btn-sm btn-outline-secondary px-3">Tutup</button>
            </div>
        </div>
    </div>

    <!-- EDIT CONVERSION MODAL -->
    <div x-show="editConversionModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editConversionModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning-emphasis p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-pencil-square fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Edit Aturan Konversi Satuan</h6>
                        <span class="fs-8 text-secondary">Perbarui informasi rasio satuan</span>
                    </div>
                </div>
                <button type="button" @click="editConversionModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form :action="conversionUpdateUrl" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div class="position-relative" x-data="{
                        open: false,
                        search: '',
                        get selectedItem() {
                            return allItemsCatalog.find(i => String(i.id) === String(selectedConversion.item_id)) || null;
                        },
                        get filteredItems() {
                            if (!this.search.trim()) return allItemsCatalog;
                            let q = this.search.toLowerCase();
                            return allItemsCatalog.filter(i => 
                                (i.name && i.name.toLowerCase().includes(q)) || 
                                (i.sku && i.sku.toLowerCase().includes(q)) ||
                                (i.uom && i.uom.toLowerCase().includes(q))
                            );
                        },
                        select(item) {
                            selectedConversion.item_id = item ? item.id : '';
                            this.open = false;
                            this.search = '';
                        }
                    }" :style="open ? 'z-index: 1060;' : 'z-index: 1;'" @click.outside="open = false" @keydown.escape.window="open = false">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Target Barang (Opsional)</label>
                        <input type="hidden" name="item_id" :value="selectedConversion.item_id">
                        <button type="button" 
                                @click="open = !open; if(open) { $nextTick(() => $refs.sEditConvInput?.focus()); }" 
                                class="form-select form-select-sm fs-8 text-start d-flex align-items-center justify-content-between text-truncate bg-body">
                            <span class="text-truncate" x-text="selectedItem ? ('[' + selectedItem.sku + '] ' + selectedItem.name + ' (Satuan Dasar: ' + selectedItem.uom + ')') : '-- Standar Global (Berlaku untuk Semua Barang) --'"></span>
                        </button>
                        <div x-show="open" 
                             x-cloak 
                             class="position-absolute start-0 mt-1 w-100 bg-body border border-secondary-subtle rounded-3 shadow-lg p-2" 
                             style="z-index: 1060; min-width: 280px;">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-body text-secondary border-end-0 py-1 px-2"><i class="bi bi-search"></i></span>
                                <input type="text" x-ref="sEditConvInput" x-model="search" class="form-control form-control-sm border-start-0 fs-8 py-1" autocomplete="off" @keydown.enter.prevent="if (filteredItems.length > 0) select(filteredItems[0])">
                                <button type="button" x-show="search" @click="search = ''; $refs.sEditConvInput.focus()" class="btn btn-sm btn-outline-secondary border-start-0 py-0 px-2 fs-9"><i class="bi bi-x"></i></button>
                            </div>
                            <div class="overflow-y-auto" style="max-height: 200px;">
                                <div @click="select(null)" 
                                     class="p-2 rounded-2 cursor-pointer border-bottom border-light-subtle d-flex flex-column hover-bg-body-secondary"
                                     :class="!selectedConversion.item_id ? 'bg-danger-subtle text-danger-emphasis' : ''"
                                     role="button">
                                    <span class="fw-semibold fs-8">-- Standar Global (Berlaku untuk Semua Barang) --</span>
                                </div>
                                <template x-for="cat in filteredItems" :key="cat.id">
                                    <div @click="select(cat)" 
                                         class="p-2 rounded-2 searchable-item-option border-bottom border-light-subtle d-flex flex-column gap-0.5"
                                         :class="String(cat.id) === String(selectedConversion.item_id) ? 'bg-danger-subtle text-danger-emphasis' : ''"
                                         role="button">
                                        <div class="d-flex align-items-center justify-content-between gap-1">
                                            <span class="fw-semibold fs-8 text-truncate" x-text="cat.name"></span>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-9" x-text="cat.sku"></span>
                                        </div>
                                        <div class="text-secondary fs-9" x-text="'Satuan Dasar: ' + cat.uom"></div>
                                    </div>
                                </template>
                                <div x-show="filteredItems.length === 0" class="text-center py-3 text-secondary fs-8">
                                    <i class="bi bi-inbox me-1"></i> Tidak ada barang yang cocok
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-sm-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">1 Satuan Asal <span class="text-danger">*</span></label>
                            <select name="from_uom" x-model="selectedConversion.from_uom" required class="form-select form-select-sm font-monospace fw-bold fs-8 text-uppercase">
                                <option value="" disabled>-- Pilih Satuan Asal --</option>
                                @foreach($allUoms as $u)
                                    <option value="{{ $u['code'] }}">{{ $u['code'] }} - {{ $u['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Setara Dengan (=) <span class="text-danger">*</span></label>
                            <input type="number" name="conversion_factor" x-model="selectedConversion.conversion_factor" step="any" min="0.0001" required class="form-control form-control-sm font-monospace fw-bold fs-8">
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Satuan Tujuan <span class="text-danger">*</span></label>
                            <select name="to_uom" x-model="selectedConversion.to_uom" required class="form-select form-select-sm font-monospace fw-bold fs-8 text-uppercase">
                                <option value="" disabled>-- Pilih Satuan Tujuan --</option>
                                @foreach($allUoms as $u)
                                    <option value="{{ $u['code'] }}">{{ $u['code'] }} - {{ $u['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Deskripsi / Keterangan Formula</label>
                        <textarea name="description" x-model="selectedConversion.description" rows="2" class="form-control form-control-sm fs-8"></textarea>
                    </div>

                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_conv_active" :checked="selectedConversion.is_active == 1">
                        <label class="form-check-label fs-8 fw-semibold text-body" for="edit_conv_active">Status Konversi Aktif</label>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="editConversionModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CONVERSION MODAL -->
    <div x-show="deleteConversionModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteConversionModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger-subtle text-danger d-flex align-items-center justify-content-between py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <h6 class="mb-0 fw-bold">Konfirmasi Hapus Konversi</h6>
                </div>
                <button type="button" @click="deleteConversionModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form :action="conversionDeleteUrl" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-4 text-center">
                    <p class="mb-2 fs-7 text-body">Apakah Anda yakin ingin menghapus aturan konversi satuan ini?</p>
                    <div class="fw-bold fs-6 text-danger mb-2 font-monospace" x-text="'1 ' + selectedConversion.from_uom + ' = ' + selectedConversion.conversion_factor + ' ' + selectedConversion.to_uom"></div>
                    <div class="fs-8 text-secondary" x-text="'Target: ' + selectedConversion.item_name"></div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="deleteConversionModal = false" class="btn btn-sm btn-outline-secondary px-3">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Konversi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Datalist for UOM input autocomplete -->
    <datalist id="uom-datalist">
        @foreach($uomList as $ul)
            <option value="{{ $ul['code'] }}">{{ $ul['name'] }}</option>
        @endforeach
    </datalist>

</div>
@endsection

