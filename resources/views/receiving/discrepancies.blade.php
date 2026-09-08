@extends('layouts.app')
@section('title', 'Laporan Discrepancy & Klaim')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Penerimaan & QC</li>
    <li class="breadcrumb-item"><a href="{{ route('receiving.index') }}" class="text-decoration-none text-danger">Penerimaan Cabang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Discrepancy & Klaim</li>
@endsection

@section('content')
<div class="space-y-4" x-data="discrepancyManager()">

    <!-- Summary Metrics (AdminLTE 4 Info-Boxes matching po_index / early_warning) -->
    <div class="row g-3">
        <!-- 1. Total Laporan Selisih -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-secondary"><i class="bi bi-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Laporan Selisih</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">{{ number_format($totalCount) }} Kasus</span>
                    <span class="fs-9 text-secondary">Seluruh berita acara tercatat</span>
                </div>
            </div>
        </div>

        <!-- 2. Menunggu Peninjauan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Menunggu Review</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $pendingCount > 0 ? 'text-warning-emphasis' : 'text-body-emphasis' }}">
                        {{ number_format($pendingCount) }} Kasus
                    </span>
                    <span class="fs-9 text-secondary">Butuh investigasi logistik</span>
                </div>
            </div>
        </div>

        <!-- 3. Klaim Selesai / Teratasi -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Klaim Selesai</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($resolvedCount) }} Kasus</span>
                    <span class="fs-9 text-secondary">Telah diselesaikan / diganti</span>
                </div>
            </div>
        </div>

        <!-- 4. Total Fisik Rusak / Selisih -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-x-octagon"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Fisik Rusak / Kurang</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $totalDamagedQty > 0 ? 'text-danger' : 'text-body-emphasis' }}">
                        {{ number_format($totalDamagedQty) }} Unit
                    </span>
                    <span class="fs-9 text-secondary">Akumulasi unit terdampak</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-medical text-danger fs-5"></i>
                <h3 class="card-title fw-bold text-slate-800 fs-6 mb-0">
                    Daftar Berita Acara Selisih & Klaim (Discrepancy)
                    @if($discrepancies->total() > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $discrepancies->total() }}</span>
                    @endif
                </h3>
            </div>
        </div>

        <!-- Filter Bar matching po_index -->
        <div class="p-3 bg-body-tertiary border-bottom">
            <form method="GET" action="{{ route('receiving.discrepancies') }}" class="row g-2 align-items-center">
                <input type="hidden" name="per_page" value="{{ $perPage }}">

                <!-- Jenis Selisih Filter -->
                <div class="col-12 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-muted"><i class="bi bi-tag"></i></span>
                        <select name="discrepancy_type" class="form-select" onchange="this.form.submit()">
                            <option value="ALL">Semua Jenis Selisih</option>
                            <option value="DAMAGED" {{ $discrepancyType === 'DAMAGED' ? 'selected' : '' }}>Barang Rusak (Damaged)</option>
                            <option value="MISSING" {{ $discrepancyType === 'MISSING' ? 'selected' : '' }}>Barang Hilang (Missing)</option>
                            <option value="SHORTAGE" {{ $discrepancyType === 'SHORTAGE' ? 'selected' : '' }}>Kurang Kirim (Shortage)</option>
                            <option value="WRONG_ITEM" {{ $discrepancyType === 'WRONG_ITEM' ? 'selected' : '' }}>Salah Barang (Wrong Item)</option>
                            <option value="EXCESS" {{ $discrepancyType === 'EXCESS' ? 'selected' : '' }}>Lebih Kirim (Excess)</option>
                        </select>
                    </div>
                </div>

                <!-- Status Penyelesaian Filter -->
                <div class="col-12 col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body text-muted"><i class="bi bi-toggle-on"></i></span>
                        <select name="resolution_status" class="form-select" onchange="this.form.submit()">
                            <option value="ALL">Semua Status Penyelesaian</option>
                            <option value="REPORTED" {{ $resolutionStatus === 'REPORTED' ? 'selected' : '' }}>Dilaporkan (Reported)</option>
                            <option value="UNDER_REVIEW" {{ $resolutionStatus === 'UNDER_REVIEW' ? 'selected' : '' }}>Dalam Peninjauan (Under Review)</option>
                            <option value="RESOLVED" {{ $resolutionStatus === 'RESOLVED' ? 'selected' : '' }}>Selesai / Diganti (Resolved)</option>
                            <option value="CLAIMED" {{ $resolutionStatus === 'CLAIMED' ? 'selected' : '' }}>Klaim Diajukan (Claimed)</option>
                        </select>
                    </div>
                </div>

                <!-- Cabang Filter (Admin Only) -->
                @if(!$isBranch)
                    <div class="col-12 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body text-muted"><i class="bi bi-building"></i></span>
                            <select name="organization_id" class="form-select" onchange="this.form.submit()">
                                <option value="ALL">Semua Cabang Pemohon</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" {{ ((string)$organizationId === (string)$org->id) ? 'selected' : '' }}>
                                        [{{ $org->code }}] {{ $org->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                <!-- Search Box -->
                <div class="col-12 col-md ms-md-auto">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Cari No. Penerimaan, No. Order, SKU, item..." value="{{ $search ?? '' }}">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-search"></i> Cari
                        </button>
                        @if(!empty($search) || (!empty($discrepancyType) && $discrepancyType !== 'ALL') || (!empty($resolutionStatus) && $resolutionStatus !== 'ALL') || (!empty($organizationId) && $organizationId !== 'ALL'))
                            <a href="{{ route('receiving.discrepancies') }}" class="btn btn-outline-secondary" title="Reset Filter">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <!-- Table View -->
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
                <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                    <tr>
                        <th class="ps-3 py-2" style="width: 170px;">No. Penerimaan</th>
                        <th class="py-2" style="width: 200px;">Cabang Pemohon</th>
                        <th class="py-2">Item Barang</th>
                        <th class="py-2 text-center" style="width: 140px;">Jenis Selisih</th>
                        <th class="py-2 text-center" style="width: 90px;">Dikirim</th>
                        <th class="py-2 text-center" style="width: 110px;">Diterima Baik</th>
                        <th class="py-2 text-center" style="width: 120px;">Rusak / Kurang</th>
                        <th class="py-2 text-center" style="width: 130px;">Status Klaim</th>
                        <th class="pe-3 py-2 text-center" style="width: 80px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($discrepancies as $disc)
                        @php
                            $discJson = [
                                'id' => $disc->id,
                                'receiving_number' => $disc->receiving->receiving_number ?? '-',
                                'receipt_date' => $disc->receiving && $disc->receiving->receipt_date ? $disc->receiving->receipt_date->format('d M Y') : '-',
                                'order_number' => $disc->receiving && $disc->receiving->order ? $disc->receiving->order->order_number : '-',
                                'branch_name' => $disc->receiving && $disc->receiving->order && $disc->receiving->order->requestingOrganization ? $disc->receiving->order->requestingOrganization->name : '-',
                                'branch_city' => $disc->receiving && $disc->receiving->order && $disc->receiving->order->requestingOrganization ? $disc->receiving->order->requestingOrganization->city : '-',
                                'origin_warehouse' => $disc->receiving && $disc->receiving->shipment && $disc->receiving->shipment->originWarehouse ? $disc->receiving->shipment->originWarehouse->name : 'Gudang Pusat',
                                'receiver_name' => $disc->receiving && $disc->receiving->receiver ? $disc->receiving->receiver->name : '-',
                                'item_name' => $disc->item->name ?? 'Item',
                                'item_sku' => $disc->item->sku ?? '-',
                                'item_category' => $disc->item->category->name ?? '-',
                                'item_uom' => $disc->item->uom ?? 'PCS',
                                'discrepancy_type' => $disc->discrepancy_type,
                                'qty_expected' => $disc->qty_expected,
                                'qty_actual' => $disc->qty_actual,
                                'qty_damaged' => $disc->qty_damaged,
                                'resolution_status' => $disc->resolution_status,
                                'resolution_notes' => $disc->resolution_notes ?? $disc->receiving->notes ?? '-',
                            ];
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <span class="font-monospace fw-bold text-danger d-block">{{ $disc->receiving->receiving_number ?? '-' }}</span>
                                <small class="text-muted fs-8">{{ $disc->created_at ? $disc->created_at->format('d M Y') : '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-slate-800 d-block">{{ $disc->receiving->order->requestingOrganization->name ?? '-' }}</span>
                                <small class="text-muted fs-8">Order: {{ $disc->receiving->order->order_number ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-bold text-slate-800 d-block">{{ $disc->item->name ?? '-' }}</span>
                                <small class="text-muted font-monospace fs-8">{{ $disc->item->sku ?? '-' }} &bull; {{ $disc->item->category->name ?? '-' }}</small>
                            </td>
                            <td class="text-center">
                                @php
                                    $typeBadge = match($disc->discrepancy_type) {
                                        'DAMAGED' => 'text-bg-danger',
                                        'MISSING', 'SHORTAGE' => 'text-bg-warning text-dark',
                                        'WRONG_ITEM' => 'text-bg-info',
                                        default => 'text-bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $typeBadge }} fs-8">
                                    <i class="bi bi-exclamation-circle me-1"></i> {{ str_replace('_', ' ', $disc->discrepancy_type) }}
                                </span>
                            </td>
                            <td class="text-center font-monospace fw-bold text-slate-800">
                                {{ $disc->qty_expected }}
                            </td>
                            <td class="text-center font-monospace fw-bold text-success">
                                {{ $disc->qty_actual }}
                            </td>
                            <td class="text-center font-monospace fw-bold text-danger">
                                {{ $disc->qty_damaged }}
                            </td>
                            <td class="text-center">
                                @php
                                    $resClass = match($disc->resolution_status) {
                                        'RESOLVED' => 'text-bg-success',
                                        'CLAIMED' => 'text-bg-primary',
                                        'UNDER_REVIEW', 'IN_REVIEW' => 'text-bg-info',
                                        default => 'text-bg-warning text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $resClass }} fs-8">
                                    {{ str_replace('_', ' ', $disc->resolution_status) }}
                                </span>
                            </td>
                            <td class="pe-3 text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" 
                                            class="btn-action-icon text-secondary" 
                                            @click="openDiscrepancyModal({{ json_encode($discJson) }})"
                                            title="Lihat Detail Berita Acara">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="{{ route('receiving.discrepancies.print', $disc->id) }}" 
                                       target="_blank" 
                                       class="btn-action-icon text-dark" 
                                       title="Cetak Berita Acara Discrepancy">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-check fs-1 text-success opacity-50 d-block mb-2"></i>
                                <span class="fw-semibold">Tidak ada laporan discrepancy / klaim selisih ditemukan.</span>
                                <p class="fs-8 text-muted mb-0">Seluruh penerimaan barang di cabang terverifikasi lengkap dan sesuai.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Standardized Pagination Footer -->
        <x-pagination-footer :paginator="$discrepancies" :perPage="$perPage" />
    </div>

    <!-- DETAIL DISCREPANCY MODAL -->
    <div x-show="detailModalOpen" 
         x-cloak 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4"
         style="display: none;"
         @keydown.escape.window="detailModalOpen = false">
        
        <div class="bg-white rounded-3 shadow-xl max-w-2xl w-full flex flex-col max-h-[90vh] overflow-hidden" 
             @click.outside="detailModalOpen = false">
            
            <div class="bg-danger text-white px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div>
                        <h5 class="modal-title mb-0 fs-6 fw-bold">Berita Acara Selisih & Klaim</h5>
                        <small class="text-white-50 fs-8 font-monospace" x-text="detailData.receiving_number"></small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="detailModalOpen = false"></button>
            </div>

            <div class="p-4 overflow-y-auto flex-grow-1 space-y-3">
                <div class="row g-3 p-3 bg-light rounded-2 border">
                    <div class="col-12 col-sm-6">
                        <span class="fs-8 text-muted d-block">Cabang Pelapor</span>
                        <span class="fw-bold text-slate-800" x-text="`${detailData.branch_name} (${detailData.branch_city})`"></span>
                    </div>
                    <div class="col-12 col-sm-6">
                        <span class="fs-8 text-muted d-block">Gudang Pengirim</span>
                        <span class="fw-bold text-slate-800" x-text="detailData.origin_warehouse"></span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="fs-8 text-muted d-block">No. Order</span>
                        <span class="fw-semibold text-slate-700 font-monospace" x-text="detailData.order_number"></span>
                    </div>
                    <div class="col-6 col-sm-4">
                        <span class="fs-8 text-muted d-block">Tanggal Terima</span>
                        <span class="fw-semibold text-slate-700" x-text="detailData.receipt_date"></span>
                    </div>
                    <div class="col-12 col-sm-4">
                        <span class="fs-8 text-muted d-block">Petugas Penerima</span>
                        <span class="fw-semibold text-slate-700" x-text="detailData.receiver_name"></span>
                    </div>
                </div>

                <!-- Detail Item & Selisih Breakdown -->
                <div class="p-3 border rounded-2 border-danger-subtle bg-danger-subtle/30">
                    <h6 class="fw-bold text-danger fs-7 mb-2">Item Barang Bermasalah</h6>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="fw-bold text-slate-900 fs-6 d-block" x-text="detailData.item_name"></span>
                            <small class="text-muted font-monospace fs-8" x-text="`SKU: ${detailData.item_sku} | Kategori: ${detailData.item_category}`"></small>
                        </div>
                        <span class="badge text-bg-danger fs-8" x-text="detailData.discrepancy_type"></span>
                    </div>

                    <div class="row g-2 text-center font-monospace">
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <span class="fs-9 text-muted text-uppercase d-block">Dikirim</span>
                                <span class="fw-bold fs-6 text-slate-800" x-text="`${detailData.qty_expected} ${detailData.item_uom}`"></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <span class="fs-9 text-muted text-uppercase d-block">Diterima Baik</span>
                                <span class="fw-bold fs-6 text-success" x-text="`${detailData.qty_actual} ${detailData.item_uom}`"></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-white rounded border">
                                <span class="fs-9 text-muted text-uppercase d-block">Rusak / Kurang</span>
                                <span class="fw-bold fs-6 text-danger" x-text="`${detailData.qty_damaged} ${detailData.item_uom}`"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Penyelesaian & Keterangan -->
                <div class="p-3 bg-light rounded-2 border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-8 text-muted">Status Penanganan / Klaim:</span>
                        <span class="badge text-bg-primary fs-8" x-text="detailData.resolution_status"></span>
                    </div>
                    <span class="fs-8 text-muted d-block">Catatan / Keterangan Berita Acara:</span>
                    <p class="fs-8 text-slate-800 mb-0 mt-1 font-monospace" x-text="detailData.resolution_notes"></p>
                </div>
            </div>

            <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-between gap-2">
                <a :href="'/receiving/discrepancies/' + detailData.id + '/print'" 
                   target="_blank" 
                   class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 shadow-xs">
                    <i class="bi bi-printer"></i>
                    <span>Cetak Berita Acara</span>
                </a>
                <button type="button" class="btn btn-sm btn-secondary" @click="detailModalOpen = false">Tutup</button>
            </div>
        </div>
    </div>

</div>

<script>
function discrepancyManager() {
    return {
        detailModalOpen: false,
        detailData: {},

        openDiscrepancyModal(data) {
            this.detailData = data;
            this.detailModalOpen = true;
        }
    };
}
</script>
@endsection
