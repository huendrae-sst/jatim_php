<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use App\Models\ExpeditionMapping;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Shipment;
use App\Models\SwitchingStock;
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
        $readyOrders = Order::with(['requestingOrganization.defaultExpeditionMapping.courier', 'items.item', 'packings'])
            ->where('status', 'READY_TO_SHIP')
            ->get();

        $readySwitchings = SwitchingStock::with([
            'sourceOrganization',
            'sourceWarehouse',
            'destinationOrganization.defaultExpeditionMapping.courier',
            'destinationWarehouse',
            'items.item',
            'item',
            'proposer',
        ])
            ->whereIn('status', ['APPROVED', 'RESERVED'])
            ->whereNull('shipment_id')
            ->get();

        $query = Shipment::with([
            'order.requestingOrganization',
            'switchingStock.sourceWarehouse',
            'switchingStock.destinationWarehouse',
            'switchingStock.destinationOrganization',
            'originWarehouse',
            'destinationOrganization',
            'courier',
            'dispatcher',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('courier_id')) {
            $query->where('courier_id', $request->courier_id);
        }

        if ($request->filled('organization_id')) {
            $query->where(function ($sub) use ($request) {
                $sub->where('destination_organization_id', $request->organization_id)
                    ->orWhereHas('order', function ($o) use ($request) {
                        $o->where('requesting_organization_id', $request->organization_id);
                    })
                    ->orWhereHas('switchingStock', function ($sw) use ($request) {
                        $sw->where('destination_organization_id', $request->organization_id);
                    });
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('manifest_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($sub) use ($search) {
                        $sub->where('order_number', 'like', "%{$search}%")
                            ->orWhereHas('requestingOrganization', function ($sub2) use ($search) {
                                $sub2->where('name', 'like', "%{$search}%")
                                    ->orWhere('city', 'like', "%{$search}%");
                            });
                    })
                    ->orWhereHas('switchingStock', function ($sub) use ($search) {
                        $sub->whereHas('destinationOrganization', function ($sub2) use ($search) {
                            $sub2->where('name', 'like', "%{$search}%");
                        })->orWhereHas('sourceWarehouse', function ($sub2) use ($search) {
                            $sub2->where('name', 'like', "%{$search}%");
                        });
                    });
            });
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $shipments = $query->latest()
            ->paginate($perPage)
            ->withQueryString();

        $couriers = Courier::where('is_active', true)->get();
        $organizations = Organization::orderBy('name')->get();
        $expeditionMappings = ExpeditionMapping::with(['courier', 'organization'])->where('is_active', true)->get();

        return view('distribution.index', compact('readyOrders', 'readySwitchings', 'shipments', 'couriers', 'organizations', 'expeditionMappings', 'perPage'));
    }

    public function createShipment(Request $request)
    {
        $isPickup = $request->delivery_method === 'PICKUP_KP';

        $rules = [
            'order_id' => 'nullable|required_without:switching_stock_id|exists:orders,id',
            'switching_stock_id' => 'nullable|required_without:order_id|exists:switching_stocks,id',
            'delivery_method' => 'nullable|in:COURIER,PICKUP_KP',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($isPickup) {
            $rules['pickup_pic_nip'] = 'required|string|max:50';
            $rules['pickup_pic_name'] = 'required|string|max:150';
            $rules['pickup_pic_position'] = 'required|string|max:100';
        } else {
            $rules['courier_id'] = 'required|exists:couriers,id';
            $rules['service_type'] = 'required|string';
            $rules['tracking_number'] = 'required|string';
            $rules['shipping_cost'] = 'required|numeric|min:0';
            $rules['eta_date'] = 'required|date';
            $rules['koli_count'] = 'nullable|integer|min:1';
            $rules['total_weight_kg'] = 'nullable|numeric|min:0.1';
        }

        $request->validate($rules, [
            'pickup_pic_nip.required' => 'Wajib mengisi NIP PIC Pengambil untuk metode Ambil di KP.',
            'pickup_pic_name.required' => 'Wajib mengisi Nama Lengkap PIC Pengambil untuk metode Ambil di KP.',
            'pickup_pic_position.required' => 'Wajib mengisi Jabatan PIC Pengambil untuk metode Ambil di KP.',
        ]);

        try {
            if ($request->filled('switching_stock_id')) {
                $switching = SwitchingStock::findOrFail($request->switching_stock_id);
                $shipment = $this->orderFulfillmentService->createSwitchingShipment(
                    $switching,
                    (int) $request->courier_id,
                    $request->service_type,
                    $request->tracking_number,
                    (float) $request->shipping_cost,
                    $request->eta_date,
                    Auth::user(),
                    (int) ($request->koli_count ?? 1),
                    (float) ($request->total_weight_kg ?? 1.0),
                    $request->notes
                );
            } else {
                $order = Order::findOrFail($request->order_id);
                $deliveryMethod = $isPickup ? 'PICKUP_KP' : 'COURIER';
                $courierId = $isPickup ? null : (int) $request->courier_id;
                $serviceType = $isPickup ? 'AMBIL_DI_KP' : $request->service_type;
                $trackingNumber = $isPickup ? ('PKP/'.date('Ymd').'/'.sprintf('%04d', rand(1, 9999))) : $request->tracking_number;
                $shippingCost = $isPickup ? 0.0 : (float) $request->shipping_cost;
                $etaDate = $isPickup ? now()->toDateString() : $request->eta_date;

                $pickupData = $isPickup ? [
                    'nip' => $request->pickup_pic_nip,
                    'name' => $request->pickup_pic_name,
                    'position' => $request->pickup_pic_position,
                    'notes' => $request->notes,
                ] : null;

                $shipment = $this->orderFulfillmentService->createShipment(
                    $order,
                    $courierId,
                    $serviceType,
                    $trackingNumber,
                    $shippingCost,
                    $etaDate,
                    Auth::user(),
                    $deliveryMethod,
                    $pickupData
                );
            }

            $successMsg = $isPickup
                ? "Serah terima Ambil di KP {$shipment->manifest_number} berhasil dicatat kepada {$request->pickup_pic_name}."
                : "Manifest {$shipment->manifest_number} berhasil diterbitkan dan status barang kini IN_TRANSIT.";

            return redirect()->route('distribution.shipments.index')
                ->with('success', $successMsg);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $shipment = Shipment::with([
            'order.requestingOrganization',
            'order.items.item',
            'switchingStock.sourceWarehouse',
            'switchingStock.destinationWarehouse',
            'switchingStock.items.item',
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
            'switchingStock.sourceWarehouse',
            'switchingStock.destinationWarehouse',
            'switchingStock.items.item',
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
            'switchingStock.destinationOrganization',
            'courier',
            'destinationOrganization',
        ])->findOrFail($id);

        return view('distribution.label_print', compact('shipment'));
    }
}
