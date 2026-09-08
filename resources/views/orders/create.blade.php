@extends('layouts.app')
@section('title', 'Buat Order Permintaan Barang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('orders.index') }}" class="text-decoration-none text-danger">Permintaan & Order</a></li>
    <li class="breadcrumb-item active" aria-current="page">Buat Order Baru</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    rows: [{ item_id: '', qty: 1 }],
    itemsCatalog: {{ Js::from($items) }},
    addRow() {
        this.rows.push({ item_id: '', qty: 1 });
    },
    removeRow(index) {
        if (this.rows.length > 1) {
            this.rows.splice(index, 1);
        }
    },
    resetForm() {
        this.rows = [{ item_id: '', qty: 1 }];
        if (this.$refs.orderForm) {
            this.$refs.orderForm.reset();
        }
    }
}">

    <!-- Form Card -->
    <div class="card card-outline card-danger shadow-xs">
        <form action="{{ route('orders.store') }}" method="POST" x-ref="orderForm">
            @csrf
            <div class="card-header border-bottom d-flex flex-column flex-sm-row sm:items-center justify-content-between gap-2 py-3 px-3 px-md-4">
                <h3 class="card-title fs-6 fw-bold mb-0 text-body">
                    Informasi Permintaan & Logistik
                </h3>
                <div class="card-tools w-100 w-sm-auto">
                    <a href="{{ route('orders.index') }}" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center justify-content-center gap-1 w-100 w-sm-auto">
                        <i class="bi bi-arrow-left"></i>
                        <span>Kembali ke Daftar</span>
                    </a>
                </div>
            </div>
            <div class="card-body p-4 space-y-4">
                <div class="row g-3">
                    <!-- Unit Peminta -->
                    <div class="col-12 col-md-4">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Unit Kerja Peminta</label>
                        <select name="organization_id" required class="form-select fs-7">
                            @foreach($organizations as $org)
                                <option value="{{ $org->id }}" {{ $org->id === auth()->user()->organization_id ? 'selected' : '' }}>
                                    {{ $org->code }} - {{ $org->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Prioritas -->
                    <div class="col-12 col-md-4">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Prioritas Permintaan</label>
                        <select name="priority" required class="form-select fs-7">
                            <option value="NORMAL">Normal (Rutin / Bulanan)</option>
                            <option value="HIGH">High (Tinggi)</option>
                            <option value="URGENT">Urgent (Mendesak / Stok Teller Menipis)</option>
                        </select>
                    </div>

                    <!-- Target Tanggal Dibutuhkan -->
                    <div class="col-12 col-md-4">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Target Tanggal Dibutuhkan</label>
                        <input type="date" name="required_date" value="{{ date('Y-m-d', strtotime('+3 days')) }}" required class="form-control fs-7">
                    </div>

                    <!-- Catatan -->
                    <div class="col-12">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan / Keterangan Order</label>
                        <textarea name="notes" rows="2" class="form-control fs-7"></textarea>
                    </div>
                </div>

                <!-- Items Dynamic Table -->
                <div class="border-top pt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold fs-6 mb-0 text-body">
                            Daftar Barang yang Diminta
                        </h5>
                        <button type="button" @click="addRow()" class="btn btn-sm btn-outline-danger fw-semibold">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Item
                        </button>
                    </div>

                    <div class="table-responsive border rounded-3" style="overflow: visible;">
                        <table class="table table-hover align-middle mb-0 fs-7">
                            <thead class="border-bottom fs-8 text-uppercase text-secondary bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Item / SKU Barang</th>
                                    <th class="text-center" style="width: 160px;">Jumlah (Qty)</th>
                                    <th class="text-center" style="width: 70px;">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, index) in rows" :key="index">
                                    <tr>
                                        <td class="ps-3">
                                            <div class="position-relative" 
                                                 x-data="{
                                                     open: false,
                                                     search: '',
                                                     get selectedItem() {
                                                         return itemsCatalog.find(i => i.id == row.item_id) || null;
                                                     },
                                                     get filteredItems() {
                                                         if (!this.search.trim()) return itemsCatalog;
                                                         let q = this.search.toLowerCase();
                                                         return itemsCatalog.filter(i => 
                                                             (i.name && i.name.toLowerCase().includes(q)) || 
                                                             (i.sku && i.sku.toLowerCase().includes(q)) ||
                                                             (i.uom && i.uom.toLowerCase().includes(q))
                                                         );
                                                     },
                                                     select(cat) {
                                                         row.item_id = cat.id;
                                                         this.open = false;
                                                         this.search = '';
                                                     }
                                                 }" 
                                                 :style="open ? 'z-index: 1060;' : 'z-index: 1;'" 
                                                 @click.outside="open = false" 
                                                 @keydown.escape.window="open = false">

                                                <button type="button" 
                                                        @click="open = !open; if(open) { $nextTick(() => $refs.sInput?.focus()); }" 
                                                        class="form-select fs-7 text-start d-flex align-items-center justify-content-between text-truncate bg-body" 
                                                        :class="row.item_id ? 'text-body' : 'text-secondary'">
                                                    <span class="text-truncate" x-text="selectedItem ? (selectedItem.sku + ' - ' + selectedItem.name + ' (' + selectedItem.uom + ')') : 'Pilih Barang dari Katalog...'"></span>
                                                </button>
                                                <input type="hidden" :name="'items[' + index + '][item_id]'" :value="row.item_id" required>

                                                <div x-show="open" 
                                                     x-cloak 
                                                     class="position-absolute start-0 mt-1 w-100 bg-body border border-secondary-subtle rounded-3 shadow-lg p-2" 
                                                     style="z-index: 1060; min-width: 280px;">
                                                    <div class="input-group input-group-sm mb-2">
                                                        <span class="input-group-text bg-body text-secondary border-end-0 py-1 px-2">
                                                            <i class="bi bi-search"></i>
                                                        </span>
                                                        <input type="text" 
                                                               x-ref="sInput" 
                                                               x-model="search" 
                                                               class="form-control form-control-sm border-start-0 fs-8 py-1" 
                                                               autocomplete="off" 
                                                               @keydown.enter.prevent="if (filteredItems.length > 0) select(filteredItems[0])">
                                                        <button type="button" 
                                                                x-show="search" 
                                                                @click="search = ''; $refs.sInput.focus()" 
                                                                class="btn btn-sm btn-outline-secondary border-start-0 py-0 px-2 fs-9">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </div>
                                                    <div class="overflow-y-auto" style="max-height: 180px;">
                                                        <template x-for="cat in filteredItems" :key="cat.id">
                                                            <div @click="select(cat)" 
                                                                 class="p-2 rounded-2 searchable-item-option border-bottom border-light-subtle d-flex flex-column gap-0.5" 
                                                                 :class="cat.id == row.item_id ? 'bg-danger-subtle text-danger-emphasis' : ''" 
                                                                 role="button">
                                                                <div class="d-flex align-items-center justify-content-between gap-1">
                                                                    <span class="fw-semibold fs-8 text-truncate" x-text="cat.name"></span>
                                                                    <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace fs-9" x-text="cat.sku"></span>
                                                                </div>
                                                                <div class="d-flex align-items-center justify-content-between text-secondary fs-9">
                                                                    <span x-text="'Satuan: ' + cat.uom"></span>
                                                                    <span class="font-monospace" x-text="cat.estimated_unit_price ? ('Rp ' + Number(cat.estimated_unit_price).toLocaleString('id-ID')) : ''"></span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <div x-show="filteredItems.length === 0" class="text-center py-3 text-secondary fs-8">
                                                            <i class="bi bi-inbox me-1"></i> Tidak ada barang yang cocok
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" :name="'items[' + index + '][qty]'" x-model="row.qty" min="1" required class="form-control form-control-sm text-center fw-bold font-monospace fs-7">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" @click="removeRow(index)" class="btn-action-icon text-danger" title="Hapus Baris">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Card Footer Submit -->
            <div class="card-footer bg-body border-top d-flex flex-column-reverse flex-sm-row justify-content-end align-items-stretch align-items-sm-center gap-2 py-3 px-3 px-md-4">
                <button type="button" @click="resetForm()" class="btn btn-sm btn-outline-secondary px-3 fw-semibold d-inline-flex align-items-center justify-content-center gap-1" title="Bersihkan dan reset form">
                    <i class="bi bi-x-circle"></i>
                    <span>Batal</span>
                </button>
                <button type="submit" class="btn btn-sm btn-danger px-4 fw-bold shadow-xs d-inline-flex align-items-center justify-content-center gap-1">
                    <i class="bi bi-send"></i>
                    <span>Submit Order Permintaan</span>
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
