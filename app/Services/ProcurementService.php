<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Models\Warehouse;
use Exception;
use Illuminate\Support\Facades\DB;

class ProcurementService
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    public function createPurchaseRequest(
        int $organizationId,
        string $procurementMethod,
        string $purpose,
        array $items,
        User $user
    ): PurchaseRequest {
        return DB::transaction(function () use ($organizationId, $procurementMethod, $purpose, $items, $user) {
            $currentYear = (int) date('Y');
            $prNumber = 'PR/'.date('Y/m').'/'.sprintf('%04d', PurchaseRequest::count() + 1);

            $totalEstimated = 0;
            foreach ($items as $itemData) {
                $totalEstimated += ($itemData['qty'] * $itemData['unit_price']);
            }

            // Check budget
            $budget = Budget::where('organization_id', $organizationId)->where('year', $currentYear)->first();
            $budgetStatus = 'VALIDATED';
            if ($budget && $budget->available_amount < $totalEstimated) {
                $budgetStatus = 'INSUFFICIENT';
            }

            $pr = PurchaseRequest::create([
                'pr_number' => $prNumber,
                'organization_id' => $organizationId,
                'created_by_user_id' => $user->id,
                'procurement_method' => $procurementMethod,
                'purpose' => $purpose,
                'estimated_total_cost' => $totalEstimated,
                'budget_status' => $budgetStatus,
                'status' => 'SUBMITTED',
                'submitted_at' => now(),
            ]);

            foreach ($items as $itemData) {
                PurchaseRequestItem::create([
                    'purchase_request_id' => $pr->id,
                    'item_id' => $itemData['item_id'],
                    'qty_requested' => $itemData['qty'],
                    'qty_approved' => 0,
                    'qty_ordered' => 0,
                    'estimated_unit_price' => $itemData['unit_price'],
                    'estimated_subtotal' => $itemData['qty'] * $itemData['unit_price'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            AuditTrailService::log('CREATE_PR', $pr, null, $pr->toArray(), $user);

            NotificationService::sendActionRequired(
                "Persetujuan Purchase Request {$pr->pr_number}",
                'Purchase Request baru sebesar Rp '.number_format($pr->estimated_total_cost, 0, ',', '.').' memerlukan persetujuan.',
                'PROCUREMENT_APPROVER',
                $organizationId,
                'PR',
                $pr->id,
                "/procurement/pr/{$pr->id}"
            );

            return $pr;
        });
    }

    public function approvePurchaseRequest(PurchaseRequest $pr, User $approver): PurchaseRequest
    {
        if ($pr->created_by_user_id === $approver->id) {
            throw new Exception('Segregation of Duties: Pembuat PR tidak boleh menyetujui PR sendiri.');
        }

        return DB::transaction(function () use ($pr, $approver) {
            $pr->status = 'APPROVED';
            $pr->approved_by_user_id = $approver->id;
            $pr->approved_at = now();
            $pr->save();

            // Set approved qty = requested qty for items
            foreach ($pr->items as $item) {
                $item->qty_approved = $item->qty_requested;
                $item->save();
            }

            // Update Budget Commitment
            $currentYear = (int) date('Y');
            $budget = Budget::where('organization_id', $pr->organization_id)->where('year', $currentYear)->first();
            if ($budget) {
                $budget->committed_amount += $pr->estimated_total_cost;
                $budget->save();
            }

            AuditTrailService::log('APPROVE_PR', $pr, null, ['status' => 'APPROVED'], $approver);

            NotificationService::sendInfo(
                'Purchase Request Disetujui',
                "PR {$pr->pr_number} telah disetujui dan masuk ke Approved PR Pool untuk dikonsolidasi.",
                $pr->created_by_user_id,
                'PROCUREMENT_OFFICER',
                "/procurement/pr/{$pr->id}"
            );

            return $pr;
        });
    }

    public function consolidatePRsToPO(
        array $prItemSelections, // [ ['pr_item_id' => 1, 'qty' => 100, 'unit_price' => 250000], ... ]
        int $vendorId,
        int $warehouseId,
        string $expectedDeliveryDate,
        string $notes,
        User $user
    ): PurchaseOrder {
        return DB::transaction(function () use ($prItemSelections, $vendorId, $warehouseId, $expectedDeliveryDate, $notes, $user) {
            $poNumber = 'PO/'.date('Y/m').'/'.sprintf('%04d', PurchaseOrder::count() + 1);

            $subtotal = 0;
            foreach ($prItemSelections as $sel) {
                $subtotal += ($sel['qty'] * $sel['unit_price']);
            }
            $taxAmount = round($subtotal * 0.11, 2); // PPN 11%
            $totalAmount = $subtotal + $taxAmount;

            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'vendor_id' => $vendorId,
                'warehouse_id' => $warehouseId,
                'created_by_user_id' => $user->id,
                'order_date' => now(),
                'expected_delivery_date' => $expectedDeliveryDate,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'ISSUED',
                'notes' => $notes,
            ]);

            $affectedPrIds = [];

            foreach ($prItemSelections as $sel) {
                $prItem = PurchaseRequestItem::findOrFail($sel['pr_item_id']);

                if ($sel['qty'] > $prItem->remaining_qty_to_order) {
                    throw new Exception("Quantity untuk item {$prItem->item->name} melebihi sisa approved quantity pada PR.");
                }

                $prItem->qty_ordered += $sel['qty'];
                $prItem->save();

                $affectedPrIds[$prItem->purchase_request_id] = true;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'purchase_request_item_id' => $prItem->id,
                    'item_id' => $prItem->item_id,
                    'qty_ordered' => $sel['qty'],
                    'qty_received' => 0,
                    'unit_price' => $sel['unit_price'],
                    'subtotal' => $sel['qty'] * $sel['unit_price'],
                ]);
            }

            // Update status of affected PRs
            foreach (array_keys($affectedPrIds) as $prId) {
                $pr = PurchaseRequest::with('items')->find($prId);
                $allFullyOrdered = $pr->items->every(fn ($i) => $i->qty_ordered >= $i->qty_approved);
                $anyOrdered = $pr->items->some(fn ($i) => $i->qty_ordered > 0);

                if ($allFullyOrdered) {
                    $pr->status = 'FULLY_ORDERED';
                } elseif ($anyOrdered) {
                    $pr->status = 'PARTIALLY_ORDERED';
                }
                $pr->save();
            }

            AuditTrailService::log('CONSOLIDATE_PR_TO_PO', $po, null, ['po_number' => $po->po_number, 'total' => $totalAmount], $user);

            NotificationService::sendInfo(
                'Purchase Order Baru Terbit',
                "PO {$po->po_number} berhasil diterbitkan melalui konsolidasi PR.",
                null,
                'WAREHOUSE_OFFICER',
                "/procurement/po/{$po->id}"
            );

            return $po;
        });
    }

    public function processGoodsReceipt(
        PurchaseOrder $po,
        array $receivedItemsData, // [ ['po_item_id' => 1, 'qty_accepted' => 100, 'qty_rejected' => 0, 'notes' => 'Kondisi baik'], ... ]
        string $vendorDeliveryNote,
        User $user
    ): GoodsReceipt {
        return DB::transaction(function () use ($po, $receivedItemsData, $vendorDeliveryNote, $user) {
            $grnNumber = 'GRN/'.date('Y/m').'/'.sprintf('%04d', GoodsReceipt::count() + 1);

            $grn = GoodsReceipt::create([
                'grn_number' => $grnNumber,
                'purchase_order_id' => $po->id,
                'warehouse_id' => $po->warehouse_id,
                'received_by_user_id' => $user->id,
                'vendor_delivery_note_number' => $vendorDeliveryNote,
                'receipt_date' => now(),
                'status' => 'RECEIVED',
                'notes' => 'Penerimaan barang vendor terverifikasi',
            ]);

            $warehouse = Warehouse::findOrFail($po->warehouse_id);

            $allCompleted = true;

            foreach ($receivedItemsData as $itemData) {
                $poItem = PurchaseOrderItem::findOrFail($itemData['po_item_id']);
                $qtyAccepted = (int) $itemData['qty_accepted'];
                $qtyRejected = (int) ($itemData['qty_rejected'] ?? 0);
                $qtyReceived = $qtyAccepted + $qtyRejected;

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $grn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'item_id' => $poItem->item_id,
                    'qty_received' => $qtyReceived,
                    'qty_accepted' => $qtyAccepted,
                    'qty_rejected' => $qtyRejected,
                    'condition_notes' => $itemData['notes'] ?? null,
                ]);

                $poItem->qty_received += $qtyAccepted;
                $poItem->save();

                if ($poItem->qty_received < $poItem->qty_ordered) {
                    $allCompleted = false;
                }

                // Post directly to Stock Ledger & Balance
                if ($qtyAccepted > 0) {
                    $this->stockLedgerService->postProcurementReceipt(
                        $warehouse,
                        $poItem->item,
                        $qtyAccepted,
                        (float) $poItem->unit_price,
                        $po->po_number,
                        $user
                    );
                }
            }

            $po->status = $allCompleted ? 'COMPLETED' : 'PARTIAL_RECEIVED';
            $po->save();

            AuditTrailService::log('RECEIVE_VENDOR_GOODS', $grn, null, ['grn_number' => $grn->grn_number, 'po' => $po->po_number], $user);

            NotificationService::sendInfo(
                "Penerimaan Barang PO {$po->po_number}",
                "Barang dari vendor telah diterima dan diposting ke Stock Ledger (GRN: {$grn->grn_number}).",
                null,
                'INVENTORY_OFFICER',
                "/procurement/po/{$po->id}"
            );

            return $grn;
        });
    }
}
