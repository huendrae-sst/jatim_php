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
            @if($shipment->switching_stock_id)
                <div class="mt-1"><span class="badge bg-info-subtle text-info font-monospace text-[10px]">TRANSFER SWITCHING #{{ $shipment->switchingStock->transfer_number ?? $shipment->switching_stock_id }}</span></div>
            @elseif($shipment->order)
                <div class="mt-1"><span class="badge bg-secondary-subtle text-secondary font-monospace text-[10px]">ORDER #{{ $shipment->order->order_number }}</span></div>
            @endif
        </div>

        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Unit & Gudang Penerima</div>
            @php
                $destOrg = $shipment->order->requestingOrganization ?? $shipment->switchingStock->destinationOrganization ?? $shipment->destinationOrganization ?? null;
                $destWh = $shipment->switchingStock->destinationWarehouse ?? null;
                $srcWh = $shipment->switchingStock->sourceWarehouse ?? $shipment->originWarehouse ?? null;
                $isSwitching = (bool) $shipment->switching_stock_id;
                $shipmentItems = $isSwitching 
                    ? ($shipment->switchingStock->items ?? collect()) 
                    : ($shipment->order->items ?? collect());
            @endphp
            <div class="text-xs font-bold text-slate-900 mt-1">{{ $destOrg->name ?? '-' }}</div>
            <div class="text-[11px] text-slate-500">{{ $destOrg->city ?? '-' }} @if($destWh) • Gudang: <strong>{{ $destWh->name }}</strong> @endif</div>
        </div>

        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ekspedisi & Asal Pengiriman</div>
            <div class="text-xs font-bold text-slate-900 mt-1">{{ $shipment->courier->name ?? 'Kurir Internal' }}</div>
            <div class="text-[11px] text-slate-500">
                Dari: {{ $srcWh->name ?? 'Gudang Pengirim' }} &bull; {{ $shipment->koli_count }} Koli ({{ $shipment->total_weight_kg }} kg)
            </div>
        </div>
    </div>

    <!-- Form -->
    <form action="{{ route('receiving.confirm.store', $shipment->id) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
        @csrf

        <div class="space-y-3">
            <h3 class="text-sm font-bold text-slate-900">Verifikasi Item yang Diterima</h3>

            @foreach($shipmentItems as $idx => $it)
                @php
                    $itemQty = $isSwitching ? $it->qty_requested : ($it->qty_shipped ?? $it->qty_requested);
                @endphp
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <div class="text-xs font-bold text-slate-900">{{ $it->item->name ?? 'Item' }}</div>
                            <div class="text-[10px] text-slate-500 font-mono">
                                {{ $it->item->sku ?? '-' }} • 
                                @if($isSwitching)
                                    Dialihkan dari {{ $srcWh->name ?? 'Gudang Asal' }}: <strong class="text-slate-800">{{ $itemQty }} {{ $it->item->uom ?? 'PCS' }}</strong>
                                @else
                                    Dikirim dari Gudang: <strong class="text-slate-800">{{ $itemQty }} {{ $it->item->uom ?? 'PCS' }}</strong>
                                @endif
                            </div>
                        </div>
                        @if($isSwitching)
                            <input type="hidden" name="items[{{ $idx }}][switching_stock_item_id]" value="{{ $it->id }}">
                        @else
                            <input type="hidden" name="items[{{ $idx }}][order_item_id]" value="{{ $it->id }}">
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-emerald-700 uppercase mb-1">Diterima Kondisi Baik</label>
                            <input type="number" name="items[{{ $idx }}][qty_good]" value="{{ $itemQty }}" max="{{ $itemQty }}" min="0" required class="w-full bg-white border border-slate-200 rounded-lg p-2 text-xs font-bold text-center">
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

        <!-- Mandatory Berita Acara .pdf Upload if Discrepancy Exists -->
        <div class="p-4 bg-amber-50/80 border border-amber-200 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 text-amber-900 font-bold text-xs">
                    <i class="fa-solid fa-file-pdf text-rose-600 text-base"></i>
                    <span>Unggah Berita Acara Selisih / Kerusakan (Mandatory .pdf)</span>
                </div>
                <span class="text-[10px] font-bold text-amber-800 uppercase px-2 py-0.5 bg-amber-200/70 rounded">Wajib Berita Acara</span>
            </div>
            <p class="text-[11px] text-amber-800">
                Sesuai SOP & KAK Bank Jatim, jika terdapat kuantitas <strong>rusak</strong> atau <strong>kurang/hilang</strong> pada salah satu item di atas, Anda <strong>wajib mengunggah berkas Berita Acara (.pdf)</strong> resmi yang ditandatangani.
            </p>
            <div>
                <input type="file" name="berita_acara_pdf" accept="application/pdf" class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs text-slate-800 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100 cursor-pointer">
                @error('berita_acara_pdf')
                    <div class="text-rose-600 text-xs mt-1 font-semibold">{{ $message }}</div>
                @enderror
            </div>
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
                Simpan & Posting Stok ke Gudang
            </button>
        </div>
    </form>
</div>
@endsection
