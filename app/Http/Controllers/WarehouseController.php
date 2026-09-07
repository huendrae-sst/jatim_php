<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WarehousePacking;
use App\Models\WarehousePicking;
use App\Services\OrderFulfillmentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarehouseController extends Controller
{
    public function __construct(
        protected OrderFulfillmentService $orderFulfillmentService
    ) {}

    public function pickingQueue()
    {
        $allocatedOrders = Order::with(['requestingOrganization', 'items.item'])
            ->whereIn('status', ['ALLOCATED', 'APPROVED'])
            ->latest()
            ->get();

        $completedPickings = WarehousePicking::with(['order.requestingOrganization', 'picker'])
            ->latest()
            ->limit(10)
            ->get();

        return view('warehouse.picking', compact('allocatedOrders', 'completedPickings'));
    }

    public function processPicking($id)
    {
        $order = Order::findOrFail($id);
        try {
            $picking = $this->orderFulfillmentService->generatePicking($order, Auth::user());

            return redirect()->route('warehouse.packing.queue')
                ->with('success', "Pick list {$picking->picking_number} berhasil diproses. Order siap masuk tahap packing.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function packingQueue()
    {
        $pickingOrders = Order::with(['requestingOrganization', 'items.item'])
            ->where('status', 'PICKING')
            ->latest()
            ->get();

        $completedPackings = WarehousePacking::with(['order.requestingOrganization', 'packer'])
            ->latest()
            ->limit(10)
            ->get();

        return view('warehouse.packing', compact('pickingOrders', 'completedPackings'));
    }

    public function processPacking(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $request->validate([
            'koli_count' => 'required|integer|min:1',
            'total_weight_kg' => 'required|numeric|min:0.1',
            'dimensions_cm' => 'required|string',
        ]);

        try {
            $packing = $this->orderFulfillmentService->generatePacking(
                $order,
                (int) $request->koli_count,
                (float) $request->total_weight_kg,
                $request->dimensions_cm,
                Auth::user()
            );

            return redirect()->route('distribution.shipments.index')
                ->with('success', "Packing {$packing->packing_number} selesai. Order siap dikirim (READY_TO_SHIP).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
