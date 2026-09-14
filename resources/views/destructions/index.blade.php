@extends('layouts.app')
@section('title', 'Pemusnahan Barang & Berita Acara')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pemusnahan Barang</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    createModal: false,
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
    },
    viewModal: false,
    viewItem: null,
    editModal: false,
    editItem: { id: null, destruction_number: '', reason: '', reason_details: '', witness_name_1: '', witness_title_1: '', witness_name_2: '', witness_title_2: '', status: '' },
    deleteModal: false,
    deleteItem: { id: null, destruction_number: '', warehouse_name: '', status: '', total_qty: 0 },
    openViewModal(item) {
        this.viewItem = item;
        this.viewModal = true;
    },
    openEditModal(item) {
        this.editItem = {
            id: item.id,
            destruction_number: item.destruction_number,
            reason: item.reason,
            reason_details: item.reason_details || '',
            witness_name_1: item.witness_name_1 || '',
            witness_title_1: item.witness_title_1 || '',
            witness_name_2: item.witness_name_2 || '',
            witness_title_2: item.witness_title_2 || '',
            status: item.status
        };
        this.editModal = true;
    },
    openDeleteModal(item) {
        this.deleteItem = {
            id: item.id,
            destruction_number: item.destruction_number,
            warehouse_name: item.warehouse ? item.warehouse.name : '-',
            status: item.status,
            total_qty: item.total_qty || 0
        };
        this.deleteModal = true;
    }
}">

    <!-- Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-xs" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- KPI Metric Widgets (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- Box 1: Total Pemusnahan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-fire"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Pemusnahan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($metrics['total']) }}</span>
                    <span class="fs-9 text-secondary">Semua Berkas Pemusnahan</span>
                </div>
            </div>
        </div>

        <!-- Box 2: Menunggu Otorisasi -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Menunggu Otorisasi</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-warning">{{ number_format($metrics['requested']) }}</span>
                    <span class="fs-9 text-secondary">Verifikasi Dokumen & Saksi</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Telah Dimusnahkan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-all"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Telah Dimusnahkan</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($metrics['executed']) }}</span>
                    <span class="fs-9 text-secondary">Berita Acara Resmi Terbit</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Total Nilai Kerugian -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Nilai Kerugian</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-danger">Rp {{ number_format($metrics['total_loss'], 0, ',', '.') }}</span>
                    <span class="fs-9 text-secondary">Nilai Buku Dihapuskan</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <div class="card-header border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <div>
                <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                    <i class="bi bi-fire text-danger"></i>
                    Pemusnahan Barang Terkontrol & Berita Acara
                </h3>
            </div>
            <div class="card-tools ms-md-auto">
                <button type="button" @click="createModal = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-lg"></i>
                    <span>Ajukan Pemusnahan Baru</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('destructions.index') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Status Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Status</option>
                                <option value="REQUESTED" {{ $status === 'REQUESTED' ? 'selected' : '' }}>Menunggu Otorisasi</option>
                                <option value="APPROVED" {{ $status === 'APPROVED' ? 'selected' : '' }}>Disetujui</option>
                                <option value="EXECUTED" {{ $status === 'EXECUTED' ? 'selected' : '' }}>Telah Dimusnahkan</option>
                                <option value="REJECTED" {{ $status === 'REJECTED' ? 'selected' : '' }}>Ditolak</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($status && $status !== 'ALL'))
                        <div class="col-auto">
                            <a href="{{ route('destructions.index') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm border-start-0 border-end-0 fs-8" placeholder="Cari No. Pemusnahan, No. BA, Alasan...">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9">
                        <tr>
                            <th class="ps-3 py-2">No. Berkas</th>
                            <th class="py-2">Gudang Pemusnahan</th>
                            <th class="py-2">Alasan Pemusnahan</th>
                            <th class="py-2">No. Berita Acara</th>
                            <th class="py-2">Saksi Pemusnahan</th>
                            <th class="text-center py-2">Total Item</th>
                            <th class="text-end py-2">Total Nilai</th>
                            <th class="py-2">Status</th>
                            <th class="text-center pe-3 pe-md-4 py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($destructions as $dst)
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-danger">
                                    <a href="{{ route('destructions.show', $dst->id) }}" class="text-decoration-none text-danger">
                                        {{ $dst->destruction_number }}
                                    </a>
                                </td>
                                <td class="fw-semibold">{{ $dst->warehouse?->name }}</td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                        {{ str_replace('_', ' ', $dst->reason) }}
                                    </span>
                                </td>
                                <td class="font-monospace fw-semibold">{{ $dst->berita_acara_number }}</td>
                                <td>
                                    <span class="d-block">{{ $dst->witness_name_1 }} ({{ $dst->witness_title_1 }})</span>
                                    <span class="text-muted fs-9">{{ $dst->witness_name_2 }} ({{ $dst->witness_title_2 }})</span>
                                </td>
                                <td class="text-center font-monospace fw-bold">{{ number_format($dst->total_qty) }}</td>
                                <td class="text-end font-monospace text-danger">Rp {{ number_format($dst->total_loss_value, 0, ',', '.') }}</td>
                                <td>{!! $dst->status_badge !!}</td>
                                <td class="text-center pe-3 pe-md-4 py-2">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from($dst) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Pemusnahan">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        @if($dst->status === 'EXECUTED')
                                            <a href="{{ route('destructions.berita_acara', $dst->id) }}" 
                                               target="_blank" 
                                               class="btn-action-icon text-dark" 
                                               title="Cetak Berita Acara">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        @endif
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($dst) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Pengajuan Pemusnahan">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($dst) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Batalkan / Hapus Pengajuan Pemusnahan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-secondary">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                    Belum ada berkas pemusnahan barang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Standardized Bank Jatim Pagination Footer -->
        <x-pagination-footer :paginator="$destructions" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL: AJUKAN PEMUSNAHAN BARU ==================== -->
    <div x-show="createModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="createModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-3xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-fire fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Pengajuan Pemusnahan Barang Terkontrol</h6>
                        <span class="fs-8 text-secondary">Pemusnahan resmi persediaan rusak, kartu kadaluarsa, atau barang afkir</span>
                    </div>
                </div>
                <button type="button" @click="createModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('destructions.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-2.5">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Lokasi Gudang Pemusnahan <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select form-select-sm fs-8" required>
                                <option value="">-- Pilih Lokasi Gudang --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ $wh->type === 'CENTRAL_LOGISTICS' ? 'selected' : '' }}>
                                        {{ $wh->code }} - {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alasan Pemusnahan <span class="text-danger">*</span></label>
                            <select name="reason" class="form-select form-select-sm fs-8" required>
                                <option value="EXPIRED_CHIP">Chip Kartu ATM / KUE Kadaluarsa (Expired)</option>
                                <option value="DAMAGED_UNUSABLE">Barang Rusak Total Tidak Dapat Dipakai</option>
                                <option value="DISCONTINUED_DESIGN">Desain / Format Discontinue</option>
                                <option value="FAILED_EMBOSS">Gagal Proses Personalisasi / Cacat Emboss</option>
                                <option value="OTHER">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Penjelasan & Kronologi</label>
                            <textarea name="reason_details" class="form-control form-control-sm fs-8" rows="2" placeholder="Uraikan latar belakang teknis atau dasar pemusnahan..."></textarea>
                        </div>
                    </div>

                    <hr class="my-3">

                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-2"><i class="bi bi-people me-1"></i>Data Saksi Pemusnahan (Wajib 2 Saksi Pejabat/Petugas)</label>
                    <div class="row g-2.5">
                        <div class="col-12 col-md-6">
                            <div class="card p-2.5 bg-body-tertiary border">
                                <span class="fs-9 fw-bold text-secondary text-uppercase mb-1 d-block">Saksi 1 (Pejabat Unit Kerja / Audit)</span>
                                <input type="text" name="witness_name_1" class="form-control form-control-sm mb-1 fs-8" placeholder="Nama Lengkap Saksi 1" required>
                                <input type="text" name="witness_title_1" class="form-control form-control-sm fs-8" placeholder="Jabatan Saksi 1" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card p-2.5 bg-body-tertiary border">
                                <span class="fs-9 fw-bold text-secondary text-uppercase mb-1 d-block">Saksi 2 (Petugas Logistik / Keamanan)</span>
                                <input type="text" name="witness_name_2" class="form-control form-control-sm mb-1 fs-8" placeholder="Nama Lengkap Saksi 2" required>
                                <input type="text" name="witness_title_2" class="form-control form-control-sm fs-8" placeholder="Jabatan Saksi 2" required>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-0"><i class="bi bi-box-seam me-1"></i>Item Barang yang Dimusnahkan</label>
                        <button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 fs-8 fw-semibold" @click="addItem()">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Item
                        </button>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-bordered align-middle mb-0 fs-8">
                            <thead class="table-light text-secondary">
                                <tr>
                                    <th style="width: 45%">Pilih Barang</th>
                                    <th style="width: 18%" class="text-center">Jumlah</th>
                                    <th style="width: 25%">No. Batch / Seri</th>
                                    <th style="width: 10%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, index) in items" :key="index">
                                    <tr>
                                        <td>
                                            <select :name="'items[' + index + '][item_id]'" class="form-select form-select-sm fs-8" x-model="row.item_id" required>
                                                <option value="">-- Pilih Barang --</option>
                                                @foreach($items as $it)
                                                    <option value="{{ $it->id }}">{{ $it->sku }} - {{ $it->name }} ({{ $it->uom }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" :name="'items[' + index + '][qty]'" x-model="row.qty" class="form-control form-control-sm text-center font-monospace fw-bold fs-8" min="1" required>
                                        </td>
                                        <td>
                                            <input type="text" :name="'items[' + index + '][batch_or_serial_number]'" x-model="row.batch" class="form-control form-control-sm font-monospace fs-8" placeholder="Contoh: BATCH-01">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-action-icon text-danger" @click="removeItem(index)" x-show="items.length > 1">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-3 px-4 border-top">
                    <button type="button" @click="createModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-4 shadow-xs">
                        <i class="bi bi-fire me-1"></i> Terbitkan Berkas Pemusnahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: LIHAT DETAIL PEMUSNAHAN ==================== -->
    <div x-show="viewModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="viewModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-secondary-subtle text-secondary p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-fire fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Detail Berkas Pemusnahan Barang</h6>
                        <span class="fs-8 font-monospace text-danger" x-text="viewItem?.destruction_number"></span>
                    </div>
                </div>
                <button type="button" @click="viewModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <div class="p-3 rounded-3 bg-body-tertiary border border-secondary-subtle">
                    <div class="row g-2 fs-8">
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Gudang Lokasi Fisik:</span>
                            <strong class="text-body" x-text="viewItem?.warehouse?.name || '-'"></strong>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Nomor Berita Acara:</span>
                            <span class="font-monospace text-danger fw-semibold" x-text="viewItem?.berita_acara_number || '-'"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Alasan Pemusnahan:</span>
                            <span class="badge bg-secondary bg-opacity-10 text-dark border" x-text="viewItem?.reason?.replace(/_/g, ' ')"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Status:</span>
                            <span class="badge" :class="viewItem?.status === 'EXECUTED' ? 'bg-success' : (viewItem?.status === 'APPROVED' ? 'bg-primary' : 'bg-warning text-dark')" x-text="viewItem?.status"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Saksi 1:</span>
                            <span class="text-body fw-semibold" x-text="(viewItem?.witness_name_1 || '-') + ' (' + (viewItem?.witness_title_1 || '-') + ')'"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Saksi 2:</span>
                            <span class="text-body fw-semibold" x-text="(viewItem?.witness_name_2 || '-') + ' (' + (viewItem?.witness_title_2 || '-') + ')'"></span>
                        </div>
                    </div>
                </div>

                <!-- Items Destroyed Table -->
                <div>
                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1.5">Daftar Barang yang Dimusnahkan</label>
                    <div class="table-responsive rounded border border-secondary-subtle">
                        <table class="table table-sm table-hover align-middle mb-0 fs-8">
                            <thead class="bg-body-tertiary text-secondary">
                                <tr>
                                    <th class="ps-3 py-1.5">Nama / SKU Barang</th>
                                    <th class="text-center py-1.5" style="width: 100px;">Jumlah</th>
                                    <th class="text-end py-1.5" style="width: 140px;">Nilai Kerugian</th>
                                    <th class="py-1.5">Alasan Fisik</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(it, idx) in (viewItem?.items || [])" :key="idx">
                                    <tr>
                                        <td class="ps-3 py-1.5">
                                            <span class="fw-semibold text-body" x-text="it.item?.name || ('Item #' + it.item_id)"></span>
                                            <div class="fs-9 text-secondary font-monospace" x-text="it.item?.sku || ''"></div>
                                        </td>
                                        <td class="text-center font-monospace fw-bold" x-text="it.qty"></td>
                                        <td class="text-end font-monospace text-danger fw-semibold" x-text="'Rp ' + Number(it.loss_value || 0).toLocaleString('id-ID')"></td>
                                        <td class="text-secondary fs-9" x-text="it.reason_item || '-'"></td>
                                    </tr>
                                </template>
                                <template x-if="!viewItem?.items || viewItem.items.length === 0">
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-secondary">Tidak ada rincian item.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <template x-if="viewItem?.reason_details">
                    <div class="p-2.5 rounded bg-body-secondary border border-secondary-subtle fs-8">
                        <span class="text-secondary fw-semibold">Keterangan Tambahan:</span>
                        <p class="mb-0 text-body mt-1" x-text="viewItem.reason_details"></p>
                    </div>
                </template>
            </div>

            <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-top">
                <div class="d-flex gap-2">
                    <template x-if="viewItem?.status === 'EXECUTED'">
                        <a :href="'/destructions/' + viewItem?.id + '/berita-acara'" target="_blank" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                            <i class="bi bi-printer"></i>
                            <span>Cetak Berita Acara</span>
                        </a>
                    </template>
                    <a :href="'/destructions/' + viewItem?.id" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-arrow-up-right-square"></i>
                        <span>Buka Layar Pemusnahan</span>
                    </a>
                </div>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: EDIT PENGAJUAN PEMUSNAHAN ==================== -->
    <div x-show="editModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="editModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-pencil fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Edit Pengajuan Pemusnahan Barang</h6>
                        <span class="fs-8 font-monospace text-primary" x-text="editItem.destruction_number"></span>
                    </div>
                </div>
                <button type="button" @click="editModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/destructions/' + editItem.id" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nomor Berkas Pemusnahan</label>
                        <input type="text" class="form-control form-control-sm fs-8 font-monospace" :value="editItem.destruction_number" disabled>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alasan Pemusnahan <span class="text-danger">*</span></label>
                        <select name="reason" x-model="editItem.reason" class="form-select form-select-sm fs-8" required>
                            <option value="EXPIRED_CHIP">Kartu Chip Kadaluarsa (Expired ATM/Debit/Credit)</option>
                            <option value="DEFECTIVE_PRODUCTION">Cacat Cetak / Salah Emboss Blanko Kartu</option>
                            <option value="DAMAGED_RETURN">Barang Retur Rusak Fisik dari Kantor Cabang</option>
                            <option value="OBSOLETE_TOKEN">Hard Token Internet Banking Rusak / Baterai Drop</option>
                            <option value="POLICY_DISPOSAL">Pembersihan Berkala Persediaan Afkir</option>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Saksi 1 <span class="text-danger">*</span></label>
                            <input type="text" name="witness_name_1" x-model="editItem.witness_name_1" class="form-control form-control-sm fs-8" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Jabatan Saksi 1 <span class="text-danger">*</span></label>
                            <input type="text" name="witness_title_1" x-model="editItem.witness_title_1" class="form-control form-control-sm fs-8" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nama Saksi 2 <span class="text-danger">*</span></label>
                            <input type="text" name="witness_name_2" x-model="editItem.witness_name_2" class="form-control form-control-sm fs-8" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Jabatan Saksi 2 <span class="text-danger">*</span></label>
                            <input type="text" name="witness_title_2" x-model="editItem.witness_title_2" class="form-control form-control-sm fs-8" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Keterangan Tambahan</label>
                        <textarea name="reason_details" x-model="editItem.reason_details" rows="2" class="form-control form-control-sm fs-8" placeholder="Keterangan kondisi fisik..."></textarea>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="editModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: HAPUS PENGAJUAN PEMUSNAHAN ==================== -->
    <div x-show="deleteModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="deleteModal = false" 
             class="card shadow-2xl border border-danger-subtle max-w-md w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-danger-subtle d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom border-danger-subtle">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger text-white p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-trash-fill fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Pengajuan Pemusnahan</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/destructions/' + deleteItem.id" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin membatalkan dan menghapus pengajuan pemusnahan barang berikut dari sistem?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">Nomor Pemusnahan & Lokasi:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deleteItem.destruction_number"></div>
                        <div class="fs-8 text-body fw-semibold" x-text="deleteItem.warehouse_name"></div>
                        <div class="fs-8 text-secondary mt-1">
                            Status: <span class="badge bg-secondary" x-text="deleteItem.status"></span> | 
                            Jumlah: <span class="font-monospace fw-bold" x-text="deleteItem.total_qty"></span> unit
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Pemusnahan yang telah dieksekusi resmi tidak dapat dibatalkan.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
