@extends('layouts.app')
@section('title', 'Gudang: Packing List')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik</li>
    <li class="breadcrumb-item active" aria-current="page">Packing List</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ packModal: false, selectedOrder: null }">
    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ route('distribution.shipments.index') }}" class="btn btn-sm btn-dark fw-bold shadow-xs">
            <i class="bi bi-truck me-1"></i> Buka Modul Distribusi
        </a>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900">Order Menunggu Packing (Status: PICKING)</h3>
            <span class="text-xs bg-purple-50 text-purple-700 font-bold px-2.5 py-0.5 rounded-full">{{ $pickingOrders->count() }} Order</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Order</th>
                        <th class="py-3 px-4">Tujuan Unit</th>
                        <th class="py-3 px-4">Item Fisik Diambil</th>
                        <th class="py-3 px-4 text-center">Total Qty</th>
                        <th class="py-3 px-4 text-center">Aksi Pengepakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pickingOrders as $ord)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $ord->order_number }}</td>
                            <td class="py-3.5 px-4 font-semibold text-slate-800">{{ $ord->requestingOrganization->name }}</td>
                            <td class="py-3.5 px-4">
                                <ul class="list-disc list-inside text-slate-600 space-y-0.5">
                                    @foreach($ord->items as $it)
                                        <li>{{ $it->item->name }} (<strong>{{ $it->qty_picked }} {{ $it->item->uom }}</strong>)</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-900">{{ $ord->items->sum('qty_picked') }}</td>
                            <td class="py-3.5 px-4 text-center">
                                <button @click="selectedOrder = {{ Js::from($ord) }}; packModal = true" class="bg-purple-600 hover:bg-purple-700 text-white font-bold px-3.5 py-1.5 rounded-lg text-xs shadow-sm transition inline-flex items-center space-x-1">
                                    <i class="fa-solid fa-box-open"></i>
                                    <span>Input Koli & Selesaikan Packing</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-400">Tidak ada order yang menunggu pengepakan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Packing Modal -->
    <div x-show="packModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="packModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4 pe-5">
                <h3 class="font-bold text-base text-slate-900">Form Pengepakan Barang (Packing List)</h3>
                <button type="button" @click="packModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form :action="'/warehouse/packing/' + selectedOrder?.id + '/process'" method="POST" class="space-y-4">
                @csrf
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                    <div>No. Order: <strong class="text-slate-900" x-text="selectedOrder?.order_number"></strong></div>
                    <div>Tujuan: <span class="text-slate-700" x-text="selectedOrder?.requesting_organization?.name"></span></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Jumlah Koli (Box/Karton)</label>
                        <input type="number" name="koli_count" value="1" min="1" required class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-center">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Total Berat Kotor (Kg)</label>
                        <input type="number" step="0.1" name="total_weight_kg" value="5.0" min="0.1" required class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold text-center">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Dimensi Paket (P x L x T cm)</label>
                    <input type="text" name="dimensions_cm" value="40 x 30 x 25 cm" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-xs">
                </div>

                <div class="pt-2 flex justify-end space-x-3">
                    <button type="button" @click="packModal = false" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold shadow-md">
                        Selesaikan Packing (Ready to Ship)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
