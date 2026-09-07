@extends('layouts.app')
@section('title', 'Distribusi & Ekspedisi')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item">Logistik</li>
    <li class="breadcrumb-item active" aria-current="page">Distribusi & Ekspedisi</li>
@endsection

@section('content')
<div class="space-y-4" x-data="{ dispatchModal: false, selectedOrder: null }">

    <!-- Ready to Ship Orders -->
    @if($readyOrders->count() > 0)
        <div class="bg-rose-50 border border-rose-200 rounded-2xl p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-jatim-700 font-bold text-sm">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    <span>Order Siap Diberangkatkan (Status: READY_TO_SHIP)</span>
                </div>
                <span class="text-xs bg-jatim-700 text-white font-bold px-2.5 py-0.5 rounded-full">{{ $readyOrders->count() }} Paket Siap Kirim</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($readyOrders as $ord)
                    <div class="bg-white p-4 rounded-xl border border-rose-200 shadow-sm flex flex-col justify-between space-y-3">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-sm text-slate-900">{{ $ord->order_number }}</span>
                                <span class="bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-0.5 rounded">{{ $ord->packings->last()->koli_count ?? 1 }} Koli ({{ $ord->packings->last()->total_weight_kg ?? 1 }} kg)</span>
                            </div>
                            <div class="text-xs font-semibold text-slate-700 mt-1">Tujuan: {{ $ord->requestingOrganization->name }} ({{ $ord->requestingOrganization->city }})</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">{{ $ord->requestingOrganization->address }}</div>
                        </div>

                        <button @click="selectedOrder = {{ Js::from($ord) }}; dispatchModal = true" class="w-full bg-jatim-700 hover:bg-jatim-800 text-white font-bold py-2 rounded-lg text-xs shadow-sm transition inline-flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-truck-fast"></i>
                            <span>Terbitkan Manifest & Kirim Ekspedisi</span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- All Shipments Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900">Daftar Manifest Pengiriman (Shipments)</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Manifest</th>
                        <th class="py-3 px-4">No. Order</th>
                        <th class="py-3 px-4">Tujuan Cabang</th>
                        <th class="py-3 px-4">Ekspedisi / Resi</th>
                        <th class="py-3 px-4 text-center">Koli & Berat</th>
                        <th class="py-3 px-4">Status Pengiriman</th>
                        <th class="py-3 px-4 text-center">Dokumen Cetak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($shipments as $shp)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $shp->manifest_number }}</td>
                            <td class="py-3.5 px-4 font-semibold text-slate-700">{{ $shp->order->order_number }}</td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $shp->order->requestingOrganization->name }}</div>
                                <div class="text-[10px] text-slate-400">{{ $shp->order->requestingOrganization->city }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-indigo-700">{{ $shp->courier->name ?? 'Kurir Internal' }}</div>
                                <div class="text-[10px] font-mono text-slate-500 font-semibold">Resi: {{ $shp->tracking_number }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-semibold text-slate-700">
                                {{ $shp->koli_count }} Koli ({{ $shp->total_weight_kg }} kg)
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px]
                                    @if($shp->status === 'DELIVERED') bg-emerald-100 text-emerald-800
                                    @elseif($shp->status === 'IN_TRANSIT') bg-sky-100 text-sky-800
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ str_replace('_', ' ', $shp->status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center space-x-2">
                                <a href="{{ route('distribution.manifest.print', $shp->id) }}" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded-lg text-xs font-bold transition">
                                    <i class="fa-solid fa-print mr-1"></i> Manifest
                                </a>
                                <a href="{{ route('distribution.label.print', $shp->id) }}" target="_blank" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-2.5 py-1 rounded-lg text-xs font-bold transition">
                                    <i class="fa-solid fa-qrcode mr-1"></i> Label QR
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-400">Belum ada pengiriman tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination-footer :paginator="$shipments" />
    </div>

    <!-- Dispatch Shipment Modal -->
    <div x-show="dispatchModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="dispatchModal = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4 pe-5">
                <h3 class="font-bold text-base text-slate-900">Penerbitan Manifest & Dispatch Ekspedisi</h3>
                <button type="button" @click="dispatchModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('distribution.shipments.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="order_id" :value="selectedOrder?.id">

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                    <div>No. Order: <strong class="text-slate-900" x-text="selectedOrder?.order_number"></strong></div>
                    <div>Tujuan: <span class="text-slate-700" x-text="selectedOrder?.requesting_organization?.name"></span></div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Pilih Jasa Ekspedisi / Kurir</label>
                    <select name="courier_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs">
                        @foreach($couriers as $cr)
                            <option value="{{ $cr->id }}">{{ $cr->name }} (SLA: {{ $cr->sla_days }} hari)</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Layanan Kurir</label>
                        <input type="text" name="service_type" value="REGULER" required class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nomor Resi / AWB</label>
                        <input type="text" name="tracking_number" value="BJ-EXP-{{ date('Ymd') }}-{{ rand(100, 999) }}" required class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-mono font-bold">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Biaya Ongkos Kirim (Rp)</label>
                        <input type="number" name="shipping_cost" value="125000" min="0" required class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Estimasi Tanggal Tiba (ETA)</label>
                        <input type="date" name="eta_date" value="{{ date('Y-m-d', strtotime('+2 days')) }}" required class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs">
                    </div>
                </div>

                <div class="pt-2 flex justify-end space-x-3">
                    <button type="button" @click="dispatchModal = false" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-jatim-700 hover:bg-jatim-800 text-white rounded-xl text-xs font-bold shadow-md">
                        Terbitkan Manifest & Dispatch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
