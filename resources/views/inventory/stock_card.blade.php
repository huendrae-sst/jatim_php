@extends('layouts.app')
@section('title', 'Kartu Stok: ' . $item->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Kartu Stok</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ adjModal: false }">
    <!-- Item Header Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-3.5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="w-12 h-12 rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="bi bi-box-seam fs-3"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h5 class="fw-bold mb-0 text-body">{{ $item->name }}</h5>
                            <span class="badge bg-danger-subtle text-danger font-monospace fs-9">{{ $item->sku }}</span>
                            @if($item->is_active)
                                <span class="badge bg-success-subtle text-success fs-9">Aktif</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary fs-9">Non-Aktif</span>
                            @endif
                        </div>
                        <div class="text-secondary fs-8">
                            <span class="text-body fw-medium">Kategori:</span> {{ $item->category->name ?? '-' }}
                            <span class="mx-1">•</span>
                            <span class="text-body fw-medium">Satuan (UOM):</span> {{ $item->uom }}
                            @if($item->barcode)
                                <span class="mx-1">•</span>
                                <span class="text-body fw-medium">Barcode:</span> <span class="font-monospace">{{ $item->barcode }}</span>
                            @endif
                            <span class="mx-1">•</span>
                            <span class="text-body fw-medium">Estimasi Harga:</span> <span class="font-monospace">Rp {{ number_format($item->estimated_unit_price, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <button type="button" @click="adjModal = true" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-sliders"></i> Penyesuaian Stok
                    </button>
                    <a href="{{ route('inventory.balances', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Warehouse Selector Bar -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body py-2.5 px-3 bg-body-tertiary">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="fs-8 fw-bold text-secondary text-uppercase text-nowrap">
                        <i class="bi bi-geo-alt text-danger me-1"></i> Lokasi Gudang:
                    </span>
                    <form method="GET" action="{{ route('inventory.stock_card', ['itemId' => $item->id]) }}" class="m-0">
                        <input type="hidden" name="search" value="{{ $search }}">
                        <input type="hidden" name="transaction_type" value="{{ $transactionType }}">
                        <input type="hidden" name="per_page" value="{{ $perPage }}">
                        <select name="warehouse_id" onchange="this.form.submit()" class="form-select form-select-sm fs-8 fw-semibold border-secondary-subtle">
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (string)$selectedWarehouseId === (string)$wh->id ? 'selected' : '' }}>
                                    [{{ $wh->code }}] {{ $wh->name }} - {{ $wh->organization->name ?? '' }} ({{ $wh->organization->city ?? '' }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if($currentWarehouse)
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $currentWarehouse->type === 'CENTRAL_LOGISTICS' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle' }} fs-8">
                            {{ $currentWarehouse->type === 'CENTRAL_LOGISTICS' ? 'Gudang Logistik Pusat' : 'Gudang Cabang / Unit' }}
                        </span>
                        <span class="text-secondary fs-8">
                            <i class="bi bi-building me-1"></i>{{ $currentWarehouse->organization->name ?? '-' }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Stock Metrics Summary -->
    <div class="row g-3">
        <!-- 1. On Hand (Fisik) -->
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex align-items-center justify-content-between text-secondary mb-1">
                        <span class="fs-9 fw-bold text-uppercase">On Hand (Fisik)</span>
                        <i class="bi bi-box-seam fs-6"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold font-monospace text-body">{{ number_format($currentBalance->on_hand ?? 0) }} <span class="fs-8 text-secondary fw-normal">{{ $item->uom }}</span></div>
                        <small class="text-secondary fs-9">Saldo fisik tercatat di lokasi</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Reserved (Order) -->
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex align-items-center justify-content-between text-warning-emphasis mb-1">
                        <span class="fs-9 fw-bold text-uppercase">Reserved (Order)</span>
                        <i class="bi bi-clock-history fs-6"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold font-monospace text-warning-emphasis">{{ number_format($currentBalance->reserved ?? 0) }} <span class="fs-8 text-secondary fw-normal">{{ $item->uom }}</span></div>
                        <small class="text-secondary fs-9">Alokasi pesanan berjalan</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Damaged / Rusak -->
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex align-items-center justify-content-between text-danger mb-1">
                        <span class="fs-9 fw-bold text-uppercase">Damaged / Rusak</span>
                        <i class="bi bi-exclamation-triangle fs-6"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold font-monospace text-danger">{{ number_format(($currentBalance->damaged ?? 0) + ($currentBalance->hold ?? 0)) }} <span class="fs-8 text-secondary fw-normal">{{ $item->uom }}</span></div>
                        <small class="text-secondary fs-9">Rusak: {{ number_format($currentBalance->damaged ?? 0) }} • Hold: {{ number_format($currentBalance->hold ?? 0) }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Available (Tersedia Bebas) -->
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm border-0 rounded-3 h-100 bg-success-subtle border border-success-subtle">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div class="d-flex align-items-center justify-content-between text-success-emphasis mb-1">
                        <span class="fs-9 fw-bold text-uppercase">Stok Bebas (Available)</span>
                        <i class="bi bi-check-circle fs-6"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold font-monospace text-success">{{ number_format($currentBalance->available ?? 0) }} <span class="fs-8 text-success-emphasis fw-normal">{{ $item->uom }}</span></div>
                        <small class="text-success-emphasis fs-9">Siap ditransfer atau digunakan</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Immutable Ledger History Table Card -->
    <div class="card shadow-sm border-0 rounded-3">
        <!-- Card Header -->
        <div class="card-header bg-body py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h3 class="card-title fs-6 fw-bold mb-0 text-body d-flex align-items-center">
                Histori Buku Besar Stok (Immutable Stock Ledger)
            </h3>
            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-8">{{ $ledgers->total() }} Mutasi Tercatat</span>
        </div>

        <!-- Filter & Search Toolbar (seperti master/items) -->
        <div class="card-body p-3 bg-body-tertiary border-bottom">
            <form action="{{ route('inventory.stock_card', ['itemId' => $item->id]) }}" method="GET">
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div class="row g-2 align-items-center">
                    <!-- Tipe Transaksi Filter -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-filter"></i></span>
                            <select name="transaction_type" onchange="this.form.submit()" class="form-select form-select-sm border-start-0 fs-8">
                                <option value="ALL">Semua Jenis Transaksi</option>
                                <option value="GOODS_RECEIPT" {{ $transactionType === 'GOODS_RECEIPT' ? 'selected' : '' }}>Penerimaan Barang (Goods Receipt)</option>
                                <option value="GOODS_ISSUE" {{ $transactionType === 'GOODS_ISSUE' ? 'selected' : '' }}>Pengeluaran Barang (Goods Issue)</option>
                                <option value="TRANSFER_IN" {{ $transactionType === 'TRANSFER_IN' ? 'selected' : '' }}>Transfer Masuk (Transfer In)</option>
                                <option value="TRANSFER_OUT" {{ $transactionType === 'TRANSFER_OUT' ? 'selected' : '' }}>Transfer Keluar (Transfer Out)</option>
                                <option value="STOCK_ADJUSTMENT" {{ $transactionType === 'STOCK_ADJUSTMENT' ? 'selected' : '' }}>Penyesuaian Stok (Adjustment)</option>
                                <option value="STOCK_OPNAME" {{ $transactionType === 'STOCK_OPNAME' ? 'selected' : '' }}>Stock Opname</option>
                                <option value="DAMAGED_HOLD" {{ $transactionType === 'DAMAGED_HOLD' ? 'selected' : '' }}>Pemisahan Rusak (Damaged/Hold)</option>
                                <option value="RESERVATION_ALLOCATION" {{ $transactionType === 'RESERVATION_ALLOCATION' ? 'selected' : '' }}>Alokasi Reservasi</option>
                                <option value="RESERVATION_RELEASE" {{ $transactionType === 'RESERVATION_RELEASE' ? 'selected' : '' }}>Pelepasan Reservasi</option>
                            </select>
                        </div>
                    </div>

                    <!-- Reset Button -->
                    @if($search || ($transactionType && $transactionType !== 'ALL'))
                        <div class="col-auto">
                            <a href="{{ route('inventory.stock_card', ['itemId' => $item->id, 'warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-danger fs-8" title="Reset Filter">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    @endif

                    <!-- Search Bar -->
                    <div class="col-12 col-md ms-md-auto">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-secondary border-end-0 fs-8"><i class="bi bi-search"></i></span>
                            <input type="text" 
                                   name="search" 
                                   value="{{ $search }}" 
                                   class="form-control form-control-sm border-start-0 border-end-0 fs-8" 
                                   placeholder="Cari no. referensi atau keterangan...">
                            <button type="submit" class="btn btn-sm btn-danger fw-bold fs-8 shadow-xs">Cari</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table View -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 fs-7">
                    <thead class="bg-body-tertiary text-secondary border-bottom">
                        <tr>
                            <th class="ps-4 py-3" style="width: 140px;">Waktu</th>
                            <th class="py-3" style="width: 170px;">Jenis Transaksi</th>
                            <th class="py-3" style="width: 180px;">No. Referensi Dokumen</th>
                            <th class="py-3 text-center" style="width: 110px;">Masuk (+In)</th>
                            <th class="py-3 text-center" style="width: 110px;">Keluar (-Out)</th>
                            <th class="py-3 text-center" style="width: 110px;">Saldo Akhir</th>
                            <th class="py-3">Keterangan</th>
                            <th class="py-3 pe-4" style="width: 140px;">Petugas / User</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledgers as $lg)
                            <tr>
                                <td class="ps-4 py-3 text-secondary fs-8 font-monospace">
                                    {{ $lg->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-3">
                                    @php
                                        $badgeClass = match($lg->transaction_type) {
                                            'GOODS_RECEIPT', 'TRANSFER_IN' => 'bg-success-subtle text-success',
                                            'GOODS_ISSUE', 'TRANSFER_OUT' => 'bg-danger-subtle text-danger',
                                            'STOCK_ADJUSTMENT' => 'bg-primary-subtle text-primary',
                                            'STOCK_OPNAME' => 'bg-info-subtle text-info-emphasis',
                                            'DAMAGED_HOLD' => 'bg-warning-subtle text-warning-emphasis',
                                            default => 'bg-secondary-subtle text-secondary-emphasis',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} font-monospace fs-9">
                                        {{ $lg->transaction_type }}
                                    </span>
                                </td>
                                <td class="py-3 font-monospace fw-semibold text-body fs-8">
                                    {{ $lg->reference_number ?? '-' }}
                                </td>
                                <td class="py-3 text-center font-monospace fw-bold {{ $lg->qty_in > 0 ? 'text-success' : 'text-secondary' }}">
                                    {{ $lg->qty_in > 0 ? '+' . number_format($lg->qty_in) : '-' }}
                                </td>
                                <td class="py-3 text-center font-monospace fw-bold {{ $lg->qty_out > 0 ? 'text-danger' : 'text-secondary' }}">
                                    {{ $lg->qty_out > 0 ? '-' . number_format($lg->qty_out) : '-' }}
                                </td>
                                <td class="py-3 text-center font-monospace fw-bold text-body">
                                    {{ number_format($lg->balance_after) }}
                                </td>
                                <td class="py-3 text-secondary fs-8">
                                    {{ $lg->notes ?: '-' }}
                                </td>
                                <td class="py-3 pe-4 text-body fs-8">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <i class="bi bi-person text-secondary"></i>
                                        <span>{{ $lg->creator->name ?? 'System' }}</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <i class="bi bi-inbox text-secondary fs-1 mb-2"></i>
                                        <span class="text-secondary fw-medium">Belum ada catatan mutasi buku besar untuk item ini di lokasi ini.</span>
                                        @if($search || ($transactionType && $transactionType !== 'ALL'))
                                            <a href="{{ route('inventory.stock_card', ['itemId' => $item->id, 'warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-outline-danger mt-3">
                                                <i class="bi bi-x-circle me-1"></i> Reset Filter
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Footer -->
        <x-pagination-footer :paginator="$ledgers" :perPage="$perPage" />
    </div>

    <!-- Stock Adjustment Modal -->
    <div x-show="adjModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         x-cloak 
         style="display: none; z-index: 1050;">
        <div @click.away="adjModal = false" 
             class="card shadow-2xl border border-secondary-subtle max-w-lg w-full overflow-hidden" 
             style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="card-header bg-danger text-white py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-sliders fs-5"></i>
                    <h5 class="modal-title fs-6 fw-bold mb-0">Penyesuaian Stok (Stock Adjustment)</h5>
                </div>
                <button type="button" @click="adjModal = false" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>

            <form action="{{ route('inventory.adjustments.store') }}" method="POST" class="m-0">
                @csrf
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="item_id" value="{{ $item->id }}">

                <div class="modal-body p-4 fs-8">
                    <!-- Info Item & Lokasi -->
                    <div class="p-3 bg-body-tertiary rounded-3 border mb-3">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-1 text-body">{{ $item->name }}</h6>
                                <div class="text-secondary font-monospace fs-9">
                                    {{ $item->sku }} • {{ $item->category->name ?? '-' }} • Satuan: {{ $item->uom }}
                                </div>
                            </div>
                            <span class="badge bg-secondary-subtle text-secondary font-monospace">
                                On Hand: {{ number_format($currentBalance->on_hand ?? 0) }} {{ $item->uom }}
                            </span>
                        </div>
                        <div class="mt-2 pt-2 border-top text-secondary fs-9">
                            <i class="bi bi-geo-alt text-danger me-1"></i>Lokasi: <span class="fw-semibold text-body">{{ $currentWarehouse->name ?? 'Gudang' }}</span> ({{ $currentWarehouse->code ?? '' }})
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-9 mb-1">Tipe Mutasi Penyesuaian <span class="text-danger">*</span></label>
                            <select name="transaction_type" required class="form-select form-select-sm">
                                <option value="STOCK_ADJUSTMENT">Koreksi Fisik Saldo (Stock Adjustment)</option>
                                <option value="STOCK_OPNAME">Hasil Stock Opname Fisik</option>
                                <option value="DAMAGED_HOLD">Pemisahan Barang Rusak (Damaged / Hold)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-9 mb-1">Selisih Kuantitas (+Tambah / -Kurang) <span class="text-danger">*</span></label>
                            <input type="number" name="qty_diff" required class="form-control form-control-sm font-monospace fw-bold" placeholder="Contoh: 5 untuk tambah, -3 untuk kurang">
                            <div class="form-text fs-9 text-secondary mt-1">Gunakan angka positif (+) jika barang fisik lebih banyak, atau negatif (-) jika barang fisik berkurang.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold fs-9 mb-1">Alasan / Catatan Penyesuaian <span class="text-danger">*</span></label>
                            <textarea name="notes" rows="3" required class="form-control form-control-sm" placeholder="Jelaskan alasan penyesuaian stok atau temuan fisik..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-body-tertiary py-2.5 px-4 d-flex justify-content-end gap-2 border-top">
                    <button type="button" @click="adjModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold shadow-xs">
                        Simpan Penyesuaian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
