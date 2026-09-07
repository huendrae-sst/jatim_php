@extends('layouts.app')
@section('title', 'Forecasting & Reorder Recommendation')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Forecasting & ROP</li>
@endsection

@section('content')
<div class="space-y-4">
    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ route('procurement.pr.create') }}" class="btn btn-sm btn-danger fw-bold shadow-xs">
            <i class="bi bi-cart-plus me-1"></i> Buat Purchase Request Baru
        </a>
    </div>

    <!-- Forecasting Principle Notice (Section 14 of specification) -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4 flex items-start space-x-3 text-indigo-900">
        <i class="fa-solid fa-brain-circuit text-indigo-600 text-lg mt-0.5"></i>
        <div class="text-xs leading-relaxed">
            <strong>Aturan Forecasting Bank Jatim:</strong> Forecasting menghasilkan rekomendasi reorder kuantitas dan peringatan dini stockout. Pembuatan Purchase Request (PR) atau instruksi transfer pengadaan tetap memerlukan tindakan dan persetujuan pejabat berwenang.
        </div>
    </div>

    <!-- Forecast Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900">Analisis Kebutuhan Stok per SKU</h3>
            <span class="text-xs text-slate-400 font-medium">{{ count($forecasts) }} SKU Teranalisis</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Item & SKU</th>
                        <th class="py-3 px-4 text-center">Permintaan / Bulan</th>
                        <th class="py-3 px-4 text-center">Lead Time</th>
                        <th class="py-3 px-4 text-center">Safety Stock</th>
                        <th class="py-3 px-4 text-center">Reorder Point (ROP)</th>
                        <th class="py-3 px-4 text-center">Stok Tersedia</th>
                        <th class="py-3 px-4 text-center">Sisa Pasokan (Hari)</th>
                        <th class="py-3 px-4 text-center">Rekomendasi Beli</th>
                        <th class="py-3 px-4 text-center">Status Risiko</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($forecasts as $f)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900">{{ $f['name'] }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $f['sku'] }} • {{ $f['uom'] }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-700">{{ $f['avg_monthly_demand'] }} {{ $f['uom'] }}</td>
                            <td class="py-3.5 px-4 text-center text-slate-600">{{ $f['lead_time_days'] }} Hari</td>
                            <td class="py-3.5 px-4 text-center font-bold text-indigo-700">{{ $f['recommended_safety_stock'] }} {{ $f['uom'] }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-amber-700">{{ $f['reorder_point'] }} {{ $f['uom'] }}</td>
                            <td class="py-3.5 px-4 text-center font-black text-slate-900">{{ $f['current_available'] }} {{ $f['uom'] }}</td>
                            <td class="py-3.5 px-4 text-center font-bold {{ $f['days_of_supply'] < 15 ? 'text-rose-600' : 'text-slate-700' }}">
                                ~{{ $f['days_of_supply'] }} Hari
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($f['suggested_reorder_qty'] > 0)
                                    <span class="bg-rose-100 text-rose-800 font-black text-xs px-2.5 py-1 rounded-lg">
                                        +{{ $f['suggested_reorder_qty'] }} {{ $f['uom'] }}
                                    </span>
                                @else
                                    <span class="text-slate-400 font-semibold">Cukup</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($f['risk_level'] === 'CRITICAL_STOCKOUT')
                                    <span class="bg-rose-600 text-white font-bold text-[10px] px-2.5 py-0.5 rounded-full">STOCKOUT</span>
                                @elseif($f['risk_level'] === 'HIGH_REORDER')
                                    <span class="bg-amber-100 text-amber-800 font-bold text-[10px] px-2.5 py-0.5 rounded-full">REORDER NOW</span>
                                @else
                                    <span class="bg-emerald-100 text-emerald-800 font-bold text-[10px] px-2.5 py-0.5 rounded-full">AMAN</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
