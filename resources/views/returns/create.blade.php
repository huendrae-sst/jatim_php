@extends('layouts.app')
@section('title', 'Form Pengajuan Retur Barang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('returns.index') }}" class="text-decoration-none text-danger">Retur Barang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pengajuan Baru</li>
@endsection

@section('content')
<div class="row justify-content-center" x-data="returnForm()">
    <div class="col-12 col-xl-10">

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-xs mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Terdapat kesalahan pengisian data:
                <ul class="mb-0 mt-1 ps-3">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-xs border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title fw-bold mb-1">Pengajuan Retur Barang Persediaan</h5>
                <p class="text-secondary fs-8 mb-0">Formulir pengembalian barang rusak, cacat, atau tidak sesuai dari kantor cabang ke gudang logistik pusat.</p>
            </div>
            <form action="{{ route('returns.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-semibold text-dark">Gudang Asal Cabang <span class="text-danger">*</span></label>
                            <select name="origin_warehouse_id" class="form-select" required>
                                <option value="">-- Pilih Gudang Cabang Pemohon --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ old('origin_warehouse_id') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->code }} - {{ $wh->name }} ({{ $wh->type }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-semibold text-dark">Gudang Tujuan Retur <span class="text-danger">*</span></label>
                            <select name="destination_warehouse_id" class="form-select" required>
                                <option value="">-- Pilih Gudang Pusat Penerima --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ old('destination_warehouse_id', $wh->type === 'CENTRAL_LOGISTICS' ? $wh->id : '') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->code }} - {{ $wh->name }} ({{ $wh->type }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-semibold text-dark">Alasan Retur <span class="text-danger">*</span></label>
                            <select name="reason" class="form-select" required>
                                <option value="DAMAGED_ON_ARRIVAL">Barang Rusak Saat Diterima (Damaged on Arrival)</option>
                                <option value="DEFECTIVE">Barang Cacat Produksi / Chip Mati</option>
                                <option value="WRONG_SPECIFICATION">Salah Spesifikasi / Salah Kirim</option>
                                <option value="EXCESS_STOCK">Kelebihan Kirim dari Bon / Order</option>
                                <option value="OTHER">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-semibold text-dark">Referensi Order Asal (Opsional)</label>
                            <select name="order_id" class="form-select">
                                <option value="">-- Tanpa Referensi Order Spesifik --</option>
                                @foreach($orders as $ord)
                                    <option value="{{ $ord->id }}" {{ old('order_id') == $ord->id ? 'selected' : '' }}>
                                        {{ $ord->order_number }} - {{ $ord->requestingOrganization?->name }} ({{ $ord->created_at->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-semibold text-dark">Keterangan / Kronologi Kerusakan</label>
                            <textarea name="reason_details" class="form-control" rows="2" placeholder="Jelaskan kondisi detail fisik atau masalah teknis barang yang diretur..."></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Daftar Item yang Diretur -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam me-2"></i>Item Barang yang Diretur</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 fs-8 fw-semibold" @click="addItem()">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Item
                        </button>
                    </div>

                    <div class="table-responsive border rounded-3">
                        <table class="table table-bordered align-middle mb-0 fs-8">
                            <thead class="table-light text-secondary">
                                <tr>
                                    <th style="width: 40%">Pilih Barang Persediaan</th>
                                    <th style="width: 15%" class="text-center">Jumlah Retur</th>
                                    <th style="width: 20%">Kondisi Fisik</th>
                                    <th>Catatan Item</th>
                                    <th style="width: 5%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, index) in items" :key="index">
                                    <tr>
                                        <td>
                                            <select :name="'items[' + index + '][item_id]'" class="form-select form-select-sm" x-model="row.item_id" required>
                                                <option value="">-- Pilih Barang --</option>
                                                @foreach($items as $it)
                                                    <option value="{{ $it->id }}">{{ $it->sku }} - {{ $it->name }} ({{ $it->uom }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" :name="'items[' + index + '][qty_returned]'" x-model="row.qty" class="form-control form-control-sm text-center font-monospace fw-bold" min="1" required>
                                        </td>
                                        <td>
                                            <select :name="'items[' + index + '][condition]'" class="form-select form-select-sm" x-model="row.condition">
                                                <option value="DAMAGED">Rusak Fisik</option>
                                                <option value="DEFECTIVE">Cacat Fungsi/Chip</option>
                                                <option value="GOOD">Kondisi Baik/Segel</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" :name="'items[' + index + '][notes]'" x-model="row.notes" class="form-control form-control-sm" placeholder="Catatan opsional...">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm p-1" @click="removeItem(index)" x-show="items.length > 1">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                    <a href="{{ route('returns.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-danger btn-sm px-4 shadow-xs">
                        <i class="bi bi-send me-1"></i> Kirim Pengajuan Retur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function returnForm() {
    return {
        items: [
            { item_id: '', qty: 1, condition: 'DAMAGED', notes: '' }
        ],
        addItem() {
            this.items.push({ item_id: '', qty: 1, condition: 'DAMAGED', notes: '' });
        },
        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        }
    }
}
</script>
@endsection
