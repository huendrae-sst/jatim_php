@extends('layouts.app')
@section('title', 'Rekonsiliasi Stok Persediaan (Stock Reconciliation)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Inventori</a></li>
    <li class="breadcrumb-item active" aria-current="page">Rekonsiliasi Stok</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{
    adjustmentModal: false,
    viewModal: false,
    viewItem: null,
    deleteModal: false,
    deleteItem: { item_id: '', item_name: '', sku: '', diff: 0, warehouse_name: '' },
    selectedWarehouseId: '{{ $selectedWarehouse ? $selectedWarehouse->id : '' }}',
    selectedItemId: '',
    qtyDiff: 0,
    openAdjustmentModal(whId = '', itId = '', diff = 0) {
        this.selectedWarehouseId = whId || '{{ $selectedWarehouse ? $selectedWarehouse->id : '' }}';
        this.selectedItemId = itId || '';
        this.qtyDiff = Math.abs(diff) || 0;
        this.adjustmentModal = true;
    },
    openViewModal(row) {
        this.viewItem = row;
        this.viewModal = true;
    },
    openEditModal(row) {
        let diff = row.diff || 0;
        this.selectedWarehouseId = '{{ $selectedWarehouse ? $selectedWarehouse->id : '' }}';
        this.selectedItemId = row.item ? row.item.id : '';
        this.qtyDiff = Math.abs(diff) || 1;
        this.adjustmentModal = true;
    },
    openDeleteModal(row) {
        this.deleteItem = {
            item_id: row.item ? row.item.id : '',
            item_name: row.item ? row.item.name : '',
            sku: row.item ? row.item.sku : '',
            diff: row.diff || 0,
            warehouse_name: '{{ $selectedWarehouse ? $selectedWarehouse->name : "Gudang Logistik" }}'
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

    <!-- KPI Metric Widgets (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- Box 1: Total SKU -->
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-boxes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total SKU Diaudit</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($totalAuditCount) }}</span>
                    <span class="fs-9 text-secondary">Semua Item di {{ $selectedWarehouse ? $selectedWarehouse->name : 'Gudang' }}</span>
                </div>
            </div>
        </div>

        <!-- Box 2: 100% Cocok -->
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">100% Cocok (Balanced)</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($balancedCount) }}</span>
                    <span class="fs-9 text-secondary">Fisik Sesuai Transaksi</span>
                </div>
            </div>
        </div>

        <!-- Box 3: Terdapat Selisih -->
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Terdapat Selisih</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-warning">{{ number_format($discrepancyCount) }}</span>
                    <span class="fs-9 text-secondary">Perlu Investigasi / Koreksi</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card Container -->
    <div class="card card-outline card-danger shadow-xs">
        <div class="card-header border-bottom p-3 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <div>
                <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-danger"></i>
                    Audit Rekonsiliasi Konsistensi Stok
                </h3>
            </div>
            <div class="card-tools d-flex align-items-center gap-2 ms-md-auto">
                <button type="button" @click="openAdjustmentModal('{{ $selectedWarehouse?->id }}')" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle"></i>
                    <span>Koreksi Stok Fisik</span>
                </button>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('inventory.reconciliation') }}" method="GET">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-building"></i></span>
                            <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}" {{ $selectedWarehouse && $selectedWarehouse->id === $w->id ? 'selected' : '' }}>
                                        {{ $w->name }} ({{ $w->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-12 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-funnel"></i></span>
                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="" {{ empty($statusFilter) ? 'selected' : '' }}>Semua Status Konsistensi</option>
                                <option value="BALANCED" {{ $statusFilter === 'BALANCED' ? 'selected' : '' }}>Balanced / 100% Cocok</option>
                                <option value="DISCREPANCY" {{ $statusFilter === 'DISCREPANCY' ? 'selected' : '' }}>Discrepancy / Terdapat Selisih</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-12 col-md-4 ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama barang atau SKU..." class="form-control form-control-sm border-start-0 fs-8">
                            @if($search || $statusFilter)
                                <a href="{{ route('inventory.reconciliation', ['warehouse_id' => $warehouseId]) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Reconciliation Table -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9">
                        <tr>
                            <th class="ps-3 py-2">SKU & Barang</th>
                            <th class="text-center py-2">Saldo Fisik Sistem</th>
                            <th class="text-center py-2">Total Masuk</th>
                            <th class="text-center py-2">Total Keluar</th>
                            <th class="text-center py-2">Kalkulasi Mutasi</th>
                            <th class="text-center py-2">Selisih (Diff)</th>
                            <th class="text-center py-2">Status Audit</th>
                            <th class="py-2">Rincian Transaksi Pembentuk</th>
                            <th class="text-center pe-3 pe-md-4 py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reconciliationData as $row)
                            <tr>
                                <td class="ps-3 py-2">
                                    <span class="font-monospace fw-bold text-dark d-block">{{ $row['item']->sku }}</span>
                                    <span class="fw-semibold">{{ $row['item']->name }}</span>
                                    <span class="text-muted fs-9 d-block">{{ $row['item']->category?->name }} ({{ $row['item']->uom }})</span>
                                </td>
                                <td class="text-center font-monospace fs-7 fw-bold py-2">
                                    {{ number_format($row['system_on_hand']) }}
                                </td>
                                <td class="text-center font-monospace text-success fw-semibold py-2">
                                    +{{ number_format($row['total_in']) }}
                                </td>
                                <td class="text-center font-monospace text-danger fw-semibold py-2">
                                    -{{ number_format($row['total_out']) }}
                                </td>
                                <td class="text-center font-monospace fs-7 fw-bold py-2 {{ $row['is_balanced'] ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($row['calculated_closing']) }}
                                </td>
                                <td class="text-center font-monospace fw-bold py-2">
                                    @if($row['diff'] === 0)
                                        <span class="badge bg-success bg-opacity-10 text-success border">0</span>
                                    @else
                                        <span class="badge bg-danger text-white">{{ $row['diff'] > 0 ? '+'.$row['diff'] : $row['diff'] }}</span>
                                    @endif
                                </td>
                                <td class="text-center py-2">
                                    @if($row['is_balanced'])
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>BALANCED</span>
                                    @else
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>DISCREPANCY</span>
                                    @endif
                                </td>
                                <td class="py-2 fs-9 text-secondary">
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-light text-dark border">Awal: {{ $row['breakdown']['initial'] }}</span>
                                        <span class="badge bg-light text-dark border">PO: +{{ $row['breakdown']['procurement'] }}</span>
                                        <span class="badge bg-light text-dark border">Transfer In: +{{ $row['breakdown']['transfer_in'] }}</span>
                                        <span class="badge bg-light text-dark border">Retur In: +{{ $row['breakdown']['return_in'] }}</span>
                                        <span class="badge bg-light text-dark border">Issue: -{{ $row['breakdown']['issue'] }}</span>
                                        <span class="badge bg-light text-dark border">Transfer Out: -{{ $row['breakdown']['transfer_out'] }}</span>
                                        <span class="badge bg-light text-dark border">Retur Out: -{{ $row['breakdown']['return_out'] }}</span>
                                        <span class="badge bg-light text-dark border">Produksi: -{{ $row['breakdown']['production'] }}</span>
                                        <span class="badge bg-light text-dark border">Dimusnahkan: -{{ $row['breakdown']['destroyed'] }}</span>
                                        <span class="badge bg-light text-dark border">Adj: {{ $row['breakdown']['adjustments'] >= 0 ? '+'.$row['breakdown']['adjustments'] : $row['breakdown']['adjustments'] }}</span>
                                    </div>
                                </td>
                                <td class="text-center pe-3 pe-md-4 py-2">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" 
                                                @click="openViewModal({{ Js::from($row) }})" 
                                                class="btn-action-icon text-secondary" 
                                                title="Lihat Detail Rekonsiliasi & Audit">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="{{ route('inventory.movement_inquiry', ['item_id' => $row['item']->id, 'warehouse_id' => $selectedWarehouse?->id]) }}" 
                                           class="btn-action-icon text-dark" 
                                           title="Inquiry Riwayat Mutasi Item">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <button type="button" 
                                                @click="openEditModal({{ Js::from($row) }})" 
                                                class="btn-action-icon text-primary" 
                                                title="Koreksi Penyesuaian Stok (Maker-Checker)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" 
                                                @click="openDeleteModal({{ Js::from($row) }})" 
                                                class="btn-action-icon text-danger" 
                                                title="Reset / Dismiss Catatan Selisih">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-secondary">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                                    Tidak ada data persediaan untuk rekonsiliasi pada gudang ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Standardized Bank Jatim Pagination Footer -->
        <x-pagination-footer :paginator="$reconciliationData" :perPage="$perPage ?? 10" />
    </div>

    <!-- ==================== MODAL: AJUKAN KOREKSI PENYESUAIAN STOK ==================== -->
    <div x-show="adjustmentModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         x-cloak 
         style="display: none;">
        <div @click.away="adjustmentModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-2xl w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle text-danger p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="bi bi-wrench-adjustable fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Ajukan Koreksi Penyesuaian Stok Fisik</h6>
                        <span class="fs-8 text-secondary">Penyesuaian saldo fisik hasil audit opname persediaan</span>
                    </div>
                </div>
                <button type="button" class="btn-close" @click="adjustmentModal = false" aria-label="Close"></button>
            </div>

            <form action="{{ route('inventory.adjustments.store') }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold fs-8 mb-1">Gudang / Lokasi Penyimpanan <span class="text-danger">*</span></label>
                            <select name="warehouse_id" x-model="selectedWarehouseId" class="form-select form-select-sm fs-8" required>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold fs-8 mb-1">Item Barang <span class="text-danger">*</span></label>
                            <select name="item_id" x-model="selectedItemId" class="form-select form-select-sm fs-8" required>
                                <option value="">-- Pilih Barang / SKU --</option>
                                @foreach($allItems ?? [] as $itm)
                                    <option value="{{ $itm->id }}">{{ $itm->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold fs-8 mb-1">Tipe Penyesuaian <span class="text-danger">*</span></label>
                            <select name="transaction_type" class="form-select form-select-sm fs-8" required>
                                <option value="ADJUSTMENT_IN">Penambahan Stok Fisik (ADJUSTMENT_IN)</option>
                                <option value="ADJUSTMENT_OUT">Pengurangan Stok Fisik (ADJUSTMENT_OUT)</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold fs-8 mb-1">Jumlah Perubahan (Qty) <span class="text-danger">*</span></label>
                            <input type="number" name="qty_diff" x-model="qtyDiff" class="form-control form-control-sm fs-8 font-monospace" min="1" required placeholder="Contoh: 10">
                            <span class="fs-9 text-secondary">Masukkan angka absolut positif dari selisih fisik yang disesuaikan.</span>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold fs-8 mb-1">Alasan / Catatan Penyesuaian <span class="text-danger">*</span></label>
                            <textarea name="notes" rows="3" class="form-control form-control-sm fs-8" required placeholder="Contoh: Hasil audit opname fisik tanggal ... ditemukan selisih barang rusak/tertukar"></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2.5 px-4 border-top">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" @click="adjustmentModal = false">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-send-check me-1"></i> Ajukan Penyesuaian
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: LIHAT DETAIL AUDIT REKONSILIASI ==================== -->
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
                        <i class="bi bi-search fs-6"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-body">Detail Audit Rekonsiliasi SKU</h6>
                        <span class="fs-8 text-secondary font-monospace" x-text="viewItem?.item?.sku + ' - ' + viewItem?.item?.name"></span>
                    </div>
                </div>
                <button type="button" @click="viewModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <div class="card-body p-4 space-y-3" style="max-height: 75vh; overflow-y: auto;">
                <!-- Summary Metrics -->
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="p-2.5 border rounded bg-body">
                            <span class="fs-9 text-secondary d-block">Hasil Hitung Mutasi</span>
                            <span class="fs-5 fw-bold font-monospace text-body" x-text="viewItem?.calculated_closing"></span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2.5 border rounded bg-body">
                            <span class="fs-9 text-secondary d-block">Saldo Fisik Sistem</span>
                            <span class="fs-5 fw-bold font-monospace text-body" x-text="viewItem?.system_on_hand"></span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2.5 border rounded" :class="viewItem?.is_balanced ? 'bg-success-subtle border-success-subtle' : 'bg-danger-subtle border-danger-subtle'">
                            <span class="fs-9 d-block" :class="viewItem?.is_balanced ? 'text-success' : 'text-danger'">Selisih (Diff)</span>
                            <span class="fs-5 fw-bold font-monospace" :class="viewItem?.is_balanced ? 'text-success' : 'text-danger'" x-text="viewItem?.diff > 0 ? '+' + viewItem?.diff : viewItem?.diff"></span>
                        </div>
                    </div>
                </div>

                <!-- Breakdown of 10 Transaction Types -->
                <div>
                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1.5">Rincian Komponen Mutasi Buku Pembantu</label>
                    <div class="p-3 rounded border border-secondary-subtle bg-body-tertiary">
                        <div class="row g-2 fs-8">
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Saldo Awal:</span>
                                <strong class="font-monospace" x-text="viewItem?.breakdown?.opening_balance"></strong>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Pengadaan (PO):</span>
                                <span class="text-success font-monospace fw-bold" x-text="'+' + (viewItem?.breakdown?.procurement || 0)"></span>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Transfer Masuk:</span>
                                <span class="text-success font-monospace fw-bold" x-text="'+' + (viewItem?.breakdown?.transfer_in || 0)"></span>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Retur Masuk:</span>
                                <span class="text-success font-monospace fw-bold" x-text="'+' + (viewItem?.breakdown?.return_in || 0)"></span>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Order Cabang (Issue):</span>
                                <span class="text-danger font-monospace fw-bold" x-text="'-' + (viewItem?.breakdown?.issue || 0)"></span>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Transfer Keluar:</span>
                                <span class="text-danger font-monospace fw-bold" x-text="'-' + (viewItem?.breakdown?.transfer_out || 0)"></span>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Retur Keluar:</span>
                                <span class="text-danger font-monospace fw-bold" x-text="'-' + (viewItem?.breakdown?.return_out || 0)"></span>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Bahan Bon Produksi:</span>
                                <span class="text-danger font-monospace fw-bold" x-text="'-' + (viewItem?.breakdown?.production || 0)"></span>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Pemusnahan Resmi:</span>
                                <span class="text-danger font-monospace fw-bold" x-text="'-' + (viewItem?.breakdown?.destroyed || 0)"></span>
                            </div>
                            <div class="col-6 col-md-4">
                                <span class="text-secondary d-block">Penyesuaian (Adj):</span>
                                <span class="font-monospace fw-bold" :class="(viewItem?.breakdown?.adjustments || 0) >= 0 ? 'text-success' : 'text-danger'" x-text="viewItem?.breakdown?.adjustments >= 0 ? '+' + viewItem?.breakdown?.adjustments : viewItem?.breakdown?.adjustments"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-body-tertiary d-flex align-items-center justify-content-between py-2.5 px-4 border-top">
                <div class="d-flex gap-2">
                    <a :href="'/inventory/movement-inquiry?item_id=' + (viewItem ? viewItem.item.id : '') + '&warehouse_id=' + selectedWarehouseId" 
                       class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                        <i class="bi bi-clock-history"></i>
                        <span>Inquiry Mutasi</span>
                    </a>
                    <button type="button" 
                            @click="viewModal = false; openEditModal(viewItem)" 
                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-pencil"></i>
                        <span>Koreksi Stok</span>
                    </button>
                </div>
                <button type="button" @click="viewModal = false" class="btn btn-sm btn-outline-secondary px-3">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: HAPUS / DISMISS CATATAN SELISIH ==================== -->
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
                        <h6 class="mb-0 fw-bold text-danger">Dismiss Catatan Selisih Audit</h6>
                        <span class="fs-8 text-secondary">Verifikasi Audit Rekonsiliasi</span>
                    </div>
                </div>
                <button type="button" @click="deleteModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form @submit.prevent="deleteModal = false">
                <div class="card-body p-3.5 space-y-3">
                    <p class="text-body mb-0 fs-8">
                        Apakah Anda yakin ingin mengabaikan / me-reset notifikasi selisih rekonsiliasi pada barang berikut?
                    </p>

                    <div class="p-2.5 rounded-3 bg-body-secondary border border-secondary-subtle">
                        <div class="fs-8 text-secondary mb-0.5">Barang & Lokasi Gudang:</div>
                        <div class="fs-8 text-body fw-semibold" x-text="deleteItem.item_name"></div>
                        <div class="font-monospace text-secondary fs-9" x-text="'SKU: ' + deleteItem.sku"></div>
                        <div class="fs-8 text-secondary mt-1">
                            Gudang: <span class="fw-semibold text-body" x-text="deleteItem.warehouse_name"></span> | 
                            Selisih: <span class="font-monospace fw-bold text-danger" x-text="deleteItem.diff"></span> pcs
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 px-3 fs-8 mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle text-warning fs-6 flex-shrink-0 mt-0.5"></i>
                        <div>
                            Tindakan ini menutup notifikasi selisih pada tampilan audit saat ini. Penyesuaian fisik permanen harus dilakukan melalui alur Koreksi Stok (Maker-Checker).
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary d-flex justify-content-end align-items-center gap-2 py-2.5 px-4 border-top">
                    <button type="button" @click="deleteModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-xs">
                        <i class="bi bi-check-circle me-1"></i> Ya, Dismiss Selisih
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
