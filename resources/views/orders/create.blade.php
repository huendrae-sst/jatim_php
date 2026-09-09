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

                    <div class="table-responsive border rounded bg-body mb-2" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0 fs-8">
                            <thead class="table-light text-secondary fs-9 text-uppercase sticky-top">
                                <tr>
                                    <th class="ps-3 py-1.5">Pilih Barang / Item</th>
                                    <th class="text-center py-1.5" style="width: 160px;">Jumlah (Qty)</th>
                                    <th class="text-center py-1.5" style="width: 45px;">Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, index) in rows" :key="index">
                                    <tr>
                                        <td class="ps-3 py-1.5">
                                            <select :name="'items[' + index + '][item_id]'" 
                                                    x-model="row.item_id" 
                                                    class="form-select form-select-sm" 
                                                    required>
                                                <option value="">-- Pilih Barang / Item --</option>
                                                <template x-for="it in itemsCatalog" :key="it.id">
                                                    <option :value="it.id" x-text="it.name + ' [' + it.sku + '] (' + it.uom + ')'"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="text-center py-1.5">
                                            <input type="number" :name="'items[' + index + '][qty]'" x-model.number="row.qty" min="1" required class="form-control form-control-sm text-center font-monospace fw-bold fs-8">
                                        </td>
                                        <td class="text-center py-1.5">
                                            <button type="button" @click="removeRow(index)" :disabled="rows.length <= 1" class="btn btn-sm text-danger py-0 px-1" title="Hapus baris barang">
                                                <i class="bi bi-trash fs-8"></i>
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
