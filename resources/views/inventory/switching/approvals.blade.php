@extends('layouts.app')
@section('title', 'Persetujuan Switching Stock')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.switching.index') }}" class="text-decoration-none text-danger">Switching Stock</a></li>
    <li class="breadcrumb-item active" aria-current="page">Persetujuan Switching</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ rejectModal: false, rejectionReason: '', dispatchModal: false, receiveModal: false }">

    <!-- Filter Tabs Bar -->
    <div class="card card-outline card-danger shadow-xs mb-3">
        <div class="card-header p-2 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <ul class="nav nav-pills nav-pills-scroll flex-nowrap card-header-pills fs-7 pb-1 pb-md-0">
                <li class="nav-item">
                    <a href="{{ route('inventory.switching.approvals', ['tab' => 'pending']) }}" class="nav-link {{ $statusTab === 'pending' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        <i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan
                        <span class="badge {{ $statusTab === 'pending' ? 'bg-white text-danger' : 'text-bg-warning' }} ms-1">{{ $pendingCount }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('inventory.switching.approvals', ['tab' => 'history']) }}" class="nav-link {{ $statusTab === 'history' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        <i class="bi bi-check2-circle me-1"></i> Riwayat Persetujuan
                        <span class="badge {{ $statusTab === 'history' ? 'bg-white text-danger' : 'text-bg-secondary' }} ms-1">{{ $historyCount }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('inventory.switching.approvals', ['tab' => 'all']) }}" class="nav-link {{ $statusTab === 'all' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        <i class="bi bi-collection me-1"></i> Semua Transaksi
                        <span class="badge {{ $statusTab === 'all' ? 'bg-white text-danger' : 'text-bg-secondary' }} ms-1">{{ $allCount }}</span>
                    </a>
                </li>
            </ul>

            <!-- Quick Search -->
            <form action="{{ route('inventory.switching.approvals') }}" method="GET" class="d-flex align-items-center gap-2 m-0 w-100 w-md-auto">
                <input type="hidden" name="tab" value="{{ $statusTab }}">
                <div class="input-group input-group-sm w-100" style="max-width: 320px;">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm fs-8" placeholder="Cari barang / unit / no order...">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                    @if(request('search'))
                        <a href="{{ route('inventory.switching.approvals', ['tab' => $statusTab]) }}" class="btn btn-outline-danger btn-sm" title="Reset Search">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if(!$selectedSwitching)
        <!-- Empty State -->
        <div class="card shadow-xs text-center py-5">
            <div class="card-body">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h5 class="fw-bold mt-3 mb-1 text-body">Tidak Ada Switching Stock Dalam Antrean</h5>
                <p class="text-secondary fs-7 mb-3">Seluruh pengajuan switching stock pada kategori ini telah diproses atau belum ada proposal baru.</p>
                <a href="{{ route('inventory.switching.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Lihat Daftar Semua Switching Stock
                </a>
            </div>
        </div>
    @else
        <div class="row g-3">
            <!-- Left Column: Switching Queue Selector -->
            <div class="col-12 col-xl-4">
                <div class="card card-outline card-secondary shadow-xs">
                    <div class="card-header border-bottom d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="fs-7 fw-bold text-body">
                            <i class="bi bi-arrow-left-right text-danger me-1"></i> Antrean Switching ({{ $switchings->total() }})
                        </span>
                        <span class="fs-8 text-secondary">Pilih untuk memproses</span>
                    </div>
                    <div class="card-body p-2 space-y-2" style="max-height: 760px; overflow-y: auto;">
                        @foreach($switchings as $sw)
                            @php
                                $isSelected = $sw->id === $selectedSwitching->id;
                                $swBadge = match($sw->status) {
                                    'APPROVED', 'COMPLETED', 'RECEIVED' => 'text-bg-success',
                                    'RESERVED', 'TRANSFERRED' => 'text-bg-info',
                                    'WAITING_APPROVAL', 'PROPOSED' => 'text-bg-warning',
                                    'CANCELLED', 'REJECTED' => 'text-bg-danger',
                                    default => 'text-bg-light border'
                                };
                            @endphp
                            <a href="{{ route('inventory.switching.approvals', array_merge(request()->query(), ['switching_id' => $sw->id])) }}"
                               class="d-block p-3 rounded-3 text-decoration-none border transition shadow-xs {{ $isSelected ? 'border-danger bg-danger-subtle' : 'border-secondary-subtle bg-body hover:bg-body-tertiary' }}">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="font-monospace fw-bold fs-7 {{ $isSelected ? 'text-danger' : 'text-body' }}">
                                        Switching #{{ $sw->id }}
                                    </span>
                                    <span class="badge {{ $swBadge }} fs-8">
                                        {{ str_replace('_', ' ', $sw->status) }}
                                    </span>
                                </div>
                                <div class="fs-8 text-secondary mb-1">
                                    <div class="text-truncate">
                                        <i class="bi bi-box-arrow-up text-danger me-1"></i>Dari: <strong class="text-body">{{ $sw->sourceOrganization?->name ?? '-' }}</strong>
                                    </div>
                                    <div class="text-truncate">
                                        <i class="bi bi-box-arrow-in-down text-success me-1"></i>Ke: <strong class="text-body">{{ $sw->destinationOrganization?->name ?? '-' }}</strong>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center fs-8 text-secondary mt-1">
                                    <span class="text-truncate" style="max-width: 180px;">
                                        @if($sw->items->count() > 1)
                                            {{ $sw->items->first()?->item?->name ?? 'Barang' }} (+{{ $sw->items->count() - 1 }})
                                        @else
                                            {{ $sw->items->first()?->item?->name ?? ($sw->item?->name ?? 'Barang') }}
                                        @endif
                                    </span>
                                    <span class="fw-bold font-monospace text-body">
                                        {{ $sw->total_qty }} Unit
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center fs-8 text-secondary mt-1 pt-1 border-top border-secondary-subtle">
                                    <span><i class="bi bi-clock me-1"></i>{{ $sw->created_at->format('d/m/Y H:i') }}</span>
                                    <span>Maker: <strong class="text-body">{{ $sw->proposer?->name ?? 'User' }}</strong></span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <x-pagination-footer :paginator="$switchings" />
                </div>
            </div>

            <!-- Right Column: Full Switching Detail View (Identical to orders/approvals style) -->
            <div class="col-12 col-xl-8">
                <div class="space-y-4">
                    <!-- Header Banner & Action Buttons -->
                    <div class="card card-outline card-danger shadow-xs">
                        <div class="card-body p-3 p-md-4">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h4 class="fw-bold mb-0 font-monospace text-body">Proposal Switching #{{ $selectedSwitching->id }}</h4>
                                        @php
                                            $selBadge = match($selectedSwitching->status) {
                                                'APPROVED', 'COMPLETED', 'RECEIVED' => 'text-bg-success',
                                                'RESERVED', 'TRANSFERRED' => 'text-bg-info',
                                                'WAITING_APPROVAL', 'PROPOSED' => 'text-bg-warning',
                                                'CANCELLED', 'REJECTED' => 'text-bg-danger',
                                                default => 'text-bg-light border'
                                            };
                                        @endphp
                                        <span class="badge {{ $selBadge }} fs-8 text-uppercase">
                                            {{ str_replace('_', ' ', $selectedSwitching->status) }}
                                        </span>
                                    </div>
                                    <p class="fs-7 text-secondary mb-0 mt-1">
                                        Pembuat: <strong class="text-body">{{ $selectedSwitching->proposer?->name ?? 'User' }}</strong> • 
                                        {{ $selectedSwitching->created_at->format('d M Y, H:i') }} WIB • 
                                        Dari <strong>{{ $selectedSwitching->sourceOrganization?->name ?? '-' }}</strong> ke <strong>{{ $selectedSwitching->destinationOrganization?->name ?? '-' }}</strong>
                                    </p>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    @if(in_array($selectedSwitching->status, ['PROPOSED', 'WAITING_APPROVAL']))
                                        @if(auth()->user()->canAccessModule('switching_approvals'))
                                            <button type="button" @click="rejectModal = true" class="btn btn-sm btn-outline-danger fw-bold">
                                                <i class="bi bi-x-circle me-1"></i> Tolak Switching
                                            </button>
                                            <form action="{{ route('inventory.switching.approve', $selectedSwitching->id) }}" method="POST" class="m-0"
                                                  onsubmit="return confirm('Apakah Anda yakin ingin menyetujui pengalihan stok ini? Stok pada gudang sumber akan langsung direservasi.')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success fw-bold shadow-xs">
                                                    <i class="bi bi-check2-all me-1"></i> Setujui & Reservasi Stok
                                                </button>
                                            </form>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle py-2 px-3 fs-8">
                                                <i class="bi bi-info-circle me-1"></i> Memerlukan Peran SWITCHING_APPROVER
                                            </span>
                                        @endif
                                    @elseif(in_array($selectedSwitching->status, ['APPROVED', 'RESERVED']))
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <div class="text-end me-1">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 fs-8">
                                                    <i class="bi bi-check-circle me-1"></i> Disetujui
                                                </span>
                                                <div class="fs-9 text-secondary font-monospace">{{ $selectedSwitching->approver?->name ?? 'Approver' }}</div>
                                            </div>
                                            @if(auth()->user()->hasRole('SUPER_ADMIN', 'WAREHOUSE_OFFICER', 'INVENTORY_OFFICER'))
                                                <a href="{{ route('distribution.shipments.index', ['switching_id' => $selectedSwitching->id]) }}" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                                                    <i class="bi bi-truck"></i>
                                                    <span>Kirim Transfer Barang via Distribusi</span>
                                                </a>
                                            @endif
                                        </div>
                                    @elseif($selectedSwitching->status === 'TRANSFERRED')
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <div class="text-end me-1">
                                                <span class="badge bg-info-subtle text-info border border-info-subtle py-1 px-2 fs-8">
                                                    <i class="bi bi-truck me-1"></i> Terkirim
                                                </span>
                                                <div class="fs-9 text-secondary font-monospace">{{ $selectedSwitching->transferred_at?->format('d/m/Y H:i') ?? '-' }}</div>
                                            </div>
                                            @if(auth()->user()->hasRole('SUPER_ADMIN', 'WAREHOUSE_OFFICER', 'INVENTORY_OFFICER', 'BRANCH_STAFF', 'BRANCH_OFFICER', 'BRANCH_HEAD'))
                                                @if($selectedSwitching->shipment_id)
                                                    <a href="{{ route('receiving.confirm.form', $selectedSwitching->shipment_id) }}" class="btn btn-sm btn-success fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-box-arrow-in-down"></i>
                                                        <span>Konfirmasi Penerimaan di /receiving</span>
                                                    </a>
                                                @else
                                                    <a href="{{ route('receiving.index') }}" class="btn btn-sm btn-success fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                                                        <i class="bi bi-box-arrow-in-down"></i>
                                                        <span>Konfirmasi Penerimaan via /receiving</span>
                                                    </a>
                                                @endif
                                            @endif
                                        </div>
                                    @elseif(in_array($selectedSwitching->status, ['COMPLETED', 'RECEIVED']))
                                        <div class="text-end">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1.5 px-2.5 fs-8">
                                                <i class="bi bi-check2-all me-1"></i> Selesai & Diterima
                                            </span>
                                            <div class="fs-9 text-secondary font-monospace mt-0.5">
                                                Oleh: {{ $selectedSwitching->receivedBy?->name ?? 'Gudang Tujuan' }} • {{ $selectedSwitching->received_at?->format('d/m/Y H:i') ?? '-' }} WIB
                                            </div>
                                        </div>
                                    @elseif($selectedSwitching->status === 'REJECTED')
                                        <div class="text-end">
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1.5 px-2.5 fs-8">
                                                <i class="bi bi-x-circle me-1"></i> Ditolak oleh {{ $selectedSwitching->approver?->name ?? 'Approver' }}
                                            </span>
                                            <div class="fs-9 text-secondary font-monospace mt-0.5">{{ $selectedSwitching->updated_at->format('d/m/Y H:i') }} WIB</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Pengalihan Stok (Switching Details) -->
                    <div class="card card-outline card-danger shadow-xs">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fs-7 fw-bold mb-0 text-uppercase text-secondary">
                                Informasi Pengalihan Stok (Switching Details)
                            </h3>
                        </div>
                        <div class="card-body p-3 p-md-4 space-y-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle h-100">
                                        <span class="text-secondary d-block fs-8 mb-1">
                                            <i class="bi bi-box-arrow-up text-danger me-1"></i>Unit & Gudang Sumber (Asal Pengiriman):
                                        </span>
                                        <div class="fw-bold fs-7 text-body">{{ $selectedSwitching->sourceOrganization?->name ?? '-' }}</div>
                                        <div class="text-secondary fs-8 mt-1">
                                            Gudang: <strong>{{ $selectedSwitching->sourceWarehouse?->name ?? '-' }}</strong> ({{ $selectedSwitching->sourceWarehouse?->code ?? '-' }})
                                        </div>
                                        <div class="fs-9 text-secondary font-monospace mt-0.5">
                                            Kota: {{ $selectedSwitching->sourceOrganization?->city ?? '-' }} • Cost Center: {{ $selectedSwitching->sourceOrganization?->cost_center_code ?? '-' }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="p-3 rounded-3 bg-body-secondary border border-secondary-subtle h-100">
                                        <span class="text-secondary d-block fs-8 mb-1">
                                            <i class="bi bi-box-arrow-in-down text-success me-1"></i>Unit & Gudang Tujuan (Penerima):
                                        </span>
                                        <div class="fw-bold fs-7 text-body">{{ $selectedSwitching->destinationOrganization?->name ?? '-' }}</div>
                                        <div class="text-secondary fs-8 mt-1">
                                            Gudang: <strong>{{ $selectedSwitching->destinationWarehouse?->name ?? '-' }}</strong> ({{ $selectedSwitching->destinationWarehouse?->code ?? '-' }})
                                        </div>
                                        <div class="fs-9 text-secondary font-monospace mt-0.5">
                                            Kota: {{ $selectedSwitching->destinationOrganization?->city ?? '-' }} • Cost Center: {{ $selectedSwitching->destinationOrganization?->cost_center_code ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 fs-8 pt-2">
                                <div class="col-6 col-md-3">
                                    <span class="text-secondary d-block">Tipe Pengajuan:</span>
                                    @if($selectedSwitching->order)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-8">Fulfillment Order</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-8">Manual / Rebalance</span>
                                    @endif
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="text-secondary d-block">Referensi Order:</span>
                                    @if($selectedSwitching->order)
                                        <a href="{{ route('orders.show', $selectedSwitching->order_id) }}" target="_blank" class="fw-bold font-monospace text-danger text-decoration-none">
                                            {{ $selectedSwitching->order->order_number }}
                                        </a>
                                    @else
                                        <span class="text-secondary font-monospace">-</span>
                                    @endif
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="text-secondary d-block">Total Kuantitas:</span>
                                    <span class="fw-bold font-monospace fs-7 text-body">{{ $selectedSwitching->total_qty }} Unit</span>
                                </div>
                                <div class="col-6 col-md-3">
                                    <span class="text-secondary d-block">Approver:</span>
                                    <span class="fw-semibold text-body">{{ $selectedSwitching->approver?->name ?? 'Belum Ditentukan' }}</span>
                                </div>
                            </div>

                            @if($selectedSwitching->recommendation_reason)
                                <div class="p-2.5 rounded bg-body-secondary border border-secondary-subtle fs-8">
                                    <strong class="text-secondary d-block mb-1">
                                        <i class="bi bi-chat-left-text me-1"></i>Alasan Rekomendasi / Catatan Pengajuan:
                                    </strong>
                                    <div class="text-body leading-relaxed">{{ $selectedSwitching->recommendation_reason }}</div>
                                </div>
                            @endif

                            @if($selectedSwitching->rejection_reason)
                                <div class="p-2.5 rounded bg-danger-subtle text-danger border border-danger-subtle fs-8">
                                    <strong class="d-block mb-1">
                                        <i class="bi bi-x-octagon me-1"></i>Alasan Penolakan:
                                    </strong>
                                    <div class="leading-relaxed">{{ $selectedSwitching->rejection_reason }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- RINCIAN BARANG & ANALISIS STOK GUDANG SUMBER -->
                    <div class="card card-outline card-danger shadow-xs">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fs-7 fw-bold mb-0 text-uppercase text-secondary">
                                Rincian Barang Diminta & Analisis Stok Gudang Sumber
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-8">
                                    <thead class="bg-body-tertiary border-bottom text-uppercase text-secondary">
                                        <tr>
                                            <th class="ps-3" style="width: 40px;">No</th>
                                            <th>Nama & SKU Barang</th>
                                            <th class="text-center" style="width: 100px;">Qty Diminta</th>
                                            <th class="text-center" style="width: 110px;">Stok Sumber</th>
                                            <th class="text-center" style="width: 110px;">Safety Stock</th>
                                            <th class="text-center" style="width: 110px;">Sisa Excess</th>
                                            <th class="text-center pe-3" style="width: 150px;">Ketersediaan Sumber</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($selectedSwitching->items_with_stock as $idx => $it)
                                            <tr>
                                                <td class="ps-3 font-monospace text-secondary">{{ $idx + 1 }}</td>
                                                <td>
                                                    <div class="fw-bold text-body">{{ $it['item']?->name ?? 'Item #'.$it['item_id'] }}</div>
                                                    <div class="fs-9 font-monospace text-secondary">
                                                        {{ $it['item']?->sku ?? '-' }} • Satuan: {{ $it['item']?->uom ?? 'Unit' }}
                                                    </div>
                                                </td>
                                                <td class="text-center font-monospace fw-bold fs-7 text-danger">
                                                    {{ $it['qty_requested'] }} {{ $it['item']?->uom ?? '' }}
                                                </td>
                                                <td class="text-center font-monospace fw-semibold text-body">
                                                    {{ number_format($it['available_stock']) }}
                                                </td>
                                                <td class="text-center font-monospace text-secondary">
                                                    {{ number_format($it['safety_stock']) }}
                                                </td>
                                                <td class="text-center font-monospace fw-bold {{ $it['excess_stock'] > 0 ? 'text-success' : 'text-danger' }}">
                                                    {{ number_format($it['excess_stock']) }}
                                                </td>
                                                <td class="text-center pe-3">
                                                    @if($it['is_safe_excess'])
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-shield-check"></i>
                                                            <span>Aman (> Safety Stock)</span>
                                                        </span>
                                                    @elseif($it['is_sufficient'])
                                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                            <span>Mencukupi (Di Ambang)</span>
                                                        </span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-x-octagon"></i>
                                                            <span>Stok Tidak Cukup</span>
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- STATUS ALUR TRANSAKSI (WORKFLOW TIMELINE) CARD -->
                    <div class="card card-outline card-danger shadow-xs">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fs-7 fw-bold mb-0 text-uppercase text-secondary">
                                Status Alur Transaksi Switching Stock
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            @php
                                $statusOrder = [
                                    'PROPOSED' => 1,
                                    'WAITING_APPROVAL' => 1,
                                    'APPROVED' => 2,
                                    'RESERVED' => 3,
                                    'TRANSFERRED' => 4,
                                    'RECEIVED' => 5,
                                    'COMPLETED' => 5,
                                ];
                                $currentStep = $statusOrder[$selectedSwitching->status] ?? 1;
                                $isRejected = in_array($selectedSwitching->status, ['REJECTED', 'CANCELLED'], true);
                            @endphp
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-7">
                                    <thead class="bg-body-tertiary border-bottom fs-8 text-uppercase text-secondary">
                                        <tr>
                                            <th class="ps-3" style="width: 50px;">No</th>
                                            <th style="min-width: 170px;">Tahapan Alur</th>
                                            <th style="min-width: 150px;">Waktu Eksekusi</th>
                                            <th style="min-width: 180px;">Pelaku Operasional</th>
                                            <th>Keterangan / Dampak</th>
                                            <th class="text-center pe-3" style="width: 130px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Step 1: Pembuatan Proposal -->
                                        <tr>
                                            <td class="ps-3 text-center fw-bold">
                                                <span class="badge rounded-pill bg-danger" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">1</span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-body">Pengajuan Proposal</div>
                                                <span class="fs-8 font-monospace text-secondary">PROPOSED</span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold font-monospace fs-8 text-body">
                                                    <i class="bi bi-calendar-event me-1 text-secondary"></i>{{ $selectedSwitching->created_at->format('d/m/Y H:i') }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-body">{{ $selectedSwitching->proposer?->name ?? 'User' }}</div>
                                                <div class="fs-8 text-secondary">Pembuat Usulan</div>
                                            </td>
                                            <td>
                                                <div class="fs-8 text-body-secondary">
                                                    Pengajuan pemenuhan alternatif dari unit kerja sumber.
                                                </div>
                                            </td>
                                            <td class="text-center pe-3">
                                                <span class="badge bg-success-subtle text-success border border-success-subtle fs-8">SELESAI</span>
                                            </td>
                                        </tr>

                                        <!-- Step 2: Persetujuan / Approval -->
                                        <tr class="{{ $currentStep === 1 && !$isRejected ? 'table-warning' : '' }}">
                                            <td class="ps-3 text-center fw-bold">
                                                <span class="badge rounded-pill {{ $isRejected ? 'bg-danger' : ($currentStep >= 2 ? 'bg-danger' : 'bg-warning text-dark') }}" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">2</span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-body">Persetujuan Switching</div>
                                                <span class="fs-8 font-monospace text-secondary">APPROVAL</span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold font-monospace fs-8 text-body">
                                                    @if($selectedSwitching->approved_by_user_id)
                                                        <i class="bi bi-calendar-event me-1 text-secondary"></i>{{ $selectedSwitching->updated_at->format('d/m/Y H:i') }}
                                                    @else
                                                        <span class="text-secondary">-</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-body">{{ $selectedSwitching->approver?->name ?? 'Switching Approver' }}</div>
                                                <div class="fs-8 text-secondary">Approver / Reviewer</div>
                                            </td>
                                            <td>
                                                <div class="fs-8 text-body-secondary">
                                                    @if($isRejected)
                                                        Pengajuan ditolak. Alasan: {{ $selectedSwitching->rejection_reason }}
                                                    @elseif($currentStep >= 2)
                                                        Disetujui. Stok di gudang sumber direservasi otomatis.
                                                    @else
                                                        Menunggu keputusan persetujuan dari pejabat berwenang.
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center pe-3">
                                                @if($isRejected)
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-8">DITOLAK</span>
                                                @elseif($currentStep >= 2)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-8">DISETUJUI</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-8">PROSES</span>
                                                @endif
                                            </td>
                                        </tr>

                                        <!-- Step 3: Transfer & Pengiriman Antar-Unit -->
                                        <tr class="{{ ($currentStep === 2 || $currentStep === 3) && !$isRejected ? 'table-info' : '' }}">
                                            <td class="ps-3 text-center fw-bold">
                                                <span class="badge rounded-pill {{ $currentStep >= 4 ? 'bg-danger' : (($currentStep === 2 || $currentStep === 3) && !$isRejected ? 'bg-primary' : 'bg-secondary') }}" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">3</span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-body">Transfer Antar-Gudang</div>
                                                <span class="fs-8 font-monospace text-secondary">TRANSFER</span>
                                            </td>
                                            <td>
                                                @if($selectedSwitching->transferred_at)
                                                    <div class="fw-semibold font-monospace fs-8 text-body">
                                                        <i class="bi bi-calendar-event me-1 text-secondary"></i>{{ $selectedSwitching->transferred_at->format('d/m/Y H:i') }}
                                                    </div>
                                                @else
                                                    <span class="text-secondary font-monospace fs-8">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-bold text-body">{{ $selectedSwitching->transferredBy?->name ?? 'Gudang Sumber' }}</div>
                                                <div class="fs-8 text-secondary">{{ $selectedSwitching->sourceWarehouse?->name ?? 'Warehouse' }}</div>
                                            </td>
                                            <td>
                                                <div class="fs-8 text-body-secondary">
                                                    Pengeluaran barang fisik dan pengiriman ke unit kerja tujuan melalui <strong>Distribusi & Ekspedisi (/distribution/shipments)</strong>.
                                                    @if($selectedSwitching->shipment)
                                                        <div class="mt-1 font-monospace fs-9 text-dark">
                                                            <strong>No. Manifest:</strong> {{ $selectedSwitching->shipment->manifest_number }}
                                                            @if($selectedSwitching->shipment->tracking_number)
                                                                &bull; <strong>Resi:</strong> {{ $selectedSwitching->shipment->tracking_number }}
                                                            @endif
                                                        </div>
                                                    @elseif($selectedSwitching->tracking_number)
                                                        <div class="mt-1 font-monospace fs-9 text-dark"><strong>No. Resi / Ref:</strong> {{ $selectedSwitching->tracking_number }}</div>
                                                    @endif
                                                    @if(in_array($selectedSwitching->status, ['APPROVED', 'RESERVED']))
                                                        <div class="mt-1">
                                                            <a href="{{ route('distribution.shipments.index', ['switching_id' => $selectedSwitching->id]) }}" class="btn btn-xs btn-outline-danger fs-9 py-0.5 px-2 fw-bold">
                                                                <i class="bi bi-truck me-1"></i> Buka Distribusi & Ekspedisi
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center pe-3">
                                                @if($currentStep >= 4)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-8">TERKIRIM</span>
                                                @elseif($currentStep >= 2 && !$isRejected)
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-8">SIAP KIRIM</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary fs-8">MENUNGGU</span>
                                                @endif
                                            </td>
                                        </tr>

                                        <!-- Step 4: Penerimaan di Tujuan -->
                                        <tr class="{{ $currentStep === 4 ? 'table-warning' : '' }}">
                                            <td class="ps-3 text-center fw-bold">
                                                <span class="badge rounded-pill {{ $currentStep >= 5 ? 'bg-danger' : ($currentStep === 4 ? 'bg-warning text-dark' : 'bg-secondary') }}" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">4</span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-body">Penerimaan di Tujuan</div>
                                                <span class="fs-8 font-monospace text-secondary">COMPLETED</span>
                                            </td>
                                            <td>
                                                @if($selectedSwitching->received_at)
                                                    <div class="fw-semibold font-monospace fs-8 text-body">
                                                        <i class="bi bi-calendar-event me-1 text-secondary"></i>{{ $selectedSwitching->received_at->format('d/m/Y H:i') }}
                                                    </div>
                                                @else
                                                    <span class="text-secondary font-monospace fs-8">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-bold text-body">{{ $selectedSwitching->receivedBy?->name ?? 'Gudang Tujuan' }}</div>
                                                <div class="fs-8 text-secondary">{{ $selectedSwitching->destinationWarehouse?->name ?? 'Destination' }}</div>
                                            </td>
                                            <td>
                                                <div class="fs-8 text-body-secondary">
                                                    Konfirmasi penerimaan barang fisik di unit penerima melalui <strong>Penerimaan Barang Cabang (/receiving)</strong>.
                                                    @if($selectedSwitching->status === 'TRANSFERRED')
                                                        <div class="mt-1">
                                                            @if($selectedSwitching->shipment_id)
                                                                <a href="{{ route('receiving.confirm.form', $selectedSwitching->shipment_id) }}" class="btn btn-xs btn-success fs-9 py-0.5 px-2 fw-bold">
                                                                    <i class="bi bi-box-arrow-in-down me-1"></i> Konfirmasi di /receiving
                                                                </a>
                                                            @else
                                                                <a href="{{ route('receiving.index') }}" class="btn btn-xs btn-success fs-9 py-0.5 px-2 fw-bold">
                                                                    <i class="bi bi-box-arrow-in-down me-1"></i> Buka /receiving
                                                                </a>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if($selectedSwitching->receipt_notes)
                                                        <div class="mt-1 text-secondary fst-italic fs-9">Catatan: {{ $selectedSwitching->receipt_notes }}</div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center pe-3">
                                                @if($currentStep >= 5)
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-8">SELESAI</span>
                                                @elseif($currentStep === 4)
                                                    <span class="badge bg-info-subtle text-info border border-info-subtle fs-8">PENGIRIMAN</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary fs-8">MENUNGGU</span>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endif

    <!-- ==================== MODAL PENOLAKAN (REJECT) ==================== -->
    <div x-show="rejectModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3" 
         x-cloak 
         style="display: none;">
        <div class="card card-outline card-danger shadow-lg w-100 bg-body" 
             style="max-width: 520px;"
             @click.outside="rejectModal = false">
            <div class="card-header py-2.5 px-4 d-flex align-items-center justify-content-between border-bottom">
                <h6 class="mb-0 fw-bold text-danger d-inline-flex align-items-center gap-2">
                    <i class="bi bi-x-circle-fill"></i>
                    <span>Tolak Pengajuan Switching Stock</span>
                </h6>
                <button type="button" @click="rejectModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form action="{{ $selectedSwitching ? route('inventory.switching.reject', $selectedSwitching->id) : '#' }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    <p class="fs-8 text-secondary mb-2">
                        Anda akan menolak pengajuan switching stock <span class="font-monospace fw-bold text-danger">#{{ $selectedSwitching?->id }}</span> dari <strong>{{ $selectedSwitching?->sourceOrganization?->name }}</strong> ke <strong>{{ $selectedSwitching?->destinationOrganization?->name }}</strong>. Silakan masukkan alasan penolakan.
                    </p>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Alasan Penolakan <span class="text-danger">*</span>
                        </label>
                        <textarea name="rejection_reason" 
                                  x-model="rejectionReason"
                                  rows="3" 
                                  class="form-control form-control-sm fs-8" 
                                  placeholder="Contoh: Stok di gudang sumber dialokasikan untuk kebutuhan mendesak cabang lain / berada di bawah batas safety stock..."
                                  required 
                                  minlength="5"></textarea>
                        <div class="fs-9 text-secondary mt-1">Minimal 5 karakter. Alasan ini akan tercatat dalam audit trail dan dikirimkan ke pihak pengaju via notifikasi.</div>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2 px-4 border-top">
                    <button type="button" @click="rejectModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" 
                            class="btn btn-sm btn-danger fw-bold px-3 d-inline-flex align-items-center gap-1 shadow-xs"
                            :disabled="!rejectionReason || rejectionReason.trim().length < 5">
                        <i class="bi bi-x-lg"></i>
                        <span>Tolak Pengajuan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL PENGIRIMAN / TRANSFER OUT ==================== -->
    <div x-show="dispatchModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3" 
         x-cloak 
         style="display: none;">
        <div class="card card-outline card-primary shadow-lg w-100 bg-body" 
             style="max-width: 540px;"
             @click.outside="dispatchModal = false">
            <div class="card-header py-2.5 px-4 d-flex align-items-center justify-content-between border-bottom">
                <h6 class="mb-0 fw-bold text-primary d-inline-flex align-items-center gap-2">
                    <i class="bi bi-truck"></i>
                    <span>Kirim Transfer Antar-Gudang</span>
                </h6>
                <button type="button" @click="dispatchModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form action="{{ $selectedSwitching ? route('inventory.switching.dispatch', $selectedSwitching->id) : '#' }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    <div class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle fs-8 text-body">
                        <div class="fw-bold mb-1"><i class="bi bi-info-circle me-1 text-primary"></i>Pengeluaran Barang dari Gudang Sumber</div>
                        <div>Barang fisik akan dikeluarkan dari <strong>{{ $selectedSwitching?->sourceWarehouse?->name }}</strong> ({{ $selectedSwitching?->sourceOrganization?->name }}) untuk dikirimkan ke <strong>{{ $selectedSwitching?->destinationWarehouse?->name }}</strong> ({{ $selectedSwitching?->destinationOrganization?->name }}).</div>
                        <div class="mt-1 text-secondary fs-9">Stok fisik akan dipotong dan status transaksi berubah menjadi <strong>TRANSFERRED</strong>.</div>
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            No. Resi / Surat Jalan / Dokumen Referensi <span class="text-secondary fw-normal">(Opsional)</span>
                        </label>
                        <input type="text" name="tracking_number" 
                               class="form-control form-control-sm fs-8 font-monospace" 
                               placeholder="Contoh: SJ-2026/09/001 atau RESI-JNE-12345">
                    </div>

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Catatan Pengiriman <span class="text-secondary fw-normal">(Opsional)</span>
                        </label>
                        <textarea name="notes" 
                                  rows="2" 
                                  class="form-control form-control-sm fs-8" 
                                  placeholder="Contoh: Barang dikirim menggunakan armada internal cabang, estimasi tiba sore ini..."></textarea>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2 px-4 border-top">
                    <button type="button" @click="dispatchModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 d-inline-flex align-items-center gap-1 shadow-xs">
                        <i class="bi bi-send-check"></i>
                        <span>Konfirmasi Kirim Barang</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL PENERIMAAN / TRANSFER IN ==================== -->
    <div x-show="receiveModal" 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3" 
         x-cloak 
         style="display: none;">
        <div class="card card-outline card-success shadow-lg w-100 bg-body" 
             style="max-width: 540px;"
             @click.outside="receiveModal = false">
            <div class="card-header py-2.5 px-4 d-flex align-items-center justify-content-between border-bottom">
                <h6 class="mb-0 fw-bold text-success d-inline-flex align-items-center gap-2">
                    <i class="bi bi-box-seam"></i>
                    <span>Konfirmasi Penerimaan di Gudang Tujuan</span>
                </h6>
                <button type="button" @click="receiveModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form action="{{ $selectedSwitching ? route('inventory.switching.receive', $selectedSwitching->id) : '#' }}" method="POST">
                @csrf
                <div class="card-body p-4 space-y-3">
                    <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle fs-8 text-body">
                        <div class="fw-bold mb-1"><i class="bi bi-check-circle me-1 text-success"></i>Penerimaan Barang Fisik</div>
                        <div>Konfirmasi bahwa barang fisik dari <strong>{{ $selectedSwitching?->sourceWarehouse?->name }}</strong> telah tiba dan diterima dengan baik di <strong>{{ $selectedSwitching?->destinationWarehouse?->name }}</strong>.</div>
                        <div class="mt-1 text-secondary fs-9">Stok fisik gudang tujuan akan bertambah dan status transaksi menjadi <strong>COMPLETED</strong>.</div>
                    </div>

                    @if($selectedSwitching?->tracking_number)
                        <div class="fs-8">
                            <span class="text-secondary">No. Resi / Dokumen Pengiriman:</span>
                            <span class="fw-bold font-monospace ms-1 text-dark">{{ $selectedSwitching->tracking_number }}</span>
                        </div>
                    @endif

                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                            Catatan Penerimaan / Kondisi Barang <span class="text-secondary fw-normal">(Opsional)</span>
                        </label>
                        <textarea name="notes" 
                                  rows="2" 
                                  class="form-control form-control-sm fs-8" 
                                  placeholder="Contoh: Barang diterima lengkap sesuai spesifikasi, kemasan dalam kondisi baik..."></textarea>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary d-flex justify-content-end gap-2 py-2 px-4 border-top">
                    <button type="button" @click="receiveModal = false" class="btn btn-sm btn-outline-secondary px-3">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3 d-inline-flex align-items-center gap-1 shadow-xs">
                        <i class="bi bi-check2-circle"></i>
                        <span>Konfirmasi & Tambah Stok</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
