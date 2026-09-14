@extends('layouts.app')
@section('title', 'Detail Pemusnahan ' . $destruction->destruction_number)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('destructions.index') }}" class="text-decoration-none text-danger">Pemusnahan Barang</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $destruction->destruction_number }}</li>
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
                    <h5 class="card-title fw-bold mb-0 font-monospace text-danger">{{ $destruction->destruction_number }}</h5>
                    {!! $destruction->status_badge !!}
                </div>
                <p class="text-secondary fs-8 mb-0 mt-1">
                    No. Berita Acara: <strong class="font-monospace text-dark">{{ $destruction->berita_acara_number }}</strong> |
                    Diajukan pada {{ $destruction->created_at->translatedFormat('d F Y H:i') }} oleh {{ $destruction->requester?->name }}
                </p>
            </div>
            <div class="d-flex gap-2">
                @if($destruction->status === 'REQUESTED')
                    <button type="button" class="btn btn-success btn-sm px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#approveModal">
                        <i class="bi bi-check-circle me-1"></i> Otorisasi Persetujuan Pemusnahan
                    </button>
                @elseif($destruction->status === 'APPROVED')
                    <button type="button" class="btn btn-dark btn-sm px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#executeModal">
                        <i class="bi bi-fire me-1"></i> Eksekusi Pemusnahan Fisik (Mutasi Stok)
                    </button>
                @endif
                <a href="{{ route('destructions.berita_acara', $destruction->id) }}" target="_blank" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Cetak Berita Acara Resmi
                </a>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Lokasi Gudang</span>
                    <span class="fw-bold text-dark fs-7">{{ $destruction->warehouse?->name }}</span>
                    <span class="text-muted fs-8 d-block">{{ $destruction->warehouse?->organization?->name }}</span>
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Alasan Pemusnahan</span>
                    <span class="badge bg-secondary bg-opacity-10 text-dark border fs-8 mt-1">
                        {{ str_replace('_', ' ', $destruction->reason) }}
                    </span>
                    @if($destruction->reason_details)
                        <p class="text-secondary fs-8 mt-1 mb-0">{{ $destruction->reason_details }}</p>
                    @endif
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Saksi-Saksi Berita Acara</span>
                    <span class="fw-semibold text-dark fs-8 d-block">1. {{ $destruction->witness_name_1 }} ({{ $destruction->witness_title_1 }})</span>
                    <span class="fw-semibold text-dark fs-8 d-block">2. {{ $destruction->witness_name_2 }} ({{ $destruction->witness_title_2 }})</span>
                </div>
                <div class="col-12 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Status Eksekusi</span>
                    @if($destruction->status === 'EXECUTED')
                        <span class="text-success fw-bold fs-8 d-block"><i class="bi bi-check-all me-1"></i>Dimusnahkan pada {{ $destruction->executed_at?->format('d/m/Y H:i') }}</span>
                        <span class="text-muted fs-8">Eksekutor: {{ $destruction->executor?->name }}</span>
                    @else
                        <span class="text-warning fw-semibold fs-8 fst-italic">Belum dimusnahkan</span>
                    @endif
                </div>
            </div>

            <!-- Items Table -->
            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-box me-2"></i>Rincian Barang yang Dimusnahkan</h6>
            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary text-uppercase fs-9">
                        <tr>
                            <th class="ps-3">SKU Barang</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Batch / Serial</th>
                            <th class="text-center">Jumlah Fisik</th>
                            <th class="text-end">Harga Satuan</th>
                            <th class="text-end pe-3">Nilai Kerugian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($destruction->items as $item)
                            <tr>
                                <td class="ps-3 font-monospace fw-bold">{{ $item->item->sku }}</td>
                                <td class="fw-semibold">{{ $item->item->name }}</td>
                                <td>{{ $item->item->category?->name }}</td>
                                <td class="font-monospace text-muted">{{ $item->batch_or_serial_number ?: '-' }}</td>
                                <td class="text-center font-monospace fw-bold">{{ number_format($item->qty) }} {{ $item->item->uom }}</td>
                                <td class="text-end font-monospace">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="text-end pe-3 font-monospace text-danger fw-bold">Rp {{ number_format($item->total_loss_value, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="ps-3 text-uppercase">Total Pemusnahan</td>
                            <td class="text-center font-monospace">{{ number_format($destruction->total_qty) }}</td>
                            <td></td>
                            <td class="text-end pe-3 font-monospace text-danger fs-7">Rp {{ number_format($destruction->total_loss_value, 0, ',', '.') }}</td>
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
        <form action="{{ route('destructions.approve', $destruction->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Otorisasi Persetujuan Pemusnahan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fs-8 text-secondary mb-0">
                    Apakah Anda menyetujui pemusnahan resmi sebanyak <strong>{{ number_format($destruction->total_qty) }} barang</strong> dengan total estimasi kerugian <strong>Rp {{ number_format($destruction->total_loss_value, 0, ',', '.') }}</strong> di {{ $destruction->warehouse?->name }}?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success btn-sm">Setujui Pemusnahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Execute -->
<div class="modal fade" id="executeModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('destructions.execute', $destruction->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-fire me-1 text-danger"></i> Eksekusi Pemusnahan Fisik</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body space-y-3">
                <div class="alert alert-warning py-2 fs-8 border-0 mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i> Perhatian: Tindakan ini akan <strong>mengurangi saldo persediaan fisik secara permanen (DESTROYED)</strong> dan menerbitkan Berita Acara resmi.
                </div>
                <div>
                    <label class="form-label fs-8 fw-semibold">Metode / Catatan Pemusnahan (Opsional)</label>
                    <textarea name="execution_notes" class="form-control form-control-sm" rows="2" placeholder="Contoh: Dihancurkan menggunakan mesin pencacah shredder / dipotong chip EMV disaksikan saksi"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger btn-sm">Eksekusi Pemusnahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
