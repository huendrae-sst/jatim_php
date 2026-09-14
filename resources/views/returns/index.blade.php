@extends('layouts.app')
@section('title', 'Reverse Inventory - Retur Barang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Retur Barang</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    createModal: false,
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
    },
    viewModal: false,
    viewItem: null,
    editModal: false,
    editItem: { id: null, return_number: '', reason: '', reason_details: '', status: '' },
    deleteModal: false,
    deleteItem: { id: null, return_number: '', origin: '', status: '', total_qty: 0 },
    openViewModal(item) {
        this.viewItem = item;
        this.viewModal = true;
    },
    openEditModal(item) {
        this.editItem = {
            id: item.id,
            return_number: item.return_number,
            reason: item.reason,
            reason_details: item.reason_details || '',
            status: item.status
        };
        this.editModal = true;
    },
    openDeleteModal(item) {
        this.deleteItem = {
            id: item.id,
            return_number: item.return_number,
            origin: item.origin_warehouse ? item.origin_warehouse.name : '-',
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
        <!-- Box 1: Total Retur -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-arrow-return-left"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Retur Barang</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($metrics['total']) }}</span>
                    <span class="fs-9 text-secondary">Semua Transaksi Retur</span>
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
                    <span class="fs-9 text-secondary">Permohonan Baru Cabang</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Dalam Pengiriman -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-truck"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Dalam Pengiriman</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-primary">{{ number_format($metrics['shipped']) }}</span>
                    <span class="fs-9 text-secondary">Menuju Gudang Pusat SIER</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Selesai Diterima -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Selesai Diterima</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($metrics['received']) }}</span>
                    <span class="fs-9 text-secondary">Barang Masuk & Disesuaikan</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <div class="card-header border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <div>
                <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-return-left text-danger"></i>
                    Daftar Retur Barang Persediaan
                </h3>
            </div>
            <div class="card-tools ms-md-auto">
                <button type="button" @click="createModal = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-lg"></i>
                    <span>Ajukan Retur Barang</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('returns.index') }}" method="GET">
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
                                <option value="SHIPPED" {{ $status === 'SHIPPED' ? 'selected' : '' }}>Dalam Pengiriman</option>
                                <option value="RECEIVED" {{ $status === 'RECEIVED' ? 'selected' : '' }}>Selesai Diterima</option>
                                <option value="REJECTED" {{ $status === 'REJECTED' ? 'selected' : '' }}>Ditolak</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($status && $status !== 'ALL'))
                        <div class="col-auto">
                            <a href="{{ route('returns.index') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm border-start-0 border-end-0 fs-8" placeholder="Cari No. Retur, Resi, Alasan...">
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
                            <th class="ps-3 py-2">No. Retur</th>
                            <th class="py-2">Gudang Asal</th>
                            <th class="py-2">Gudang Tujuan</th>
                            <th class="py-2">Alasan Retur</th>
                            <th class="text-center py-2">Total Qty</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Diajukan Oleh</th>
                            <th class="py-2">Waktu</th>
                            <th class="text-center pe-3 pe-md-4 py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $ret)
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-danger">
                                    <a href="{{ route('returns.show', $ret->id) }}" class="text-decoration-none text-danger">
                                        {{ $ret->return_number }}
                                    </a>
                                </td>
                                <td class="fw-semibold">{{ $ret->originWarehouse?->name }}</td>
                                <td>{{ $ret->destinationWarehouse?->name }}</td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                        {{ str_replace('_', ' ', $ret->reason) }}
                                    </span>
                                </td>
                                <td class="text-center font-monospace fw-bold">{{ number_format($ret->total_qty) }}</td>
                                <td>{!! $ret->status_badge !!}</td>
                                <td>{{ $ret->requester?->name }}</td>
                                <td class="text-secondary">{{ $ret->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-center pe-3 pe-md-4 py-2">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from($ret) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Retur">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="{{ route('returns.show', $ret->id) }}" 
                                           class="btn-action-icon text-dark" 
                                           title="Buka Alur Proses Retur">
                                            <i class="bi bi-arrow-up-right-square"></i>
                                        </a>
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($ret) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Permohonan Retur">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($ret) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Batalkan / Hapus Permohonan Retur">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-secondary">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                    Belum ada permohonan retur barang.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Standardized Bank Jatim Pagination Footer -->
        <x-pagination-footer :paginator="$returns" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL: AJUKAN RETUR BARANG ==================== -->
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
                        <i class="bi bi-arrow-return-left fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Pengajuan Retur Barang Persediaan</h6>
                        <span class="fs-8 text-secondary">Pengembalian barang rusak / cacat / salah kirim ke gudang pusat</span>
                    </div>
                </div>
                <button type="button" @click="createModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('returns.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-2.5">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Gudang Asal Cabang <span class="text-danger">*</span></label>
                            <select name="origin_warehouse_id" class="form-select form-select-sm fs-8" required>
                                <option value="">-- Pilih Gudang Cabang --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">
                                        {{ $wh->code }} - {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Gudang Tujuan Retur <span class="text-danger">*</span></label>
                            <select name="destination_warehouse_id" class="form-select form-select-sm fs-8" required>
                                <option value="">-- Pilih Gudang Pusat Penerima --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ $wh->type === 'CENTRAL_LOGISTICS' ? 'selected' : '' }}>
                                        {{ $wh->code }} - {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alasan Retur <span class="text-danger">*</span></label>
                            <select name="reason" class="form-select form-select-sm fs-8" required>
                                <option value="DAMAGED_ON_ARRIVAL">Barang Rusak Saat Diterima (Damaged on Arrival)</option>
                                <option value="DEFECTIVE">Barang Cacat Produksi / Chip Mati</option>
                                <option value="WRONG_SPECIFICATION">Salah Spesifikasi / Salah Kirim</option>
                                <option value="EXCESS_STOCK">Kelebihan Kirim dari Bon / Order</option>
                                <option value="OTHER">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Referensi Order Asal (Opsional)</label>
                            <select name="order_id" class="form-select form-select-sm fs-8">
                                <option value="">-- Tanpa Referensi Order --</option>
                                @foreach($orders as $ord)
                                    <option value="{{ $ord->id }}">
                                        {{ $ord->order_number }} - {{ $ord->requestingOrganization?->name }} ({{ $ord->created_at->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Keterangan / Kronologi Kerusakan</label>
                            <textarea name="reason_details" class="form-control form-control-sm fs-8" rows="2" placeholder="Jelaskan kondisi detail fisik atau masalah teknis barang yang diretur..."></textarea>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-0"><i class="bi bi-box-seam me-1"></i>Item Barang yang Diretur</label>
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
                                    <th style="width: 22%">Kondisi</th>
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
                                            <input type="number" :name="'items[' + index + '][qty_returned]'" x-model="row.qty" class="form-control form-control-sm text-center font-monospace fw-bold fs-8" min="1" required>
                                        </td>
                                        <td>
                                            <select :name="'items[' + index + '][condition]'" class="form-select form-select-sm fs-8" x-model="row.condition">
                                                <option value="DAMAGED">Rusak Fisik</option>
                                                <option value="DEFECTIVE">Cacat Fungsi/Chip</option>
                                                <option value="GOOD">Kondisi Baik/Segel</option>
                                            </select>
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
                        <i class="bi bi-send me-1"></i> Kirim Pengajuan Retur
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: LIHAT DETAIL RETUR ==================== -->
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
                        <i class="bi bi-arrow-return-left fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Detail Permohonan Retur Barang</h6>
                        <span class="fs-8 font-monospace text-danger" x-text="viewItem?.return_number"></span>
                    </div>
                </div>
                <button type="button" @click="viewModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <div class="p-3 rounded-3 bg-body-tertiary border border-secondary-subtle">
                    <div class="row g-2 fs-8">
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Gudang Cabang Asal:</span>
                            <strong class="text-body" x-text="viewItem?.origin_warehouse?.name || '-'"></strong>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Gudang Logistik Tujuan:</span>
                            <span class="text-body fw-semibold" x-text="viewItem?.destination_warehouse?.name || '-'"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Alasan Utama Retur:</span>
                            <span class="badge bg-secondary bg-opacity-10 text-dark border" x-text="viewItem?.reason?.replace(/_/g, ' ')"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Status Permohonan:</span>
                            <span class="badge" :class="viewItem?.status === 'RECEIVED' ? 'bg-success' : (viewItem?.status === 'SHIPPED' ? 'bg-info' : (viewItem?.status === 'APPROVED' ? 'bg-primary' : 'bg-warning text-dark'))" x-text="viewItem?.status"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Ekspedisi / Resi Pengiriman:</span>
                            <span class="font-monospace text-body" x-text="(viewItem?.courier_name || '-') + ' / ' + (viewItem?.tracking_number || '-')"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Dibuat Oleh:</span>
                            <span class="text-body" x-text="viewItem?.requester?.name || '-'"></span>
                        </div>
                    </div>
                </div>

                <!-- Items Returned Table -->
                <div>
                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1.5">Daftar Barang yang Diretur</label>
                    <div class="table-responsive rounded border border-secondary-subtle">
                        <table class="table table-sm table-hover align-middle mb-0 fs-8">
                            <thead class="bg-body-tertiary text-secondary">
                                <tr>
                                    <th class="ps-3 py-1.5">Nama / SKU Barang</th>
                                    <th class="text-center py-1.5" style="width: 100px;">Kuantitas</th>
                                    <th class="text-center py-1.5" style="width: 110px;">Kondisi Fisik</th>
                                    <th class="text-center py-1.5" style="width: 100px;">Diterima Baik</th>
                                    <th class="text-center py-1.5" style="width: 100px;">Diterima Rusak</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(it, idx) in (viewItem?.items || [])" :key="idx">
                                    <tr>
                                        <td class="ps-3 py-1.5">
                                            <span class="fw-semibold text-body" x-text="it.item?.name || ('Item #' + it.item_id)"></span>
                                            <div class="fs-9 text-secondary font-monospace" x-text="it.item?.sku || ''"></div>
                                        </td>
                                        <td class="text-center font-monospace fw-bold" x-text="it.qty_returned"></td>
                                        <td class="text-center">
                                            <span class="badge" :class="it.condition === 'GOOD' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'" x-text="it.condition"></span>
                                        </td>
                                        <td class="text-center font-monospace text-success fw-bold" x-text="it.qty_good_received !== null ? it.qty_good_received : '-'"></td>
                                        <td class="text-center font-monospace text-danger fw-bold" x-text="it.qty_damaged_received !== null ? it.qty_damaged_received : '-'"></td>
                                    </tr>
                                </template>
                                <template x-if="!viewItem?.items || viewItem.items.length === 0">
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-secondary">Tidak ada rincian item.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <template x-if="viewItem?.reason_details">
                    <div class="p-2.5 rounded bg-body-secondary border border-secondary-subtle fs-8">
                        <span class="text-secondary fw-semibold">Detail Keterangan Retur:</span>
                        <p class="mb-0 text-body mt-1" x-text="viewItem.reason_details"></p>
                    </div>
                </template>
            </div>

            <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-top">
                <a :href="'/returns/' + viewItem?.id" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-up-right-square"></i>
                    <span>Buka Alur Proses Lengkap</span>
                </a>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: EDIT PERMOHONAN RETUR ==================== -->
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
                        <h6 class="mb-0 fw-bold text-body">Edit Permohonan Retur Barang</h6>
                        <span class="fs-8 font-monospace text-primary" x-text="editItem.return_number"></span>
                    </div>
                </div>
                <button type="button" @click="editModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/returns/' + editItem.id" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nomor Retur</label>
                        <input type="text" class="form-control form-control-sm fs-8 font-monospace" :value="editItem.return_number" disabled>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alasan Utama Retur <span class="text-danger">*</span></label>
                        <select name="reason" x-model="editItem.reason" class="form-select form-select-sm fs-8" required>
                            <option value="DEFECTIVE_DAMAGED">Barang Cacat Produksi / Rusak Fisik</option>
                            <option value="EXCESS_STOCK">Kelebihan Saldo Persediaan (Rebalancing Gudang)</option>
                            <option value="EXPIRED_OBSOLETE">Kartu/Token Usang / Mendekati Expired</option>
                            <option value="WRONG_DELIVERY">Salah Pengiriman / Salah Tipe Barang</option>
                            <option value="BRANCH_CLOSING">Penutupan / Penggabungan Kantor Layanan</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Detail Keterangan Tambahan</label>
                        <textarea name="reason_details" x-model="editItem.reason_details" rows="3" class="form-control form-control-sm fs-8" placeholder="Keterangan kondisi barang..."></textarea>
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

    <!-- ==================== MODAL: HAPUS PERMOHONAN RETUR ==================== -->
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
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Batalkan Permohonan Retur</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/returns/' + deleteItem.id" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin membatalkan dan menghapus permohonan retur barang berikut dari sistem?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">Nomor Retur & Cabang Asal:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deleteItem.return_number"></div>
                        <div class="fs-8 text-body fw-semibold" x-text="deleteItem.origin"></div>
                        <div class="fs-8 text-secondary mt-1">
                            Status: <span class="badge bg-secondary" x-text="deleteItem.status"></span> | 
                            Jumlah: <span class="font-monospace fw-bold" x-text="deleteItem.total_qty"></span> unit
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Permohonan retur yang telah dikirim ekspedisi atau diterima gudang pusat tidak dapat dibatalkan.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Retur
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
