@extends('layouts.app')
@section('title', 'Detail Retur ' . $return->return_number)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('returns.index') }}" class="text-decoration-none text-danger">Retur Barang</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $return->return_number }}</li>
@endsection

@section('content')
<div class="space-y-4">

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

    <div class="card shadow-xs border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="card-title fw-bold mb-0 font-monospace text-danger">{{ $return->return_number }}</h5>
                    {!! $return->status_badge !!}
                </div>
                <p class="text-secondary fs-8 mb-0 mt-1">Diajukan pada {{ $return->created_at->translatedFormat('d F Y H:i') }} oleh {{ $return->requester?->name }}</p>
            </div>
            <div class="d-flex gap-2">
                @if($return->status === 'REQUESTED')
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-circle me-1"></i> Tolak
                    </button>
                    <button type="button" class="btn btn-success btn-sm px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveModal">
                        <i class="bi bi-check-circle me-1"></i> Setujui Retur (Otorisasi)
                    </button>
                @elseif($return->status === 'APPROVED')
                    <button type="button" class="btn btn-primary btn-sm px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#shipModal">
                        <i class="bi bi-truck me-1"></i> Kirim Barang Retur (Input Resi)
                    </button>
                @elseif($return->status === 'SHIPPED')
                    <button type="button" class="btn btn-success btn-sm px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#receiveModal">
                        <i class="bi bi-box-seam me-1"></i> Konfirmasi Terima Fisik di Pusat
                    </button>
                @endif
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Gudang Asal (Cabang)</span>
                    <span class="fw-bold text-dark fs-7">{{ $return->originWarehouse?->name }}</span>
                    <span class="text-muted fs-8 d-block">{{ $return->originWarehouse?->organization?->name }}</span>
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Gudang Tujuan (Pusat)</span>
                    <span class="fw-bold text-dark fs-7">{{ $return->destinationWarehouse?->name }}</span>
                    <span class="text-muted fs-8 d-block">{{ $return->destinationWarehouse?->type }}</span>
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Alasan Retur</span>
                    <span class="badge bg-secondary bg-opacity-10 text-dark border fs-8 mt-1">
                        {{ str_replace('_', ' ', $return->reason) }}
                    </span>
                    @if($return->reason_details)
                        <p class="text-secondary fs-8 mt-1 mb-0">{{ $return->reason_details }}</p>
                    @endif
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Informasi Pengiriman</span>
                    @if($return->tracking_number)
                        <span class="fw-semibold text-primary fs-8 d-block"><i class="bi bi-upc-scan me-1"></i>Resi: {{ $return->tracking_number }}</span>
                        <span class="text-muted fs-8">Kurir: {{ $return->courier_name }} ({{ $return->shipped_at?->format('d/m/Y H:i') }})</span>
                    @else
                        <span class="text-muted fs-8 fst-italic">Belum dikirim</span>
                    @endif
                </div>
            </div>

            <!-- Items Table -->
            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-box me-2"></i>Rincian Barang yang Diretur</h6>
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9">
                        <tr>
                            <th class="ps-3">SKU Barang</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Kondisi Fisik</th>
                            <th class="text-center">Qty Retur</th>
                            <th class="text-center">Qty Diterima (Bagus)</th>
                            <th class="text-center">Qty Diterima (Rusak)</th>
                            <th class="text-end pe-3">Estimasi Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($return->items as $item)
                            <tr>
                                <td class="ps-3 font-monospace fw-bold">{{ $item->item->sku }}</td>
                                <td class="fw-semibold">{{ $item->item->name }}</td>
                                <td>{{ $item->item->category?->name }}</td>
                                <td>
                                    <span class="badge {{ $item->condition === 'GOOD' ? 'bg-success' : 'bg-danger' }} bg-opacity-10 text-{{ $item->condition === 'GOOD' ? 'success' : 'danger' }} border">
                                        {{ $item->condition }}
                                    </span>
                                </td>
                                <td class="text-center font-monospace fw-bold">{{ number_format($item->qty_returned) }} {{ $item->item->uom }}</td>
                                <td class="text-center font-monospace text-success fw-bold">{{ number_format($item->qty_received_good) }}</td>
                                <td class="text-center font-monospace text-danger fw-bold">{{ number_format($item->qty_received_damaged) }}</td>
                                <td class="text-end pe-3 font-monospace">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="ps-3 text-uppercase">Total</td>
                            <td class="text-center font-monospace">{{ number_format($return->total_qty) }}</td>
                            <td colspan="2"></td>
                            <td class="text-end pe-3 font-monospace text-danger">Rp {{ number_format($return->total_value, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Approve -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('returns.approve', $return->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Otorisasi Persetujuan Retur</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fs-8 text-secondary mb-3">Apakah Anda yakin menyetujui permohonan retur barang dari <strong>{{ $return->originWarehouse?->name }}</strong>? Cabang akan diinstruksikan untuk mengirimkan fisik barang ke gudang pusat.</p>
                <div class="mb-3">
                    <label class="form-label fs-8 fw-semibold">Catatan Otorisasi (Opsional)</label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Catatan persetujuan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success btn-sm">Setujui Permohonan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reject -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('returns.reject', $return->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-danger">Tolak Pengajuan Retur</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fs-8 fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea name="rejection_reason" class="form-control form-control-sm" rows="3" required placeholder="Tuliskan alasan penolakan pengajuan retur ini..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger btn-sm">Tolak Retur</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ship -->
<div class="modal fade" id="shipModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('returns.ship', $return->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-primary">Kirim Fisik Barang Retur</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body space-y-3">
                <div class="alert alert-info py-2 fs-8 border-0 mb-3">
                    <i class="bi bi-info-circle me-1"></i> Pengiriman akan secara otomatis memutasi keluar stok di cabang pemohon (<strong>RETURN_OUT</strong>).
                </div>
                <div class="mb-3">
                    <label class="form-label fs-8 fw-semibold">Nama Ekspedisi / Kurir <span class="text-danger">*</span></label>
                    <input type="text" name="courier_name" class="form-control form-control-sm" placeholder="Contoh: JNE Logistik / Internal Driver" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fs-8 fw-semibold">Nomor Resi / AWB / No. Polisi <span class="text-danger">*</span></label>
                    <input type="text" name="tracking_number" class="form-control form-control-sm font-monospace" placeholder="Contoh: RET-AWB-987654" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary btn-sm">Konfirmasi Kirim Retur</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Receive -->
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('returns.receive', $return->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-success">Konfirmasi Penerimaan Retur di Gudang Pusat</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body space-y-3">
                <div class="alert alert-success py-2 fs-8 border-0 mb-3">
                    <i class="bi bi-check-circle me-1"></i> Verifikasi fisik barang yang diterima di Gudang Pusat. Stok akan otomatis bertambah (<strong>RETURN_IN</strong>).
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle fs-8 mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Barang</th>
                                <th class="text-center">Qty Dikirim</th>
                                <th class="text-center" style="width: 25%">Qty Diterima Bagus</th>
                                <th class="text-center" style="width: 25%">Qty Diterima Rusak</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($return->items as $idx => $item)
                                <tr>
                                    <td>
                                        <input type="hidden" name="items[{{ $idx }}][return_item_id]" value="{{ $item->id }}">
                                        <strong>{{ $item->item->name }}</strong>
                                        <span class="text-muted d-block font-monospace fs-9">{{ $item->item->sku }}</span>
                                    </td>
                                    <td class="text-center font-monospace fw-bold">{{ $item->qty_returned }}</td>
                                    <td>
                                        <input type="number" name="items[{{ $idx }}][qty_good]" value="{{ $item->condition === 'GOOD' ? $item->qty_returned : 0 }}" class="form-control form-control-sm text-center font-monospace" min="0" max="{{ $item->qty_returned }}" required>
                                    </td>
                                    <td>
                                        <input type="number" name="items[{{ $idx }}][qty_damaged]" value="{{ $item->condition !== 'GOOD' ? $item->qty_returned : 0 }}" class="form-control form-control-sm text-center font-monospace" min="0" max="{{ $item->qty_returned }}" required>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success btn-sm">Simpan Penerimaan Retur</button>
            </div>
        </form>
    </div>
</div>
@endsection
