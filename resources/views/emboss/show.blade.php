@extends('layouts.app')
@section('title', 'Detail Berkas Emboss: ' . $file->file_id)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('emboss.index') }}" class="text-decoration-none text-danger">Integrasi Emboss</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $file->file_id }}</li>
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

    <!-- Batch Overview Card -->
    <div class="card card-outline card-danger shadow-xs">
        <div class="card-header border-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 py-3 px-3 px-md-4">
            <div>
                <span class="badge bg-danger fs-9 mb-1">{{ $file->file_id }}</span>
                <h3 class="card-title fs-5 fw-bold mb-0 text-body">
                    {{ $file->file_name }}
                </h3>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($file->reject_records > 0)
                    <a href="{{ route('emboss.reject_queue', $file->id) }}" class="btn btn-sm btn-outline-danger fw-semibold">
                        <i class="bi bi-exclamation-octagon me-1"></i> Reject Queue ({{ $file->reject_records }})
                    </a>
                @endif
                <button type="button" class="btn btn-sm btn-danger fw-bold shadow-xs" data-bs-toggle="modal" data-bs-target="#generateOrderModal" {{ $file->validRecords()->count() === 0 ? 'disabled' : '' }}>
                    <i class="bi bi-cart-plus me-1"></i> Generate Order Persediaan
                </button>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Sistem Sumber</span>
                    <span class="badge bg-light text-dark border fs-8 mt-1">{{ str_replace('_', ' ', $file->source) }}</span>
                </div>
                <div class="col-6 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Durasi Pemrosesan</span>
                    <span class="fs-8 fw-bold text-dark mt-1 d-block font-monospace">
                        <i class="bi bi-stopwatch me-1"></i>{{ $file->duration_seconds ? $file->duration_seconds . ' detik' : '-' }}
                    </span>
                </div>
                <div class="col-6 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Diunggah Oleh</span>
                    <span class="fs-8 fw-semibold text-dark mt-1 d-block">{{ $file->uploader?->name ?? 'System' }}</span>
                </div>
                <div class="col-6 col-md-3">
                    <span class="fs-9 text-uppercase text-secondary fw-semibold d-block">Waktu Unggah</span>
                    <span class="fs-8 fw-semibold text-dark mt-1 d-block font-monospace">{{ $file->created_at->format('d M Y, H:i') }} WIB</span>
                </div>
            </div>

            <!-- Progress & Record Distribution Bar -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-1 fs-8">
                    <span class="fw-semibold text-secondary">
                        Tingkat Keberhasilan: <strong class="text-dark">{{ $file->success_rate }}%</strong>
                    </span>
                    <span class="font-monospace text-muted">
                        Total: {{ number_format($file->total_records) }} | Valid: {{ number_format($file->success_records) }} | Reject: {{ number_format($file->reject_records) }} | Duplikat: {{ number_format($file->duplicate_records) }}
                    </span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $file->total_records > 0 ? ($file->success_records / $file->total_records) * 100 : 0 }}%"></div>
                    <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $file->total_records > 0 ? ($file->duplicate_records / $file->total_records) * 100 : 0 }}%"></div>
                    <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $file->total_records > 0 ? ($file->reject_records / $file->total_records) * 100 : 0 }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs border-bottom fs-8 fw-semibold" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $tab === 'records' ? 'active text-danger border-bottom-0' : 'text-secondary' }}" href="{{ route('emboss.show', ['id' => $file->id, 'tab' => 'records']) }}">
                <i class="bi bi-table me-1"></i> Data Mapping & Record Explorer
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $tab === 'grouping' ? 'active text-danger border-bottom-0' : 'text-secondary' }}" href="{{ route('emboss.show', ['id' => $file->id, 'tab' => 'grouping']) }}">
                <i class="bi bi-collection me-1"></i> Ringkasan Grouping Order Persediaan
            </a>
        </li>
    </ul>

    <!-- Tab 1: Record Explorer -->
    @if($tab === 'records')
        <div class="card border-top-0 rounded-top-0 shadow-xs">
            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="fs-8 fw-bold text-secondary text-uppercase">Tabel Record Data Personalisasi</span>
                <form action="{{ route('emboss.show', $file->id) }}" method="GET" class="d-flex gap-2">
                    <input type="hidden" name="tab" value="records">
                    <select name="record_status" class="form-select form-select-sm fs-9" onchange="this.form.submit()">
                        <option value="ALL">-- Semua Status Record --</option>
                        <option value="VALID" {{ $filterStatus === 'VALID' ? 'selected' : '' }}>VALID</option>
                        <option value="PROCESSED_TO_ORDER" {{ $filterStatus === 'PROCESSED_TO_ORDER' ? 'selected' : '' }}>PROCESSED TO ORDER</option>
                        <option value="INVALID" {{ $filterStatus === 'INVALID' ? 'selected' : '' }}>INVALID (Reject)</option>
                        <option value="DUPLICATE" {{ $filterStatus === 'DUPLICATE' ? 'selected' : '' }}>DUPLICATE</option>
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary fs-9 text-uppercase">
                        <tr>
                            <th class="ps-3 py-2">Reference ID</th>
                            <th class="py-2">Cabang</th>
                            <th class="py-2">Produk / SKU</th>
                            <th class="py-2">Tipe</th>
                            <th class="py-2">Nama Nasabah (Masked)</th>
                            <th class="py-2">Nomor Kartu / PAN</th>
                            <th class="text-end py-2">Harga</th>
                            <th class="text-center py-2">Status</th>
                            <th class="text-center py-2">Order Terkait</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $rec)
                            <tr>
                                <td class="ps-3 py-2 font-monospace fw-bold text-dark">
                                    {{ $rec->external_reference_id }}
                                </td>
                                <td class="py-2">
                                    <span class="badge bg-light text-dark border font-monospace">{{ $rec->branch_code }}</span>
                                    <span class="fs-9 text-muted d-block">{{ $rec->organization?->name ?? 'Cabang Tidak Ditemukan' }}</span>
                                </td>
                                <td class="py-2">
                                    <span class="fw-semibold font-monospace">{{ $rec->product_code }}</span>
                                    <span class="fs-9 text-muted d-block">{{ $rec->item?->name ?? 'SKU Belum Terdaftar' }}</span>
                                </td>
                                <td class="py-2">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border fs-9">{{ $rec->card_type }}</span>
                                </td>
                                <td class="py-2 text-dark font-monospace">
                                    {{ $rec->masked_cardholder_name }}
                                </td>
                                <td class="py-2 text-dark font-monospace fw-bold">
                                    <i class="bi bi-shield-lock text-success me-1"></i>{{ $rec->masked_card_number }}
                                </td>
                                <td class="text-end py-2 font-monospace fw-semibold">
                                    Rp {{ number_format($rec->unit_price, 0, ',', '.') }}
                                </td>
                                <td class="text-center py-2">
                                    @if($rec->status === 'VALID')
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">VALID</span>
                                    @elseif($rec->status === 'PROCESSED_TO_ORDER')
                                        <span class="badge bg-primary">ORDER DIBUAT</span>
                                    @elseif($rec->status === 'DUPLICATE')
                                        <span class="badge bg-warning text-dark border border-warning" title="{{ $rec->error_message }}">DUPLIKAT</span>
                                    @else
                                        <span class="badge bg-danger" title="{{ $rec->error_message }}">REJECT</span>
                                    @endif
                                </td>
                                <td class="text-center py-2">
                                    @if($rec->order)
                                        <a href="{{ route('orders.show', $rec->order_id) }}" class="badge bg-info text-dark text-decoration-none font-monospace">
                                            {{ $rec->order->order_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    Tidak ada data record yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($records->hasPages())
                <div class="card-footer bg-white border-top py-2 px-3">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- Tab 2: Grouping Order Summary -->
    @if($tab === 'grouping')
        <div class="card border-top-0 rounded-top-0 shadow-xs">
            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="fs-8 fw-bold text-secondary text-uppercase">Pengelompokan Otomatis Order Persediaan</span>
                <span class="fs-9 text-muted">Dikelompokkan berdasarkan Unit Kerja Cabang & Jenis Kartu</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light text-secondary fs-9 text-uppercase">
                        <tr>
                            <th class="ps-3 py-2">Cabang Tujuan</th>
                            <th class="py-2">Tipe Kartu</th>
                            <th class="py-2">SKU Barang Master</th>
                            <th class="text-center py-2">Jumlah Kartu (Qty)</th>
                            <th class="text-end py-2">Estimasi Nilai Beban</th>
                            <th class="text-center py-2">Status Konversi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupingSummary as $g)
                            <tr>
                                <td class="ps-3 py-2">
                                    <span class="fw-bold text-dark font-monospace">{{ $g->organization?->code ?? '-' }}</span>
                                    <span class="fs-9 text-muted d-block">{{ $g->organization?->name ?? 'Cabang' }}</span>
                                </td>
                                <td class="py-2">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border">{{ $g->card_type }}</span>
                                </td>
                                <td class="py-2">
                                    <span class="font-monospace fw-semibold">{{ $g->product_code }}</span>
                                    <span class="fs-9 text-muted d-block">{{ $g->item?->name ?? '-' }}</span>
                                </td>
                                <td class="text-center py-2 font-monospace fw-bold fs-7">
                                    {{ number_format($g->card_count) }} PCS
                                </td>
                                <td class="text-end py-2 font-monospace fw-bold text-dark">
                                    Rp {{ number_format($g->estimated_val, 0, ',', '.') }}
                                </td>
                                <td class="text-center py-2">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">SIAP GENERATE</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Belum ada data valid untuk dikelompokkan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>

<!-- Modal Generate Orders -->
<div class="modal fade" id="generateOrderModal" tabindex="-1" aria-labelledby="generateOrderModalLabel" aria-hidden="true" x-data="{ deliveryMethod: 'COURIER' }">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('emboss.generate_orders', $file->id) }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header bg-danger text-white py-2 px-3">
                <h5 class="modal-title fs-6 fw-bold" id="generateOrderModalLabel">
                    <i class="bi bi-cart-check me-1"></i> Generate Order Persediaan JIMS
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 space-y-3 fs-8">
                <p class="text-secondary mb-3">
                    Sistem akan membuat <strong>Order Persediaan</strong> otomatis untuk setiap cabang dari <strong>{{ number_format($file->validRecords()->count()) }} kartu valid</strong> yang ada di berkas ini.
                </p>

                <!-- Metode Distribusi -->
                <div>
                    <label class="form-label fs-8 fw-bold text-secondary text-uppercase mb-1">
                        Pilih Metode Distribusi / Pengiriman <span class="text-danger">*</span>
                    </label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delivery_method" id="methodCourier" value="COURIER" x-model="deliveryMethod" checked>
                            <label class="form-check-label fw-semibold" for="methodCourier">
                                <i class="bi bi-truck me-1"></i> Ekspedisi Kurir Reguler
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delivery_method" id="methodPickup" value="PICKUP_KP" x-model="deliveryMethod">
                            <label class="form-check-label fw-semibold text-danger" for="methodPickup">
                                <i class="bi bi-building me-1"></i> Ambil di Kantor Pusat (KP)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Input PIC Pengambil jika Ambil di KP -->
                <div x-show="deliveryMethod === 'PICKUP_KP'" x-transition class="border rounded p-3 bg-light space-y-2 mt-2">
                    <span class="fs-9 text-uppercase text-danger fw-bold d-block">
                        <i class="bi bi-person-badge me-1"></i> Data PIC Pengambil di Kantor Pusat (Wajib)
                    </span>
                    <div>
                        <label class="form-label fs-9 fw-semibold text-secondary mb-0">NIP Pegawai Pengambil <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_nip" class="form-control form-control-sm" placeholder="Contoh: 198501102010121001" :required="deliveryMethod === 'PICKUP_KP'">
                    </div>
                    <div>
                        <label class="form-label fs-9 fw-semibold text-secondary mb-0">Nama Lengkap PIC Pengambil <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_name" class="form-control form-control-sm" placeholder="Contoh: Agus Setiawan" :required="deliveryMethod === 'PICKUP_KP'">
                    </div>
                    <div>
                        <label class="form-label fs-9 fw-semibold text-secondary mb-0">Jabatan / Unit Kerja <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_position" class="form-control form-control-sm" placeholder="Contoh: Staff Operasional Cabang Surabaya" :required="deliveryMethod === 'PICKUP_KP'">
                    </div>
                    <div>
                        <label class="form-label fs-9 fw-semibold text-secondary mb-0">Catatan Serah Terima</label>
                        <textarea name="pickup_notes" rows="2" class="form-control form-control-sm" placeholder="Keterangan surat tugas / serah terima langsung"></textarea>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light py-2 px-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-danger fw-bold">
                    <i class="bi bi-check2-circle me-1"></i> Konfirmasi & Buat Order
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
