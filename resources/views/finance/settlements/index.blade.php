@extends('layouts.app')
@section('title', 'Inter-unit Financial Settlement')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Keuangan</li>
    <li class="breadcrumb-item active" aria-current="page">Settlement Antar-Unit</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Summary Metrics (AdminLTE 4 Info-Boxes) -->
    <div class="row g-3">
        <!-- 1. Perlu Settlement -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-info"><i class="bi bi-file-earmark-text"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Perlu Settlement</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $unsettledCount > 0 ? 'text-primary' : 'text-body-emphasis' }}">{{ number_format($unsettledCount) }} Order</span>
                    <span class="fs-9 text-secondary">Order diterima belum disettlement</span>
                </div>
            </div>
        </div>

        <!-- 2. Menunggu Approval -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Menunggu Approval</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace {{ $waitingApprovalCount > 0 ? 'text-warning-emphasis' : 'text-body-emphasis' }}">{{ number_format($waitingApprovalCount) }} Draft</span>
                    <span class="fs-9 text-secondary">Verifikasi & posting finance</span>
                </div>
            </div>
        </div>

        <!-- 3. Telah Diposting -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Telah Diposting</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-success">{{ number_format($postedCount) }} Jurnal</span>
                    <span class="fs-9 text-secondary">Realisasi beban anggaran selesai</span>
                </div>
            </div>
        </div>

        <!-- 4. Total Nilai Settlement -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="info-box shadow-xs mb-0 h-100 bg-body">
                <span class="info-box-icon text-bg-danger"><i class="bi bi-cash-stack"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text fs-8 text-secondary fw-bold text-uppercase">Total Nilai Settlement</span>
                    <span class="info-box-number fs-4 fw-bold font-monospace text-body-emphasis">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                    <span class="fs-9 text-secondary">Akumulasi settlement antar-unit</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Waiting for Settlement Generation -->
    @if($unsettledOrders->count() > 0)
        <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-amber-900 font-bold text-sm">
                    <i class="fa-solid fa-file-invoice-dollar text-amber-600"></i>
                    <span>Order Selesai Diterima (Menunggu Pembuatan Settlement)</span>
                </div>
                <span class="text-xs bg-amber-600 text-white font-bold px-2.5 py-0.5 rounded-full">{{ $unsettledOrders->count() }} Order</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($unsettledOrders as $ord)
                    <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm flex flex-col justify-between space-y-3">
                        <div>
                            <div class="font-bold text-sm text-slate-900">{{ $ord->order_number }}</div>
                            <div class="text-xs font-semibold text-slate-700 mt-0.5">Unit Pembebanan: {{ $ord->requestingOrganization->name }}</div>
                            <div class="text-[11px] text-slate-400">Total Nilai Barang: Rp {{ number_format($ord->total_estimated_value, 0, ',', '.') }}</div>
                        </div>

                        <form action="{{ route('finance.settlements.create', $ord->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 rounded-lg text-xs shadow-sm transition inline-flex items-center justify-center space-x-2">
                                <i class="fa-solid fa-calculator"></i>
                                <span>Hitung & Buat Draft Settlement</span>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Settlements Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900">Daftar Jurnal Settlement Antar-Unit</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Settlement</th>
                        <th class="py-3 px-4">No. Order</th>
                        <th class="py-3 px-4">Unit Debit (Peminta)</th>
                        <th class="py-3 px-4">Unit Kredit (Penyedia)</th>
                        <th class="py-3 px-4 text-right">Nilai Barang</th>
                        <th class="py-3 px-4 text-right">Ongkir</th>
                        <th class="py-3 px-4 text-right">Total Settlement</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($settlements as $set)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $set->settlement_number }}</td>
                            <td class="py-3.5 px-4 font-semibold text-slate-700">{{ $set->order->order_number }}</td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $set->debitOrganization->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $set->debit_cost_center }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $set->creditOrganization->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $set->credit_cost_center }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-right text-slate-700">Rp {{ number_format($set->item_amount, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right text-slate-700">Rp {{ number_format($set->shipping_amount, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-black text-jatim-700">Rp {{ number_format($set->total_amount, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px]
                                    @if($set->status === 'POSTED') bg-emerald-100 text-emerald-800
                                    @elseif($set->status === 'WAITING_APPROVAL') bg-amber-100 text-amber-800
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ str_replace('_', ' ', $set->status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($set->status === 'WAITING_APPROVAL')
                                    @if(auth()->user()->hasRole('SUPER_ADMIN', 'FINANCE_APPROVER'))
                                        <form action="{{ route('finance.settlements.approve', $set->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1 rounded-lg text-xs shadow-sm transition">
                                                Approve & Post
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[10px] text-slate-400">Waiting Approver</span>
                                    @endif
                                @else
                                    <span class="text-[10px] text-emerald-700 font-bold">Closed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-slate-400">Belum ada settlement tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination-footer :paginator="$settlements" />
    </div>
</div>
@endsection
