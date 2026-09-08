@extends('layouts.app')
@section('title', 'Gudang: Packing List')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik & Gudang</li>
    <li class="breadcrumb-item active" aria-current="page">Packing List</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ packModal: false, selectedOrder: null }">

    <!-- Main Card -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-boxes text-danger fs-5"></i>
                <h3 class="card-title fw-bold text-slate-800 fs-6 mb-0">
                    Antrean Pengepakan (Status: PICKING)
                    @if($pickingOrders->count() > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $pickingOrders->count() }}</span>
                    @endif
                </h3>
            </div>
            <div>
                <a href="{{ route('distribution.shipments.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-xs">
                    <i class="bi bi-truck"></i>
                    <span>Buka Modul Distribusi</span>
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
                <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                    <tr>
                        <th class="ps-3 py-2" style="width: 170px;">No. Order & Tanggal</th>
                        <th class="py-2" style="width: 220px;">Tujuan Unit Kerja</th>
                        <th class="py-2">Item Fisik Diambil</th>
                        <th class="py-2 text-center" style="width: 110px;">Total Qty</th>
                        <th class="pe-3 py-2 text-center" style="width: 90px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pickingOrders as $ord)
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
                                                {{ $it->qty_picked }} {{ $it->item->uom }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-light border font-monospace fs-7 fw-bold">
                                    {{ $ord->items->sum('qty_picked') }}
                                </span>
                            </td>
                            <td class="pe-3 text-center">
                                <button type="button" 
                                        @click="selectedOrder = {{ Js::from($ord) }}; packModal = true" 
                                        class="btn-action-icon text-danger" 
                                        title="Input Koli & Selesaikan Packing">
                                    <i class="bi bi-box-seam"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 text-secondary opacity-50 d-block mb-2"></i>
                                <span class="fw-semibold">Tidak ada order yang menunggu pengepakan saat ini.</span>
                                <p class="fs-8 text-muted mb-0">Semua order yang selesai picking telah dipacking.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Packing Modal (Standard Bank Jatim) -->
    <div x-show="packModal" 
         x-cloak 
         class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 p-md-4" 
         style="display: none;"
         @keydown.escape.window="packModal = false">
        
        <div class="bg-white rounded-3 shadow-xl max-w-lg w-full flex flex-col overflow-hidden" 
             @click.outside="packModal = false">
            
            <!-- Modal Header -->
            <div class="bg-danger text-white px-4 py-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-seam fs-5"></i>
                    <div>
                        <h5 class="modal-title mb-0 fs-6 fw-bold">Form Pengepakan Barang (Packing List)</h5>
                        <small class="text-white-50 fs-8">Verifikasi koli dan bobot paket sebelum ekspedisi</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="packModal = false" aria-label="Close"></button>
            </div>

            <!-- Modal Form Body -->
            <form :action="'{{ url('/warehouse/packing') }}/' + selectedOrder?.id + '/process'" method="POST">
                @csrf
                <div class="p-4 space-y-3">
                    
                    <!-- Order Info Box -->
                    <div class="p-3 bg-light rounded-2 border">
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="fs-8 text-muted d-block">Nomor Order</span>
                                <span class="font-monospace fw-bold text-danger" x-text="selectedOrder?.order_number"></span>
                            </div>
                            <div class="col-6">
                                <span class="fs-8 text-muted d-block">Tujuan Cabang</span>
                                <span class="fw-semibold text-slate-800" x-text="selectedOrder?.requesting_organization?.name"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fs-7 fw-bold text-slate-800">
                                Jumlah Koli (Box) <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   name="koli_count" 
                                   value="1" 
                                   min="1" 
                                   required 
                                   class="form-control form-control-sm text-center fw-bold font-monospace">
                            <small class="text-muted fs-8">Karton / koli fisik.</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-7 fw-bold text-slate-800">
                                Berat Kotor (Kg) <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   step="0.1" 
                                   name="total_weight_kg" 
                                   value="5.0" 
                                   min="0.1" 
                                   required 
                                   class="form-control form-control-sm text-center fw-bold font-monospace">
                            <small class="text-muted fs-8">Termasuk kardus & lakban.</small>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fs-7 fw-bold text-slate-800">
                            Dimensi Paket (P x L x T cm) <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="dimensions_cm" 
                               value="40 x 30 x 25 cm" 
                               required 
                               class="form-control form-control-sm font-monospace">
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-4 py-3 bg-light border-top d-flex align-items-center justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="packModal = false">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold shadow-xs d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check2-circle"></i>
                        <span>Selesaikan Packing (Ready to Ship)</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
