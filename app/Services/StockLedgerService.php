<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockLedgerService
{
    public function getOrCreateBalance(Warehouse $warehouse, Item $item): StockBalance
    {
        return StockBalance::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'item_id' => $item->id],
            [
                'on_hand' => 0,
                'reserved' => 0,
                'allocated' => 0,
                'in_transit' => 0,
                'hold' => 0,
                'damaged' => 0,
            ]
        );
    }

    public function postProcurementReceipt(Warehouse $warehouse, Item $item, int $qty, float $unitCost, string $refNo, ?User $user = null): StockLedger
    {
        return DB::transaction(function () use ($warehouse, $item, $qty, $unitCost, $refNo, $user) {
            $balance = $this->getOrCreateBalance($warehouse, $item);
            $balance->on_hand += $qty;
            $balance->save();

            return StockLedger::create([
                'warehouse_id' => $warehouse->id,
                'item_id' => $item->id,
                'transaction_type' => 'PROCUREMENT_RECEIPT',
                'reference_number' => $refNo,
                'qty_in' => $qty,
                'qty_out' => 0,
                'balance_after' => $balance->on_hand,
                'unit_cost' => $unitCost,
                'total_value' => $qty * $unitCost,
                'notes' => "Penerimaan Barang Pengadaan Vendor (PO {$refNo})",
                'created_by_user_id' => $user?->id,
            ]);
        });
    }

    public function reserveStock(Warehouse $warehouse, Item $item, int $qty): StockBalance
    {
        $balance = $this->getOrCreateBalance($warehouse, $item);
        $balance->reserved += $qty;
        $balance->save();

        return $balance;
    }

    public function releaseReservedStock(Warehouse $warehouse, Item $item, int $qty): StockBalance
    {
        $balance = $this->getOrCreateBalance($warehouse, $item);
        $balance->reserved = max(0, $balance->reserved - $qty);
        $balance->save();

        return $balance;
    }

    public function dispatchShipment(Warehouse $warehouse, Item $item, int $qty, string $refNo, ?User $user = null): StockLedger
    {
        return DB::transaction(function () use ($warehouse, $item, $qty, $refNo, $user) {
            $balance = $this->getOrCreateBalance($warehouse, $item);
            $balance->reserved = max(0, $balance->reserved - $qty);
            $balance->on_hand = max(0, $balance->on_hand - $qty);
            $balance->save();

            return StockLedger::create([
                'warehouse_id' => $warehouse->id,
                'item_id' => $item->id,
                'transaction_type' => 'GOODS_ISSUE',
                'reference_number' => $refNo,
                'qty_in' => 0,
                'qty_out' => $qty,
                'balance_after' => $balance->on_hand,
                'unit_cost' => $item->estimated_unit_price,
                'total_value' => $qty * $item->estimated_unit_price,
                'notes' => "Pengeluaran Pengiriman Manifest {$refNo}",
                'created_by_user_id' => $user?->id,
            ]);
        });
    }

    public function receiveBranchStock(Warehouse $warehouse, Item $item, int $qtyAccepted, int $qtyDamaged, string $refNo, ?User $user = null): StockLedger
    {
        return DB::transaction(function () use ($warehouse, $item, $qtyAccepted, $qtyDamaged, $refNo, $user) {
            $balance = $this->getOrCreateBalance($warehouse, $item);
            $balance->on_hand += $qtyAccepted;
            $balance->damaged += $qtyDamaged;
            $balance->save();

            return StockLedger::create([
                'warehouse_id' => $warehouse->id,
                'item_id' => $item->id,
                'transaction_type' => 'GOODS_RECEIPT_UNIT',
                'reference_number' => $refNo,
                'qty_in' => $qtyAccepted + $qtyDamaged,
                'qty_out' => 0,
                'balance_after' => $balance->on_hand,
                'unit_cost' => $item->estimated_unit_price,
                'total_value' => ($qtyAccepted + $qtyDamaged) * $item->estimated_unit_price,
                'notes' => "Penerimaan Barang Cabang (Diterima Baik: {$qtyAccepted}, Rusak: {$qtyDamaged})",
                'created_by_user_id' => $user?->id,
            ]);
        });
    }

    public function adjustStock(Warehouse $warehouse, Item $item, int $qtyDiff, string $type, string $refNo, string $notes, ?User $user = null): StockLedger
    {
        return DB::transaction(function () use ($warehouse, $item, $qtyDiff, $type, $refNo, $notes, $user) {
            $balance = $this->getOrCreateBalance($warehouse, $item);
            $balance->on_hand = max(0, $balance->on_hand + $qtyDiff);
            $balance->save();

            $qtyIn = $qtyDiff > 0 ? $qtyDiff : 0;
            $qtyOut = $qtyDiff < 0 ? abs($qtyDiff) : 0;

            return StockLedger::create([
                'warehouse_id' => $warehouse->id,
                'item_id' => $item->id,
                'transaction_type' => $type,
                'reference_number' => $refNo,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'balance_after' => $balance->on_hand,
                'unit_cost' => $item->estimated_unit_price,
                'total_value' => abs($qtyDiff) * $item->estimated_unit_price,
                'notes' => $notes,
                'created_by_user_id' => $user?->id,
            ]);
        });
    }
}
