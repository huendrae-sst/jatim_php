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
    budgetsCatalog: {{ Js::from($budgets) }},
    selectedOrgId: '{{ old('organization_id', auth()->user()->organization_id ?? $organizations->first()?->id) }}',
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
    },
    getItem(id) {
        return this.itemsCatalog.find(it => String(it.id) === String(id));
    },
    getItemPrice(id) {
        const it = this.getItem(id);
        return it ? parseFloat(it.estimated_unit_price || 0) : 0;
    },
    getCurrentBudget() {
        return this.budgetsCatalog[this.selectedOrgId] || null;
    },
    getTotalOrderEstimate() {
        return this.rows.reduce((sum, row) => {
            if (!row.item_id || !row.qty) return sum;
            return sum + (this.getItemPrice(row.item_id) * parseInt(row.qty || 0));
        }, 0);
    },
    getBudgetAvailable() {
        const b = this.getCurrentBudget();
        if (!b) return 0;
        const allocated = parseFloat(b.allocated_amount || 0);
        const committed = parseFloat(b.committed_amount || 0);
        const realized = parseFloat(b.realized_amount || 0);
        return Math.max(0, allocated - committed - realized);
    },
    getProjectedUtilization() {
        const b = this.getCurrentBudget();
        if (!b) return 0;
        const allocated = parseFloat(b.allocated_amount || 0);
        if (allocated <= 0) return 0;
        const committed = parseFloat(b.committed_amount || 0);
        const realized = parseFloat(b.realized_amount || 0);
        const totalOrder = this.getTotalOrderEstimate();
        return Math.round(((committed + realized + totalOrder) / allocated) * 100 * 10) / 10;
    },
    isOverbudget() {
        const b = this.getCurrentBudget();
        if (!b) return false;
        return this.getProjectedUtilization() > 100;
    },
    formatRupiah(val) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(val || 0));
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
                        <select name="organization_id" x-model="selectedOrgId" required class="form-select fs-7">
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
                        <div>
                            <h5 class="fw-bold fs-6 mb-0 text-body">
                                Daftar Barang yang Diminta
                            </h5>
                            <span class="fs-9 text-muted">Pastikan jumlah dan estimasi nilai tidak melebihi sisa anggaran cabang</span>
                        </div>
                        <button type="button" @click="addRow()" class="btn btn-sm btn-outline-danger fw-semibold">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Item
                        </button>
                    </div>

                    <div class="table-responsive border rounded bg-body mb-2" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0 fs-8">
                            <thead class="table-light text-secondary fs-9 text-uppercase sticky-top">
                                <tr>
                                    <th class="ps-3 py-1.5">Pilih Barang / Item</th>
                                    <th class="text-end py-1.5" style="width: 140px;">Estimasi Harga</th>
                                    <th class="text-center py-1.5" style="width: 130px;">Jumlah (Qty)</th>
                                    <th class="text-end py-1.5" style="width: 150px;">Subtotal</th>
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
                                        <td class="text-end py-1.5 font-monospace text-muted" x-text="formatRupiah(getItemPrice(row.item_id))">
                                        </td>
                                        <td class="text-center py-1.5">
                                            <input type="number" :name="'items[' + index + '][qty]'" x-model.number="row.qty" min="1" required class="form-control form-control-sm text-center font-monospace fw-bold fs-8">
                                        </td>
                                        <td class="text-end py-1.5 font-monospace fw-semibold" x-text="formatRupiah(getItemPrice(row.item_id) * (row.qty || 0))">
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

                    <!-- Real-Time Budget & Order Estimate Summary -->
                    <div class="card bg-light border-0 shadow-none mt-3 p-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-3">
                                <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Estimasi Total Order</span>
                                <span class="fs-6 fw-bold text-dark font-monospace" x-text="formatRupiah(getTotalOrderEstimate())"></span>
                            </div>
                            <template x-if="getCurrentBudget()">
                                <div class="col-12 col-md-9">
                                    <div class="row g-2">
                                        <div class="col-6 col-md-4">
                                            <span class="fs-9 text-uppercase text-secondary d-block">Plafon Anggaran:</span>
                                            <span class="fs-8 fw-semibold text-secondary font-monospace" x-text="formatRupiah(getCurrentBudget().allocated_amount)"></span>
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <span class="fs-9 text-uppercase text-secondary d-block">Sisa Plafon Tersedia:</span>
                                            <span class="fs-8 fw-bold font-monospace" :class="getBudgetAvailable() < getTotalOrderEstimate() ? 'text-danger' : 'text-success'" x-text="formatRupiah(getBudgetAvailable())"></span>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <span class="fs-9 text-uppercase text-secondary d-block">Proyeksi Utilisasi:</span>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar" :class="{
                                                        'bg-success': getProjectedUtilization() <= 80,
                                                        'bg-warning': getProjectedUtilization() > 80 && getProjectedUtilization() <= 100,
                                                        'bg-danger': getProjectedUtilization() > 100
                                                    }" role="progressbar" :style="'width: ' + Math.min(100, getProjectedUtilization()) + '%'"></div>
                                                </div>
                                                <span class="fs-8 fw-bold font-monospace" :class="getProjectedUtilization() > 100 ? 'text-danger' : 'text-dark'" x-text="getProjectedUtilization() + '%'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!getCurrentBudget()">
                                <div class="col-12 col-md-9 text-muted fs-8">
                                    <i class="bi bi-info-circle me-1"></i> Data alokasi anggaran tahun berjalan belum diset untuk unit kerja ini.
                                </div>
                            </template>
                        </div>

                        <!-- Warning Overbudget Banner -->
                        <div x-show="isOverbudget()" x-transition class="alert alert-warning border border-warning d-flex align-items-center gap-2 mt-3 mb-0 py-2 px-3 fs-8">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                            <div>
                                <strong>PERINGATAN OVERBUDGET:</strong> Nilai order melebihi sisa plafon anggaran cabang (proyeksi utilisasi <span class="fw-bold text-danger" x-text="getProjectedUtilization() + '%'"></span>). Order ini akan otomatis ditandai status <strong>OVERBUDGET</strong> dan memerlukan persetujuan khusus serta catatan dispensasi dari Checker/Pimpinan.
                            </div>
                        </div>
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
