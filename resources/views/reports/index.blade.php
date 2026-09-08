@extends('layouts.app')
@section('title', 'Pusat Laporan & Analitik')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">Laporan & Analitik</li>
@endsection

@section('content')
<div class="space-y-4">

    <!-- Report Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- 1. Stock Valuation Report -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between space-y-4 hover:border-jatim-700 transition group">
            <div class="space-y-2">
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-jatim-700 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-vault"></i>
                </div>
                <h3 class="font-bold text-base text-slate-900 group-hover:text-jatim-700">Posisi & Valuasi Stok</h3>
                <p class="text-xs text-slate-500">Rincian saldo on hand, reserved, damaged, available dan total nilai aset persediaan per lokasi gudang.</p>
            </div>
            <div class="space-y-2 pt-2 border-t border-slate-100">
                <a href="{{ route('reports.stock_valuation') }}" class="w-full bg-slate-900 hover:bg-jatim-700 text-white font-bold py-2 rounded-xl text-xs text-center block transition">
                    Buka Laporan
                </a>
                <a href="{{ route('reports.stock_valuation.csv') }}" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2 rounded-xl text-xs text-center block transition">
                    <i class="fa-solid fa-file-csv mr-1"></i> Unduh CSV / Excel
                </a>
            </div>
        </div>

        <!-- 2. Procurement Coverage Report -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between space-y-4 hover:border-indigo-600 transition group">
            <div class="space-y-2">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <h3 class="font-bold text-base text-slate-900 group-hover:text-indigo-600">Coverage Pengadaan</h3>
                <p class="text-xs text-slate-500">Audit keterlacakan konsolidasi PR menjadi PO, progress pengiriman vendor, dan outstanding barang.</p>
            </div>
            <div class="pt-2 border-t border-slate-100">
                <a href="{{ route('reports.procurement_coverage') }}" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-xl text-xs text-center block transition">
                    Buka Matriks Pengadaan
                </a>
            </div>
        </div>

        <!-- 3. Inter-unit Settlement Report -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between space-y-4 hover:border-emerald-600 transition group">
            <div class="space-y-2">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                </div>
                <h3 class="font-bold text-base text-slate-900 group-hover:text-emerald-600">Settlement Antarunit</h3>
                <p class="text-xs text-slate-500">Rekapitulasi beban debit/kredit biaya pengiriman dan persediaan antar cabang dan kantor pusat.</p>
            </div>
            <div class="pt-2 border-t border-slate-100">
                <a href="{{ route('reports.settlements') }}" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-xl text-xs text-center block transition">
                    Buka Jurnal Settlement
                </a>
            </div>
        </div>

        <!-- 4. General Ledger (Buku Besar) Report -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between space-y-4 hover:border-sky-600 transition group">
            <div class="space-y-2">
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-book-journal-whills"></i>
                </div>
                <h3 class="font-bold text-base text-slate-900 group-hover:text-sky-600">Buku Besar (General Ledger)</h3>
                <p class="text-xs text-slate-500">Mutasi debit, kredit, saldo awal, dan saldo berjalan per akun akuntansi per cabang maupun konsolidasi global.</p>
            </div>
            <div class="space-y-2 pt-2 border-t border-slate-100">
                <a href="{{ route('reports.general_ledger') }}" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 rounded-xl text-xs text-center block transition">
                    Buka Buku Besar
                </a>
                <a href="{{ route('reports.general_ledger.csv') }}" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2 rounded-xl text-xs text-center block transition">
                    <i class="fa-solid fa-file-csv mr-1"></i> Unduh CSV / Excel
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
