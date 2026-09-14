@extends('layouts.app')
@section('title', 'Reject Queue Berkas Emboss: ' . $file->file_id)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('emboss.index') }}" class="text-decoration-none text-danger">Integrasi Emboss</a></li>
    <li class="breadcrumb-item"><a href="{{ route('emboss.show', $file->id) }}" class="text-decoration-none text-danger">{{ $file->file_id }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Reject Queue</li>
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
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-xs" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-xs" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Card Reject Queue -->
    <div class="card card-outline card-danger shadow-xs">
        <div class="card-header border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 py-3 px-3 px-md-4">
            <div>
                <h3 class="card-title fs-6 fw-bold mb-0 text-body">
                    <i class="bi bi-exclamation-octagon text-danger me-1"></i> Antrean Reject & Koreksi Data
                </h3>
                <span class="fs-9 text-muted">Daftar record cacat pada berkas <strong>{{ $file->file_id }}</strong>. Anda dapat memperbaiki data spesifik dan memproses ulang (reprocess).</span>
            </div>
            <div>
                <a href="{{ route('emboss.show', $file->id) }}" class="btn btn-sm btn-outline-secondary fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail Berkas
                </a>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="card-body border-bottom bg-light py-2 px-3">
            <form action="{{ route('emboss.reject_queue', $file->id) }}" method="GET">
                <div class="input-group input-group-sm" style="max-width: 450px;">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Cari Reference ID, Kode Cabang, SKU, Pesan Error...">
                    <button type="submit" class="btn btn-primary">Cari</button>
                    @if($search)
                        <a href="{{ route('emboss.reject_queue', $file->id) }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Rejects Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0 fs-8">
                <thead class="table-light text-secondary fs-9 text-uppercase">
                    <tr>
                        <th class="ps-3 py-2">Reference ID</th>
                        <th class="py-2">Cabang</th>
                        <th class="py-2">Produk / SKU</th>
                        <th class="py-2">Tipe Kartu</th>
                        <th class="py-2">Nama Nasabah</th>
                        <th class="py-2 text-danger">Alasan Penolakan (Error Message)</th>
                        <th class="text-center py-2" style="width: 120px;">Aksi Koreksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rejects as $rej)
                        <tr>
                            <td class="ps-3 py-2 font-monospace fw-bold text-dark">
                                {{ $rej->external_reference_id }}
                            </td>
                            <td class="py-2">
                                <span class="badge bg-light text-dark border font-monospace">{{ $rej->branch_code }}</span>
                            </td>
                            <td class="py-2">
                                <span class="font-monospace fw-semibold">{{ $rej->product_code }}</span>
                            </td>
                            <td class="py-2">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border fs-9">{{ $rej->card_type }}</span>
                            </td>
                            <td class="py-2">
                                {{ $rej->cardholder_name ?: '-' }}
                            </td>
                            <td class="py-2 text-danger fw-semibold">
                                <i class="bi bi-exclamation-circle me-1"></i> {{ $rej->error_message }}
                            </td>
                            <td class="text-center py-2">
                                <button type="button" class="btn btn-sm btn-outline-primary py-0.5 px-2" data-bs-toggle="modal" data-bs-target="#editRecordModal{{ $rej->id }}">
                                    <i class="bi bi-pencil-square me-1"></i> Koreksi
                                </button>

                                <!-- Modal Edit & Reprocess -->
                                <div class="modal fade text-start" id="editRecordModal{{ $rej->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <form action="{{ route('emboss.records.reprocess', $rej->id) }}" method="POST" class="modal-content">
                                            @csrf
                                            <div class="modal-header bg-light py-2 px-3 border-bottom">
                                                <h5 class="modal-title fs-6 fw-bold">
                                                    Koreksi Record: {{ $rej->external_reference_id }}
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-3 space-y-3 fs-8">
                                                <div class="alert alert-danger py-1.5 px-2 mb-2 fs-9">
                                                    <strong>Penyebab Reject:</strong> {{ $rej->error_message }}
                                                </div>

                                                <!-- Pilih Kode Cabang Valid -->
                                                <div>
                                                    <label class="form-label fs-9 fw-bold text-secondary mb-1">Kode Cabang (Branch Code)</label>
                                                    <select name="branch_code" class="form-select form-select-sm" required>
                                                        @foreach($organizations as $org)
                                                            <option value="{{ $org->code }}" {{ $org->code === $rej->branch_code ? 'selected' : '' }}>
                                                                {{ $org->code }} - {{ $org->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <!-- Pilih SKU Valid -->
                                                <div>
                                                    <label class="form-label fs-9 fw-bold text-secondary mb-1">Produk / SKU Barang</label>
                                                    <select name="product_code" class="form-select form-select-sm" required>
                                                        @foreach($items as $it)
                                                            <option value="{{ $it->sku }}" {{ $it->sku === $rej->product_code ? 'selected' : '' }}>
                                                                {{ $it->sku }} - {{ $it->name }} (Rp {{ number_format($it->estimated_unit_price, 0, ',', '.') }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <!-- Nama Nasabah -->
                                                <div>
                                                    <label class="form-label fs-9 fw-bold text-secondary mb-1">Nama Pemegang Kartu</label>
                                                    <input type="text" name="cardholder_name" value="{{ $rej->cardholder_name }}" class="form-control form-control-sm">
                                                </div>

                                                <!-- Qty -->
                                                <div>
                                                    <label class="form-label fs-9 fw-bold text-secondary mb-1">Jumlah (Qty)</label>
                                                    <input type="number" name="qty" value="{{ $rej->qty }}" min="1" class="form-control form-control-sm" required>
                                                </div>

                                            </div>
                                            <div class="modal-footer bg-light py-2 px-3">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-sm btn-danger fw-bold">
                                                    <i class="bi bi-arrow-repeat me-1"></i> Simpan & Reprocess
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-success fw-semibold">
                                <i class="bi bi-check2-circle fs-3 d-block mb-1"></i>
                                Tidak ada record di dalam antrean reject. Semua record telah valid atau berhasil dikoreksi!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rejects->hasPages())
            <div class="card-footer bg-white border-top py-2 px-3">
                {{ $rejects->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
