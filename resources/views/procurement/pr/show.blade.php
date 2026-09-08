@extends('layouts.app')
@section('title', 'Detail PR: ' . $pr->pr_number)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('procurement.pr.index') }}" class="text-decoration-none text-danger">Purchase Request</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $pr->pr_number }}</li>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-4" x-data="{ rejectModal: false }">
    <!-- Status & Action Toolbar -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 bg-white p-3 rounded-2xl border border-slate-200 shadow-xs">
        <div class="d-flex align-items-center gap-2">
            <span class="text-xs font-semibold text-slate-500">Status Dokumen:</span>
            <span class="px-2.5 py-1 rounded-full font-bold text-xs
                @if($pr->status === 'APPROVED') bg-emerald-100 text-emerald-800
                @elseif($pr->status === 'FULLY_ORDERED') bg-purple-100 text-purple-800
                @elseif($pr->status === 'PARTIALLY_ORDERED') bg-indigo-100 text-indigo-800
                @elseif($pr->status === 'SUBMITTED' || $pr->status === 'WAITING_APPROVAL') bg-amber-100 text-amber-800
                @elseif($pr->status === 'REJECTED') bg-rose-100 text-rose-800
                @else bg-slate-100 text-slate-700 @endif">
                {{ str_replace('_', ' ', $pr->status) }}
            </span>
            <span class="text-xs text-slate-400 ms-2">Dibuat: {{ $pr->created_at->format('d M Y, H:i') }} WIB</span>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if(in_array($pr->status, ['SUBMITTED', 'WAITING_APPROVAL']))
                @if(auth()->user()->hasRole('SUPER_ADMIN', 'PROCUREMENT_APPROVER'))
                    <button type="button" @click="rejectModal = true" class="btn btn-sm btn-outline-danger fw-bold shadow-xs">
                        <i class="bi bi-x-circle me-1"></i> Tolak PR
                    </button>
                    <form action="{{ route('procurement.pr.approve', $pr->id) }}" method="POST" class="m-0"
                          onsubmit="return confirm('Apakah Anda yakin ingin menyetujui Purchase Request ini?')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success fw-bold shadow-xs">
                            <i class="bi bi-check2-all me-1"></i> Setujui PR (Approve)
                        </button>
                    </form>
                @else
                    <div class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg font-medium">
                        Menunggu persetujuan oleh Procurement Approver.
                    </div>
                @endif
            @elseif($pr->status === 'APPROVED')
                <a href="{{ route('procurement.consolidation.index') }}" class="btn btn-sm btn-primary fw-bold shadow-xs">
                    <i class="bi bi-layers me-1"></i> Masuk ke Konsolidasi PO
                </a>
            @endif
            <a href="{{ route('procurement.pr.print', $pr->id) }}" target="_blank" class="btn btn-sm btn-outline-danger fw-bold shadow-xs">
                <i class="bi bi-printer me-1"></i> Cetak PR
            </a>
            <a href="{{ route('procurement.approvals.pr') }}" class="btn btn-sm btn-light border fw-bold text-slate-600">
                <i class="bi bi-arrow-left me-1"></i> Antrean Persetujuan
            </a>
        </div>
    </div>

    <!-- Metadata Card -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Unit Pengaju</div>
            <div class="text-xs font-bold text-slate-900 mt-1">{{ $pr->organization->name }}</div>
            <div class="text-[11px] text-slate-500">Cost Center: {{ $pr->organization->cost_center_code ?? '-' }}</div>
        </div>

        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Maker & Approver</div>
            <div class="text-xs font-bold text-slate-900 mt-1">Maker: {{ $pr->requester->name }}</div>
            <div class="text-[11px] text-slate-500">Approver: {{ $pr->approver->name ?? 'Belum disetujui' }}</div>
        </div>

        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status Validasi Anggaran</div>
            <div class="text-xs font-bold text-emerald-600 mt-1 flex items-center space-x-1.5">
                <i class="fa-solid fa-circle-check"></i>
                <span>Tervalidasi (Pagu Mencukupi)</span>
            </div>
            <div class="text-[11px] text-slate-500">Metode: {{ $pr->procurement_method }}</div>
        </div>
    </div>

    <!-- Purpose Box -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Tujuan Pengadaan</h3>
        <p class="text-xs text-slate-700 leading-relaxed">{{ $pr->purpose }}</p>
    </div>

    <!-- Items Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <h3 class="font-bold text-sm text-slate-900">Rincian Item Purchase Request</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Item SKU & Nama</th>
                        <th class="py-3 px-4 text-center">Diajukan</th>
                        <th class="py-3 px-4 text-center">Disetujui</th>
                        <th class="py-3 px-4 text-center">Dikonsolidasi (PO)</th>
                        <th class="py-3 px-4 text-center">Sisa Order</th>
                        <th class="py-3 px-4 text-right">Est. Harga Satuan</th>
                        <th class="py-3 px-4 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($pr->items as $it)
                        <tr>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900">{{ $it->item->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $it->item->sku }} • Satuan: {{ $it->item->uom }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-700">{{ $it->qty_requested }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600">{{ $it->qty_approved }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-indigo-600">{{ $it->qty_ordered }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-rose-600">{{ $it->remaining_qty_to_order }}</td>
                            <td class="py-3.5 px-4 text-right text-slate-600">Rp {{ number_format($it->estimated_unit_price, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-bold text-slate-900">Rp {{ number_format($it->estimated_subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-slate-50 p-4 flex items-center justify-between border-t border-slate-200">
            <span class="text-xs font-bold text-slate-500 uppercase">Total Nilai PR</span>
            <span class="text-lg font-black text-jatim-700">Rp {{ number_format($pr->estimated_total_cost, 0, ',', '.') }}</span>
        </div>
    </div>

    @if($pr->status === 'REJECTED' && $pr->rejection_reason)
        <div class="alert alert-danger p-3 rounded-2xl border border-danger-subtle d-flex align-items-start gap-2 shadow-xs">
            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger flex-shrink-0"></i>
            <div>
                <strong class="d-block text-danger">Purchase Request Ditolak</strong>
                <span class="text-body fs-8">Alasan Penolakan: {{ $pr->rejection_reason }}</span>
            </div>
        </div>
    @endif

    <!-- Reject Modal -->
    <div class="modal fade show" 
         x-show="rejectModal" 
         x-cloak 
         tabindex="-1" 
         style="display: block; background: rgba(0,0,0,0.5); z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('procurement.pr.reject', $pr->id) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-danger text-white py-2.5 px-3">
                        <h6 class="modal-title fw-bold">
                            Tolak Purchase Request
                        </h6>
                        <button type="button" class="btn-close btn-close-white" @click="rejectModal = false"></button>
                    </div>
                    <div class="modal-body p-3 fs-8">
                        <p class="text-secondary mb-2">
                            Anda akan menolak pengajuan Purchase Request <strong class="text-danger font-monospace">{{ $pr->pr_number }}</strong>.
                        </p>
                        <div class="mb-2">
                            <label class="form-label fw-bold text-body fs-8">
                                Alasan Penolakan <span class="text-danger">*</span>:
                            </label>
                            <textarea name="rejection_reason" 
                                      class="form-control form-control-sm fs-8" 
                                      rows="3" 
                                      placeholder="Tuliskan catatan atau alasan penolakan secara jelas..."
                                      required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2 px-3">
                        <button type="button" class="btn btn-secondary btn-sm" @click="rejectModal = false">Batal</button>
                        <button type="submit" class="btn btn-danger btn-sm fw-bold">
                            <i class="bi bi-x-octagon me-1"></i> Konfirmasi Tolak PR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
