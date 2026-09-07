@extends('layouts.app')
@section('title', 'Laporan Discrepancy & Klaim')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('receiving.index') }}" class="text-decoration-none text-danger">Penerimaan Cabang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Discrepancy & Klaim</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ route('receiving.index') }}" class="btn btn-sm btn-light border fw-bold text-slate-700 shadow-xs">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Penerimaan
        </a>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Penerimaan</th>
                        <th class="py-3 px-4">Cabang</th>
                        <th class="py-3 px-4">Barang</th>
                        <th class="py-3 px-4">Jenis Selisih</th>
                        <th class="py-3 px-4 text-center">Dikirim</th>
                        <th class="py-3 px-4 text-center">Diterima Baik</th>
                        <th class="py-3 px-4 text-center">Rusak / Kurang</th>
                        <th class="py-3 px-4">Status Klaim</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($discrepancies as $disc)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $disc->receiving->receiving_number }}</td>
                            <td class="py-3.5 px-4 font-semibold text-slate-800">{{ $disc->receiving->order->requestingOrganization->name }}</td>
                            <td class="py-3.5 px-4">{{ $disc->item->name }}</td>
                            <td class="py-3.5 px-4">
                                <span class="bg-rose-100 text-rose-800 font-bold text-[10px] px-2 py-0.5 rounded">{{ $disc->discrepancy_type }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold">{{ $disc->qty_expected }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600">{{ $disc->qty_actual }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-rose-600">{{ $disc->qty_damaged }}</td>
                            <td class="py-3.5 px-4">
                                <span class="bg-amber-100 text-amber-800 font-bold text-[10px] px-2.5 py-0.5 rounded-full">{{ $disc->resolution_status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-400">Tidak ada laporan discrepancy tercatat. Semua penerimaan sesuai 100%.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination-footer :paginator="$discrepancies" />
    </div>
</div>
@endsection
