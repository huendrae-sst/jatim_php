@extends('layouts.app')
@section('title', 'Laporan Rekapitulasi Settlement Antar-Unit')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none text-danger">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Rekap Settlement</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Summary Box -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <div class="text-xs uppercase font-bold text-slate-400">Total Settlement Berstatus POSTED:</div>
            <div class="text-2xl font-black text-emerald-700 mt-1">Rp {{ number_format($totalSettled, 0, ',', '.') }}</div>
        </div>
        <div class="text-xs text-slate-400">Total Transaksi: {{ $settlements->count() }}</div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Settlement</th>
                        <th class="py-3 px-4">No. Order</th>
                        <th class="py-3 px-4">Unit Pembebanan (Debit)</th>
                        <th class="py-3 px-4">Unit Penyedia (Kredit)</th>
                        <th class="py-3 px-4 text-right">Nilai Barang</th>
                        <th class="py-3 px-4 text-right">Ongkir</th>
                        <th class="py-3 px-4 text-right">Total Nominal</th>
                        <th class="py-3 px-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($settlements as $set)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $set->settlement_number }}</td>
                            <td class="py-3.5 px-4 font-semibold text-slate-700">{{ $set->order->order_number }}</td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $set->debitOrganization->name }}</div>
                                <div class="text-[10px] font-mono text-slate-400">{{ $set->debit_cost_center }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $set->creditOrganization->name }}</div>
                                <div class="text-[10px] font-mono text-slate-400">{{ $set->credit_cost_center }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-right">Rp {{ number_format($set->item_amount, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right">Rp {{ number_format($set->shipping_amount, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-black text-jatim-700">Rp {{ number_format($set->total_amount, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded font-bold text-[10px] {{ $set->status === 'POSTED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $set->status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
