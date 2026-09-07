<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Order;
use App\Models\StockBalance;
use App\Models\SwitchingStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class SwitchingStockService
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    public function findAlternativeSources(Item $item, int $requiredQty, int $excludeOrgId): array
    {
        // Find branches/warehouses where available stock > safety_stock
        $balances = StockBalance::with(['warehouse.organization'])
            ->where('item_id', $item->id)
            ->whereHas('warehouse.organization', function ($q) use ($excludeOrgId) {
                $q->where('id', '!=', $excludeOrgId)->where('is_active', true);
            })
            ->get();

        $recommendations = [];
        foreach ($balances as $sb) {
            $available = $sb->available;
            $excess = max(0, $available - $item->safety_stock);

            if ($excess > 0) {
                $recommendations[] = [
                    'warehouse_id' => $sb->warehouse_id,
                    'warehouse_name' => $sb->warehouse->name,
                    'organization_id' => $sb->warehouse->organization_id,
                    'organization_name' => $sb->warehouse->organization->name,
                    'city' => $sb->warehouse->organization->city,
                    'available_stock' => $available,
                    'safety_stock' => $item->safety_stock,
                    'excess_stock' => $excess,
                    'recommended_qty' => min($requiredQty, $excess),
                ];
            }
        }

        // Sort by excess stock descending
        usort($recommendations, fn ($a, $b) => $b['excess_stock'] <=> $a['excess_stock']);

        return $recommendations;
    }

    public function getSwitchingRecommendations(Order $order): array
    {
        $recommendations = [];
        $centralWarehouse = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();

        foreach ($order->items as $orderItem) {
            $centralAvailable = 0;
            if ($centralWarehouse) {
                $sb = $this->stockLedgerService->getOrCreateBalance($centralWarehouse, $orderItem->item);
                $centralAvailable = $sb->available;
            }

            $deficit = $orderItem->qty_requested - $centralAvailable;
            if ($deficit > 0) {
                $alternatives = $this->findAlternativeSources(
                    $orderItem->item,
                    $deficit,
                    $order->requesting_organization_id
                );

                if (! empty($alternatives)) {
                    $recommendations[] = [
                        'item' => $orderItem->item,
                        'deficit' => $deficit,
                        'central_available' => $centralAvailable,
                        'alternatives' => $alternatives,
                    ];
                }
            }
        }

        return $recommendations;
    }

    public function proposeSwitching(
        Order $order,
        Item $item,
        int $sourceWarehouseId,
        int $qty,
        string $reason,
        User $user
    ): SwitchingStock {
        return DB::transaction(function () use ($order, $item, $sourceWarehouseId, $qty, $reason, $user) {
            $sourceWarehouse = Warehouse::findOrFail($sourceWarehouseId);
            $destWarehouse = $order->requestingWarehouse ?: Warehouse::where('organization_id', $order->requesting_organization_id)->firstOrFail();

            $switching = SwitchingStock::create([
                'order_id' => $order->id,
                'item_id' => $item->id,
                'source_organization_id' => $sourceWarehouse->organization_id,
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_organization_id' => $order->requesting_organization_id,
                'destination_warehouse_id' => $destWarehouse->id,
                'qty_requested' => $qty,
                'proposed_by_user_id' => $user->id,
                'status' => 'PROPOSED',
                'recommendation_reason' => $reason,
            ]);

            AuditTrailService::log('PROPOSE_SWITCHING_STOCK', $switching, null, $switching->toArray(), $user);

            NotificationService::sendActionRequired(
                "Proposal Switching Stock {$order->order_number}",
                "Pengajuan pemenuhan {$qty} {$item->uom} {$item->name} dari {$sourceWarehouse->name} memerlukan persetujuan.",
                'SWITCHING_APPROVER',
                $sourceWarehouse->organization_id,
                'SWITCHING_STOCK',
                $switching->id,
                "/orders/{$order->id}"
            );

            return $switching;
        });
    }

    public function approveSwitching(SwitchingStock $switching, User $approver): SwitchingStock
    {
        return DB::transaction(function () use ($switching, $approver) {
            $switching->status = 'APPROVED';
            $switching->approved_by_user_id = $approver->id;
            $switching->save();

            // Reserve stock at source warehouse
            $this->stockLedgerService->reserveStock($switching->sourceWarehouse, $switching->item, $switching->qty_requested);

            AuditTrailService::log('APPROVE_SWITCHING_STOCK', $switching, null, ['status' => 'APPROVED'], $approver);

            NotificationService::sendInfo(
                'Switching Stock Disetujui',
                "Switching stock {$switching->qty_requested} unit untuk order #{$switching->order->order_number} telah disetujui.",
                null,
                'INVENTORY_OFFICER',
                "/orders/{$switching->order_id}"
            );

            return $switching;
        });
    }
}
