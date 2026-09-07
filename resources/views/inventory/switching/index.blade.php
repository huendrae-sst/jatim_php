@extends('layouts.app')
@section('title', 'Switching Stock Antar-Unit')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Switching Stock</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Workflow Banner (Section 10 of Specification) -->
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start space-x-3 text-amber-900">
        <i class="fa-solid fa-arrows-split-up-and-left text-amber-600 text-lg mt-0.5"></i>
        <div class="text-xs leading-relaxed">
            <strong>Alur Switching Stock Bank Jatim:</strong> Ketika stok gudang utama tidak mencukupi, sistem merekomendasikan cabang alternatif dengan stok di atas *safety stock*. Pengajuan proposal ini harus melalui approval pejabat unit sumber sebelum stok direservasi dan dikirim.
        </div>
    </div>

    <!-- Switching Stocks Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Order Tujuan</th>
                        <th class="py-3 px-4">Barang & SKU</th>
                        <th class="py-3 px-4">Unit Sumber (Penyedia)</th>
                        <th class="py-3 px-4">Unit Tujuan (Peminta)</th>
                        <th class="py-3 px-4 text-center">Jumlah (Qty)</th>
                        <th class="py-3 px-4">Status Switching</th>
                        <th class="py-3 px-4 text-center">Aksi Otorisasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($switchings as $sw)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                <a href="{{ route('orders.index') }}" class="text-jatim-700 hover:underline font-monospace">
                                    {{ $sw->order->order_number }}
                                </a>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900">{{ $sw->item->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $sw->item->sku }} • {{ $sw->item->uom }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-800">{{ $sw->sourceOrganization->name }}</div>
                                <div class="text-[10px] text-slate-400">Gudang: {{ $sw->sourceWarehouse->name }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-800">{{ $sw->destinationOrganization->name }}</div>
                                <div class="text-[10px] text-slate-400">Gudang: {{ $sw->destinationWarehouse->name }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-black text-indigo-700 text-sm">
                                {{ $sw->qty_requested }} {{ $sw->item->uom }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px]
                                    @if($sw->status === 'APPROVED' || $sw->status === 'COMPLETED') bg-emerald-100 text-emerald-800
                                    @elseif($sw->status === 'PROPOSED') bg-amber-100 text-amber-800
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ $sw->status }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($sw->status === 'PROPOSED')
                                    @if(auth()->user()->hasRole('SUPER_ADMIN', 'SWITCHING_APPROVER', 'ORDER_APPROVER'))
                                        <form action="{{ route('inventory.switching.approve', $sw->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1 rounded-lg text-xs shadow-sm transition inline-flex items-center space-x-1">
                                                <i class="fa-solid fa-check"></i>
                                                <span>Setujui Switching</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[10px] text-slate-400">Waiting Approver</span>
                                    @endif
                                @else
                                    <span class="text-[10px] text-emerald-700 font-bold">Approved & Reserved</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-400">Belum ada proposal switching stock aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination-footer :paginator="$switchings" />
    </div>
</div>
@endsection
