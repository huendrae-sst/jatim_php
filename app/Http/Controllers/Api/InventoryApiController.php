<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discrepancy;
use App\Models\Item;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\ForecastingService;
use App\Services\StockLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    public function __construct(
        protected StockLedgerService $stockLedgerService,
        protected ForecastingService $forecastingService
    ) {}

    /**
     * System Health Check
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'UP',
            'system' => 'Bank Jatim JIMS API Gateway',
            'version' => '2.4.0',
            'timestamp' => now()->toIso8601String(),
            'database' => 'CONNECTED',
        ]);
    }

    /**
     * Get Real-time Stock Balance for a specific Warehouse & Item SKU
     */
    public function getStockBalance(int $warehouseId, string $sku): JsonResponse
    {
        $warehouse = Warehouse::find($warehouseId);
        if (! $warehouse) {
            return response()->json(['error' => 'Warehouse not found'], 404);
        }

        $item = Item::where('sku', $sku)->first();
        if (! $item) {
            return response()->json(['error' => 'Item SKU not found'], 404);
        }

        $balance = $this->stockLedgerService->getOrCreateBalance($warehouse, $item);

        return response()->json([
            'warehouse' => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
            ],
            'item' => [
                'id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'uom' => $item->uom,
                'safety_stock' => $item->safety_stock,
                'reorder_point' => $item->reorder_point,
            ],
            'stock' => [
                'on_hand' => $balance->on_hand,
                'reserved' => $balance->reserved,
                'hold' => $balance->hold,
                'damaged' => $balance->damaged,
                'available' => $balance->available,
            ],
            'is_below_rop' => $balance->available <= $item->reorder_point,
        ]);
    }

    /**
     * Scan Barcode / SKU Validation Endpoint
     * Used by Mobile Scanners & Web Scanner Widgets
     */
    public function scanBarcode(Request $request): JsonResponse
    {
        $code = trim((string) $request->query('code'));
        if (! $code) {
            return response()->json(['valid' => false, 'message' => 'Parameter code is required'], 400);
        }

        // Match by SKU or barcode or Order/Shipment number
        $item = Item::with('category')->where('sku', $code)->orWhere('barcode', $code)->first();
        if ($item) {
            $totalAvailable = StockBalance::where('item_id', $item->id)->sum('available');

            return response()->json([
                'type' => 'ITEM',
                'valid' => true,
                'data' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'barcode' => $item->barcode,
                    'name' => $item->name,
                    'category' => $item->category->name,
                    'uom' => $item->uom,
                    'unit_price' => (float) $item->estimated_unit_price,
                    'total_available' => $totalAvailable,
                ],
            ]);
        }

        // Check if it's a Shipment Tracking / AWB
        $shipment = Shipment::with(['order.requestingOrganization', 'courier', 'items.orderItem.item'])
            ->where('tracking_number', $code)
            ->orWhere('manifest_number', $code)
            ->first();

        if ($shipment) {
            return response()->json([
                'type' => 'SHIPMENT',
                'valid' => true,
                'data' => [
                    'id' => $shipment->id,
                    'manifest_number' => $shipment->manifest_number,
                    'tracking_number' => $shipment->tracking_number,
                    'courier' => $shipment->courier->name,
                    'destination' => $shipment->order->requestingOrganization->name,
                    'status' => $shipment->status,
                    'koli_count' => $shipment->koli_count,
                    'total_weight_kg' => (float) $shipment->total_weight_kg,
                    'items' => $shipment->items->map(fn ($it) => [
                        'name' => $it->orderItem->item->name,
                        'sku' => $it->orderItem->item->sku,
                        'qty_shipped' => $it->qty_shipped,
                    ]),
                ],
            ]);
        }

        // Check if it's an Order Number
        $order = Order::with(['requestingOrganization', 'items.item'])->where('order_number', $code)->first();
        if ($order) {
            return response()->json([
                'type' => 'ORDER',
                'valid' => true,
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'unit' => $order->requestingOrganization->name,
                    'status' => $order->status,
                    'items_count' => $order->items->count(),
                ],
            ]);
        }

        return response()->json([
            'valid' => false,
            'message' => "Kode '{$code}' tidak ditemukan dalam katalog barang, manifest pengiriman, atau nomor order.",
        ], 404);
    }

    /**
     * Track Shipment Status API
     */
    public function trackShipment(string $trackingNumber): JsonResponse
    {
        $shipment = Shipment::with(['order.requestingOrganization', 'originWarehouse', 'destinationWarehouse', 'courier', 'items.orderItem.item', 'receiver'])
            ->where('tracking_number', $trackingNumber)
            ->orWhere('manifest_number', $trackingNumber)
            ->first();

        if (! $shipment) {
            return response()->json(['error' => 'Shipment not found'], 404);
        }

        return response()->json([
            'manifest_number' => $shipment->manifest_number,
            'tracking_number' => $shipment->tracking_number,
            'courier' => [
                'name' => $shipment->courier->name,
                'service' => $shipment->service_type,
            ],
            'origin' => $shipment->originWarehouse->name,
            'destination' => $shipment->destinationWarehouse->name,
            'status' => $shipment->status,
            'koli_count' => $shipment->koli_count,
            'total_weight_kg' => (float) $shipment->total_weight_kg,
            'shipping_cost' => (float) $shipment->shipping_cost,
            'dispatched_at' => $shipment->dispatched_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            'receiver' => $shipment->recipient_name,
            'items' => $shipment->items->map(fn ($it) => [
                'item_name' => $it->orderItem->item->name,
                'sku' => $it->orderItem->item->sku,
                'qty_shipped' => $it->qty_shipped,
                'qty_received' => $it->qty_received,
            ]),
        ]);
    }

    /**
     * Dashboard Real-time KPI Stats Endpoint
     */
    public function getKpis(): JsonResponse
    {
        $allBalances = StockBalance::with('item')->get();
        $totalStockValue = $allBalances->sum(fn ($sb) => $sb->on_hand * ($sb->item->estimated_unit_price ?? 0));
        $availableStockValue = $allBalances->sum(fn ($sb) => $sb->available * ($sb->item->estimated_unit_price ?? 0));
        $reservedStockValue = $allBalances->sum(fn ($sb) => $sb->reserved * ($sb->item->estimated_unit_price ?? 0));

        return response()->json([
            'valuation' => [
                'total' => (float) $totalStockValue,
                'available' => (float) $availableStockValue,
                'reserved' => (float) $reservedStockValue,
            ],
            'counters' => [
                'orders_in_transit' => Shipment::where('status', 'IN_TRANSIT')->count(),
                'orders_waiting_approval' => Order::where('status', 'WAITING_APPROVAL')->count(),
                'ready_to_ship' => Order::where('status', 'READY_TO_SHIP')->count(),
                'discrepancies' => Discrepancy::where('resolution_status', 'REPORTED')->count(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
