@extends('layouts.app')
@section('title', 'Gudang: Picking List')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik & Gudang</li>
    <li class="breadcrumb-item active" aria-current="page">Picking List</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Main Card -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-box-seam text-danger fs-5"></i>
                <h3 class="card-title fw-bold text-slate-800 fs-6 mb-0">
                    Antrean Picking Barang (Status: ALLOCATED)
                    @if($allocatedOrders->count() > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $allocatedOrders->count() }}</span>
                    @endif
                </h3>
            </div>
            <div>
                <a href="{{ route('warehouse.packing.queue') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-xs">
                    <i class="bi bi-box-seam"></i>
                    <span>Buka Antrean Packing</span>
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
                <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                    <tr>
                        <th class="ps-3 py-2" style="width: 170px;">No. Order & Tanggal</th>
                        <th class="py-2" style="width: 220px;">Tujuan Unit Kerja</th>
                        <th class="py-2">Item yang Diambil (Picking)</th>
                        <th class="py-2 text-center" style="width: 110px;">Total Qty</th>
                        <th class="py-2 text-center" style="width: 110px;">Prioritas</th>
                        <th class="pe-3 py-2 text-center" style="width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allocatedOrders as $ord)
                        <tr>
                            <td class="ps-3">
                                <span class="font-monospace fw-bold text-slate-900 d-block">{{ $ord->order_number }}</span>
                                <small class="text-muted fs-8">{{ $ord->order_date ? $ord->order_date->format('d M Y') : '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-slate-800 d-block">{{ $ord->requestingOrganization->name }}</span>
                                <small class="text-muted fs-8">{{ $ord->requestingOrganization->city ?? '-' }}</small>
                            </td>
                            <td>
                                <ul class="list-unstyled mb-0 space-y-1">
                                    @foreach($ord->items as $it)
                                        <li class="d-flex align-items-center justify-content-between pe-2">
                                            <span class="text-slate-800">{{ $it->item->name }}</span>
                                            <span class="badge bg-danger-subtle text-danger-emphasis font-monospace ms-2">
                                                {{ $it->qty_allocated }} {{ $it->item->uom }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-light border font-monospace fs-7 fw-bold">
                                    {{ $ord->items->sum('qty_allocated') }}
                                </span>
                            </td>
                            <td class="text-center">
                                @php
                                    $prioClass = match($ord->priority) {
                                        'URGENT' => 'text-bg-danger',
                                        'HIGH' => 'text-bg-warning',
                                        default => 'text-bg-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $prioClass }} fs-8">{{ $ord->priority }}</span>
                            </td>
                            <td class="pe-3 text-center">
                                <form action="{{ route('warehouse.picking.process', $ord->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" 
                                            class="btn-action-icon text-danger" 
                                            title="Konfirmasi Picking Selesai (Masuk Tahap Packing)">
                                        <i class="bi bi-check2-circle"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                <span class="fw-semibold">Tidak ada order yang menunggu picking saat ini.</span>
                                <p class="fs-8 text-muted mb-0">Semua order yang dialokasikan telah selesai dipicking atau belum ada alokasi baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
