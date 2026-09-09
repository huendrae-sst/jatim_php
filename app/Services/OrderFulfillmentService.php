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
use App\Models\SwitchingStock;
use App\Models\SwitchingStockItem;
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
        protected SwitchingStockService $switchingStockService,
        protected GeneralLedgerService $generalLedgerService
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
                    } else {
                        $orderItem->qty_allocated = $orderItem->qty_approved;
                    }
                    $orderItem->save();

                    OrderAllocation::create([
                        'order_item_id' => $orderItem->id,
                        'source_warehouse_id' => $centralWarehouse->id,
                        'qty_allocated' => $orderItem->qty_allocated,
                        'allocation_type' => 'DIRECT_WAREHOUSE',
                        'status' => 'RESERVED',
                    ]);
                } else {
                    $orderItem->qty_allocated = $orderItem->qty_approved;
                    $orderItem->save();
                }
            }

            $order->status = 'ALLOCATED';
            $order->save();

            AuditTrailService::log('APPROVE_ORDER', $order, null, ['status' => 'ALLOCATED'], $approver);

            NotificationService::sendUser(
                $order->created_by_user_id,
                "Order {$order->order_number} Disetujui",
                "Order permintaan barang dari unit {$order->requestingOrganization->name} telah disetujui dan dialokasikan untuk pemenuhan gudang.",
                'INFORMATION',
                'INFO',
                'ORDER',
                $order->id,
                "/orders/{$order->id}"
            );

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

            NotificationService::sendUser(
                $order->created_by_user_id,
                "Order {$order->order_number} Ditolak",
                "Order dari unit {$order->requestingOrganization->name} ditolak oleh {$user->name}.".($reason ? " Alasan: {$reason}" : ''),
                'INFORMATION',
                'WARNING',
                'ORDER',
                $order->id,
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
                $item->qty_picked = $item->qty_allocated > 0
                    ? $item->qty_allocated
                    : ($item->qty_approved > 0 ? $item->qty_approved : $item->qty_requested);
                $item->save();
            }

            $order->status = 'PICKING';
            $order->save();

            AuditTrailService::log('GENERATE_PICKING', $picking, null, $picking->toArray(), $user);

            NotificationService::sendUser(
                $order->created_by_user_id,
                "Order {$order->order_number} Sedang Dipersiapkan",
                "Pick list {$picking->picking_number} sedang diproses di gudang logistik pusat.",
                'INFORMATION',
                'INFO',
                'ORDER',
                $order->id,
                "/orders/{$order->id}"
            );

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

            NotificationService::sendUser(
                $order->created_by_user_id,
                "Order {$order->order_number} Selesai Dipacking",
                "Pesanan Anda telah selesai dipacking ({$koliCount} koli, {$totalWeightKg} kg) dan siap diserahkan ke ekspedisi.",
                'INFORMATION',
                'INFO',
                'ORDER',
                $order->id,
                "/orders/{$order->id}"
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

            $courierName = $shipment->courier ? $shipment->courier->name : 'Ekspedisi';

            NotificationService::sendUser(
                $order->created_by_user_id,
                "Pengiriman Order {$order->order_number} Dalam Perjalanan",
                "Manifest {$shipment->manifest_number} via {$courierName} (No Resi: {$trackingNumber}) telah diberangkatkan menuju {$order->requestingOrganization->name}. Estimasi tiba: {$etaDate}.",
                'INFORMATION',
                'INFO',
                'SHIPMENT',
                $shipment->id,
                "/distribution/shipments/{$shipment->id}"
            );

            NotificationService::sendActionRequired(
                "Konfirmasi Penerimaan Pengiriman {$shipment->manifest_number}",
                "Pengiriman order {$order->order_number} via {$courierName} (Resi: {$trackingNumber}) menuju unit Anda. Mohon lakukan konfirmasi penerimaan fisik saat barang tiba.",
                'RECEIVING_OFFICER',
                $order->requesting_organization_id,
                'SHIPMENT',
                $shipment->id,
                "/receiving/confirm/{$shipment->id}"
            );

            return $shipment;
        });
    }

    public function createSwitchingShipment(
        SwitchingStock $switching,
        int $courierId,
        string $serviceType,
        string $trackingNumber,
        float $shippingCost,
        string $etaDate,
        User $user,
        int $koliCount = 1,
        float $totalWeightKg = 1.0,
        ?string $notes = null
    ): Shipment {
        if (! in_array($switching->status, ['APPROVED', 'RESERVED'], true)) {
            throw new Exception('Hanya switching stock dengan status APPROVED atau RESERVED yang dapat dikirim melalui distribusi.');
        }

        return DB::transaction(function () use ($switching, $courierId, $serviceType, $trackingNumber, $shippingCost, $etaDate, $user, $koliCount, $totalWeightKg, $notes) {
            $manifestNumber = 'MNF/'.date('Y/m').'/'.sprintf('%04d', Shipment::count() + 1);

            $shipment = Shipment::create([
                'manifest_number' => $manifestNumber,
                'order_id' => $switching->order_id,
                'switching_stock_id' => $switching->id,
                'origin_warehouse_id' => $switching->source_warehouse_id,
                'destination_organization_id' => $switching->destination_organization_id,
                'courier_id' => $courierId,
                'service_type' => $serviceType,
                'tracking_number' => $trackingNumber,
                'dispatched_by_user_id' => $user->id,
                'koli_count' => $koliCount,
                'total_weight_kg' => $totalWeightKg,
                'shipping_cost' => $shippingCost,
                'eta_date' => $etaDate,
                'status' => 'IN_TRANSIT',
                'dispatched_at' => now(),
            ]);

            // Update switching stock
            $switching->shipment_id = $shipment->id;
            $switching->status = 'TRANSFERRED';
            $switching->transferred_by_user_id = $user->id;
            $switching->transferred_at = now();
            $switching->tracking_number = $trackingNumber;
            $switching->transfer_notes = $notes;
            $switching->save();

            // Mutate stock out from source warehouse & deduct reserved
            $switching->loadMissing('items.item', 'sourceWarehouse', 'destinationWarehouse', 'item', 'destinationOrganization');
            $refNo = $manifestNumber;

            if ($switching->items->isNotEmpty()) {
                foreach ($switching->items as $itemRow) {
                    $this->stockLedgerService->dispatchSwitchingTransfer(
                        $switching->sourceWarehouse,
                        $itemRow->item,
                        $itemRow->qty_requested,
                        $refNo,
                        $user
                    );
                }
            } elseif ($switching->item && $switching->qty_requested) {
                $this->stockLedgerService->dispatchSwitchingTransfer(
                    $switching->sourceWarehouse,
                    $switching->item,
                    $switching->qty_requested,
                    $refNo,
                    $user
                );
            }

            // Record balanced General Ledger journal for inter-branch transfer
            $this->generalLedgerService->recordSwitchingDispatchJournal($switching, $shipment, $user);

            AuditTrailService::log('DISPATCH_SWITCHING_SHIPMENT', $shipment, null, $shipment->toArray(), $user);

            $courierName = $shipment->courier ? $shipment->courier->name : 'Ekspedisi';
            $destName = $switching->destinationOrganization?->name ?? 'Cabang Tujuan';

            NotificationService::sendActionRequired(
                "Konfirmasi Penerimaan Switching Stock {$manifestNumber}",
                "Pengiriman transfer switching stock via {$courierName} (Resi: {$trackingNumber}) dari {$switching->sourceWarehouse->name} menuju unit Anda. Mohon lakukan konfirmasi penerimaan fisik saat barang tiba.",
                'RECEIVING_OFFICER',
                $switching->destination_organization_id,
                'SHIPMENT',
                $shipment->id,
                "/receiving/confirm/{$shipment->id}"
            );

            if ($switching->proposed_by_user_id && $switching->proposed_by_user_id !== $user->id) {
                NotificationService::sendUser(
                    $switching->proposed_by_user_id,
                    'Switching Stock Dikirim',
                    "Pengiriman switching stock ({$manifestNumber}) telah diberangkatkan menuju {$destName}. No Resi: {$trackingNumber}.",
                    'INFORMATION',
                    'INFO',
                    'SHIPMENT',
                    $shipment->id,
                    "/distribution/shipments/{$shipment->id}"
                );
            }

            return $shipment;
        });
    }

    public function processReceiving(
        Shipment $shipment,
        array $receivedItemsData,
        string $podSignature,
        ?string $notes,
        User $user
    ): Receiving {
        return DB::transaction(function () use ($shipment, $receivedItemsData, $podSignature, $notes, $user) {
            $rcvNumber = 'RCV/'.date('Y/m').'/'.sprintf('%04d', Receiving::count() + 1);
            $order = $shipment->order;
            $switching = $shipment->switchingStock;

            $destOrgId = $shipment->destination_organization_id ?: ($order ? $order->requesting_organization_id : null);
            $destWarehouse = null;
            if ($switching && $switching->destinationWarehouse) {
                $destWarehouse = $switching->destinationWarehouse;
            } elseif ($order && $order->requestingWarehouse) {
                $destWarehouse = $order->requestingWarehouse;
            } else {
                $destWarehouse = Warehouse::where('organization_id', $destOrgId)->firstOrFail();
            }

            $hasDiscrepancy = false;

            $receiving = Receiving::create([
                'receiving_number' => $rcvNumber,
                'shipment_id' => $shipment->id,
                'order_id' => $order?->id,
                'switching_stock_id' => $switching?->id,
                'organization_id' => $destOrgId,
                'warehouse_id' => $destWarehouse->id,
                'received_by_user_id' => $user->id,
                'receipt_date' => now(),
                'status' => 'RECEIVED_FULL',
                'pod_signature' => $podSignature,
                'notes' => $notes,
            ]);

            $swReceiptItems = [];

            foreach ($receivedItemsData as $itemData) {
                $qtyGood = (int) ($itemData['qty_good'] ?? 0);
                $qtyDamaged = (int) ($itemData['qty_damaged'] ?? 0);
                $qtyMissing = (int) ($itemData['qty_missing'] ?? 0);

                if (! empty($itemData['switching_stock_item_id'])) {
                    $swItem = SwitchingStockItem::with('item')->findOrFail($itemData['switching_stock_item_id']);
                    $itemModel = $swItem->item;
                    $qtyExpected = $swItem->qty_requested;

                    if ($qtyDamaged > 0 || $qtyMissing > 0) {
                        $hasDiscrepancy = true;
                        if ($qtyDamaged > 0) {
                            Discrepancy::create([
                                'receiving_id' => $receiving->id,
                                'switching_stock_item_id' => $swItem->id,
                                'item_id' => $itemModel->id,
                                'discrepancy_type' => 'DAMAGED',
                                'qty_expected' => $qtyExpected,
                                'qty_actual' => $qtyGood,
                                'qty_damaged' => $qtyDamaged,
                                'resolution_status' => 'REPORTED',
                                'resolution_notes' => "Ditemukan {$qtyDamaged} barang rusak pada penerimaan switching stock.",
                            ]);
                        }
                        if ($qtyMissing > 0) {
                            Discrepancy::create([
                                'receiving_id' => $receiving->id,
                                'switching_stock_item_id' => $swItem->id,
                                'item_id' => $itemModel->id,
                                'discrepancy_type' => 'MISSING',
                                'qty_expected' => $qtyExpected,
                                'qty_actual' => $qtyGood,
                                'qty_damaged' => $qtyMissing,
                                'resolution_status' => 'REPORTED',
                                'resolution_notes' => "Ditemukan {$qtyMissing} barang kurang pada penerimaan switching stock.",
                            ]);
                        }
                    }

                    $this->stockLedgerService->receiveSwitchingTransfer(
                        $destWarehouse,
                        $itemModel,
                        $qtyGood,
                        $qtyDamaged,
                        $rcvNumber,
                        $user
                    );

                    $swReceiptItems[] = [
                        'item' => $itemModel,
                        'qty_good' => $qtyGood,
                        'qty_damaged' => $qtyDamaged,
                    ];
                } elseif (! empty($itemData['order_item_id'])) {
                    $orderItem = OrderItem::with('item')->findOrFail($itemData['order_item_id']);
                    $orderItem->qty_received = $qtyGood;
                    $orderItem->save();

                    if ($qtyDamaged > 0 || $qtyMissing > 0) {
                        $hasDiscrepancy = true;
                        if ($qtyDamaged > 0) {
                            Discrepancy::create([
                                'receiving_id' => $receiving->id,
                                'order_item_id' => $orderItem->id,
                                'item_id' => $orderItem->item_id,
                                'discrepancy_type' => 'DAMAGED',
                                'qty_expected' => $orderItem->qty_shipped,
                                'qty_actual' => $qtyGood,
                                'qty_damaged' => $qtyDamaged,
                                'resolution_status' => 'REPORTED',
                                'resolution_notes' => "Ditemukan {$qtyDamaged} barang rusak pada penerimaan.",
                            ]);
                        }
                        if ($qtyMissing > 0) {
                            Discrepancy::create([
                                'receiving_id' => $receiving->id,
                                'order_item_id' => $orderItem->id,
                                'item_id' => $orderItem->item_id,
                                'discrepancy_type' => 'MISSING',
                                'qty_expected' => $orderItem->qty_shipped,
                                'qty_actual' => $qtyGood,
                                'qty_damaged' => $qtyMissing,
                                'resolution_status' => 'REPORTED',
                                'resolution_notes' => "Ditemukan {$qtyMissing} barang kurang pada penerimaan.",
                            ]);
                        }
                    }

                    $this->stockLedgerService->receiveBranchStock(
                        $destWarehouse,
                        $orderItem->item,
                        $qtyGood,
                        $qtyDamaged,
                        $rcvNumber,
                        $user
                    );
                }
            }

            if (! empty($swReceiptItems)) {
                $sw = $shipment->switchingStock ?: SwitchingStock::find($shipment->switching_stock_id);
                if ($sw) {
                    $this->generalLedgerService->recordSwitchingReceiptJournal($sw, $receiving, $user, $swReceiptItems);
                }
            }

            if ($hasDiscrepancy) {
                $receiving->status = 'DISCREPANCY';
                $receiving->save();
            }

            $shipment->status = 'DELIVERED';
            $shipment->delivered_at = now();
            $shipment->save();

            if ($order) {
                $order->status = 'RECEIVED';
                $order->save();
            }

            if ($switching) {
                $switching->status = 'COMPLETED';
                $switching->received_by_user_id = $user->id;
                $switching->received_at = now();
                $switching->receipt_notes = $notes;
                $switching->save();
            }

            AuditTrailService::log('RECEIVE_SHIPMENT', $receiving, null, $receiving->toArray(), $user);

            if ($order) {
                NotificationService::sendUser(
                    $order->created_by_user_id,
                    "Barang Order {$order->order_number} Telah Diterima",
                    "Penerimaan barang telah dikonfirmasi di {$destWarehouse->name} (No Bukti: {$receiving->receiving_number}). Stok unit Anda telah bertambah.",
                    'INFORMATION',
                    'INFO',
                    'RECEIVING',
                    $receiving->id,
                    "/orders/{$order->id}"
                );

                NotificationService::sendActionRequired(
                    "Settlement Diperlukan untuk Order {$order->order_number}",
                    "Order {$order->order_number} telah selesai diterima di {$destWarehouse->name}. Silakan buat dan proses settlement finansial antarunit.",
                    'FINANCE_OFFICER',
                    null,
                    'ORDER',
                    $order->id,
                    '/finance/settlements'
                );
            }

            if ($switching) {
                if ($switching->proposed_by_user_id) {
                    NotificationService::sendUser(
                        $switching->proposed_by_user_id,
                        "Switching Stock {$shipment->manifest_number} Telah Selesai Diterima",
                        "Barang switching stock telah diterima oleh {$user->name} di {$destWarehouse->name} (No Bukti: {$receiving->receiving_number}). Transaksi selesai.",
                        'INFORMATION',
                        'INFO',
                        'RECEIVING',
                        $receiving->id,
                        '/receiving?tab=history'
                    );
                }

                NotificationService::sendRole(
                    'WAREHOUSE_OFFICER',
                    'Switching Stock Selesai Diterima',
                    "Barang switching stock manifest {$shipment->manifest_number} telah selesai diterima di {$destWarehouse->name}.",
                    $switching->source_organization_id,
                    'INFORMATION',
                    'INFO',
                    'SWITCHING_STOCK',
                    $switching->id,
                    '/receiving?tab=history'
                );
            }

            if ($hasDiscrepancy) {
                $refDesc = $order ? "order {$order->order_number}" : "switching stock manifest {$shipment->manifest_number}";
                NotificationService::sendAlert(
                    "Laporan Discrepancy Penerimaan {$rcvNumber}",
                    "Terdapat selisih/kerusakan barang pada penerimaan {$refDesc} di unit {$destWarehouse->name}.",
                    'HIGH',
                    'DISTRIBUTION_OFFICER',
                    null,
                    'RECEIVING',
                    $receiving->id,
                    '/receiving/discrepancies'
                );
            }

            return $receiving;
        });
    }
}
