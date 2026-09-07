@extends('layouts.app')
@section('title', 'Penerimaan Barang Cabang')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik</li>
    <li class="breadcrumb-item active" aria-current="page">Penerimaan Cabang</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ route('receiving.discrepancies') }}" class="btn btn-sm btn-danger fw-bold shadow-xs">
            <i class="bi bi-exclamation-triangle me-1"></i> Daftar Discrepancy / Klaim
        </a>
    </div>

    <!-- Incoming In-Transit Shipments -->
    <div class="bg-sky-50/70 border border-sky-200 rounded-2xl p-5 space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2 text-sky-900 font-bold text-sm">
                <i class="fa-solid fa-truck-fast text-sky-600"></i>
                <span>Pengiriman Dalam Perjalanan (Menuju Cabang)</span>
            </div>
            <span class="text-xs bg-sky-600 text-white font-bold px-2.5 py-0.5 rounded-full">{{ $incomingShipments->count() }} Paket In-Transit</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($incomingShipments as $shp)
                <div class="bg-white p-4 rounded-xl border border-sky-200 shadow-sm flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm text-slate-900">{{ $shp->manifest_number }}</span>
                            <span class="bg-sky-100 text-sky-800 text-[10px] font-bold px-2 py-0.5 rounded">IN_TRANSIT</span>
                        </div>
                        <div class="text-xs font-semibold text-slate-700 mt-1">Tujuan: {{ $shp->order->requestingOrganization->name }}</div>
                        <div class="text-[11px] text-slate-400">Resi: {{ $shp->tracking_number }} ({{ $shp->courier->name ?? 'Kurir' }})</div>
                    </div>

                    <a href="{{ route('receiving.confirm.form', $shp->id) }}" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-lg text-xs shadow-sm transition inline-flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-box-check"></i>
                        <span>Konfirmasi Terima Barang di Cabang</span>
                    </a>
                </div>
            @empty
                <div class="col-span-2 py-4 text-center text-xs text-slate-400">Tidak ada pengiriman in-transit saat ini.</div>
            @endforelse
        </div>
    </div>

    <!-- Receivings History -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900">Histori Penerimaan Barang di Cabang</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Penerimaan</th>
                        <th class="py-3 px-4">No. Manifest</th>
                        <th class="py-3 px-4">Cabang Penerima</th>
                        <th class="py-3 px-4">Petugas Penerima</th>
                        <th class="py-3 px-4">Tanggal Terima</th>
                        <th class="py-3 px-4">Status Kondisi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($receivings as $rcv)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $rcv->receiving_number }}</td>
                            <td class="py-3.5 px-4 font-semibold text-slate-700">{{ $rcv->shipment->manifest_number }}</td>
                            <td class="py-3.5 px-4">{{ $rcv->order->requestingOrganization->name }}</td>
                            <td class="py-3.5 px-4">{{ $rcv->receiver->name }}</td>
                            <td class="py-3.5 px-4">{{ $rcv->receipt_date->format('d M Y') }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px]
                                    @if($rcv->status === 'RECEIVED_FULL') bg-emerald-100 text-emerald-800
                                    @elseif($rcv->status === 'DISCREPANCY') bg-rose-100 text-rose-800
                                    @else bg-amber-100 text-amber-800 @endif">
                                    {{ str_replace('_', ' ', $rcv->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-400">Belum ada histori penerimaan tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination-footer :paginator="$receivings" />
    </div>
</div>
@endsection
