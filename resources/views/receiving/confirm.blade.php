@extends('layouts.app')
@section('title', 'Konfirmasi Penerimaan: ' . $shipment->manifest_number)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-danger">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('receiving.index') }}" class="text-decoration-none text-danger">Penerimaan Cabang</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $shipment->manifest_number }}</li>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-4">
    <!-- Action Bar -->
    <div class="d-flex justify-content-end align-items-center gap-2">
        <a href="{{ route('receiving.index') }}" class="btn btn-sm btn-light border fw-bold text-slate-700 shadow-xs">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Metadata Card -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">No. Manifest & Resi</div>
            <div class="text-xs font-bold text-slate-900 mt-1">{{ $shipment->manifest_number }}</div>
            <div class="text-[11px] text-indigo-700 font-mono">Resi: {{ $shipment->tracking_number }}</div>
        </div>

        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Unit Penerima</div>
            <div class="text-xs font-bold text-slate-900 mt-1">{{ $shipment->order->requestingOrganization->name }}</div>
            <div class="text-[11px] text-slate-500">{{ $shipment->order->requestingOrganization->city }}</div>
        </div>

        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ekspedisi Pengirim</div>
            <div class="text-xs font-bold text-slate-900 mt-1">{{ $shipment->courier->name ?? 'Kurir Internal' }}</div>
            <div class="text-[11px] text-slate-500">{{ $shipment->koli_count }} Koli ({{ $shipment->total_weight_kg }} kg)</div>
        </div>
    </div>

    <!-- Form -->
    <form action="{{ route('receiving.confirm.store', $shipment->id) }}" method="POST" class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
        @csrf

        <div class="space-y-3">
            <h3 class="text-sm font-bold text-slate-900">Verifikasi Item yang Diterima</h3>

            @foreach($shipment->order->items as $idx => $it)
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="text-xs font-bold text-slate-900">{{ $it->item->name }}</div>
                            <div class="text-[10px] text-slate-500 font-mono">{{ $it->item->sku }} • Dikirim dari Gudang: <strong class="text-slate-800">{{ $it->qty_shipped }} {{ $it->item->uom }}</strong></div>
                        </div>
                        <input type="hidden" name="items[{{ $idx }}][order_item_id]" value="{{ $it->id }}">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-emerald-700 uppercase mb-1">Diterima Kondisi Baik</label>
                            <input type="number" name="items[{{ $idx }}][qty_good]" value="{{ $it->qty_shipped }}" max="{{ $it->qty_shipped }}" min="0" required class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs font-bold text-center">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-rose-700 uppercase mb-1">Kondisi Rusak / Cacat</label>
                            <input type="number" name="items[{{ $idx }}][qty_damaged]" value="0" min="0" class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs font-bold text-center text-rose-600">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-amber-700 uppercase mb-1">Kurang / Tidak Terkirim</label>
                            <input type="number" name="items[{{ $idx }}][qty_missing]" value="0" min="0" class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs font-bold text-center text-amber-600">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-4 border-t border-slate-100">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama / Tanda Tangan Penerima (POD)</label>
                <input type="text" name="pod_signature" value="{{ auth()->user()->name }}" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-800">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Catatan Tambahan Penerimaan</label>
                <input type="text" name="notes" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800">
            </div>
        </div>

        <div class="pt-4 flex justify-end space-x-3">
            <a href="{{ route('receiving.index') }}" class="px-5 py-2.5 border border-slate-200 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-50 transition">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition">
                Simpan & Posting Stok ke Cabang
            </button>
        </div>
    </form>
</div>
@endsection
