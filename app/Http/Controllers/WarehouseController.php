<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderAllocation;
use App\Models\Organization;
use App\Models\WarehousePacking;
use App\Models\WarehousePicking;
use App\Services\AuditTrailService;
use App\Services\OrderFulfillmentService;
use App\Services\StockLedgerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function __construct(
        protected OrderFulfillmentService $orderFulfillmentService,
        protected StockLedgerService $stockLedgerService
    ) {}

    public function pickingQueue(Request $request)
    {
        $query = Order::with(['requestingOrganization', 'items.item'])
            ->whereIn('status', ['ALLOCATED', 'APPROVED']);

        if ($request->filled('organization_id')) {
            $query->where('requesting_organization_id', $request->organization_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('requestingOrganization', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.item', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $allocatedOrders = $query->latest()
            ->paginate($perPage)
            ->withQueryString();

        $completedPickings = WarehousePicking::with(['order.requestingOrganization', 'picker'])
            ->latest()
            ->limit(10)
            ->get();

        $organizations = Organization::orderBy('name')->get();

        return view('warehouse.picking', compact('allocatedOrders', 'completedPickings', 'organizations', 'perPage'));
    }

    public function processPicking($id)
    {
        $order = Order::findOrFail($id);
        try {
            $picking = $this->orderFulfillmentService->generatePicking($order, Auth::user());

            return redirect()->route('warehouse.picking.queue')
                ->with('success', "Pick list {$picking->picking_number} berhasil dikonfirmasi. Order siap masuk tahap packing.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroyPicking($id)
    {
        $order = Order::with(['items.item', 'items.allocations.sourceWarehouse', 'pickings'])->findOrFail($id);
        $user = Auth::user();

        if (! in_array($order->status, ['ALLOCATED', 'APPROVED'])) {
            return back()->with('error', "Order {$order->order_number} tidak berada dalam status antrean picking (ALLOCATED/APPROVED).");
        }

        try {
            DB::transaction(function () use ($order, $user) {
                $oldStatus = $order->status;

                foreach ($order->items as $orderItem) {
                    $allocations = OrderAllocation::where('order_item_id', $orderItem->id)->get();
                    foreach ($allocations as $allocation) {
                        if ($allocation->sourceWarehouse && $orderItem->item) {
                            $this->stockLedgerService->releaseReservedStock(
                                $allocation->sourceWarehouse,
                                $orderItem->item,
                                (int) $allocation->qty_allocated
                            );
                        }
                        $allocation->delete();
                    }

                    $orderItem->qty_allocated = 0;
                    $orderItem->qty_approved = 0;
                    $orderItem->qty_picked = 0;
                    $orderItem->save();
                }

                $order->pickings()->delete();

                $order->status = 'SUBMITTED';
                $order->approved_by_user_id = null;
                $order->approved_at = null;
                $order->save();

                AuditTrailService::log('CANCEL_ALLOCATION', $order, [
                    'status' => $oldStatus,
                ], [
                    'status' => 'SUBMITTED',
                ], $user);
            });

            return redirect()->route('warehouse.picking.queue')
                ->with('success', "Order {$order->order_number} berhasil dihapus dari antrean picking dan dikembalikan ke antrean pesanan (/orders).");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal mengembalikan order ke antrean pesanan: '.$e->getMessage());
        }
    }

    public function packingQueue(Request $request)
    {
        $query = Order::with(['requestingOrganization', 'items.item'])
            ->where('status', 'PICKING');

        if ($request->filled('organization_id')) {
            $query->where('requesting_organization_id', $request->organization_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('requestingOrganization', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.item', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $pickingOrders = $query->latest()
            ->paginate($perPage)
            ->withQueryString();

        $completedPackings = WarehousePacking::with(['order.requestingOrganization', 'packer'])
            ->latest()
            ->limit(10)
            ->get();

        $organizations = Organization::orderBy('name')->get();

        return view('warehouse.packing', compact('pickingOrders', 'completedPackings', 'organizations', 'perPage'));
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

            return redirect()->route('warehouse.packing.queue')
                ->with('success', "Packing {$packing->packing_number} selesai. Order siap dikirim (READY_TO_SHIP).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroyPacking($id)
    {
        $order = Order::with(['items', 'pickings'])->findOrFail($id);
        $user = Auth::user();

        if ($order->status !== 'PICKING') {
            return back()->with('error', "Order {$order->order_number} tidak berada dalam status antrean pengepakan (PICKING).");
        }

        try {
            DB::transaction(function () use ($order, $user) {
                AuditTrailService::log('CANCEL_PICKING', $order, [
                    'status' => 'PICKING',
                ], [
                    'status' => 'ALLOCATED',
                ], $user);

                foreach ($order->items as $item) {
                    $item->qty_picked = 0;
                    $item->save();
                }

                $order->pickings()->delete();

                $order->status = 'ALLOCATED';
                $order->save();
            });

            return redirect()->route('warehouse.packing.queue')
                ->with('success', "Order {$order->order_number} berhasil dihapus dari antrean packing dan dikembalikan ke antrean picking.");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal mengembalikan order ke antrean picking: '.$e->getMessage());
        }
    }
}
