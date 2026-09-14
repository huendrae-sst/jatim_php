<?php

namespace App\Services;

use App\Models\InventoryReturn;
use App\Models\InventoryReturnItem;
use App\Models\Item;
use App\Models\Order;
use App\Models\StockDestruction;
use App\Models\StockDestructionItem;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class ReverseInventoryService
{
    public function __construct(
        protected StockLedgerService $stockService
    ) {}

    /**
     * Maker: Cabang mengajukan retur barang (POC-41)
     */
    public function createReturn(
        Warehouse $originWarehouse,
        Warehouse $destinationWarehouse,
        array $itemsData,
        string $reason,
        User $user,
        ?string $reasonDetails = null,
        ?Order $order = null
    ): InventoryReturn {
        return DB::transaction(function () use ($originWarehouse, $destinationWarehouse, $itemsData, $reason, $user, $reasonDetails, $order) {
            $seq = InventoryReturn::count() + 1;
            $returnNumber = 'RET/'.date('Ym').'/'.sprintf('%04d', $seq);

            $return = InventoryReturn::create([
                'return_number' => $returnNumber,
                'order_id' => $order?->id,
                'origin_warehouse_id' => $originWarehouse->id,
                'destination_warehouse_id' => $destinationWarehouse->id,
                'status' => 'REQUESTED',
                'reason' => $reason,
                'reason_details' => $reasonDetails,
                'requested_by_user_id' => $user->id,
            ]);

            foreach ($itemsData as $itemRow) {
                $item = Item::findOrFail($itemRow['item_id']);
                $qty = (int) ($itemRow['qty_returned'] ?? 1);
                $unitPrice = (float) ($itemRow['unit_price'] ?? $item->estimated_unit_price);
                $condition = $itemRow['condition'] ?? 'DAMAGED';

                InventoryReturnItem::create([
                    'inventory_return_id' => $return->id,
                    'item_id' => $item->id,
                    'qty_returned' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $qty * $unitPrice,
                    'condition' => $condition,
                    'notes' => $itemRow['notes'] ?? null,
                ]);
            }

            AuditTrailService::log('CREATE_RETURN_REQUEST', $return, null, [
                'return_number' => $return->return_number,
                'origin_warehouse' => $originWarehouse->name,
                'items_count' => count($itemsData),
                'total_qty' => $return->total_qty,
                'reason' => $reason,
            ], $user);

            return $return->load('items.item', 'originWarehouse', 'destinationWarehouse');
        });
    }

    /**
     * Checker: Otorisasi persetujuan pengajuan retur oleh Pusat (POC-41)
     */
    public function approveReturn(InventoryReturn $return, User $user, ?string $notes = null): InventoryReturn
    {
        if ($return->status !== 'REQUESTED') {
            throw new Exception("Retur {$return->return_number} tidak dalam status permohonan (Status: {$return->status}).");
        }

        $return->update([
            'status' => 'APPROVED',
            'approved_by_user_id' => $user->id,
            'notes' => $notes ?: $return->notes,
        ]);

        AuditTrailService::log('APPROVE_RETURN_REQUEST', $return, null, [
            'return_number' => $return->return_number,
            'approver' => $user->name,
        ], $user);

        return $return;
    }

    /**
     * Checker: Penolakan pengajuan retur oleh Pusat
     */
    public function rejectReturn(InventoryReturn $return, string $reason, User $user): InventoryReturn
    {
        if ($return->status !== 'REQUESTED') {
            throw new Exception("Retur {$return->return_number} tidak dalam status permohonan.");
        }

        $return->update([
            'status' => 'REJECTED',
            'approved_by_user_id' => $user->id,
            'rejection_reason' => $reason,
        ]);

        AuditTrailService::log('REJECT_RETURN_REQUEST', $return, null, [
            'return_number' => $return->return_number,
            'reason' => $reason,
        ], $user);

        return $return;
    }

    /**
     * Cabang mengirimkan barang retur ke Pusat (POC-41)
     */
    public function shipReturn(InventoryReturn $return, string $courierName, string $trackingNumber, User $user): InventoryReturn
    {
        if ($return->status !== 'APPROVED') {
            throw new Exception("Retur {$return->return_number} belum disetujui atau sudah diproses.");
        }

        return DB::transaction(function () use ($return, $courierName, $trackingNumber, $user) {
            $return->update([
                'status' => 'SHIPPED',
                'courier_name' => $courierName,
                'tracking_number' => $trackingNumber,
                'shipped_at' => now(),
            ]);

            // Mutasi stok cabang berkurang (RETURN_OUT)
            foreach ($return->items as $retItem) {
                $this->stockService->recordReturnOut(
                    $return->originWarehouse,
                    $retItem->item,
                    $retItem->qty_returned,
                    $return->return_number,
                    $retItem->condition,
                    $user
                );
            }

            AuditTrailService::log('SHIP_RETURN', $return, null, [
                'return_number' => $return->return_number,
                'courier' => $courierName,
                'tracking_number' => $trackingNumber,
            ], $user);

            return $return;
        });
    }

    /**
     * Gudang Pusat menerima fisik barang retur (POC-41)
     */
    public function receiveReturn(InventoryReturn $return, array $receivedItems, User $user): InventoryReturn
    {
        if ($return->status !== 'SHIPPED') {
            throw new Exception("Retur {$return->return_number} belum berstatus dikirim.");
        }

        return DB::transaction(function () use ($return, $receivedItems, $user) {
            $return->update([
                'status' => 'RECEIVED',
                'received_at' => now(),
                'received_by_user_id' => $user->id,
            ]);

            foreach ($receivedItems as $itemRow) {
                $retItem = $return->items()->where('id', $itemRow['return_item_id'])->firstOrFail();
                $qtyGood = (int) ($itemRow['qty_good'] ?? 0);
                $qtyDamaged = (int) ($itemRow['qty_damaged'] ?? 0);

                $retItem->update([
                    'qty_received_good' => $qtyGood,
                    'qty_received_damaged' => $qtyDamaged,
                ]);

                // Mutasi stok pusat bertambah (RETURN_IN) tanpa double-counting
                $this->stockService->recordReturnIn(
                    $return->destinationWarehouse,
                    $retItem->item,
                    $qtyGood,
                    $qtyDamaged,
                    $return->return_number,
                    $user
                );
            }

            AuditTrailService::log('RECEIVE_RETURN', $return, null, [
                'return_number' => $return->return_number,
                'receiver' => $user->name,
            ], $user);

            return $return;
        });
    }

    /**
     * Maker: Pengajuan Pemusnahan Barang (POC-43)
     */
    public function createDestruction(
        Warehouse $warehouse,
        array $itemsData,
        string $reason,
        string $witnessName1,
        string $witnessTitle1,
        string $witnessName2,
        string $witnessTitle2,
        User $user,
        ?string $reasonDetails = null
    ): StockDestruction {
        return DB::transaction(function () use ($warehouse, $itemsData, $reason, $witnessName1, $witnessTitle1, $witnessName2, $witnessTitle2, $user, $reasonDetails) {
            $seq = StockDestruction::count() + 1;
            $destrNumber = 'DST/'.date('Ym').'/'.sprintf('%04d', $seq);
            $baNumber = 'BA-DST/'.date('Ym').'/'.sprintf('%04d', $seq);

            $destruction = StockDestruction::create([
                'destruction_number' => $destrNumber,
                'warehouse_id' => $warehouse->id,
                'status' => 'REQUESTED',
                'reason' => $reason,
                'reason_details' => $reasonDetails,
                'berita_acara_number' => $baNumber,
                'witness_name_1' => $witnessName1,
                'witness_title_1' => $witnessTitle1,
                'witness_name_2' => $witnessName2,
                'witness_title_2' => $witnessTitle2,
                'requested_by_user_id' => $user->id,
            ]);

            foreach ($itemsData as $itemRow) {
                $item = Item::findOrFail($itemRow['item_id']);
                $qty = (int) ($itemRow['qty'] ?? 1);
                $unitPrice = (float) ($itemRow['unit_price'] ?? $item->estimated_unit_price);

                StockDestructionItem::create([
                    'stock_destruction_id' => $destruction->id,
                    'item_id' => $item->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'total_loss_value' => $qty * $unitPrice,
                    'batch_or_serial_number' => $itemRow['batch_or_serial_number'] ?? null,
                    'notes' => $itemRow['notes'] ?? null,
                ]);
            }

            AuditTrailService::log('CREATE_DESTRUCTION_REQUEST', $destruction, null, [
                'destruction_number' => $destruction->destruction_number,
                'ba_number' => $baNumber,
                'warehouse' => $warehouse->name,
                'items_count' => count($itemsData),
                'total_qty' => $destruction->total_qty,
                'total_loss' => $destruction->total_loss_value,
            ], $user);

            return $destruction->load('items.item', 'warehouse');
        });
    }

    /**
     * Checker: Otorisasi persetujuan pemusnahan (POC-43)
     */
    public function approveDestruction(StockDestruction $destruction, User $user): StockDestruction
    {
        if ($destruction->status !== 'REQUESTED') {
            throw new Exception("Pengajuan pemusnahan {$destruction->destruction_number} tidak dalam status menunggu persetujuan.");
        }

        $destruction->update([
            'status' => 'APPROVED',
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        AuditTrailService::log('APPROVE_DESTRUCTION', $destruction, null, [
            'destruction_number' => $destruction->destruction_number,
            'approver' => $user->name,
        ], $user);

        return $destruction;
    }

    /**
     * Eksekusi pemusnahan fisik dan mutasi stok DESTROYED (POC-43)
     */
    public function executeDestruction(StockDestruction $destruction, User $user, ?string $executionNotes = null): StockDestruction
    {
        if ($destruction->status !== 'APPROVED') {
            throw new Exception("Pemusnahan {$destruction->destruction_number} belum disetujui (Status: {$destruction->status}).");
        }

        return DB::transaction(function () use ($destruction, $user, $executionNotes) {
            $destruction->update([
                'status' => 'EXECUTED',
                'executed_by_user_id' => $user->id,
                'executed_at' => now(),
                'execution_notes' => $executionNotes,
            ]);

            // Mutasi stok berkurang dengan tipe DESTROYED
            foreach ($destruction->items as $destItem) {
                $this->stockService->recordDestruction(
                    $destruction->warehouse,
                    $destItem->item,
                    $destItem->qty,
                    $destruction->berita_acara_number,
                    $user,
                    "Pemusnahan resmi sesuai {$destruction->berita_acara_number}: {$destruction->reason}"
                );
            }

            AuditTrailService::log('EXECUTE_DESTRUCTION', $destruction, null, [
                'destruction_number' => $destruction->destruction_number,
                'ba_number' => $destruction->berita_acara_number,
                'executor' => $user->name,
            ], $user);

            return $destruction;
        });
    }
}
