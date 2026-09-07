<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Discrepancy;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderAllocation;
use App\Models\OrderItem;
use App\Models\Receiving;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehousePacking;
use App\Models\WarehousePicking;
use Exception;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    public function __construct(
        protected StockLedgerService $stockLedgerService,
        protected SwitchingStockService $switchingStockService
    ) {}

    public function createOrder(
        int $organizationId,
        ?int $warehouseId,
        string $priority,
        ?string $requiredDate,
        string $notes,
        array $items,
        User $user
    ): Order {
        return DB::transaction(function () use ($organizationId, $warehouseId, $priority, $requiredDate, $notes, $items, $user) {
            $orderNumber = 'ORD/'.date('Y/m').'/'.sprintf('%04d', Order::count() + 1);

            $totalItems = 0;
            $totalEstValue = 0;

            foreach ($items as $it) {
                $itemModel = Item::findOrFail($it['item_id']);
                $totalItems += (int) $it['qty'];
                $totalEstValue += ((int) $it['qty'] * (float) $itemModel->estimated_unit_price);
            }

            // Verify requesting branch budget
            $currentYear = (int) date('Y');
            $budget = Budget::where('organization_id', $organizationId)->where('year', $currentYear)->first();
            if ($budget && $budget->available_amount < $totalEstValue) {
                // Warning or alert
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'requesting_organization_id' => $organizationId,
                'requesting_warehouse_id' => $warehouseId,
                'created_by_user_id' => $user->id,
                'priority' => $priority,
                'required_date' => $requiredDate,
                'total_items' => $totalItems,
                'total_estimated_value' => $totalEstValue,
                'status' => 'SUBMITTED',
                'notes' => $notes,
                'submitted_at' => now(),
            ]);

            foreach ($items as $it) {
                $itemModel = Item::findOrFail($it['item_id']);
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $itemModel->id,
                    'qty_requested' => (int) $it['qty'],
                    'qty_approved' => 0,
                    'qty_allocated' => 0,
                    'unit_price_ref' => $itemModel->estimated_unit_price,
                    'subtotal_ref' => (int) $it['qty'] * (float) $itemModel->estimated_unit_price,
                    'notes' => $it['notes'] ?? null,
                ]);
            }

            AuditTrailService::log('CREATE_ORDER', $order, null, $order->toArray(), $user);

            NotificationService::sendActionRequired(
                "Persetujuan Order Permintaan Barang {$order->order_number}",
                "Order dari unit {$order->requestingOrganization->name} senilai Rp ".number_format($order->total_estimated_value, 0, ',', '.').' menunggu persetujuan.',
                'ORDER_APPROVER',
                $organizationId,
                'ORDER',
                $order->id,
                "/orders/{$order->id}"
            );

            return $order;
        });
    }

    public function approveOrder(Order $order, User $approver): Order
    {
        if ($order->created_by_user_id === $approver->id) {
            throw new Exception('Segregation of Duties: Pembuat order tidak boleh menyetujui order sendiri.');
        }

        return DB::transaction(function () use ($order, $approver) {
            $order->status = 'APPROVED';
            $order->approved_by_user_id = $approver->id;
            $order->approved_at = now();
            $order->save();

            // Set approved qty = requested qty
            foreach ($order->items as $item) {
                $item->qty_approved = $item->qty_requested;
                $item->save();
            }

            // Auto Reserve from Central Warehouse where available
            $centralWarehouse = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();

            foreach ($order->items as $orderItem) {
                if ($centralWarehouse) {
                    $sb = $this->stockLedgerService->getOrCreateBalance($centralWarehouse, $orderItem->item);
                    $available = $sb->available;
                    $allocQty = min($orderItem->qty_approved, $available);

                    if ($allocQty > 0) {
                        $this->stockLedgerService->reserveStock($centralWarehouse, $orderItem->item, $allocQty);
                        $orderItem->qty_allocated += $allocQty;
                        $orderItem->save();

                        OrderAllocation::create([
                            'order_item_id' => $orderItem->id,
                            'source_warehouse_id' => $centralWarehouse->id,
                            'qty_allocated' => $allocQty,
                            'allocation_type' => 'DIRECT_WAREHOUSE',
                            'status' => 'RESERVED',
                        ]);
                    }
                }
            }

            $order->status = 'ALLOCATED';
            $order->save();

            AuditTrailService::log('APPROVE_ORDER', $order, null, ['status' => 'ALLOCATED'], $approver);

            NotificationService::sendActionRequired(
                "Order {$order->order_number} Siap Diproses Gudang",
                'Order telah disetujui dan dialokasikan. Mohon lakukan picking dan packing.',
                'WAREHOUSE_OFFICER',
                null,
                'ORDER',
                $order->id,
                '/warehouse/picking'
            );

            $order->load('items');

            return $order;
        });
    }

    public function rejectOrder(Order $order, User $user, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $user, $reason) {
            $order->status = 'REJECTED';
            if ($reason) {
                $order->notes = trim($order->notes.' | Alasan Penolakan: '.$reason);
            }
            $order->save();

            AuditTrailService::log('REJECT_ORDER', $order, null, ['status' => 'REJECTED', 'reason' => $reason], $user);

            NotificationService::sendInfo(
                "Order {$order->order_number} Ditolak",
                "Order dari unit {$order->requestingOrganization->name} ditolak oleh {$user->name}.".($reason ? " Alasan: {$reason}" : ''),
                $order->created_by_user_id,
                null,
                "/orders/{$order->id}"
            );

            return $order;
        });
    }

    public function generatePicking(Order $order, User $user): WarehousePicking
    {
        return DB::transaction(function () use ($order, $user) {
            $pickNumber = 'PICK/'.date('Y/m').'/'.sprintf('%04d', WarehousePicking::count() + 1);
            $centralWarehouse = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();

            $picking = WarehousePicking::create([
                'picking_number' => $pickNumber,
                'order_id' => $order->id,
                'warehouse_id' => $centralWarehouse ? $centralWarehouse->id : 1,
                'picked_by_user_id' => $user->id,
                'status' => 'COMPLETED',
                'picked_at' => now(),
            ]);

            foreach ($order->items as $item) {
                $item->qty_picked = $item->qty_allocated;
                $item->save();
            }

            $order->status = 'PICKING';
            $order->save();

            AuditTrailService::log('GENERATE_PICKING', $picking, null, $picking->toArray(), $user);

            return $picking;
        });
    }

    public function generatePacking(
        Order $order,
        int $koliCount,
        float $totalWeightKg,
        string $dimensions,
        User $user
    ): WarehousePacking {
        return DB::transaction(function () use ($order, $koliCount, $totalWeightKg, $dimensions, $user) {
            $packNumber = 'PACK/'.date('Y/m').'/'.sprintf('%04d', WarehousePacking::count() + 1);
            $centralWarehouse = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();

            $packing = WarehousePacking::create([
                'packing_number' => $packNumber,
                'order_id' => $order->id,
                'warehouse_id' => $centralWarehouse ? $centralWarehouse->id : 1,
                'packed_by_user_id' => $user->id,
                'koli_count' => $koliCount,
                'total_weight_kg' => $totalWeightKg,
                'dimensions_cm' => $dimensions,
                'status' => 'VERIFIED',
                'packed_at' => now(),
            ]);

            foreach ($order->items as $item) {
                $item->qty_packed = $item->qty_picked;
                $item->save();
            }

            $order->status = 'READY_TO_SHIP';
            $order->save();

            AuditTrailService::log('GENERATE_PACKING', $packing, null, $packing->toArray(), $user);

            NotificationService::sendActionRequired(
                "Order {$order->order_number} Siap Kirim",
                "Packing telah selesai ({$koliCount} koli, {$totalWeightKg} kg). Silakan buat manifest & serah terima ekspedisi.",
                'DISTRIBUTION_OFFICER',
                null,
                'ORDER',
                $order->id,
                '/distribution/shipments'
            );

            return $packing;
        });
    }

    public function createShipment(
        Order $order,
        int $courierId,
        string $serviceType,
        string $trackingNumber,
        float $shippingCost,
        string $etaDate,
        User $user
    ): Shipment {
        return DB::transaction(function () use ($order, $courierId, $serviceType, $trackingNumber, $shippingCost, $etaDate, $user) {
            $manifestNumber = 'MNF/'.date('Y/m').'/'.sprintf('%04d', Shipment::count() + 1);
            $centralWarehouse = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
            $packing = $order->packings->last();

            $shipment = Shipment::create([
                'manifest_number' => $manifestNumber,
                'order_id' => $order->id,
                'origin_warehouse_id' => $centralWarehouse ? $centralWarehouse->id : 1,
                'destination_organization_id' => $order->requesting_organization_id,
                'courier_id' => $courierId,
                'service_type' => $serviceType,
                'tracking_number' => $trackingNumber,
                'dispatched_by_user_id' => $user->id,
                'koli_count' => $packing ? $packing->koli_count : 1,
                'total_weight_kg' => $packing ? $packing->total_weight_kg : 1.0,
                'shipping_cost' => $shippingCost,
                'eta_date' => $etaDate,
                'status' => 'IN_TRANSIT',
                'dispatched_at' => now(),
            ]);

            // Mutate stock out from origin warehouse & deduct reserved
            foreach ($order->items as $item) {
                $item->qty_shipped = $item->qty_packed;
                $item->save();

                if ($centralWarehouse && $item->qty_shipped > 0) {
                    $this->stockLedgerService->dispatchShipment(
                        $centralWarehouse,
                        $item->item,
                        $item->qty_shipped,
                        $manifestNumber,
                        $user
                    );
                }
            }

            $order->status = 'IN_TRANSIT';
            $order->save();

            AuditTrailService::log('DISPATCH_SHIPMENT', $shipment, null, $shipment->toArray(), $user);

            NotificationService::sendInfo(
                "Pengiriman Order {$order->order_number} Dalam Perjalanan",
                "Manifest {$shipment->manifest_number} dengan resi {$trackingNumber} telah diberangkatkan menuju {$order->requestingOrganization->name}.",
                $order->created_by_user_id,
                'RECEIVING_OFFICER',
                "/distribution/shipments/{$shipment->id}"
            );

            return $shipment;
        });
    }

    public function processReceiving(
        Shipment $shipment,
        array $receivedItemsData, // [ ['order_item_id' => 1, 'qty_good' => 50, 'qty_damaged' => 0, 'qty_missing' => 0], ... ]
        string $podSignature,
        ?string $notes,
        User $user
    ): Receiving {
        return DB::transaction(function () use ($shipment, $receivedItemsData, $podSignature, $notes, $user) {
            $rcvNumber = 'RCV/'.date('Y/m').'/'.sprintf('%04d', Receiving::count() + 1);
            $order = $shipment->order;
            $destWarehouse = $order->requestingWarehouse ?: Warehouse::where('organization_id', $order->requesting_organization_id)->firstOrFail();

            $hasDiscrepancy = false;

            $receiving = Receiving::create([
                'receiving_number' => $rcvNumber,
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'organization_id' => $order->requesting_organization_id,
                'warehouse_id' => $destWarehouse->id,
                'received_by_user_id' => $user->id,
                'receipt_date' => now(),
                'status' => 'RECEIVED_FULL',
                'pod_signature' => $podSignature,
                'notes' => $notes,
            ]);

            foreach ($receivedItemsData as $itemData) {
                $orderItem = OrderItem::findOrFail($itemData['order_item_id']);
                $qtyGood = (int) $itemData['qty_good'];
                $qtyDamaged = (int) ($itemData['qty_damaged'] ?? 0);
                $qtyMissing = (int) ($itemData['qty_missing'] ?? 0);

                $orderItem->qty_received = $qtyGood;
                $orderItem->save();

                if ($qtyDamaged > 0 || $qtyMissing > 0) {
                    $hasDiscrepancy = true;
                    Discrepancy::create([
                        'receiving_id' => $receiving->id,
                        'order_item_id' => $orderItem->id,
                        'item_id' => $orderItem->item_id,
                        'discrepancy_type' => $qtyDamaged > 0 ? 'DAMAGED' : 'MISSING',
                        'qty_expected' => $orderItem->qty_shipped,
                        'qty_actual' => $qtyGood,
                        'qty_damaged' => $qtyDamaged,
                        'resolution_status' => 'REPORTED',
                        'resolution_notes' => "Ditemukan {$qtyDamaged} rusak dan {$qtyMissing} kurang pada penerimaan.",
                    ]);
                }

                // Add stock to destination warehouse
                $this->stockLedgerService->receiveBranchStock(
                    $destWarehouse,
                    $orderItem->item,
                    $qtyGood,
                    $qtyDamaged,
                    $rcvNumber,
                    $user
                );
            }

            $shipment->status = 'DELIVERED';
            $shipment->delivered_at = now();
            $shipment->save();

            $order->status = 'RECEIVED';
            $order->save();

            if ($hasDiscrepancy) {
                $receiving->status = 'DISCREPANCY';
                $receiving->save();

                NotificationService::sendAlert(
                    "Laporan Discrepancy Penerimaan {$rcvNumber}",
                    "Terdapat selisih/kerusakan barang pada order {$order->order_number}.",
                    'HIGH',
                    'DISTRIBUTION_OFFICER',
                    null,
                    '/receiving/discrepancies'
                );
            }

            AuditTrailService::log('RECEIVE_SHIPMENT', $receiving, null, $receiving->toArray(), $user);

            return $receiving;
        });
    }
}
