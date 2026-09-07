@extends('layouts.app')
@section('title', 'Persetujuan Order Permintaan')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('orders.index') }}" class="text-decoration-none text-danger">Permintaan & Order</a></li>
    <li class="breadcrumb-item active" aria-current="page">Persetujuan Order</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ rejectModal: false, switchingModal: false, selectedSwitchItem: null }">

    <!-- Filter Tabs Bar -->
    <div class="card card-outline card-danger shadow-xs mb-3">
        <div class="card-header p-2 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-2">
            <ul class="nav nav-pills nav-pills-scroll flex-nowrap card-header-pills fs-7 pb-1 pb-md-0">
                <li class="nav-item">
                    <a href="{{ route('orders.approvals', ['tab' => 'pending']) }}" class="nav-link {{ $statusTab === 'pending' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        <i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan
                        <span class="badge {{ $statusTab === 'pending' ? 'bg-white text-danger' : 'text-bg-warning' }} ms-1">{{ $pendingCount }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('orders.approvals', ['tab' => 'history']) }}" class="nav-link {{ $statusTab === 'history' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        <i class="bi bi-check2-circle me-1"></i> Riwayat Persetujuan
                        <span class="badge {{ $statusTab === 'history' ? 'bg-white text-danger' : 'text-bg-secondary' }} ms-1">{{ $historyCount }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('orders.approvals', ['tab' => 'all']) }}" class="nav-link {{ $statusTab === 'all' ? 'active bg-danger fw-bold' : 'text-body' }} py-1 px-3 text-nowrap">
                        <i class="bi bi-collection me-1"></i> Semua Transaksi
                        <span class="badge {{ $statusTab === 'all' ? 'bg-white text-danger' : 'text-bg-secondary' }} ms-1">{{ $allCount }}</span>
                    </a>
                </li>
            </ul>

            <!-- Quick Search -->
            <form action="{{ route('orders.approvals') }}" method="GET" class="d-flex align-items-center gap-2 m-0 w-100 w-md-auto">
                <input type="hidden" name="tab" value="{{ $statusTab }}">
                <div class="input-group input-group-sm w-100" style="max-width: 300px;">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm fs-8">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    @if(!$selectedOrder)
        <!-- Empty State -->
        <div class="card shadow-xs text-center py-5">
            <div class="card-body">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h5 class="fw-bold mt-3 mb-1 text-body">Tidak Ada Order Dalam Antrean</h5>
                <p class="text-secondary fs-7 mb-3">Seluruh order pada kategori ini telah diproses atau belum ada pengajuan baru.</p>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Lihat Daftar Semua Order
                </a>
            </div>
        </div>
    @else
        <div class="row g-3">
            <!-- Left Column: Orders Queue Selector -->
            <div class="col-12 col-xl-4">
                <div class="card card-outline card-secondary shadow-xs">
                    <div class="card-header border-bottom d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="fs-7 fw-bold text-body">
                            <i class="bi bi-list-task text-danger me-1"></i> Antrean Order ({{ $orders->total() }})
                        </span>
                        <span class="fs-8 text-secondary">Pilih untuk memproses</span>
                    </div>
                    <div class="card-body p-2 space-y-2" style="max-height: 720px; overflow-y: auto;">
                        @foreach($orders as $ord)
                            @php
                                $isSelected = $ord->id === $selectedOrder->id;
                                $ordBadge = match($ord->status) {
                                    'COMPLETED', 'RECEIVED' => 'text-bg-success',
                                    'IN_TRANSIT' => 'text-bg-info',
                                    'READY_TO_SHIP', 'ALLOCATED' => 'text-bg-primary',
                                    'WAITING_APPROVAL', 'SUBMITTED' => 'text-bg-warning',
                                    'CANCELLED', 'REJECTED' => 'text-bg-danger',
                                    default => 'text-bg-light border'
                                };
                            @endphp
                            <a href="{{ route('orders.approvals', array_merge(request()->query(), ['order_id' => $ord->id])) }}"
                               class="d-block p-3 rounded-3 text-decoration-none border transition shadow-xs {{ $isSelected ? 'border-danger bg-danger-subtle' : 'border-secondary-subtle bg-body hover:bg-body-tertiary' }}">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="font-monospace fw-bold fs-7 {{ $isSelected ? 'text-danger' : 'text-body' }}">
                                        {{ $ord->order_number }}
                                    </span>
                                    <span class="badge {{ $ordBadge }} fs-8">
                                        {{ str_replace('_', ' ', $ord->status) }}
                                    </span>
                                </div>
                                <div class="fs-8 fw-semibold text-body mb-1">
                                    {{ $ord->requestingOrganization->name }}
                                </div>
                                <div class="d-flex justify-content-between align-items-center fs-8 text-secondary">
                                    <span>Maker: {{ $ord->requester->name }}</span>
                                    <span class="fw-bold font-monospace text-body">
                                        Rp {{ number_format($ord->total_estimated_value, 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center fs-8 text-secondary mt-1 pt-1 border-top border-secondary-subtle">
                                    <span><i class="bi bi-clock me-1"></i>{{ $ord->created_at->format('d/m/Y H:i') }}</span>
                                    <span class="badge {{ $ord->priority === 'URGENT' ? 'bg-danger' : ($ord->priority === 'HIGH' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                        {{ $ord->priority }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <x-pagination-footer :paginator="$orders" />
                </div>
            </div>

            <!-- Right Column: Full Order Approval Detail View (Identical to orders/[id]) -->
            <div class="col-12 col-xl-8">
                <div class="space-y-4">
                    <!-- Order Header Banner & Action Buttons -->
                    <div class="card card-outline card-danger shadow-xs">
                        <div class="card-body p-3 p-md-4">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h4 class="fw-bold mb-0 font-monospace text-body">{{ $selectedOrder->order_number }}</h4>
                                        @php
                                            $selBadge = match($selectedOrder->status) {
                                                'COMPLETED', 'RECEIVED' => 'text-bg-success',
                                                'IN_TRANSIT' => 'text-bg-info',
                                                'READY_TO_SHIP', 'ALLOCATED' => 'text-bg-primary',
                                                'WAITING_APPROVAL' => 'text-bg-warning',
                                                'SUBMITTED' => 'text-bg-warning',
                                                'CANCELLED', 'REJECTED' => 'text-bg-danger',
                                                default => 'text-bg-light border'
                                            };
                                        @endphp
                                        <span class="badge {{ $selBadge }} fs-8 text-uppercase">
                                            {{ str_replace('_', ' ', $selectedOrder->status) }}
                                        </span>
                                    </div>
                                    <p class="fs-7 text-secondary mb-0 mt-1">
                                        Pemohon: <strong class="text-body">{{ $selectedOrder->requester->name }}</strong> • {{ $selectedOrder->created_at->format('d M Y, H:i') }} WIB • Unit: {{ $selectedOrder->requestingOrganization->name }} ({{ $selectedOrder->requestingOrganization->code }})
                                    </p>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-flex flex-wrap align-items-center gap-2">

                                    @if(in_array($selectedOrder->status, ['SUBMITTED', 'WAITING_APPROVAL']))
                                        @if(auth()->user()->hasRole('SUPER_ADMIN', 'ORDER_APPROVER'))
                                            <button type="button" @click="rejectModal = true" class="btn btn-sm btn-outline-danger fw-bold">
                                                <i class="bi bi-x-circle me-1"></i> Tolak Order
                                            </button>
                                            <form action="{{ route('orders.approve', $selectedOrder->id) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success fw-bold shadow-xs">
                                                    <i class="bi bi-check2-all me-1"></i> Setujui & Reservasi Stok
                                                </button>
                                            </form>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle py-2 px-3 fs-8">
                                                <i class="bi bi-info-circle me-1"></i> Memerlukan Peran ORDER_APPROVER
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STATUS ALUR TRANSAKSI (WORKFLOW TIMELINE) CARD -->
                    <div class="card card-outline card-danger shadow-xs">
                        <div class="card-header border-bottom">
                            <h3 class="card-title fs-7 fw-bold mb-0 text-uppercase text-secondary">
                                <i class="bi bi-diagram-3-fill text-danger me-1"></i> Status Alur Transaksi (Workflow Timeline)
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <!-- Detailed Timeline Table -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-7">
                                    <thead class="bg-body-tertiary border-bottom fs-8 text-uppercase text-secondary">
                                        <tr>
                                            <th class="ps-3" style="width: 50px;">No</th>
                                            <th style="min-width: 170px;">Status Alur</th>
                                            <th style="min-width: 160px;">Tanggal & Waktu</th>
                                            <th style="min-width: 180px;">Siapa yang Memproses</th>
                                            <th>Keterangan / Catatan</th>
                                            <th class="text-center pe-3" style="width: 130px;">Kondisi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($timeline as $tl)
                                            <tr class="{{ $tl['status_state'] === 'CURRENT' ? 'table-warning' : '' }}">
                                                <td class="ps-3 text-center fw-bold">
                                                    <span class="badge rounded-pill {{ $tl['status_state'] === 'DONE' ? 'bg-danger' : ($tl['status_state'] === 'CURRENT' ? 'bg-warning text-dark' : ($tl['status_state'] === 'REJECTED' ? 'bg-danger' : 'bg-secondary')) }}" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                                                        {{ $tl['step'] }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-body">{{ $tl['label'] }}</div>
                                                    <span class="fs-8 font-monospace text-secondary">{{ $tl['code'] }}</span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold font-monospace fs-8 text-body">
                                                        <i class="bi bi-calendar-event me-1 text-secondary"></i>{{ $tl['date_formatted'] }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-body">{{ $tl['actor_name'] }}</div>
                                                    <div class="fs-8 text-secondary">{{ $tl['actor_role'] }}</div>
                                                </td>
                                                <td>
                                                    <div class="fs-8 text-body-secondary leading-relaxed">
                                                        {{ $tl['description'] }}
                                                    </div>
                                                </td>
                                                <td class="text-center pe-3">
                                                    <span class="badge {{ $tl['badge_class'] }} fs-8">
                                                        {{ $tl['badge_label'] }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Switching Stock Recommendation Banner -->
                    @if(!empty($switchingRecommendations))
                        <div class="alert alert-warning border-warning shadow-xs">
                            <div class="d-flex align-items-center gap-2 fw-bold fs-7 mb-1">
                                <i class="bi bi-lightbulb-fill text-warning fs-5"></i>
                                <span>Rekomendasi Cerdas Switching Stock Antar-Cabang (Intelligent Fulfillment)</span>
                            </div>
                            <p class="fs-8 mb-3">
                                Stok pada Gudang Logistik Utama tidak mencukupi untuk memenuhi seluruh permintaan. Sistem mendeteksi unit alternatif dengan kelebihan stok:
                            </p>

                            <div class="space-y-2">
                                @foreach($switchingRecommendations as $rec)
                                    <div class="card p-3 border-warning-subtle bg-body">
                                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                                            <div>
                                                <div class="fs-7 fw-bold text-body">{{ $rec['item']->name }}</div>
                                                <div class="fs-8 text-secondary">
                                                    Defisit: <strong class="text-danger">{{ $rec['deficit'] }} {{ $rec['item']->uom }}</strong> (Stok Pusat: {{ $rec['central_available'] }} {{ $rec['item']->uom }})
                                                </div>
                                                <div class="fs-8 text-primary fw-semibold mt-1">
                                                    Sumber Alternatif: {{ $rec['alternatives'][0]['organization_name'] }} (Stok Lebih: {{ $rec['alternatives'][0]['excess_stock'] }} {{ $rec['item']->uom }})
                                                </div>
                                            </div>
                                            <button @click="selectedSwitchItem = {{ Js::from($rec) }}; switchingModal = true" class="btn btn-sm btn-warning text-dark fw-bold">
                                                Ajukan Switching Stock
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Items Table Card -->
                    <div class="card card-outline card-secondary shadow-xs">
                        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                            <h3 class="card-title fs-6 fw-bold mb-0 text-body">
                                <i class="bi bi-box-seam me-1"></i> Rincian Barang yang Diminta
                            </h3>
                            <div class="card-tools">
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-8">
                                    {{ $selectedOrder->items->count() }} jenis barang
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-7">
                                    <thead class="border-bottom fs-8 text-uppercase text-secondary bg-body-tertiary">
                                        <tr>
                                            <th class="ps-3">Item & SKU</th>
                                            <th class="text-center">Diminta</th>
                                            <th class="text-center">Dialokasikan</th>
                                            <th class="text-center">Dipick/Pack</th>
                                            <th class="text-center">Dikirim</th>
                                            <th class="text-center">Diterima</th>
                                            <th class="text-end pe-3">Est. Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($selectedOrder->items as $it)
                                            <tr>
                                                <td class="ps-3">
                                                    <div class="fw-bold text-body">{{ $it->item->name }}</div>
                                                    <div class="fs-8 font-monospace text-secondary">{{ $it->item->sku }} • {{ $it->item->uom }}</div>
                                                </td>
                                                <td class="text-center fw-bold text-body">{{ $it->qty_requested }}</td>
                                                <td class="text-center fw-bold text-primary">{{ $it->qty_allocated }}</td>
                                                <td class="text-center fw-bold text-info">{{ $it->qty_packed }}</td>
                                                <td class="text-center fw-bold text-warning">{{ $it->qty_shipped }}</td>
                                                <td class="text-center fw-bold text-success">{{ $it->qty_received }}</td>
                                                <td class="text-end pe-3 fw-bold font-monospace text-body-emphasis">
                                                    Rp {{ number_format($it->subtotal_ref, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-body-tertiary border-top">
                                        <tr>
                                            <th colspan="6" class="text-end ps-3 fw-bold text-body">Total Estimasi Nilai Order:</th>
                                            <th class="text-end pe-3 fw-bold font-monospace text-danger fs-6">
                                                Rp {{ number_format($selectedOrder->total_estimated_value, 0, ',', '.') }}
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Order Modal -->
        <div x-show="rejectModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak style="display: none;">
            <div @click.away="rejectModal = false" class="card shadow-2xl border border-secondary-subtle max-w-md w-full p-4 space-y-3" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                    <h5 class="fw-bold fs-6 mb-0 text-danger"><i class="bi bi-x-circle me-1"></i> Konfirmasi Penolakan Order</h5>
                    <button type="button" @click="rejectModal = false" class="btn-close" aria-label="Close"></button>
                </div>
                <form action="{{ route('orders.reject', $selectedOrder->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alasan Penolakan</label>
                        <textarea name="reason" rows="3" required class="form-control fs-7"></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" @click="rejectModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                        <button type="submit" class="btn btn-sm btn-danger fw-bold">Tolak Order</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Switching Stock Modal -->
        <div x-show="switchingModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak style="display: none;">
            <div @click.away="switchingModal = false" class="card shadow-2xl border border-secondary-subtle max-w-lg w-full p-4 space-y-3" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                    <h5 class="fw-bold fs-6 mb-0 text-body">Form Pengajuan Switching Stock</h5>
                    <button type="button" @click="switchingModal = false" class="btn-close" aria-label="Close"></button>
                </div>
                <form action="{{ route('orders.switching', $selectedOrder->id) }}" method="POST" class="space-y-3">
                    @csrf
                    <input type="hidden" name="item_id" :value="selectedSwitchItem?.item?.id">
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Barang</label>
                        <div class="p-2 rounded-3 bg-body-secondary fw-bold fs-7 text-body" x-text="selectedSwitchItem?.item?.name"></div>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Pilih Unit Sumber Alternatif</label>
                        <select name="source_warehouse_id" required class="form-select fs-7">
                            <template x-for="alt in selectedSwitchItem?.alternatives" :key="alt.warehouse_id">
                                <option :value="alt.warehouse_id" x-text="alt.organization_name + ' (Stok Lebih: ' + alt.excess_stock + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Jumlah Switching Stock (Qty)</label>
                        <input type="number" name="qty" :value="selectedSwitchItem?.deficit" min="1" required class="form-control fw-bold font-monospace fs-7">
                    </div>
                    <div>
                        <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Alasan Rekomendasi</label>
                        <textarea name="notes" rows="2" class="form-control fs-7">Pemenuhan kekurangan stok pusat dari kelebihan stok cabang regional.</textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <button type="button" @click="switchingModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                        <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold">Ajukan Proposal Switching</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
@endsection
