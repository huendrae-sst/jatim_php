@extends('layouts.app')
@section('title', 'Purchase Orders (PO)')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Pengadaan</li>
    <li class="breadcrumb-item active" aria-current="page">Purchase Orders</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ route('procurement.consolidation.index') }}" class="btn btn-sm btn-primary fw-bold shadow-xs">
            <i class="bi bi-layers me-1"></i> Konsolidasikan PR Baru
        </a>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. PO</th>
                        <th class="py-3 px-4">Vendor</th>
                        <th class="py-3 px-4">Tanggal Terbit</th>
                        <th class="py-3 px-4">Target Kirim</th>
                        <th class="py-3 px-4 text-right">Subtotal</th>
                        <th class="py-3 px-4 text-right">Total (+PPN)</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pos as $po)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $po->po_number }}</td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-800">{{ $po->vendor->name }}</div>
                                <div class="text-[10px] text-slate-400">Gudang: {{ $po->warehouse->name }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">{{ $po->order_date->format('d M Y') }}</td>
                            <td class="py-3.5 px-4 text-slate-600">{{ $po->expected_delivery_date ? $po->expected_delivery_date->format('d M Y') : '-' }}</td>
                            <td class="py-3.5 px-4 text-right text-slate-600">Rp {{ number_format($po->subtotal, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-bold text-slate-900">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px]
                                    @if($po->status === 'COMPLETED') bg-emerald-100 text-emerald-800
                                    @elseif($po->status === 'PARTIAL_RECEIVED') bg-amber-100 text-amber-800
                                    @elseif($po->status === 'ISSUED') bg-sky-100 text-sky-800
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ str_replace('_', ' ', $po->status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <a href="{{ route('procurement.po.show', $po->id) }}" class="bg-slate-100 hover:bg-jatim-700 hover:text-white px-3 py-1 rounded-lg text-xs font-bold transition">
                                    Detail & GRN
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-400">Belum ada Purchase Order.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination-footer :paginator="$pos" />
    </div>
</div>
@endsection
