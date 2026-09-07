@extends('layouts.app')
@section('title', 'Buat Purchase Request (PR)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('procurement.pr.index') }}" class="text-decoration-none text-danger">Purchase Request</a></li>
    <li class="breadcrumb-item active" aria-current="page">Buat PR</li>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-4" x-data="{
    rows: [{ item_id: '', qty: 1, unit_price: 0, subtotal: 0 }],
    itemsCatalog: {{ Js::from($items) }},
    updatePrice(index) {
        const selectedId = this.rows[index].item_id;
        const found = this.itemsCatalog.find(i => i.id == selectedId);
        if (found) {
            this.rows[index].unit_price = parseFloat(found.estimated_unit_price);
            this.rows[index].subtotal = this.rows[index].qty * this.rows[index].unit_price;
        }
    },
    updateSubtotal(index) {
        this.rows[index].subtotal = this.rows[index].qty * this.rows[index].unit_price;
    },
    addRow() {
        this.rows.push({ item_id: '', qty: 1, unit_price: 0, subtotal: 0 });
    },
    removeRow(index) {
        if (this.rows.length > 1) {
            this.rows.splice(index, 1);
        }
    },
    getTotal() {
        return this.rows.reduce((sum, r) => sum + (parseFloat(r.subtotal) || 0), 0);
    }
}">

    <!-- Form -->
    <form action="{{ route('procurement.pr.store') }}" method="POST" class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Unit Pengaju</label>
                <select name="organization_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-jatim-700">
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}" {{ $org->id === auth()->user()->organization_id ? 'selected' : '' }}>
                            {{ $org->code }} - {{ $org->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Metode Pengadaan</label>
                <select name="procurement_method" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-jatim-700">
                    <option value="DIRECT">Pengadaan Langsung</option>
                    <option value="TENDER">Tender Terbuka / Terbatas</option>
                    <option value="E_CATALOG">E-Katalog Bank Jatim</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tujuan / Justifikasi Pengadaan</label>
            <textarea name="purpose" rows="2" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-jatim-700"></textarea>
        </div>

        <!-- Items Table -->
        <div class="border-t border-slate-100 pt-5 space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900">Daftar Barang yang Diajukan</h3>
                <button type="button" @click="addRow()" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-3 py-1.5 rounded-lg">
                    <i class="fa-solid fa-plus mr-1"></i> Tambah Item
                </button>
            </div>

            <div class="overflow-visible">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase tracking-wider font-bold">
                            <th class="py-2.5 px-3">Item / SKU</th>
                            <th class="py-2.5 px-3 w-28">Jumlah (Qty)</th>
                            <th class="py-2.5 px-3 w-40 text-right">Est. Harga Satuan (Rp)</th>
                            <th class="py-2.5 px-3 w-40 text-right">Subtotal (Rp)</th>
                            <th class="py-2.5 px-3 w-12 text-center">Hapus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(row, index) in rows" :key="index">
                            <tr>
                                <td class="py-2.5 px-3">
                                    <div class="relative" 
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
                                                 updatePrice(index);
                                                 this.open = false;
                                                 this.search = '';
                                             }
                                         }" 
                                         :style="open ? 'z-index: 1060;' : 'z-index: 1;'" 
                                         @click.outside="open = false" 
                                         @keydown.escape.window="open = false">

                                        <button type="button" 
                                                @click="open = !open; if(open) { $nextTick(() => $refs.sPrInput?.focus()); }" 
                                                class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs text-left flex items-center justify-between text-truncate"
                                                :class="row.item_id ? 'text-slate-800 font-medium' : 'text-slate-400'">
                                            <span class="truncate" x-text="selectedItem ? (selectedItem.sku + ' - ' + selectedItem.name + ' (' + selectedItem.uom + ')') : '-- Pilih Barang --'"></span>
                                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 ms-1 flex-shrink-0"></i>
                                        </button>
                                        <input type="hidden" :name="'items[' + index + '][item_id]'" :value="row.item_id" required>

                                        <div x-show="open" 
                                             x-cloak 
                                             class="absolute left-0 mt-1 w-full min-w-[280px] bg-white border border-slate-200 rounded-xl shadow-xl p-2 z-50">
                                            <div class="relative mb-2">
                                                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400 text-xs"></i>
                                                <input type="text" 
                                                       x-ref="sPrInput" 
                                                       x-model="search" 
                                                       class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-7 py-1 text-xs focus:ring-2 focus:ring-jatim-700 focus:outline-none" 
                                                       autocomplete="off" 
                                                       @keydown.enter.prevent="if (filteredItems.length > 0) select(filteredItems[0])">
                                                <button type="button" 
                                                        x-show="search" 
                                                        @click="search = ''; $refs.sPrInput.focus()" 
                                                        class="absolute right-2 top-1.5 text-slate-400 hover:text-slate-600 text-xs">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                            <div class="max-h-48 overflow-y-auto divide-y divide-slate-100">
                                                <template x-for="cat in filteredItems" :key="cat.id">
                                                    <div @click="select(cat)" 
                                                         class="p-2 rounded-lg cursor-pointer hover:bg-slate-50 flex flex-col gap-0.5 transition" 
                                                         :class="cat.id == row.item_id ? 'bg-amber-50 font-semibold text-amber-900' : 'text-slate-700'" 
                                                         role="button">
                                                        <div class="flex items-center justify-between gap-1">
                                                            <span class="text-xs truncate" x-text="cat.name"></span>
                                                            <span class="bg-slate-100 text-slate-600 font-mono text-[10px] px-1.5 py-0.5 rounded" x-text="cat.sku"></span>
                                                        </div>
                                                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                                                            <span x-text="'Satuan: ' + cat.uom"></span>
                                                            <span class="font-mono" x-text="'Rp ' + Number(cat.estimated_unit_price || 0).toLocaleString('id-ID')"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                                <div x-show="filteredItems.length === 0" class="text-center py-3 text-slate-400 text-xs">
                                                    <i class="fa-solid fa-inbox me-1"></i> Tidak ada barang yang cocok
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="number" :name="'items[' + index + '][qty]'" x-model="row.qty" @input="updateSubtotal(index)" min="1" required class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs text-center font-bold">
                                </td>
                                <td class="py-2.5 px-3">
                                    <input type="number" :name="'items[' + index + '][unit_price]'" x-model="row.unit_price" @input="updateSubtotal(index)" min="0" required class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs text-right font-semibold">
                                </td>
                                <td class="py-2.5 px-3 text-right font-bold text-slate-900">
                                    <span x-text="'Rp ' + (row.subtotal).toLocaleString('id-ID')"></span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <button type="button" @click="removeRow(index)" class="text-rose-500 hover:text-rose-700 p-1">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Total Bar -->
            <div class="flex items-center justify-between bg-slate-50 p-4 rounded-xl border border-slate-200 mt-4">
                <span class="text-xs font-bold uppercase text-slate-500">Total Estimasi Nilai PR:</span>
                <span class="text-lg font-black text-jatim-700" x-text="'Rp ' + getTotal().toLocaleString('id-ID')"></span>
            </div>
        </div>

        <div class="pt-4 flex justify-end space-x-3">
            <a href="{{ route('procurement.pr.index') }}" class="px-5 py-2.5 border border-slate-200 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-50 transition">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 bg-jatim-700 hover:bg-jatim-800 text-white rounded-xl text-xs font-bold shadow-md transition">
                Submit Purchase Request
            </button>
        </div>
    </form>
</div>
@endsection
