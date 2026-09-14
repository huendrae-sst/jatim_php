@extends('layouts.app')
@section('title', 'Form Pengajuan Pemusnahan Barang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('destructions.index') }}" class="text-decoration-none text-danger">Pemusnahan Barang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pengajuan Baru</li>
@endsection

@section('content')
<div class="row justify-content-center" x-data="destructionForm()">
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
                <h5 class="card-title fw-bold mb-1">Pengajuan Pemusnahan Barang Terkontrol</h5>
                <p class="text-secondary fs-8 mb-0">Formulir pemusnahan resmi persediaan rusak permanen, chip kartu kadaluarsa, atau formulir/buku discontinue.</p>
            </div>
            <form action="{{ route('destructions.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-semibold text-dark">Lokasi Gudang Pemusnahan <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select" required>
                                <option value="">-- Pilih Lokasi Gudang --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ old('warehouse_id', $wh->type === 'CENTRAL_LOGISTICS' ? $wh->id : '') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->code }} - {{ $wh->name }} ({{ $wh->type }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-semibold text-dark">Alasan Pemusnahan <span class="text-danger">*</span></label>
                            <select name="reason" class="form-select" required>
                                <option value="EXPIRED_CHIP">Chip Kartu ATM / KUE Kadaluarsa (Expired)</option>
                                <option value="DAMAGED_UNUSABLE">Barang Rusak Total Tidak Dapat Dipakai</option>
                                <option value="DISCONTINUED_DESIGN">Desain / Format Discontinue (Tidak Berlaku)</option>
                                <option value="FAILED_EMBOSS">Gagal Proses Personalisasi / Cacat Emboss</option>
                                <option value="OTHER">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-semibold text-dark">Penjelasan & Kronologi Pemusnahan</label>
                            <textarea name="reason_details" class="form-control" rows="2" placeholder="Uraikan latar belakang teknis atau dasar persetujuan pemusnahan..."></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Saksi-Saksi Pemusnahan (Mandatory 2 Saksi) -->
                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-people me-2"></i>Data Saksi Pemusnahan (Wajib 2 Pejabat/Petugas)</h6>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="card p-3 bg-light border-0">
                                <span class="fs-8 fw-bold text-secondary mb-2 d-block">Saksi 1 (Pejabat Unit Kerja / Kepatuhan)</span>
                                <div class="mb-2">
                                    <label class="form-label fs-9 fw-semibold mb-1">Nama Lengkap Saksi 1 <span class="text-danger">*</span></label>
                                    <input type="text" name="witness_name_1" class="form-control form-control-sm" placeholder="Contoh: Achmad Soebarjo" required>
                                </div>
                                <div>
                                    <label class="form-label fs-9 fw-semibold mb-1">Jabatan Saksi 1 <span class="text-danger">*</span></label>
                                    <input type="text" name="witness_title_1" class="form-control form-control-sm" placeholder="Contoh: Pemimpin Cabang Pembantu" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card p-3 bg-light border-0">
                                <span class="fs-8 fw-bold text-secondary mb-2 d-block">Saksi 2 (Petugas Logistik / Keamanan)</span>
                                <div class="mb-2">
                                    <label class="form-label fs-9 fw-semibold mb-1">Nama Lengkap Saksi 2 <span class="text-danger">*</span></label>
                                    <input type="text" name="witness_name_2" class="form-control form-control-sm" placeholder="Contoh: Bambang Irawan" required>
                                </div>
                                <div>
                                    <label class="form-label fs-9 fw-semibold mb-1">Jabatan Saksi 2 <span class="text-danger">*</span></label>
                                    <input type="text" name="witness_title_2" class="form-control form-control-sm" placeholder="Contoh: Supervisor Operasional / Audit" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Daftar Item yang Dimusnahkan -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam me-2"></i>Item Barang yang Dimusnahkan</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 fs-8 fw-semibold" @click="addItem()">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Item
                        </button>
                    </div>

                    <div class="table-responsive border rounded-3">
                        <table class="table table-bordered align-middle mb-0 fs-8">
                            <thead class="table-light text-secondary">
                                <tr>
                                    <th style="width: 40%">Pilih Barang Persediaan</th>
                                    <th style="width: 15%" class="text-center">Jumlah Unit</th>
                                    <th style="width: 25%">No. Batch / Nomor Seri</th>
                                    <th>Keterangan Kerusakan</th>
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
                                            <input type="number" :name="'items[' + index + '][qty]'" x-model="row.qty" class="form-control form-control-sm text-center font-monospace fw-bold" min="1" required>
                                        </td>
                                        <td>
                                            <input type="text" :name="'items[' + index + '][batch_or_serial_number]'" x-model="row.batch" class="form-control form-control-sm font-monospace" placeholder="Contoh: BATCH-2025-Q1">
                                        </td>
                                        <td>
                                            <input type="text" :name="'items[' + index + '][notes]'" x-model="row.notes" class="form-control form-control-sm" placeholder="Contoh: Chip korosi / cetakan cacat">
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
                    <a href="{{ route('destructions.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-danger btn-sm px-4 shadow-xs">
                        <i class="bi bi-send me-1"></i> Kirim Pengajuan Pemusnahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function destructionForm() {
    return {
        items: [
            { item_id: '', qty: 1, batch: '', notes: '' }
        ],
        addItem() {
            this.items.push({ item_id: '', qty: 1, batch: '', notes: '' });
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
