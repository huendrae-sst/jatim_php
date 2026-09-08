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
        protected StockLedgerService $stockLedgerService,
        protected GeneralLedgerService $generalLedgerService
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

            NotificationService::sendUser(
                $pr->created_by_user_id,
                'Purchase Request Disetujui',
                "PR {$pr->pr_number} telah disetujui dan masuk ke Approved PR Pool untuk dikonsolidasi menjadi PO.",
                'INFORMATION',
                'INFO',
                'PR',
                $pr->id,
                "/procurement/pr/{$pr->id}"
            );

            NotificationService::sendRole(
                'PROCUREMENT_OFFICER',
                'PR Siap Dikonsolidasi',
                "PR {$pr->pr_number} telah disetujui dan siap dikonsolidasi ke Purchase Order pada Approved PR Pool.",
                null,
                'INFORMATION',
                'INFO',
                'PR',
                $pr->id,
                '/procurement/consolidation'
            );

            return $pr;
        });
    }

    public function rejectPurchaseRequest(PurchaseRequest $pr, string $reason, User $user): PurchaseRequest
    {
        if ($pr->created_by_user_id === $user->id) {
            throw new Exception('Segregation of Duties: Pembuat PR tidak boleh menolak PR sendiri.');
        }

        if (! in_array($pr->status, ['DRAFT', 'SUBMITTED', 'WAITING_APPROVAL'])) {
            throw new Exception("Purchase Request {$pr->pr_number} tidak dalam status menunggu persetujuan.");
        }

        return DB::transaction(function () use ($pr, $reason, $user) {
            $previousStatus = $pr->status;
            $pr->status = 'REJECTED';
            $pr->rejection_reason = $reason;
            $pr->approved_by_user_id = $user->id;
            $pr->approved_at = now();
            $pr->save();

            AuditTrailService::log('REJECT_PR', $pr, ['status' => $previousStatus], ['status' => 'REJECTED', 'reason' => $reason], $user);

            NotificationService::sendUser(
                $pr->created_by_user_id,
                'Purchase Request Ditolak',
                "PR {$pr->pr_number} telah ditolak dengan alasan: {$reason}",
                'INFORMATION',
                'WARNING',
                'PR',
                $pr->id,
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
                'status' => 'WAITING_APPROVAL',
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

            NotificationService::sendActionRequired(
                "Persetujuan Purchase Order {$po->po_number}",
                'Purchase Order baru dari konsolidasi PR sebesar Rp '.number_format($po->total_amount, 0, ',', '.').' memerlukan persetujuan penerbitan.',
                'PROCUREMENT_APPROVER',
                null,
                'PO',
                $po->id,
                '/procurement/approvals/po'
            );

            return $po;
        });
    }

    public function approvePurchaseOrder(PurchaseOrder $po, User $approver): PurchaseOrder
    {
        if ($po->created_by_user_id === $approver->id) {
            throw new Exception('Segregation of Duties: Pembuat PO tidak boleh menyetujui PO sendiri.');
        }

        if (! in_array($po->status, ['DRAFT', 'WAITING_APPROVAL'])) {
            throw new Exception("Purchase Order {$po->po_number} tidak dalam status menunggu persetujuan (Status: {$po->status}).");
        }

        return DB::transaction(function () use ($po, $approver) {
            $po->status = 'ISSUED';
            $po->approved_by_user_id = $approver->id;
            $po->approved_at = now();
            $po->save();

            AuditTrailService::log('APPROVE_PO', $po, null, ['status' => 'ISSUED', 'approved_by' => $approver->name], $approver);

            NotificationService::sendUser(
                $po->created_by_user_id,
                'Purchase Order Disetujui & Diterbitkan',
                "PO {$po->po_number} senilai Rp ".number_format($po->total_amount, 0, ',', '.').' telah disetujui dan resmi diterbitkan.',
                'INFORMATION',
                'INFO',
                'PO',
                $po->id,
                "/procurement/po/{$po->id}"
            );

            NotificationService::sendRole(
                'WAREHOUSE_OFFICER',
                'PO Baru Diterbitkan - Persiapan Penerimaan',
                "PO {$po->po_number} telah disetujui dan diterbitkan. Mohon monitor pengiriman serta persiapan penerimaan barang vendor (GRN).",
                null,
                'INFORMATION',
                'INFO',
                'PO',
                $po->id,
                '/receiving/po'
            );

            return $po;
        });
    }

    public function rejectPurchaseOrder(PurchaseOrder $po, string $reason, User $user): PurchaseOrder
    {
        if ($po->created_by_user_id === $user->id) {
            throw new Exception('Segregation of Duties: Pembuat PO tidak boleh menolak PO sendiri.');
        }

        if (! in_array($po->status, ['DRAFT', 'WAITING_APPROVAL'])) {
            throw new Exception("Purchase Order {$po->po_number} tidak dalam status menunggu persetujuan.");
        }

        return DB::transaction(function () use ($po, $reason, $user) {
            $previousStatus = $po->status;
            $po->status = 'REJECTED';
            $po->rejection_reason = $reason;
            $po->approved_by_user_id = $user->id;
            $po->approved_at = now();
            $po->save();

            // Rollback ordered quantity on PR items so they can be re-consolidated
            $affectedPrIds = [];
            foreach ($po->items as $poItem) {
                if ($poItem->purchaseRequestItem) {
                    $prItem = $poItem->purchaseRequestItem;
                    $prItem->qty_ordered = max(0, $prItem->qty_ordered - $poItem->qty_ordered);
                    $prItem->save();
                    $affectedPrIds[$prItem->purchase_request_id] = true;
                }
            }

            foreach (array_keys($affectedPrIds) as $prId) {
                $pr = PurchaseRequest::with('items')->find($prId);
                if ($pr) {
                    $allFullyOrdered = $pr->items->every(fn ($i) => $i->qty_ordered >= $i->qty_approved);
                    $anyOrdered = $pr->items->some(fn ($i) => $i->qty_ordered > 0);

                    if ($allFullyOrdered) {
                        $pr->status = 'FULLY_ORDERED';
                    } elseif ($anyOrdered) {
                        $pr->status = 'PARTIALLY_ORDERED';
                    } else {
                        $pr->status = 'APPROVED';
                    }
                    $pr->save();
                }
            }

            AuditTrailService::log('REJECT_PO', $po, ['status' => $previousStatus], ['status' => 'REJECTED', 'reason' => $reason], $user);

            NotificationService::sendUser(
                $po->created_by_user_id,
                'Purchase Order Ditolak',
                "PO {$po->po_number} telah ditolak. Alasan: {$reason}. Alokasi item PR telah dikembalikan ke Approved PR Pool.",
                'INFORMATION',
                'WARNING',
                'PO',
                $po->id,
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

            // Record double-entry General Ledger journal
            $this->generalLedgerService->recordGoodsReceiptJournal($grn, $user);

            AuditTrailService::log('RECEIVE_VENDOR_GOODS', $grn, null, ['grn_number' => $grn->grn_number, 'po' => $po->po_number], $user);

            NotificationService::sendUser(
                $po->created_by_user_id,
                "Penerimaan Barang Vendor (PO {$po->po_number})",
                "Barang untuk PO {$po->po_number} telah diterima di gudang (GRN: {$grn->grn_number}, Status PO: {$po->status}).",
                'INFORMATION',
                'INFO',
                'PO',
                $po->id,
                '/receiving/po?tab=history'
            );

            NotificationService::sendRole(
                'INVENTORY_OFFICER',
                "Penerimaan Barang PO {$po->po_number}",
                "Barang dari vendor telah diterima dan diposting ke Stock Ledger (GRN: {$grn->grn_number}).",
                null,
                'INFORMATION',
                'INFO',
                'PO',
                $po->id,
                '/receiving/po?tab=history'
            );

            $totalRejected = collect($receivedItemsData)->sum(fn ($i) => (int) ($i['qty_rejected'] ?? 0));
            if ($totalRejected > 0) {
                NotificationService::sendAlert(
                    "Discrepancy Penolakan Barang Vendor PO {$po->po_number}",
                    "Terdapat {$totalRejected} unit barang ditolak/rusak dari vendor pada penerimaan GRN {$grn->grn_number}.",
                    'HIGH',
                    'PROCUREMENT_OFFICER',
                    null,
                    'PO',
                    $po->id,
                    '/receiving/po?tab=history'
                );
            }

            return $grn;
        });
    }

    public function updatePurchaseOrder(
        PurchaseOrder $po,
        array $data,
        User $user
    ): PurchaseOrder {
        if (in_array($po->status, ['COMPLETED', 'PARTIAL_RECEIVED', 'CANCELLED', 'REJECTED'])) {
            throw new Exception("Purchase Order {$po->po_number} dengan status {$po->status} tidak dapat diubah.");
        }

        if ($po->goodsReceipts()->exists() || $po->items()->where('qty_received', '>', 0)->exists()) {
            throw new Exception("Purchase Order {$po->po_number} tidak dapat diubah karena sudah memiliki riwayat penerimaan barang.");
        }

        return DB::transaction(function () use ($po, $data, $user) {
            $oldData = $po->only(['vendor_id', 'warehouse_id', 'expected_delivery_date', 'notes', 'subtotal', 'tax_amount', 'total_amount']);

            if (isset($data['vendor_id'])) {
                $po->vendor_id = $data['vendor_id'];
            }
            if (isset($data['warehouse_id'])) {
                $po->warehouse_id = $data['warehouse_id'];
            }
            if (array_key_exists('expected_delivery_date', $data)) {
                $po->expected_delivery_date = $data['expected_delivery_date'];
            }
            if (array_key_exists('notes', $data)) {
                $po->notes = $data['notes'];
            }

            if (! empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    if (isset($itemData['id'])) {
                        $poItem = $po->items()->where('id', $itemData['id'])->first();
                        if ($poItem && isset($itemData['unit_price'])) {
                            $newUnitPrice = max(0, (float) $itemData['unit_price']);
                            $poItem->unit_price = $newUnitPrice;
                            $poItem->subtotal = $poItem->qty_ordered * $newUnitPrice;
                            $poItem->save();
                        }
                    }
                }
            }

            $po->load('items');
            $subtotal = $po->items->sum('subtotal');
            $taxAmount = round($subtotal * 0.11, 2);
            $totalAmount = $subtotal + $taxAmount;

            $po->subtotal = $subtotal;
            $po->tax_amount = $taxAmount;
            $po->total_amount = $totalAmount;
            $po->save();

            AuditTrailService::log('UPDATE_PO', $po, $oldData, $po->only(['vendor_id', 'warehouse_id', 'expected_delivery_date', 'notes', 'subtotal', 'tax_amount', 'total_amount']), $user);

            return $po;
        });
    }

    public function deletePurchaseOrder(
        PurchaseOrder $po,
        User $user
    ): bool {
        if (in_array($po->status, ['COMPLETED', 'PARTIAL_RECEIVED'])) {
            throw new Exception("Purchase Order {$po->po_number} tidak dapat dihapus karena sudah dalam status {$po->status}.");
        }

        if ($po->goodsReceipts()->exists() || $po->items()->where('qty_received', '>', 0)->exists()) {
            throw new Exception("Purchase Order {$po->po_number} tidak dapat dihapus karena sudah memiliki riwayat penerimaan barang.");
        }

        return DB::transaction(function () use ($po, $user) {
            $po->load(['items.purchaseRequestItem']);
            $affectedPrIds = [];

            // Rollback ordered quantity on PR items so they return to Approved PR Pool
            foreach ($po->items as $poItem) {
                if ($poItem->purchaseRequestItem) {
                    $prItem = $poItem->purchaseRequestItem;
                    $prItem->qty_ordered = max(0, $prItem->qty_ordered - $poItem->qty_ordered);
                    $prItem->save();
                    $affectedPrIds[$prItem->purchase_request_id] = true;
                }
            }

            foreach (array_keys($affectedPrIds) as $prId) {
                $pr = PurchaseRequest::with('items')->find($prId);
                if ($pr) {
                    $allFullyOrdered = $pr->items->every(fn ($i) => $i->qty_ordered >= $i->qty_approved);
                    $anyOrdered = $pr->items->some(fn ($i) => $i->qty_ordered > 0);

                    if ($allFullyOrdered) {
                        $pr->status = 'FULLY_ORDERED';
                    } elseif ($anyOrdered) {
                        $pr->status = 'PARTIALLY_ORDERED';
                    } else {
                        $pr->status = 'APPROVED';
                    }
                    $pr->save();
                }
            }

            AuditTrailService::log('DELETE_PO', $po, $po->toArray(), null, $user);

            $po->items()->delete();
            $po->delete();

            return true;
        });
    }
}
