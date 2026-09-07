@extends('layouts.app')
@section('title', 'Detail Order: ' . $order->order_number)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Overview</a></li>
    <li class="breadcrumb-item"><a href="{{ route('orders.index') }}" class="text-decoration-none text-danger">Orders</a></li>
    <li class="breadcrumb-item active font-monospace" aria-current="page">{{ $order->order_number }}</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ rejectModal: false, switchingModal: false, selectedSwitchItem: null }">

    <!-- Header Action Bar -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0 font-monospace text-body">{{ $order->order_number }}</h4>
                @php
                    $badgeClass = match($order->status) {
                        'COMPLETED', 'RECEIVED' => 'text-bg-success',
                        'IN_TRANSIT' => 'text-bg-info',
                        'READY_TO_SHIP', 'ALLOCATED' => 'text-bg-primary',
                        'WAITING_APPROVAL', 'SUBMITTED' => 'text-bg-warning',
                        'CANCELLED', 'REJECTED' => 'text-bg-danger',
                        default => 'text-bg-light border'
                    };
                @endphp
                <span class="badge {{ $badgeClass }} fs-8 text-uppercase">
                    {{ str_replace('_', ' ', $order->status) }}
                </span>
            </div>
            <p class="fs-7 text-secondary mb-0 mt-1">
                Dibuat oleh: <strong class="text-body">{{ $order->requester->name }}</strong> • {{ $order->created_at->format('d M Y, H:i') }} WIB • Unit: {{ $order->requestingOrganization->name }} ({{ $order->requestingOrganization->code }})
            </p>
        </div>

        <!-- Approval / Action Buttons -->
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
            </a>
            @if(in_array($order->status, ['SUBMITTED', 'WAITING_APPROVAL']))
                @if(auth()->user()->hasRole('SUPER_ADMIN', 'ORDER_APPROVER'))
                    <button type="button" @click="rejectModal = true" class="btn btn-sm btn-outline-danger fw-bold">
                        <i class="bi bi-x-circle me-1"></i> Tolak Order
                    </button>
                    <form action="{{ route('orders.approve', $order->id) }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success fw-bold shadow-xs">
                            <i class="bi bi-check2-all me-1"></i> Setujui Order & Reservasi Stok
                        </button>
                    </form>
                @endif
            @elseif($order->status === 'ALLOCATED')
                <a href="{{ route('warehouse.picking.queue') }}" class="btn btn-sm btn-primary fw-bold shadow-xs">
                    <i class="bi bi-boxes me-1"></i> Proses Picking Gudang
                </a>
            @endif
        </div>
    </div>

    <!-- Workflow Progress Bar Card -->
    <div class="card card-outline card-danger shadow-xs">
        <div class="card-header border-bottom">
            <h3 class="card-title fs-7 fw-bold mb-0 text-uppercase text-secondary">
                <i class="bi bi-diagram-3-fill text-danger me-1"></i> Status Alur Transaksi (Workflow Timeline)
            </h3>
        </div>
        <div class="card-body py-3 border-bottom bg-body-tertiary">
            @php
                $steps = [
                    'SUBMITTED' => 'Submitted',
                    'APPROVED' => 'Approved',
                    'ALLOCATED' => 'Allocated',
                    'PICKING' => 'Picking',
                    'READY_TO_SHIP' => 'Packed',
                    'IN_TRANSIT' => 'In-Transit',
                    'RECEIVED' => 'Received',
                    'COMPLETED' => 'Settled',
                ];
                $currentStatus = $order->status;
                $allStatuses = array_keys($steps);
                $currentIndex = array_search($currentStatus, $allStatuses);
                if ($currentIndex === false) $currentIndex = 1;
            @endphp
            <div class="row text-center g-2">
                @foreach($steps as $stCode => $stLabel)
                    @php
                        $stepIdx = array_search($stCode, $allStatuses);
                        $isPassed = $stepIdx <= $currentIndex && $order->status !== 'REJECTED';
                        $isCurrent = $stCode === $currentStatus;
                    @endphp
                    <div class="col-6 col-sm-3 col-md">
                        <div class="d-flex flex-column align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold fs-7 mb-1 {{ $isPassed ? 'bg-danger text-white shadow-xs' : 'bg-body-secondary text-secondary' }}" style="width: 32px; height: 32px;">
                                {{ $loop->iteration }}
                            </div>
                            <span class="fs-8 fw-bold {{ $isPassed ? 'text-body' : 'text-secondary' }}">{{ $stLabel }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Detailed Timeline Table: Status, Tanggal, Siapa yang memproses, Keterangan -->
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
                Stok pada Gudang Logistik Utama tidak mencukupi untuk memenuhi seluruh permintaan. Sistem mendeteksi cabang lain dengan kelebihan stok (di atas safety stock):
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
                    {{ $order->items->count() }} jenis barang
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
                        @foreach($order->items as $it)
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
                                Rp {{ number_format($order->total_estimated_value, 0, ',', '.') }}
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Switching Stock Modal -->
    <div x-show="switchingModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak style="display: none;">
        <div @click.away="switchingModal = false" class="card shadow-2xl border border-secondary-subtle max-w-lg w-full p-4 p-sm-5 space-y-4" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-3">
                <h5 class="fw-bold fs-6 mb-0 text-body">Form Pengajuan Switching Stock</h5>
                <button type="button" @click="switchingModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('orders.switching', $order->id) }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="item_id" :value="selectedSwitchItem?.item?.id">
                
                <div>
                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">Barang</label>
                    <div class="p-2.5 rounded-3 bg-body-secondary fw-bold fs-7 text-body" x-text="selectedSwitchItem?.item?.name"></div>
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
                    <textarea name="reason" rows="2" class="form-control fs-7">Pemenuhan kekurangan stok pusat dari kelebihan stok cabang regional.</textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                    <button type="button" @click="switchingModal = false" class="btn btn-sm btn-outline-secondary">Batal</button>
                    <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold">
                        Ajukan Proposal Switching
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Order Modal -->
    <div x-show="rejectModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak style="display: none;">
        <div @click.away="rejectModal = false" class="card shadow-2xl border border-secondary-subtle max-w-md w-full p-4 space-y-3" style="background-color: var(--bs-body-bg); color: var(--bs-body-color);">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                <h5 class="fw-bold fs-6 mb-0 text-danger"><i class="bi bi-x-circle me-1"></i> Konfirmasi Penolakan Order</h5>
                <button type="button" @click="rejectModal = false" class="btn-close" aria-label="Close"></button>
            </div>
            <form action="{{ route('orders.reject', $order->id) }}" method="POST">
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

</div>
@endsection
