<?php

namespace App\Services;

use App\Models\EmbossFile;
use App\Models\Item;
use App\Models\Order;
use App\Models\Organization;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    public function __construct(
        protected StockLedgerService $stockService
    ) {}

    /**
     * Membuat Bon Kerja / Rencana Produksi Personalisasi (POC-30, POC-32)
     */
    public function createProductionOrder(
        Warehouse $warehouse,
        Organization $destinationOrg,
        array $itemsData,
        User $user,
        ?EmbossFile $embossFile = null,
        ?Order $order = null,
        ?string $notes = null
    ): ProductionOrder {
        return DB::transaction(function () use ($warehouse, $destinationOrg, $itemsData, $user, $embossFile, $order, $notes) {
            $seq = ProductionOrder::count() + 1;
            $prodNumber = 'PROD/'.date('Ym').'/'.sprintf('%04d', $seq);

            $prodOrder = ProductionOrder::create([
                'production_number' => $prodNumber,
                'emboss_file_id' => $embossFile?->id,
                'order_id' => $order?->id,
                'warehouse_id' => $warehouse->id,
                'destination_organization_id' => $destinationOrg->id,
                'status' => 'PLANNED',
                'notes' => $notes,
                'created_by_user_id' => $user->id,
            ]);

            foreach ($itemsData as $itemRow) {
                $item = Item::findOrFail($itemRow['item_id']);
                $qty = (int) ($itemRow['qty_planned'] ?? 1);

                ProductionOrderItem::create([
                    'production_order_id' => $prodOrder->id,
                    'item_id' => $item->id,
                    'qty_planned' => $qty,
                    'qty_issued' => 0,
                    'notes' => $itemRow['notes'] ?? null,
                ]);
            }

            AuditTrailService::log('CREATE_PRODUCTION_ORDER', $prodOrder, null, [
                'production_number' => $prodOrder->production_number,
                'destination' => $destinationOrg->name,
                'items_count' => count($itemsData),
                'total_planned_qty' => $prodOrder->total_planned_qty,
            ], $user);

            return $prodOrder->load('items.item', 'destinationOrganization', 'warehouse');
        });
    }

    /**
     * Pengeluaran Stok Bahan Blankcard/Token/KUE untuk Proses Produksi (POC-32)
     * Proteksi Over-Issue: Menolak jika pengeluaran melebihi bon atau melebihi stok yang tersedia.
     *
     * @throws Exception
     */
    public function issueProductionStock(ProductionOrder $prodOrder, array $issuedItems, User $user): ProductionOrder
    {
        if (in_array($prodOrder->status, ['COMPLETED', 'CANCELLED'], true)) {
            throw new Exception("Bon produksi {$prodOrder->production_number} sudah selesai atau dibatalkan.");
        }

        return DB::transaction(function () use ($prodOrder, $issuedItems, $user) {
            foreach ($issuedItems as $row) {
                $prodItem = $prodOrder->items()->where('id', $row['production_order_item_id'])->firstOrFail();
                $qtyToIssue = (int) ($row['qty_to_issue'] ?? 0);

                if ($qtyToIssue <= 0) {
                    continue;
                }

                // 1. Validasi Over-Issue terhadap Bon Rencana (POC-32)
                $newTotalIssued = $prodItem->qty_issued + $qtyToIssue;
                if ($newTotalIssued > $prodItem->qty_planned) {
                    $excess = $newTotalIssued - $prodItem->qty_planned;
                    throw new Exception("Over-issue ditolak! Pengeluaran bahan untuk '{$prodItem->item->name}' ({$newTotalIssued} unit) melebihi kuantitas yang disetujui pada bon ({$prodItem->qty_planned} unit) sebanyak {$excess} unit.");
                }

                // 2. Validasi Ketersediaan Saldo Fisik di Gudang (POC-14, POC-32)
                $balance = StockBalance::where('warehouse_id', $prodOrder->warehouse_id)
                    ->where('item_id', $prodItem->item_id)
                    ->first();

                $available = $balance ? $balance->available : 0;
                if ($qtyToIssue > $available) {
                    throw new Exception("Stok tidak mencukupi! Kebutuhan pengeluaran '{$prodItem->item->name}' ({$qtyToIssue} unit) melebihi saldo tersedia di {$prodOrder->warehouse->name} ({$available} unit).");
                }

                // Update item bon
                $prodItem->update([
                    'qty_issued' => $newTotalIssued,
                ]);

                // Mutasi stok berkurang (PRODUCTION_ISSUE)
                $this->stockService->recordProductionIssue(
                    $prodOrder->warehouse,
                    $prodItem->item,
                    $qtyToIssue,
                    $prodOrder->production_number,
                    $user
                );
            }

            $allIssued = $prodOrder->items->every(fn ($i) => $i->qty_issued >= $i->qty_planned);
            $newStatus = $allIssued ? 'COMPLETED' : 'IN_PRODUCTION';

            $prodOrder->update([
                'status' => $newStatus,
                'issued_by_user_id' => $user->id,
                'issued_at' => now(),
                'completed_at' => $allIssued ? now() : null,
            ]);

            AuditTrailService::log('ISSUE_PRODUCTION_STOCK', $prodOrder, null, [
                'production_number' => $prodOrder->production_number,
                'status' => $newStatus,
                'total_issued_qty' => $prodOrder->fresh()->total_issued_qty,
            ], $user);

            return $prodOrder->fresh(['items.item', 'destinationOrganization', 'warehouse']);
        });
    }

    /**
     * Generate Dokumen Rekap Manifest Produksi (POC-30)
     * Menggabungkan referensi produksi, daftar produk, kuantitas, dan database alamat tujuan cabang.
     */
    public function getProductionManifestData(ProductionOrder $prodOrder): array
    {
        $org = $prodOrder->destinationOrganization;

        return [
            'manifest_number' => 'MNF-PRD-'.$prodOrder->id.'-'.date('Ymd'),
            'production_order' => $prodOrder,
            'warehouse' => $prodOrder->warehouse,
            'destination' => [
                'code' => $org->code,
                'name' => $org->name,
                'type' => $org->type,
                'address' => $org->address ?: 'Jl. Basuki Rahmat No. 98-104',
                'city' => $org->city ?: 'Surabaya',
                'phone' => $org->phone ?: '(031) 5310090',
                'cost_center' => $org->cost_center_code,
            ],
            'items' => $prodOrder->items->map(fn ($item) => [
                'sku' => $item->item->sku,
                'name' => $item->item->name,
                'category' => $item->item->category?->name,
                'uom' => $item->item->uom,
                'qty_planned' => $item->qty_planned,
                'qty_issued' => $item->qty_issued,
                'unit_price' => (float) $item->item->estimated_unit_price,
                'subtotal' => (float) ($item->item->estimated_unit_price * $item->qty_planned),
            ]),
            'total_items' => $prodOrder->items->count(),
            'total_qty' => $prodOrder->total_planned_qty,
            'generated_at' => now()->translatedFormat('d F Y H:i'),
        ];
    }
}
