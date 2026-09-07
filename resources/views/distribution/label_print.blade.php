<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Label Pengiriman - {{ $shipment->manifest_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/poppins@5.0.14/index.css" crossorigin="anonymous" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: 'Poppins', system-ui, -apple-system, sans-serif; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 p-8 flex flex-col items-center">
    <div class="no-print mb-4">
        <button onclick="window.print()" class="bg-red-700 text-white text-xs font-bold px-4 py-2 rounded shadow">
            Cetak Label (Print)
        </button>
    </div>

    <!-- Label Box (Standard Shipping Size) -->
    <div class="w-[450px] bg-white p-6 border-2 border-dashed border-slate-400 shadow-md space-y-4">
        <div class="border-b-2 border-red-700 pb-2 flex justify-between items-center">
            <div class="flex items-center gap-2.5">
                <img src="{{ asset('images/logo-bankjatim.png') }}" alt="Bank Jatim" class="h-6 w-auto object-contain">
                <div>
                    <h1 class="text-xs font-black text-red-700 leading-tight">LOGISTICS</h1>
                    <p class="text-[9px] text-slate-500 leading-tight">Shipping Identification Label</p>
                </div>
            </div>
            <span class="bg-red-100 text-red-800 text-[10px] font-bold px-2 py-0.5 rounded">FRAGILE / RESMI</span>
        </div>

        <div class="flex justify-between items-center">
            <div>
                <div class="text-[10px] uppercase text-slate-400 font-bold">No. Manifest:</div>
                <div class="text-sm font-black font-mono text-slate-900">{{ $shipment->manifest_number }}</div>
                <div class="text-[10px] uppercase text-slate-400 font-bold mt-1">No. Resi:</div>
                <div class="text-xs font-mono font-bold text-indigo-700">{{ $shipment->tracking_number }}</div>
            </div>
            <!-- QR Code Container -->
            <div id="qrcode" class="p-1 bg-white border border-slate-300 rounded"></div>
        </div>

        <div class="bg-slate-50 p-3 rounded border border-slate-200 text-xs space-y-1">
            <div class="text-[10px] font-bold uppercase text-slate-400">Penerima:</div>
            <div class="font-black text-sm text-slate-900">{{ $shipment->order->requestingOrganization->name }}</div>
            <div class="text-slate-600">{{ $shipment->order->requestingOrganization->address }}</div>
            <div class="text-[11px] text-slate-500 font-semibold">Kota: {{ $shipment->order->requestingOrganization->city }}</div>
        </div>

        <div class="grid grid-cols-2 gap-2 text-center text-xs font-bold">
            <div class="p-2 bg-slate-100 rounded">Koli: {{ $shipment->koli_count }} Paket</div>
            <div class="p-2 bg-slate-100 rounded">Berat: {{ $shipment->total_weight_kg }} Kg</div>
        </div>
    </div>

    <script>
        new QRCode(document.getElementById("qrcode"), {
            text: "{{ $shipment->manifest_number }}|{{ $shipment->tracking_number }}",
            width: 80,
            height: 80
        });
    </script>
</body>
</html>
