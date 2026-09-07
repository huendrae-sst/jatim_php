@extends('layouts.app')
@section('title', 'Gudang: Picking List')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik</li>
    <li class="breadcrumb-item active" aria-current="page">Picking List</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ route('warehouse.packing.queue') }}" class="btn btn-sm btn-dark fw-bold shadow-xs">
            <i class="bi bi-box-seam me-1"></i> Buka Antrean Packing
        </a>
    </div>

    <!-- Orders Queue Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900">Order Menunggu Picking (Status: ALLOCATED)</h3>
            <span class="text-xs bg-indigo-50 text-indigo-700 font-bold px-2.5 py-0.5 rounded-full">{{ $allocatedOrders->count() }} Order</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Order</th>
                        <th class="py-3 px-4">Tujuan Unit</th>
                        <th class="py-3 px-4">Item yang Diambil</th>
                        <th class="py-3 px-4 text-center">Total Qty</th>
                        <th class="py-3 px-4">Prioritas</th>
                        <th class="py-3 px-4 text-center">Aksi Gudang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($allocatedOrders as $ord)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $ord->order_number }}</td>
                            <td class="py-3.5 px-4 font-semibold text-slate-800">{{ $ord->requestingOrganization->name }}</td>
                            <td class="py-3.5 px-4">
                                <ul class="list-disc list-inside text-slate-600 space-y-0.5">
                                    @foreach($ord->items as $it)
                                        <li>{{ $it->item->name }} (<strong class="text-indigo-700">{{ $it->qty_allocated }} {{ $it->item->uom }}</strong>)</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-900">{{ $ord->items->sum('qty_allocated') }}</td>
                            <td class="py-3.5 px-4">
                                <span class="bg-slate-100 text-slate-700 px-2 py-0.5 rounded font-bold text-[10px]">{{ $ord->priority }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <form action="{{ route('warehouse.picking.process', $ord->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs shadow-sm transition inline-flex items-center space-x-1">
                                        <i class="fa-solid fa-check"></i>
                                        <span>Konfirmasi Picking Selesai</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-400">Tidak ada order yang menunggu picking.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
