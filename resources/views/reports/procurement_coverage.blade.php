@extends('layouts.app')
@section('title', 'Matriks Keterlacakan Pengadaan')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none text-danger">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Keterlacakan Pengadaan</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. PO</th>
                        <th class="py-3 px-4">Vendor</th>
                        <th class="py-3 px-4">Referensi PR Sumber</th>
                        <th class="py-3 px-4">Item & SKU</th>
                        <th class="py-3 px-4 text-center">Dipesan (PO)</th>
                        <th class="py-3 px-4 text-center">Diterima (GRN)</th>
                        <th class="py-3 px-4 text-center">Outstanding</th>
                        <th class="py-3 px-4">Status PO</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($pos as $po)
                        @foreach($po->items as $it)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3.5 px-4 font-bold text-slate-900">{{ $po->po_number }}</td>
                                <td class="py-3.5 px-4">{{ $po->vendor->name }}</td>
                                <td class="py-3.5 px-4 font-mono font-bold text-indigo-700">
                                    {{ $it->purchaseRequestItem->purchaseRequest->pr_number ?? '-' }}
                                    <div class="text-[10px] text-slate-400 font-sans">{{ $it->purchaseRequestItem->purchaseRequest->organization->name ?? '' }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-800">{{ $it->item->name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ $it->item->sku }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-center font-bold text-slate-900">{{ $it->qty_ordered }}</td>
                                <td class="py-3.5 px-4 text-center font-bold text-emerald-600">{{ $it->qty_received }}</td>
                                <td class="py-3.5 px-4 text-center font-bold text-rose-600">{{ $it->outstanding_qty }}</td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded font-bold text-[10px] bg-slate-100 text-slate-700">{{ $po->status }}</span>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
