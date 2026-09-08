@extends('layouts.app')
@section('title', 'Laporan Discrepancy & Klaim')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Penerimaan & QC</li>
    <li class="breadcrumb-item"><a href="{{ route('receiving.index') }}" class="text-decoration-none text-danger">Penerimaan Cabang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Discrepancy & Klaim</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Main Card -->
    <div class="card card-outline card-danger shadow-xs mb-0">
        <div class="card-header p-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
                <h3 class="card-title fw-bold text-slate-800 fs-6 mb-0">
                    Daftar Berita Acara Selisih & Klaim (Discrepancy)
                    @if($discrepancies->total() > 0)
                        <span class="badge bg-danger rounded-pill ms-1">{{ $discrepancies->total() }}</span>
                    @endif
                </h3>
            </div>
            <div>
                <a href="{{ route('receiving.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-xs">
                    <i class="bi bi-arrow-left"></i>
                    <span>Kembali ke Penerimaan Cabang</span>
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0 fs-7">
                <thead class="table-light text-secondary text-uppercase fs-8 border-bottom">
                    <tr>
                        <th class="ps-3 py-2" style="width: 170px;">No. Penerimaan</th>
                        <th class="py-2" style="width: 200px;">Cabang Pemohon</th>
                        <th class="py-2">Item Barang</th>
                        <th class="py-2 text-center" style="width: 130px;">Jenis Selisih</th>
                        <th class="py-2 text-center" style="width: 90px;">Dikirim</th>
                        <th class="py-2 text-center" style="width: 110px;">Diterima Baik</th>
                        <th class="py-2 text-center" style="width: 120px;">Rusak / Kurang</th>
                        <th class="pe-3 py-2 text-center" style="width: 120px;">Status Klaim</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($discrepancies as $disc)
                        <tr>
                            <td class="ps-3">
                                <span class="font-monospace fw-bold text-danger d-block">{{ $disc->receiving->receiving_number }}</span>
                                <small class="text-muted fs-8">{{ $disc->created_at ? $disc->created_at->format('d M Y') : '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-slate-800 d-block">{{ $disc->receiving->order->requestingOrganization->name ?? '-' }}</span>
                                <small class="text-muted fs-8">{{ $disc->receiving->order->requestingOrganization->city ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-bold text-slate-800 d-block">{{ $disc->item->name }}</span>
                                <small class="text-muted font-monospace fs-8">{{ $disc->item->sku }}</small>
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-danger fs-8">
                                    {{ str_replace('_', ' ', $disc->discrepancy_type) }}
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
                            <td class="pe-3 text-center">
                                @php
                                    $resClass = match($disc->resolution_status) {
                                        'RESOLVED' => 'text-bg-success',
                                        'IN_REVIEW' => 'text-bg-info',
                                        default => 'text-bg-warning',
                                    };
                                @endphp
                                <span class="badge {{ $resClass }} fs-8">
                                    {{ str_replace('_', ' ', $disc->resolution_status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-check fs-1 text-success opacity-50 d-block mb-2"></i>
                                <span class="fw-semibold">Tidak ada laporan discrepancy / klaim selisih saat ini.</span>
                                <p class="fs-8 text-muted mb-0">Seluruh penerimaan barang di cabang telah terverifikasi lengkap dan sesuai 100%.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top">
            <x-pagination-footer :paginator="$discrepancies" />
        </div>
    </div>

</div>
@endsection
