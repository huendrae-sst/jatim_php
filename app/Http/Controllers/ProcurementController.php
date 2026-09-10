<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\AuditTrailService;
use App\Services\EarlyWarningService;
use App\Services\ProcurementService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    public function __construct(
        protected ProcurementService $procurementService,
        protected EarlyWarningService $earlyWarningService
    ) {}

    public function prIndex(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $organizationId = $request->get('organization_id');
        $method = $request->get('procurement_method');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;

        $query = PurchaseRequest::with(['organization', 'requester', 'approver', 'items.item']);

        if ($isBranch) {
            $query->where('organization_id', $user->organization_id);
            $organizationId = (string) $user->organization_id;
        } elseif ($organizationId && $organizationId !== 'ALL') {
            $query->where('organization_id', $organizationId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pr_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($oq) => $oq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('requester', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status && $status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($method && $method !== 'ALL') {
            $query->where('procurement_method', $method);
        }

        $allowedSorts = ['created_at', 'pr_number', 'estimated_total_cost', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $prs = $query->paginate($perPage)->withQueryString();

        // KPI Counts (scoped to branch if branch user)
        $aggregateQuery = PurchaseRequest::query();
        if ($isBranch) {
            $aggregateQuery->where('organization_id', $user->organization_id);
        }

        $totalPrCount = (clone $aggregateQuery)->count();
        $submittedPrCount = (clone $aggregateQuery)->where('status', 'SUBMITTED')->count();
        $approvedPrCount = (clone $aggregateQuery)->where('status', 'APPROVED')->count();
        $orderedPrCount = (clone $aggregateQuery)->whereIn('status', ['PARTIALLY_ORDERED', 'FULLY_ORDERED'])->count();
        $totalEstimatedCost = (float) (clone $aggregateQuery)->sum('estimated_total_cost');

        // Reference Catalogs for Modals & Filters
        $itemsCatalog = Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'uom', 'estimated_unit_price']);
        $organizations = $isBranch
            ? Organization::where('id', $user->organization_id)->get(['id', 'name', 'code'])
            : Organization::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return view('procurement.pr.index', compact(
            'prs',
            'perPage',
            'search',
            'status',
            'organizationId',
            'method',
            'sortBy',
            'sortDir',
            'totalPrCount',
            'submittedPrCount',
            'approvedPrCount',
            'orderedPrCount',
            'totalEstimatedCost',
            'itemsCatalog',
            'organizations'
        ));
    }

    public function ewsItems(Request $request): JsonResponse
    {
        $user = Auth::user();
        $isBranch = $user && $user->isBranchUser() && $user->organization_id;

        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
        ], [
            'organization_id.required' => 'Unit kerja wajib dipilih.',
            'organization_id.exists' => 'Unit kerja tidak ditemukan.',
        ]);

        $orgId = $isBranch ? (int) $user->organization_id : (int) $request->organization_id;

        $warehouse = Warehouse::where('organization_id', $orgId)->where('is_active', true)->first();
        $warehouseId = $warehouse?->id;

        $evaluations = $this->earlyWarningService->getAllEvaluations($warehouseId);

        $alertTypes = ['CRITICAL_STOCKOUT', 'HIGH_REORDER'];
        $items = [];

        foreach ($evaluations as $eval) {
            $hasAlert = in_array('CRITICAL_STOCKOUT', $eval['alerts'] ?? [], true)
                || in_array('HIGH_REORDER', $eval['alerts'] ?? [], true)
                || in_array($eval['primary_alert'] ?? '', $alertTypes, true);

            if ($hasAlert) {
                $qty = (int) ($eval['suggested_reorder_qty'] ?? 0);
                if ($qty <= 0) {
                    $maxStock = (int) ($eval['max_stock'] ?? 0);
                    $available = (int) ($eval['available'] ?? 0);
                    $qty = max(20, $maxStock - $available);
                }

                $items[] = [
                    'item_id' => $eval['item_id'],
                    'name' => $eval['name'],
                    'sku' => $eval['sku'],
                    'uom' => $eval['uom'],
                    'estimated_unit_price' => (float) ($eval['estimated_unit_price'] ?? 0),
                    'qty_requested' => max(1, $qty),
                    'primary_alert' => $eval['primary_alert'],
                    'alerts' => $eval['alerts'],
                    'available' => $eval['available'],
                    'reorder_point' => $eval['reorder_point'],
                    'safety_stock' => $eval['safety_stock'],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'organization_id' => $orgId,
            'warehouse_id' => $warehouseId,
            'total_items' => count($items),
            'items' => $items,
        ]);
    }

    public function prStore(Request $request)
    {
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;
        $targetOrgId = $isBranch ? $user->organization_id : (int) $request->organization_id;

        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'procurement_method' => 'required|string',
            'purpose' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $pr = $this->procurementService->createPurchaseRequest(
                (int) $targetOrgId,
                $request->procurement_method,
                $request->purpose,
                $request->items,
                $user
            );

            return redirect()->route('procurement.pr.index')
                ->with('success', "Purchase Request {$pr->pr_number} berhasil dibuat dan menunggu persetujuan.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function prShow($id)
    {
        $pr = PurchaseRequest::with(['organization', 'requester', 'approver', 'items.item.category'])->findOrFail($id);

        return view('procurement.pr.show', compact('pr'));
    }

    public function prPrint($id)
    {
        $pr = PurchaseRequest::with(['organization', 'requester', 'approver', 'items.item.category'])->findOrFail($id);

        return view('procurement.pr.print', compact('pr'));
    }

    public function prUpdate(Request $request, $id)
    {
        $pr = PurchaseRequest::with('items.purchaseOrderItems')->findOrFail($id);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $pr->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk mengubah PR milik unit kerja lain.');
        }

        $isEditable = ! in_array($pr->status, ['APPROVED', 'FULLY_ORDERED', 'PARTIALLY_ORDERED']);
        if (! $isEditable) {
            $statusLabel = str_replace('_', ' ', $pr->status);

            return back()->with('error', "Purchase Request {$pr->pr_number} tidak dapat diubah karena status sudah {$statusLabel}.");
        }

        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'procurement_method' => 'required|string|max:100',
            'purpose' => 'required|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($pr, $validated) {
                $totalEstimated = 0;
                foreach ($validated['items'] as $itemData) {
                    $totalEstimated += ((int) $itemData['qty'] * (float) $itemData['unit_price']);
                }

                $currentYear = (int) date('Y');
                $budget = Budget::where('organization_id', $validated['organization_id'])->where('year', $currentYear)->first();
                $budgetStatus = 'VALIDATED';
                if ($budget && $budget->available_amount < $totalEstimated) {
                    $budgetStatus = 'INSUFFICIENT';
                }

                $pr->update([
                    'organization_id' => $validated['organization_id'],
                    'procurement_method' => $validated['procurement_method'],
                    'purpose' => $validated['purpose'],
                    'estimated_total_cost' => $totalEstimated,
                    'budget_status' => $budgetStatus,
                ]);

                $pr->items()->delete();
                foreach ($validated['items'] as $itemData) {
                    PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'item_id' => $itemData['item_id'],
                        'qty_requested' => $itemData['qty'],
                        'qty_approved' => $pr->status === 'APPROVED' ? $itemData['qty'] : 0,
                        'qty_ordered' => 0,
                        'estimated_unit_price' => $itemData['unit_price'],
                        'estimated_subtotal' => (int) $itemData['qty'] * (float) $itemData['unit_price'],
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }

                AuditTrailService::log('UPDATE_PR', $pr, null, $pr->toArray(), Auth::user());
            });

            return redirect()->route('procurement.pr.index')
                ->with('success', "Purchase Request {$pr->pr_number} berhasil diperbarui.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', 'Gagal memperbarui PR: '.$e->getMessage());
        }
    }

    public function prDestroy(Request $request, $id)
    {
        $pr = PurchaseRequest::with('items.purchaseOrderItems')->findOrFail($id);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $pr->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk menghapus PR milik unit kerja lain.');
        }

        if (in_array($pr->status, ['APPROVED', 'FULLY_ORDERED', 'PARTIALLY_ORDERED'])) {
            $statusLabel = str_replace('_', ' ', $pr->status);

            return back()->with('error', "Purchase Request {$pr->pr_number} tidak dapat dihapus karena status sudah {$statusLabel}.");
        }

        $hasPo = $pr->items->contains(fn ($it) => $it->purchaseOrderItems->isNotEmpty());
        if ($hasPo) {
            return back()->with('error', "Purchase Request {$pr->pr_number} tidak dapat dihapus karena item sudah terkonsolidasi ke Purchase Order.");
        }

        try {
            DB::transaction(function () use ($pr) {
                AuditTrailService::log('DELETE_PR', $pr, [
                    'pr_number' => $pr->pr_number,
                    'organization_id' => $pr->organization_id,
                    'estimated_total_cost' => (float) $pr->estimated_total_cost,
                    'status' => $pr->status,
                ], null, Auth::user());

                $pr->items()->delete();
                $pr->delete();
            });

            return redirect()->route('procurement.pr.index')
                ->with('success', "Purchase Request {$pr->pr_number} berhasil dihapus dari sistem.");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal menghapus PR: '.$e->getMessage());
        }
    }

    public function prApprove($id)
    {
        $pr = PurchaseRequest::findOrFail($id);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $pr->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk menyetujui PR milik unit kerja lain.');
        }
        try {
            $this->procurementService->approvePurchaseRequest($pr, Auth::user());

            return redirect()->back()->with('success', "Purchase Request {$pr->pr_number} telah disetujui dan masuk ke Approved PR Pool.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // Approved PR Pool & Consolidation View
    public function consolidationPool()
    {
        // Get all approved PR items with remaining quantity to order
        $approvedPrItems = PurchaseRequestItem::with(['purchaseRequest.organization', 'item.category'])
            ->whereHas('purchaseRequest', fn ($q) => $q->whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED']))
            ->get()
            ->filter(fn ($item) => $item->remaining_qty_to_order > 0);

        $vendors = Vendor::where('is_active', true)->get();
        $warehouses = Warehouse::where('is_active', true)->get();

        return view('procurement.consolidation.index', compact('approvedPrItems', 'vendors', 'warehouses'));
    }

    public function consolidateStore(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'expected_delivery_date' => 'required|date',
            'selections' => 'required|array|min:1',
            'selections.*.pr_item_id' => 'required|exists:purchase_request_items,id',
            'selections.*.qty' => 'required|integer|min:1',
            'selections.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $po = $this->procurementService->consolidatePRsToPO(
                $request->selections,
                (int) $request->vendor_id,
                (int) $request->warehouse_id,
                $request->expected_delivery_date,
                $request->notes ?? 'Konsolidasi PR',
                Auth::user()
            );

            return redirect()->route('procurement.consolidation.index')
                ->with('success', "Purchase Order {$po->po_number} berhasil diterbitkan dari konsolidasi PR.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function poIndex(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $vendorId = $request->get('vendor_id');
        $warehouseId = $request->get('warehouse_id');
        $sortBy = $request->get('sort_by', 'order_date');
        $sortDir = $request->get('sort_dir', 'desc');

        $query = PurchaseOrder::with(['vendor', 'warehouse', 'creator', 'items.item']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('warehouse', fn ($wq) => $wq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('creator', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status && $status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($vendorId && $vendorId !== 'ALL') {
            $query->where('vendor_id', $vendorId);
        }

        if ($warehouseId && $warehouseId !== 'ALL') {
            $query->where('warehouse_id', $warehouseId);
        }

        $allowedSorts = ['order_date', 'created_at', 'po_number', 'total_amount', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest('order_date');
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $pos = $query->paginate($perPage)->withQueryString();

        $vendors = Vendor::where('is_active', true)->get();
        $warehouses = Warehouse::where('is_active', true)->get();

        return view('procurement.po.index', compact(
            'pos',
            'vendors',
            'warehouses',
            'search',
            'status',
            'vendorId',
            'warehouseId',
            'sortBy',
            'sortDir',
            'perPage'
        ));
    }

    public function poPrint($id)
    {
        $po = PurchaseOrder::with([
            'vendor',
            'warehouse',
            'creator',
            'approver',
            'items.item.category',
        ])->findOrFail($id);

        return view('procurement.po.print', compact('po'));
    }

    public function poUpdate(Request $request, $id)
    {
        $po = PurchaseOrder::with(['items', 'goodsReceipts'])->findOrFail($id);

        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.id' => 'required|exists:purchase_order_items,id',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $this->procurementService->updatePurchaseOrder($po, $request->only([
                'vendor_id',
                'warehouse_id',
                'expected_delivery_date',
                'notes',
                'items',
            ]), Auth::user());

            return redirect()->route('procurement.po.index')
                ->with('success', "Purchase Order {$po->po_number} berhasil diperbarui.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function poDestroy($id)
    {
        $po = PurchaseOrder::with(['items.purchaseRequestItem', 'goodsReceipts'])->findOrFail($id);

        try {
            $poNumber = $po->po_number;
            $this->procurementService->deletePurchaseOrder($po, Auth::user());

            return redirect()->route('procurement.po.index')
                ->with('success', "Purchase Order {$poNumber} berhasil dihapus dan item PR telah dikembalikan ke Approved PR Pool.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // Goods Receipt from Vendor
    public function goodsReceiptStore(Request $request, $id)
    {
        $po = PurchaseOrder::with('items')->findOrFail($id);

        $request->validate([
            'vendor_delivery_note_number' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.po_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.qty_accepted' => 'required|integer|min:0',
            'items.*.qty_rejected' => 'nullable|integer|min:0',
        ]);

        try {
            $grn = $this->procurementService->processGoodsReceipt(
                $po,
                $request->items,
                $request->vendor_delivery_note_number,
                Auth::user()
            );

            return redirect()->route('procurement.po.index')
                ->with('success', "Penerimaan barang vendor berhasil diposting ke Stock Ledger (GRN: {$grn->grn_number}).");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function prApprovals(Request $request)
    {
        $search = $request->get('search');
        $tab = $request->get('tab', 'pending');
        $organizationId = $request->get('organization_id');
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;

        $query = PurchaseRequest::with(['organization', 'requester', 'approver', 'items.item']);

        if ($isBranch) {
            $query->where('organization_id', $user->organization_id);
            $organizationId = (string) $user->organization_id;
        } elseif ($organizationId && $organizationId !== 'ALL') {
            $query->where('organization_id', $organizationId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pr_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($oq) => $oq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('requester', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
            });
        }

        // Apply Tab Filter
        match ($tab) {
            'approved' => $query->whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED']),
            'rejected' => $query->where('status', 'REJECTED'),
            'all' => null,
            default => $query->whereIn('status', ['SUBMITTED', 'WAITING_APPROVAL']),
        };

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $prs = $query->latest()->paginate($perPage)->withQueryString();

        // Calculate KPI Counts (Branch-scoped if branch user)
        $kpiQuery = PurchaseRequest::query();
        if ($isBranch) {
            $kpiQuery->where('organization_id', $user->organization_id);
        }

        $pendingCount = (clone $kpiQuery)->whereIn('status', ['SUBMITTED', 'WAITING_APPROVAL'])->count();
        $approvedCount = (clone $kpiQuery)->whereIn('status', ['APPROVED', 'PARTIALLY_ORDERED', 'FULLY_ORDERED'])->count();
        $rejectedCount = (clone $kpiQuery)->where('status', 'REJECTED')->count();
        $allCount = (clone $kpiQuery)->count();
        $pendingValue = (float) (clone $kpiQuery)->whereIn('status', ['SUBMITTED', 'WAITING_APPROVAL'])->sum('estimated_total_cost');

        // PO Pending count for top tab switcher badge
        $pendingPoBadge = PurchaseOrder::whereIn('status', ['WAITING_APPROVAL', 'DRAFT'])->count();

        $organizations = $isBranch
            ? Organization::where('id', $user->organization_id)->get(['id', 'name', 'code'])
            : Organization::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return view('procurement.approvals.pr', compact(
            'prs',
            'tab',
            'search',
            'organizationId',
            'organizations',
            'perPage',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
            'allCount',
            'pendingValue',
            'pendingPoBadge'
        ));
    }

    public function prReject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $pr = PurchaseRequest::findOrFail($id);
        $user = Auth::user();

        if (! $user->hasRole('SUPER_ADMIN', 'PROCUREMENT_APPROVER')) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk menolak Purchase Request.');
        }

        if ($user->isBranchUser() && $user->organization_id && $pr->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk memproses PR milik unit kerja lain.');
        }

        try {
            $this->procurementService->rejectPurchaseRequest($pr, $request->rejection_reason, $user);

            return back()->with('success', "Purchase Request {$pr->pr_number} telah ditolak.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function poApprovals(Request $request)
    {
        $search = $request->get('search');
        $tab = $request->get('tab', 'pending');
        $vendorId = $request->get('vendor_id');
        $warehouseId = $request->get('warehouse_id');

        $query = PurchaseOrder::with(['vendor', 'warehouse', 'creator', 'approver', 'items.item', 'items.purchaseRequestItem.purchaseRequest']);

        if ($vendorId && $vendorId !== 'ALL') {
            $query->where('vendor_id', $vendorId);
        }

        if ($warehouseId && $warehouseId !== 'ALL') {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('warehouse', fn ($wq) => $wq->where('name', 'like', "%{$search}%"));
            });
        }

        // Apply Tab Filter
        match ($tab) {
            'issued' => $query->whereIn('status', ['ISSUED', 'VENDOR_PROCESS', 'IN_DELIVERY', 'PARTIAL_RECEIVED']),
            'completed' => $query->where('status', 'COMPLETED'),
            'rejected' => $query->whereIn('status', ['REJECTED', 'CANCELLED']),
            'all' => null,
            default => $query->whereIn('status', ['WAITING_APPROVAL', 'DRAFT']),
        };

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $pos = $query->latest()->paginate($perPage)->withQueryString();

        // Calculate KPI Counts
        $pendingCount = PurchaseOrder::whereIn('status', ['WAITING_APPROVAL', 'DRAFT'])->count();
        $issuedCount = PurchaseOrder::whereIn('status', ['ISSUED', 'VENDOR_PROCESS', 'IN_DELIVERY', 'PARTIAL_RECEIVED'])->count();
        $completedCount = PurchaseOrder::where('status', 'COMPLETED')->count();
        $rejectedCount = PurchaseOrder::whereIn('status', ['REJECTED', 'CANCELLED'])->count();
        $allCount = PurchaseOrder::count();
        $pendingValue = (float) PurchaseOrder::whereIn('status', ['WAITING_APPROVAL', 'DRAFT'])->sum('total_amount');

        // PR Pending count for top tab switcher badge
        $pendingPrBadge = PurchaseRequest::whereIn('status', ['SUBMITTED', 'WAITING_APPROVAL'])->count();

        $vendors = Vendor::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('procurement.approvals.po', compact(
            'pos',
            'tab',
            'search',
            'vendorId',
            'warehouseId',
            'vendors',
            'warehouses',
            'perPage',
            'pendingCount',
            'issuedCount',
            'completedCount',
            'rejectedCount',
            'allCount',
            'pendingValue',
            'pendingPrBadge'
        ));
    }

    public function poApprove(Request $request, $id)
    {
        $user = Auth::user();

        if (! $user->hasRole('SUPER_ADMIN', 'PROCUREMENT_APPROVER')) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk menyetujui Purchase Order.');
        }

        $po = PurchaseOrder::findOrFail($id);

        try {
            $this->procurementService->approvePurchaseOrder($po, $user);

            return back()->with('success', "Purchase Order {$po->po_number} berhasil disetujui dan diterbitkan.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function poReject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        if (! $user->hasRole('SUPER_ADMIN', 'PROCUREMENT_APPROVER')) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk menolak Purchase Order.');
        }

        $po = PurchaseOrder::findOrFail($id);

        try {
            $this->procurementService->rejectPurchaseOrder($po, $request->rejection_reason, $user);

            return back()->with('success', "Purchase Order {$po->po_number} telah ditolak dan alokasi item PR telah dikembalikan.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
