<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manifest Pengiriman - {{ $shipment->manifest_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/poppins@5.0.14/index.css" crossorigin="anonymous" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Poppins', system-ui, -apple-system, sans-serif; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 p-8">
    <div class="max-w-3xl mx-auto bg-white p-8 border border-slate-300 shadow-md">
        <!-- Print Button -->
        <div class="no-print mb-6 flex justify-between items-center bg-slate-50 p-4 rounded-lg border border-slate-200">
            <span class="text-xs text-slate-600">Dokumen Resmi Surat Jalan / Delivery Manifest Bank Jatim</span>
            <button onclick="window.print()" class="bg-red-700 text-white text-xs font-bold px-4 py-2 rounded shadow">
                Cetak Dokumen (Print)
            </button>
        </div>

        <!-- Header Bank Jatim -->
        <div class="border-b-2 border-red-700 pb-4 mb-6 flex justify-between items-start">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/logo-bankjatim.png') }}" alt="Bank Jatim" class="h-10 w-auto object-contain">
                <div>
                    <h1 class="text-base font-black tracking-tight text-red-700">PT BANK PEMBANGUNAN DAERAH JAWA TIMUR TBK</h1>
                    <p class="text-xs font-semibold text-slate-600">DIVISI LOGISTIK & UMUM - GUDANG PUSAT SIER SURABAYA</p>
                    <p class="text-[10px] text-slate-400">Jl. Basuki Rahmat No. 98-104, Surabaya | Telp: (031) 5310090</p>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-lg font-black tracking-tight text-slate-900">SURAT JALAN / MANIFEST</h2>
                <p class="text-xs font-mono font-bold text-red-700">{{ $shipment->manifest_number }}</p>
            </div>
        </div>

        @php
            $isSwitching = (bool) $shipment->switching_stock_id;
            $destOrg = $shipment->order->requestingOrganization ?? $shipment->switchingStock->destinationOrganization ?? $shipment->destinationOrganization ?? null;
            $destWh = $shipment->switchingStock->destinationWarehouse ?? null;
            $srcWh = $shipment->switchingStock->sourceWarehouse ?? $shipment->originWarehouse ?? null;
            $items = $isSwitching ? ($shipment->switchingStock->items ?? collect()) : ($shipment->order->items ?? collect());
        @endphp

        <!-- Metadata -->
        <div class="grid grid-cols-2 gap-6 text-xs mb-6">
            <div class="space-y-1">
                <div><strong>Tanggal Kirim:</strong> {{ $shipment->dispatched_at ? $shipment->dispatched_at->format('d F Y') : ($shipment->created_at ? $shipment->created_at->format('d F Y') : '-') }}</div>
                <div>
                    <strong>Dokumen Referensi:</strong> 
                    @if($isSwitching)
                        Transfer Switching #{{ $shipment->switchingStock->transfer_number ?? $shipment->switching_stock_id }}
                    @else
                        No. Order {{ $shipment->order->order_number ?? '-' }}
                    @endif
                </div>
                <div><strong>Gudang Pengirim (Asal):</strong> {{ $srcWh->name ?? 'Gudang Pengirim' }}</div>
                <div><strong>Ekspedisi / Kurir:</strong> {{ $shipment->courier->name ?? 'Internal Bank Jatim' }}</div>
                <div><strong>No. Resi (AWB):</strong> {{ $shipment->tracking_number }}</div>
            </div>
            <div class="space-y-1 bg-slate-50 p-3 rounded border border-slate-200">
                <div class="font-bold text-slate-700 uppercase">Unit Tujuan Pengiriman:</div>
                <div class="font-bold text-sm text-slate-900">{{ $destOrg->name ?? '-' }}</div>
                @if($destWh)
                    <div class="text-xs font-semibold text-slate-700">Gudang Tujuan: {{ $destWh->name }}</div>
                @endif
                <div>{{ $destOrg->address ?? '-' }}</div>
                <div>Telp: {{ $destOrg->phone ?? '-' }}</div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="w-full text-left text-xs border border-slate-300 mb-6">
            <thead class="bg-slate-100 border-b border-slate-300 font-bold uppercase">
                <tr>
                    <th class="p-2 border-r border-slate-300 w-10 text-center">No</th>
                    <th class="p-2 border-r border-slate-300">Kode & Nama Barang</th>
                    <th class="p-2 border-r border-slate-300 text-center w-24">Satuan</th>
                    <th class="p-2 text-center w-28">Jumlah Qty</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach($items as $idx => $it)
                    @php
                        $qty = $isSwitching ? $it->qty_requested : ($it->qty_shipped ?? $it->qty_requested);
                    @endphp
                    <tr>
                        <td class="p-2 border-r border-slate-300 text-center">{{ $idx + 1 }}</td>
                        <td class="p-2 border-r border-slate-300">
                            <strong>{{ $it->item->name ?? 'Item' }}</strong>
                            <div class="text-[10px] text-slate-500 font-mono">{{ $it->item->sku ?? '-' }}</div>
                        </td>
                        <td class="p-2 border-r border-slate-300 text-center">{{ $it->item->uom ?? 'PCS' }}</td>
                        <td class="p-2 text-center font-bold text-sm">{{ $qty }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Packaging Summary -->
        <div class="text-xs bg-slate-50 p-3 rounded border border-slate-200 mb-8 flex justify-between">
            <div>Jumlah Koli: <strong>{{ $shipment->koli_count }} Koli</strong></div>
            <div>Total Berat: <strong>{{ $shipment->total_weight_kg }} Kg</strong></div>
            <div>Biaya Pengiriman: <strong>Rp {{ number_format($shipment->shipping_cost, 0, ',', '.') }}</strong></div>
        </div>

        <!-- Signature Boxes -->
        <div class="grid grid-cols-3 gap-4 text-center text-xs pt-4 border-t border-slate-200">
            <div>
                <p class="text-slate-500 mb-12">Petugas Pengirim (Gudang)</p>
                <p class="font-bold underline">{{ $shipment->dispatcher->name }}</p>
                <p class="text-[10px] text-slate-400">Logistik Bank Jatim</p>
            </div>
            <div>
                <p class="text-slate-500 mb-12">Petugas Ekspedisi / Kurir</p>
                <p class="font-bold underline">_______________________</p>
                <p class="text-[10px] text-slate-400">{{ $shipment->courier->name ?? 'Kurir' }}</p>
            </div>
            <div>
                <p class="text-slate-500 mb-12">Penerima Cabang (Capem)</p>
                <p class="font-bold underline">_______________________</p>
                <p class="text-[10px] text-slate-400">Tanda Tangan & Cap Unit</p>
            </div>
        </div>
    </div>
</body>
</html>
