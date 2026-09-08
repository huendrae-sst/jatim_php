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

    /**
     * Cari rekomendasi cabang alternatif yang mencakup seluruh item yang dibutuhkan.
     */
    public function findAlternativeSourcesForMultipleItems(array $itemsList, int $excludeOrgId): array
    {
        if (empty($itemsList)) {
            return [];
        }

        $itemIds = array_column($itemsList, 'item_id');
        $itemsMap = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        $warehouses = Warehouse::with('organization')
            ->whereHas('organization', function ($q) use ($excludeOrgId) {
                $q->where('id', '!=', $excludeOrgId)->where('is_active', true);
            })
            ->where('is_active', true)
            ->get();

        $warehouseIds = $warehouses->pluck('id')->all();
        $balances = StockBalance::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->groupBy('warehouse_id');

        $recommendations = [];

        foreach ($warehouses as $wh) {
            $whBalances = $balances->get($wh->id, collect())->keyBy('item_id');
            $itemsDetail = [];
            $fullyCoveredCount = 0;
            $anyExcessCount = 0;
            $totalExcessQty = 0;

            foreach ($itemsList as $req) {
                $itemId = $req['item_id'];
                $item = $itemsMap->get($itemId);
                if (! $item) {
                    continue;
                }

                $qtyNeeded = (int) $req['qty'];
                $sb = $whBalances->get($itemId);
                $available = $sb ? $sb->available : 0;
                $excess = max(0, $available - $item->safety_stock);

                $isFullyCovered = ($excess >= $qtyNeeded && $qtyNeeded > 0);
                $isPartiallyCovered = ($excess > 0);

                if ($isFullyCovered) {
                    $fullyCoveredCount++;
                }
                if ($isPartiallyCovered) {
                    $anyExcessCount++;
                }

                $totalExcessQty += $excess;

                $itemsDetail[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'sku' => $item->sku,
                    'uom' => $item->uom,
                    'qty_needed' => $qtyNeeded,
                    'available_stock' => $available,
                    'safety_stock' => $item->safety_stock,
                    'excess_stock' => $excess,
                    'fulfilled_qty' => min($qtyNeeded, $excess),
                    'is_fully_covered' => $isFullyCovered,
                    'is_partially_covered' => $isPartiallyCovered,
                ];
            }

            if ($anyExcessCount > 0) {
                $totalItemsCount = count($itemsList);
                $isAllCovered = ($fullyCoveredCount === $totalItemsCount);

                $firstItemExcess = ! empty($itemsDetail[0]) ? $itemsDetail[0]['excess_stock'] : $totalExcessQty;

                $recommendations[] = [
                    'warehouse_id' => $wh->id,
                    'warehouse_name' => $wh->name,
                    'organization_id' => $wh->organization_id,
                    'organization_name' => $wh->organization->name,
                    'city' => $wh->organization->city,
                    'total_items_count' => $totalItemsCount,
                    'fully_covered_count' => $fullyCoveredCount,
                    'any_excess_count' => $anyExcessCount,
                    'is_all_covered' => $isAllCovered,
                    'total_excess_qty' => $totalExcessQty,
                    'excess_stock' => $firstItemExcess,
                    'items' => $itemsDetail,
                ];
            }
        }

        usort($recommendations, function ($a, $b) {
            if ($a['is_all_covered'] !== $b['is_all_covered']) {
                return $b['is_all_covered'] ? -1 : 1;
            }
            if ($a['fully_covered_count'] !== $b['fully_covered_count']) {
                return $b['fully_covered_count'] <=> $a['fully_covered_count'];
            }
            if ($a['any_excess_count'] !== $b['any_excess_count']) {
                return $b['any_excess_count'] <=> $a['any_excess_count'];
            }

            return $b['total_excess_qty'] <=> $a['total_excess_qty'];
        });

        return $recommendations;
    }

    /**
     * Cari rekomendasi sumber stok per masing-masing barang (item-level recommendations).
     *
     * @param  array<int, array{item_id: int, qty: int}>  $itemsList
     * @return array<int, array{item_id: int, item_name: string, sku: string, uom: string, qty_needed: int, safety_stock: int, has_recommendation: bool, sources: array}>
     */
    public function getItemRecommendations(array $itemsList, int $excludeOrgId): array
    {
        if (empty($itemsList)) {
            return [];
        }

        $itemIds = array_column($itemsList, 'item_id');
        $itemsMap = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        $warehouses = Warehouse::with('organization')
            ->whereHas('organization', function ($q) use ($excludeOrgId) {
                $q->where('id', '!=', $excludeOrgId)->where('is_active', true);
            })
            ->where('is_active', true)
            ->get();

        $warehouseIds = $warehouses->pluck('id')->all();
        $balances = StockBalance::whereIn('warehouse_id', $warehouseIds)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->groupBy('item_id');

        $results = [];

        foreach ($itemsList as $req) {
            $itemId = (int) $req['item_id'];
            $item = $itemsMap->get($itemId);
            if (! $item) {
                continue;
            }

            $qtyNeeded = (int) ($req['qty'] ?? 1);
            $itemBalances = $balances->get($itemId, collect());

            $sources = [];
            foreach ($warehouses as $wh) {
                $sb = $itemBalances->firstWhere('warehouse_id', $wh->id);
                $available = $sb ? $sb->available : 0;
                $excess = max(0, $available - $item->safety_stock);

                if ($excess > 0) {
                    $sources[] = [
                        'warehouse_id' => $wh->id,
                        'warehouse_name' => $wh->name,
                        'organization_id' => $wh->organization_id,
                        'organization_name' => $wh->organization->name,
                        'city' => $wh->organization->city,
                        'available_stock' => $available,
                        'safety_stock' => $item->safety_stock,
                        'excess_stock' => $excess,
                        'is_fully_covered' => ($excess >= $qtyNeeded && $qtyNeeded > 0),
                    ];
                }
            }

            usort($sources, function ($a, $b) {
                if ($a['is_fully_covered'] !== $b['is_fully_covered']) {
                    return $b['is_fully_covered'] ? -1 : 1;
                }

                return $b['excess_stock'] <=> $a['excess_stock'];
            });

            $results[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'sku' => $item->sku,
                'uom' => $item->uom,
                'qty_needed' => $qtyNeeded,
                'safety_stock' => $item->safety_stock,
                'has_recommendation' => ! empty($sources),
                'sources' => $sources,
            ];
        }

        return $results;
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

            $switching->items()->create([
                'item_id' => $item->id,
                'qty_requested' => $qty,
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

    public function createManualSwitching(array $data, User $user): SwitchingStock
    {
        return DB::transaction(function () use ($data, $user) {
            $sourceWarehouse = Warehouse::findOrFail($data['source_warehouse_id']);
            $destWarehouse = Warehouse::findOrFail($data['destination_warehouse_id']);

            $itemsList = [];
            if (! empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $it) {
                    if (! empty($it['item_id']) && ! empty($it['qty_requested'])) {
                        $itemsList[] = [
                            'item_id' => (int) $it['item_id'],
                            'qty_requested' => (int) $it['qty_requested'],
                        ];
                    }
                }
            } elseif (! empty($data['item_id'])) {
                $itemsList[] = [
                    'item_id' => (int) $data['item_id'],
                    'qty_requested' => (int) ($data['qty_requested'] ?? 1),
                ];
            }

            if (empty($itemsList)) {
                throw new \InvalidArgumentException('Minimal harus ada 1 barang yang diajukan dalam switching stock.');
            }

            $primaryItem = Item::findOrFail($itemsList[0]['item_id']);
            $totalQty = array_sum(array_column($itemsList, 'qty_requested'));

            $switching = SwitchingStock::create([
                'order_id' => $data['order_id'] ?? null,
                'item_id' => $primaryItem->id,
                'source_organization_id' => $sourceWarehouse->organization_id,
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_organization_id' => $destWarehouse->organization_id,
                'destination_warehouse_id' => $destWarehouse->id,
                'qty_requested' => $totalQty,
                'proposed_by_user_id' => $user->id,
                'status' => 'PROPOSED',
                'recommendation_reason' => $data['recommendation_reason'] ?? null,
            ]);

            foreach ($itemsList as $it) {
                $switching->items()->create([
                    'item_id' => $it['item_id'],
                    'qty_requested' => $it['qty_requested'],
                ]);
            }

            AuditTrailService::log('CREATE_MANUAL_SWITCHING_STOCK', $switching, null, $switching->toArray(), $user);

            $notifDesc = count($itemsList) > 1
                ? 'Pengajuan manual switching '.count($itemsList)." jenis barang (total {$totalQty} unit) dari {$sourceWarehouse->name} ke {$destWarehouse->name} memerlukan persetujuan."
                : "Pengajuan manual switching {$totalQty} {$primaryItem->uom} {$primaryItem->name} dari {$sourceWarehouse->name} ke {$destWarehouse->name} memerlukan persetujuan.";

            NotificationService::sendActionRequired(
                'Proposal Switching Stock Manual',
                $notifDesc,
                'SWITCHING_APPROVER',
                $sourceWarehouse->organization_id,
                'SWITCHING_STOCK',
                $switching->id,
                '/inventory/switching-stocks'
            );

            return $switching;
        });
    }

    public function updateSwitching(SwitchingStock $switching, array $data, User $user): SwitchingStock
    {
        if ($switching->status !== 'PROPOSED') {
            throw new \Exception('Hanya switching stock berstatus PROPOSED yang dapat diubah.');
        }

        return DB::transaction(function () use ($switching, $data, $user) {
            $oldData = $switching->toArray();

            $sourceWarehouse = Warehouse::findOrFail($data['source_warehouse_id']);
            $destWarehouse = Warehouse::findOrFail($data['destination_warehouse_id']);

            $itemsList = [];
            if (! empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $it) {
                    if (! empty($it['item_id']) && ! empty($it['qty_requested'])) {
                        $itemsList[] = [
                            'item_id' => (int) $it['item_id'],
                            'qty_requested' => (int) $it['qty_requested'],
                        ];
                    }
                }
            } elseif (! empty($data['item_id'])) {
                $itemsList[] = [
                    'item_id' => (int) $data['item_id'],
                    'qty_requested' => (int) ($data['qty_requested'] ?? 1),
                ];
            }

            if (! empty($itemsList)) {
                $primaryItem = Item::findOrFail($itemsList[0]['item_id']);
                $totalQty = array_sum(array_column($itemsList, 'qty_requested'));

                $switching->update([
                    'item_id' => $primaryItem->id,
                    'source_organization_id' => $sourceWarehouse->organization_id,
                    'source_warehouse_id' => $sourceWarehouse->id,
                    'destination_organization_id' => $destWarehouse->organization_id,
                    'destination_warehouse_id' => $destWarehouse->id,
                    'qty_requested' => $totalQty,
                    'recommendation_reason' => $data['recommendation_reason'] ?? $switching->recommendation_reason,
                ]);

                $switching->items()->delete();
                foreach ($itemsList as $it) {
                    $switching->items()->create([
                        'item_id' => $it['item_id'],
                        'qty_requested' => $it['qty_requested'],
                    ]);
                }
            } else {
                $switching->update([
                    'source_organization_id' => $sourceWarehouse->organization_id,
                    'source_warehouse_id' => $sourceWarehouse->id,
                    'destination_organization_id' => $destWarehouse->organization_id,
                    'destination_warehouse_id' => $destWarehouse->id,
                    'recommendation_reason' => $data['recommendation_reason'] ?? $switching->recommendation_reason,
                ]);
            }

            AuditTrailService::log('UPDATE_SWITCHING_STOCK', $switching, $oldData, $switching->toArray(), $user);

            return $switching;
        });
    }

    public function deleteSwitching(SwitchingStock $switching, User $user): void
    {
        if ($switching->status !== 'PROPOSED') {
            throw new \Exception('Hanya switching stock berstatus PROPOSED yang dapat dihapus.');
        }

        DB::transaction(function () use ($switching, $user) {
            AuditTrailService::log('DELETE_SWITCHING_STOCK', $switching, $switching->toArray(), null, $user);
            $switching->delete();
        });
    }

    public function approveSwitching(SwitchingStock $switching, User $approver): SwitchingStock
    {
        return DB::transaction(function () use ($switching, $approver) {
            $switching->status = 'APPROVED';
            $switching->approved_by_user_id = $approver->id;
            $switching->save();

            // Reserve stock at source warehouse for all items in the switching stock
            $switching->loadMissing('items.item', 'sourceWarehouse', 'item');

            if ($switching->items->isNotEmpty()) {
                foreach ($switching->items as $itemRow) {
                    $this->stockLedgerService->reserveStock(
                        $switching->sourceWarehouse,
                        $itemRow->item,
                        $itemRow->qty_requested
                    );
                }
            } elseif ($switching->item && $switching->qty_requested) {
                $this->stockLedgerService->reserveStock(
                    $switching->sourceWarehouse,
                    $switching->item,
                    $switching->qty_requested
                );
            }

            AuditTrailService::log('APPROVE_SWITCHING_STOCK', $switching, null, ['status' => 'APPROVED'], $approver);

            $refNumber = $switching->order ? "Order #{$switching->order->order_number}" : "Switching Manual #{$switching->id}";
            $itemDesc = $switching->items->count() > 1
                ? "{$switching->items->count()} jenis barang ({$switching->total_qty} unit)"
                : "{$switching->total_qty} unit";

            $targetUrl = $switching->order_id ? "/orders/{$switching->order_id}" : '/inventory/switching-stocks';

            if ($switching->proposed_by_user_id) {
                NotificationService::sendUser(
                    $switching->proposed_by_user_id,
                    'Switching Stock Disetujui',
                    "Pengajuan switching stock {$itemDesc} untuk {$refNumber} telah disetujui.",
                    'INFORMATION',
                    'INFO',
                    'SWITCHING_STOCK',
                    $switching->id,
                    $targetUrl
                );
            }

            if ($switching->order && $switching->order->created_by_user_id !== $switching->proposed_by_user_id) {
                NotificationService::sendUser(
                    $switching->order->created_by_user_id,
                    'Switching Stock Order Disetujui',
                    "Pemenuhan alternatif dari {$switching->sourceWarehouse->name} untuk order {$switching->order->order_number} telah disetujui.",
                    'INFORMATION',
                    'INFO',
                    'SWITCHING_STOCK',
                    $switching->id,
                    "/orders/{$switching->order_id}"
                );
            }

            NotificationService::sendRole(
                'INVENTORY_OFFICER',
                'Switching Stock Disetujui & Stok Direservasi',
                "Switching stock {$itemDesc} untuk {$refNumber} telah disetujui. Stok pada {$switching->sourceWarehouse->name} telah direservasi.",
                null,
                'INFORMATION',
                'INFO',
                'SWITCHING_STOCK',
                $switching->id,
                $targetUrl
            );

            NotificationService::sendActionRequired(
                "Persiapan Transfer Switching Stock ({$refNumber})",
                "Switching stock {$itemDesc} telah disetujui. Mohon gudang {$switching->sourceWarehouse->name} mempersiapkan transfer barang ke {$switching->destinationWarehouse->name}.",
                'WAREHOUSE_OFFICER',
                $switching->source_organization_id,
                'SWITCHING_STOCK',
                $switching->id,
                '/inventory/switching-stocks'
            );

            return $switching;
        });
    }
}
