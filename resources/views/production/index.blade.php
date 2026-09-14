@extends('layouts.app')
@section('title', 'Produksi, Personalisasi & Manifest')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">Produksi & Personalisasi</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    createModal: false,
    items: [
        { item_id: '', qty: 100, notes: '' }
    ],
    addItem() {
        this.items.push({ item_id: '', qty: 100, notes: '' });
    },
    removeItem(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
        }
    },
    viewModal: false,
    viewItem: null,
    editModal: false,
    editItem: { id: null, production_number: '', target_completion_date: '', notes: '', status: '' },
    deleteModal: false,
    deleteItem: { id: null, production_number: '', branch: '', status: '', total_planned_qty: 0 },
    openViewModal(item) {
        this.viewItem = item;
        this.viewModal = true;
    },
    openEditModal(item) {
        this.editItem = {
            id: item.id,
            production_number: item.production_number,
            target_completion_date: item.target_completion_date ? item.target_completion_date.substring(0, 10) : '',
            notes: item.notes || '',
            status: item.status
        };
        this.editModal = true;
    },
    openDeleteModal(item) {
        this.deleteItem = {
            id: item.id,
            production_number: item.production_number,
            branch: item.destination_organization ? item.destination_organization.name : '-',
            status: item.status,
            total_planned_qty: item.total_planned_qty || 0
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
        <!-- Box 1: Total Bon Produksi -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cpu"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Bon Produksi</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($metrics['total']) }}</span>
                    <span class="fs-9 text-secondary">Semua Surat Perintah Kerja</span>
                </div>
            </div>
        </div>

        <!-- Box 2: Rencana Planned -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-clock-history"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Rencana / Planned</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-warning">{{ number_format($metrics['planned']) }}</span>
                    <span class="fs-9 text-secondary">Menunggu Pengeluaran Bahan</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Dalam Proses Perso -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-primary"><i class="bi bi-gear-wide-connected"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Dalam Proses Perso</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-primary">{{ number_format($metrics['in_production']) }}</span>
                    <span class="fs-9 text-secondary">Sedang Dicetak / Dipersonalisasi</span>
                </div>
            </div>
        </div>

        <!-- Box 4: Selesai Produksi -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-all"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Selesai Produksi</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($metrics['completed']) }}</span>
                    <span class="fs-9 text-secondary">Siap Kirim / Diserahkan</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <div class="card-header border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <div>
                <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                    <i class="bi bi-cpu text-danger"></i>
                    Manajemen Bon Produksi & Personalisasi Kartu
                </h3>
            </div>
            <div class="card-tools ms-md-auto">
                <button type="button" @click="createModal = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-lg"></i>
                    <span>Buat Bon Produksi Baru</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('production.index') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Status Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-toggle-on"></i></span>
                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Status</option>
                                <option value="PLANNED" {{ $status === 'PLANNED' ? 'selected' : '' }}>Direncanakan (Planned)</option>
                                <option value="ISSUED" {{ $status === 'ISSUED' ? 'selected' : '' }}>Bahan Dikeluarkan</option>
                                <option value="IN_PRODUCTION" {{ $status === 'IN_PRODUCTION' ? 'selected' : '' }}>Dalam Proses Perso</option>
                                <option value="COMPLETED" {{ $status === 'COMPLETED' ? 'selected' : '' }}>Selesai</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($status && $status !== 'ALL'))
                        <div class="col-auto">
                            <a href="{{ route('production.index') }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm border-start-0 border-end-0 fs-8" placeholder="Cari No. Bon, Cabang Tujuan...">
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
                            <th class="ps-3 py-2">No. Bon Produksi</th>
                            <th class="py-2">Cabang Tujuan</th>
                            <th class="py-2">Gudang Bahan</th>
                            <th class="py-2">Status</th>
                            <th class="text-center py-2">Rencana Qty</th>
                            <th class="text-center py-2">Bahan Keluar</th>
                            <th class="py-2">Operator</th>
                            <th class="py-2">Waktu Dibuat</th>
                            <th class="text-center pe-3 pe-md-4 py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productionOrders as $prod)
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-danger">
                                    <a href="{{ route('production.show', $prod->id) }}" class="text-decoration-none text-danger">
                                        {{ $prod->production_number }}
                                    </a>
                                </td>
                                <td class="fw-semibold">{{ $prod->destinationOrganization?->name }}</td>
                                <td>{{ $prod->warehouse?->name }}</td>
                                <td>{!! $prod->status_badge !!}</td>
                                <td class="text-center font-monospace fw-bold">{{ number_format($prod->total_planned_qty) }}</td>
                                <td class="text-center font-monospace fw-bold {{ $prod->total_issued_qty >= $prod->total_planned_qty ? 'text-success' : 'text-primary' }}">
                                    {{ number_format($prod->total_issued_qty) }}
                                </td>
                                <td>{{ $prod->creator?->name }}</td>
                                <td class="text-secondary">{{ $prod->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-center pe-3 pe-md-4 py-2">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from($prod) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Bon Produksi">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="{{ route('production.manifest', $prod->id) }}" 
                                           target="_blank" 
                                           class="btn-action-icon text-dark" 
                                           title="Cetak Manifest Produksi">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($prod) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Edit Bon Produksi">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($prod) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Batalkan / Hapus Bon Produksi">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-secondary">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                    Belum ada bon produksi personalisasi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Standardized Bank Jatim Pagination Footer -->
        <x-pagination-footer :paginator="$productionOrders" :perPage="$perPage" />
    </div>

    <!-- ==================== MODAL: BUAT BON PRODUKSI BARU ==================== -->
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
                        <i class="bi bi-cpu fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Buat Bon Produksi Personalisasi Kartu</h6>
                        <span class="fs-8 text-secondary">Rencana pengambilan bahan blankcard/token/KUE untuk personalisasi</span>
                    </div>
                </div>
                <button type="button" @click="createModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('production.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-2.5">
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Gudang Sumber Bahan Baku <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select form-select-sm fs-8" required>
                                <option value="">-- Pilih Gudang Sumber --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ $wh->type === 'CENTRAL_LOGISTICS' ? 'selected' : '' }}>
                                        {{ $wh->code }} - {{ $wh->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Cabang / Unit Kerja Tujuan <span class="text-danger">*</span></label>
                            <select name="destination_organization_id" class="form-select form-select-sm fs-8" required>
                                <option value="">-- Pilih Cabang Pemesan --</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">
                                        {{ $org->code }} - {{ $org->name }} ({{ $org->city }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tautkan Berkas Intake Emboss (Opsional)</label>
                            <select name="emboss_file_id" class="form-select form-select-sm fs-8">
                                <option value="">-- Tanpa Tautan Berkas Emboss --</option>
                                @foreach($embossFiles as $emb)
                                    <option value="{{ $emb->id }}">
                                        {{ $emb->file_id }} - {{ $emb->file_name }} ({{ $emb->success_records }} records)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Tautkan Nomor Pesanan Order (Opsional)</label>
                            <select name="order_id" class="form-select form-select-sm fs-8">
                                <option value="">-- Tanpa Tautan Order --</option>
                                @foreach($orders as $ord)
                                    <option value="{{ $ord->id }}">
                                        {{ $ord->order_number }} - {{ $ord->requestingOrganization?->name }} ({{ $ord->created_at->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan Khusus Produksi</label>
                            <textarea name="notes" class="form-control form-control-sm fs-8" rows="2" placeholder="Catatan spesifikasi kartu, prioritas cetak, atau instruksi..."></textarea>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-0"><i class="bi bi-credit-card-2-front me-1"></i>Bahan Baku yang Diambil</label>
                        <button type="button" class="btn btn-outline-danger btn-xs" @click="addItem()">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Bahan
                        </button>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-bordered align-middle mb-0 fs-8">
                            <thead class="table-light text-secondary">
                                <tr>
                                    <th style="width: 50%">Pilih Jenis Blankcard / Token</th>
                                    <th style="width: 20%" class="text-center">Kuantitas</th>
                                    <th>Keterangan</th>
                                    <th style="width: 5%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, index) in items" :key="index">
                                    <tr>
                                        <td>
                                            <select :name="'items[' + index + '][item_id]'" class="form-select form-select-sm fs-8" x-model="row.item_id" required>
                                                <option value="">-- Pilih Bahan --</option>
                                                @foreach($items as $it)
                                                    <option value="{{ $it->id }}">{{ $it->sku }} - {{ $it->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" :name="'items[' + index + '][qty_planned]'" x-model="row.qty" class="form-control form-control-sm text-center font-monospace fw-bold fs-8" min="1" required>
                                        </td>
                                        <td>
                                            <input type="text" :name="'items[' + index + '][notes]'" x-model="row.notes" class="form-control form-control-sm fs-8" placeholder="Opsional...">
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
                        <i class="bi bi-check-lg me-1"></i> Terbitkan Bon Produksi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: LIHAT DETAIL BON PRODUKSI ==================== -->
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
                        <i class="bi bi-cpu fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Detail Surat Perintah Kerja (Bon Produksi)</h6>
                        <span class="fs-8 font-monospace text-danger" x-text="viewItem?.production_number"></span>
                    </div>
                </div>
                <button type="button" @click="viewModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <div class="p-3 rounded-3 bg-body-tertiary border border-secondary-subtle">
                    <div class="row g-2 fs-8">
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Unit Pemesan:</span>
                            <strong class="text-body" x-text="viewItem?.destination_organization?.name || '-'"></strong>
                            <div class="fs-9 text-secondary font-monospace" x-text="viewItem?.destination_organization?.code ? 'Kode: ' + viewItem.destination_organization.code : ''"></div>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Gudang Bahan Baku:</span>
                            <span class="text-body fw-semibold" x-text="viewItem?.warehouse?.name || '-'"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Status Bon:</span>
                            <span class="badge" :class="viewItem?.status === 'COMPLETED' ? 'bg-success' : (viewItem?.status === 'IN_PRODUCTION' ? 'bg-primary' : 'bg-warning text-dark')" x-text="viewItem?.status"></span>
                        </div>
                        <div class="col-12 col-md-6">
                            <span class="text-secondary d-block">Target Penyelesaian:</span>
                            <span class="text-body font-monospace" x-text="viewItem?.target_completion_date ? viewItem.target_completion_date.substring(0, 10) : '-'"></span>
                        </div>
                    </div>
                </div>

                <!-- Items to produce -->
                <div>
                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1.5">Daftar Bahan Baku & Target Personalisasi</label>
                    <div class="table-responsive rounded border border-secondary-subtle">
                        <table class="table table-sm table-hover align-middle mb-0 fs-8">
                            <thead class="bg-body-tertiary text-secondary">
                                <tr>
                                    <th class="ps-3 py-1.5">Barang / Item Material</th>
                                    <th class="text-center py-1.5" style="width: 110px;">Qty Rencana</th>
                                    <th class="text-center py-1.5" style="width: 110px;">Qty Keluar</th>
                                    <th class="text-center py-1.5" style="width: 90px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(it, idx) in (viewItem?.items || [])" :key="idx">
                                    <tr>
                                        <td class="ps-3 py-1.5">
                                            <span class="fw-semibold text-body" x-text="it.item?.name || ('Item #' + it.item_id)"></span>
                                            <div class="fs-9 text-secondary font-monospace" x-text="it.item?.sku || ''"></div>
                                        </td>
                                        <td class="text-center font-monospace fw-bold" x-text="it.qty_planned"></td>
                                        <td class="text-center font-monospace fw-bold" :class="it.qty_issued >= it.qty_planned ? 'text-success' : 'text-primary'" x-text="it.qty_issued"></td>
                                        <td class="text-center">
                                            <span class="badge" :class="it.qty_issued >= it.qty_planned ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'" x-text="it.qty_issued >= it.qty_planned ? 'SELESAI' : 'PROSES'"></span>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="!viewItem?.items || viewItem.items.length === 0">
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-secondary">Tidak ada rincian bahan.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <template x-if="viewItem?.notes">
                    <div class="p-2.5 rounded bg-body-secondary border border-secondary-subtle fs-8">
                        <span class="text-secondary fw-semibold">Catatan Produksi:</span>
                        <p class="mb-0 text-body mt-1" x-text="viewItem.notes"></p>
                    </div>
                </template>
            </div>

            <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-top">
                <div class="d-flex gap-2">
                    <a :href="'/production/' + viewItem?.id + '/manifest'" target="_blank" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                        <i class="bi bi-printer"></i>
                        <span>Cetak Manifest</span>
                    </a>
                    <a :href="'/production/' + viewItem?.id" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-arrow-up-right-square"></i>
                        <span>Buka Layar Produksi</span>
                    </a>
                </div>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: EDIT BON PRODUKSI ==================== -->
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
                        <h6 class="mb-0 fw-bold text-body">Edit Informasi Bon Produksi</h6>
                        <span class="fs-8 font-monospace text-primary" x-text="editItem.production_number"></span>
                    </div>
                </div>
                <button type="button" @click="editModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/production/' + editItem.id" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body p-4 space-y-3">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Nomor Bon Produksi</label>
                        <input type="text" class="form-control form-control-sm fs-8 font-monospace" :value="editItem.production_number" disabled>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Target Tanggal Selesai</label>
                        <input type="date" name="target_completion_date" x-model="editItem.target_completion_date" class="form-control form-control-sm fs-8">
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Catatan / Instruksi Personalisasi</label>
                        <textarea name="notes" x-model="editItem.notes" rows="3" class="form-control form-control-sm fs-8" placeholder="Instruksi tambahan personalisasi..."></textarea>
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

    <!-- ==================== MODAL: HAPUS BON PRODUKSI ==================== -->
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
                        <h6 class="mb-0 fw-bold text-danger">Konfirmasi Hapus Bon Produksi</h6>
                        <span class="fs-8 text-secondary">Tindakan ini memerlukan verifikasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/production/' + deleteItem.id" method="POST">
                @csrf
                @method('DELETE')
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin membatalkan dan menghapus bon produksi berikut dari sistem?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">Nomor Bon & Cabang:</div>
                        <div class="font-monospace fw-bold text-danger fs-7" x-text="deleteItem.production_number"></div>
                        <div class="fs-8 text-body fw-semibold" x-text="deleteItem.branch"></div>
                        <div class="fs-8 text-secondary mt-1">
                            Status: <span class="badge bg-secondary" x-text="deleteItem.status"></span> | 
                            Rencana: <span class="font-monospace fw-bold" x-text="deleteItem.total_planned_qty"></span> pcs
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Bon produksi yang telah diproses atau selesai pengeluaran bahan tidak dapat dihapus.
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus Bon
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
