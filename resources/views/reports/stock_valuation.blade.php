@extends('layouts.app')
@section('title', 'Laporan Posisi & Valuasi Persediaan')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none text-danger">Laporan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Valuasi Persediaan</li>
@endsection

@section('content')
<div class="space-y-4">
    <div class="d-flex justify-content-end mb-1">
        <a href="{{ route('reports.stock_valuation.csv') }}" class="btn btn-sm btn-outline-success fw-bold w-100 w-sm-auto text-center">
            <i class="bi bi-file-earmark-excel me-1"></i> Export CSV / Excel
        </a>
    </div>

    <!-- Valuasi Total Header -->
    <div class="bg-gradient-to-r from-navy-900 to-slate-800 text-white p-4 sm:p-6 rounded-2xl border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <div class="text-xs uppercase font-bold text-slate-400">Total Akumulasi Valuasi Seluruh Gudang:</div>
            <div class="text-2xl sm:text-3xl font-black text-rose-300 mt-1">Rp {{ number_format($totalValuation, 0, ',', '.') }}</div>
        </div>
        <div class="text-xs text-start sm:text-right text-slate-300">
            <div>{{ $balances->count() }} Record Baris Terhitung</div>
            <div class="text-emerald-400 font-bold">Audit Terverifikasi</div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Gudang / Lokasi</th>
                        <th class="py-3 px-4">Barang & SKU</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4 text-center">On Hand</th>
                        <th class="py-3 px-4 text-center">Reserved</th>
                        <th class="py-3 px-4 text-center">Damaged</th>
                        <th class="py-3 px-4 text-center">Available</th>
                        <th class="py-3 px-4 text-right">Harga Satuan</th>
                        <th class="py-3 px-4 text-right">Total Valuasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($balances as $b)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-900">{{ $b->warehouse->name }}</td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $b->item->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $b->item->sku }} • {{ $b->item->uom }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600">{{ $b->item->category->name }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-800">{{ $b->on_hand }}</td>
                            <td class="py-3.5 px-4 text-center font-semibold text-amber-600">{{ $b->reserved }}</td>
                            <td class="py-3.5 px-4 text-center font-semibold text-rose-600">{{ $b->damaged }}</td>
                            <td class="py-3.5 px-4 text-center font-black text-emerald-700 bg-emerald-50/40">{{ $b->available }}</td>
                            <td class="py-3.5 px-4 text-right text-slate-600">Rp {{ number_format($b->item->estimated_unit_price, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-black text-slate-900">Rp {{ number_format($b->on_hand * $b->item->estimated_unit_price, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
