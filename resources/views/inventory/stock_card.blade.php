@extends('layouts.app')
@section('title', 'Kartu Stok: ' . $item->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.balances') }}" class="text-decoration-none text-danger">Persediaan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Kartu Stok</li>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-3" x-data="{ adjModal: false }">
    <!-- Item Meta & Action Bar -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 bg-white p-3 rounded-2xl border border-slate-200 shadow-xs">
        <div class="text-xs font-bold text-slate-500">
            SKU: <span class="font-mono text-slate-700">{{ $item->sku }}</span> • Satuan: <span class="text-slate-700">{{ $item->uom }}</span> • Kategori: <span class="text-slate-700">{{ $item->category->name }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button @click="adjModal = true" class="btn btn-sm btn-dark fw-bold shadow-xs">
                <i class="bi bi-sliders me-1"></i> Penyesuaian Stok (Adjustment)
            </button>
            <a href="{{ route('inventory.balances', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-sm btn-light border fw-bold text-slate-700 shadow-xs">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Warehouse Selector -->
    <div class="flex items-center space-x-2">
        <span class="text-xs font-bold text-slate-500 uppercase mr-2">Pilih Lokasi Gudang:</span>
        @foreach($warehouses as $wh)
            <a href="{{ route('inventory.stock_card', ['itemId' => $item->id, 'warehouse_id' => $wh->id]) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition {{ $selectedWarehouseId == $wh->id ? 'bg-jatim-700 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                {{ $wh->name }}
            </a>
        @endforeach
    </div>

    <!-- Stock Metrics Summary -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm text-center">
            <div class="text-[10px] uppercase font-bold text-slate-400">On Hand (Fisik)</div>
            <div class="text-xl font-black text-slate-900 mt-1">{{ $currentBalance->on_hand ?? 0 }} {{ $item->uom }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm text-center">
            <div class="text-[10px] uppercase font-bold text-amber-600">Reserved (Order)</div>
            <div class="text-xl font-black text-amber-600 mt-1">{{ $currentBalance->reserved ?? 0 }} {{ $item->uom }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm text-center">
            <div class="text-[10px] uppercase font-bold text-rose-600">Damaged (Rusak)</div>
            <div class="text-xl font-black text-rose-600 mt-1">{{ $currentBalance->damaged ?? 0 }} {{ $item->uom }}</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-emerald-200 bg-emerald-50/40 shadow-sm text-center">
            <div class="text-[10px] uppercase font-bold text-emerald-800">Available (Tersedia Bebas)</div>
            <div class="text-xl font-black text-emerald-800 mt-1">{{ $currentBalance->available ?? 0 }} {{ $item->uom }}</div>
        </div>
    </div>

    <!-- Immutable Ledger History Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900">Histori Buku Besar Stok (Immutable Stock Ledger)</h3>
            <span class="text-[11px] text-slate-400 font-mono">Traceability End-to-End</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Jenis Transaksi</th>
                        <th class="py-3 px-4">No. Referensi Dokumen</th>
                        <th class="py-3 px-4 text-center">Masuk (+In)</th>
                        <th class="py-3 px-4 text-center">Keluar (-Out)</th>
                        <th class="py-3 px-4 text-center">Saldo Akhir</th>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4">User</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    @forelse($ledgers as $lg)
                        <tr class="hover:bg-slate-50 transition font-sans">
                            <td class="py-3.5 px-4 text-slate-500 text-[11px]">{{ $lg->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-3.5 px-4 font-bold text-slate-800">{{ $lg->transaction_type }}</td>
                            <td class="py-3.5 px-4 font-mono font-semibold text-indigo-700">{{ $lg->reference_number ?? '-' }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-emerald-600">{{ $lg->qty_in > 0 ? '+' . $lg->qty_in : '-' }}</td>
                            <td class="py-3.5 px-4 text-center font-bold text-rose-600">{{ $lg->qty_out > 0 ? '-' . $lg->qty_out : '-' }}</td>
                            <td class="py-3.5 px-4 text-center font-black text-slate-900">{{ $lg->balance_after }}</td>
                            <td class="py-3.5 px-4 text-slate-600 max-w-xs truncate">{{ $lg->notes }}</td>
                            <td class="py-3.5 px-4 text-slate-500 text-[11px]">{{ $lg->creator->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-400 font-sans">Belum ada catatan mutasi ledger untuk item ini di lokasi ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pagination-footer :paginator="$ledgers" />
    </div>

    <!-- Stock Adjustment Modal -->
    <div x-show="adjModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="adjModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4 pe-5">
                <h3 class="font-bold text-base text-slate-900">Penyesuaian Stok (Adjustment)</h3>
                <button type="button" @click="adjModal = false" class="btn-close" aria-label="Close"></button>
            </div>

            <form action="{{ route('inventory.adjustments.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
                <input type="hidden" name="item_id" value="{{ $item->id }}">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tipe Mutasi</label>
                    <select name="transaction_type" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs">
                        <option value="STOCK_ADJUSTMENT">Stock Adjustment (Koreksi Fisik)</option>
                        <option value="STOCK_OPNAME">Stock Opname Bulanan/Tahunan</option>
                        <option value="DAMAGED_HOLD">Pemisahan Barang Rusak (Damaged/Hold)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Selisih Quantity (+Tambah / -Kurang)</label>
                    <input type="number" name="qty_diff" required class="w-full bg-white border border-slate-200 rounded-xl p-2 text-xs font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Alasan Penyesuaian</label>
                    <textarea name="notes" rows="2" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2 text-xs"></textarea>
                </div>

                <div class="pt-2 flex justify-end space-x-3">
                    <button type="button" @click="adjModal = false" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-md">
                        Simpan Penyesuaian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
