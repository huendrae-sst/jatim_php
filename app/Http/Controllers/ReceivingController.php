<?php

namespace App\Http\Controllers;

use App\Models\Discrepancy;
use App\Models\Receiving;
use App\Models\Shipment;
use App\Services\OrderFulfillmentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReceivingController extends Controller
{
    public function __construct(
        protected OrderFulfillmentService $orderFulfillmentService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;

        $shipmentsQuery = Shipment::with(['order.requestingOrganization', 'courier'])
            ->whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY']);

        $receivingsQuery = Receiving::with(['order.requestingOrganization', 'shipment.courier', 'receiver', 'discrepancies']);

        if ($isBranch) {
            $shipmentsQuery->whereHas('order', fn ($q) => $q->where('organization_id', $user->organization_id));
            $receivingsQuery->whereHas('order', fn ($q) => $q->where('organization_id', $user->organization_id));
        }

        $incomingShipments = $shipmentsQuery->latest()->get();

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 15;
        $receivings = $receivingsQuery->latest()->paginate($perPage)->withQueryString();

        return view('receiving.index', compact('incomingShipments', 'receivings'));
    }

    public function createReceiptForm($shipmentId)
    {
        $shipment = Shipment::with(['order.items.item', 'courier', 'originWarehouse'])->findOrFail($shipmentId);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $shipment->order && $shipment->order->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak berwenang menerima pengiriman untuk unit kerja lain.');
        }

        return view('receiving.confirm', compact('shipment'));
    }

    public function confirmReceipt(Request $request, $shipmentId)
    {
        $shipment = Shipment::with('order.items')->findOrFail($shipmentId);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $shipment->order && $shipment->order->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak berwenang menerima pengiriman untuk unit kerja lain.');
        }

        $request->validate([
            'pod_signature' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.qty_good' => 'required|integer|min:0',
            'items.*.qty_damaged' => 'nullable|integer|min:0',
            'items.*.qty_missing' => 'nullable|integer|min:0',
        ]);

        try {
            $rcv = $this->orderFulfillmentService->processReceiving(
                $shipment,
                $request->items,
                $request->pod_signature,
                $request->notes ?? '',
                Auth::user()
            );

            return redirect()->route('receiving.index')
                ->with('success', "Konfirmasi penerimaan barang {$rcv->receiving_number} berhasil dicatat & stok unit telah diperbarui.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function discrepancies(Request $request)
    {
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 15;
        $discrepancies = Discrepancy::with(['receiving.order.requestingOrganization', 'item'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('receiving.discrepancies', compact('discrepancies'));
    }
}
