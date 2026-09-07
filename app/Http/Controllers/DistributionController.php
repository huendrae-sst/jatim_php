<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\OrderFulfillmentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DistributionController extends Controller
{
    public function __construct(
        protected OrderFulfillmentService $orderFulfillmentService
    ) {}

    public function index(Request $request)
    {
        $readyOrders = Order::with(['requestingOrganization', 'items.item', 'packings'])
            ->where('status', 'READY_TO_SHIP')
            ->get();

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 15;
        $shipments = Shipment::with(['order.requestingOrganization', 'courier', 'dispatcher'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $couriers = Courier::where('is_active', true)->get();

        return view('distribution.index', compact('readyOrders', 'shipments', 'couriers'));
    }

    public function createShipment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'courier_id' => 'required|exists:couriers,id',
            'service_type' => 'required|string',
            'tracking_number' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
            'eta_date' => 'required|date',
        ]);

        $order = Order::findOrFail($request->order_id);

        try {
            $shipment = $this->orderFulfillmentService->createShipment(
                $order,
                (int) $request->courier_id,
                $request->service_type,
                $request->tracking_number,
                (float) $request->shipping_cost,
                $request->eta_date,
                Auth::user()
            );

            return redirect()->route('distribution.manifest.print', $shipment->id)
                ->with('success', "Manifest {$shipment->manifest_number} berhasil diterbitkan dan status barang kini IN_TRANSIT.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $shipment = Shipment::with([
            'order.requestingOrganization',
            'order.items.item',
            'courier',
            'originWarehouse',
            'destinationOrganization',
            'receivings.discrepancies',
        ])->findOrFail($id);

        return view('distribution.show', compact('shipment'));
    }

    public function printManifest($id)
    {
        $shipment = Shipment::with([
            'order.requestingOrganization',
            'order.items.item',
            'courier',
            'originWarehouse',
            'destinationOrganization',
            'dispatcher',
        ])->findOrFail($id);

        return view('distribution.manifest_print', compact('shipment'));
    }

    public function printLabel($id)
    {
        $shipment = Shipment::with([
            'order.requestingOrganization',
            'courier',
            'destinationOrganization',
        ])->findOrFail($id);

        return view('distribution.label_print', compact('shipment'));
    }
}
