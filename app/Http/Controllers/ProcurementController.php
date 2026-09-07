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
use App\Services\ProcurementService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    public function __construct(
        protected ProcurementService $procurementService
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

    public function prCreate()
    {
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;

        $organizations = $isBranch
            ? Organization::where('id', $user->organization_id)->get()
            : Organization::where('is_active', true)->get();
        $items = Item::where('is_active', true)->get();

        return view('procurement.pr.create', compact('organizations', 'items'));
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

    public function prUpdate(Request $request, $id)
    {
        $pr = PurchaseRequest::with('items.purchaseOrderItems')->findOrFail($id);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $pr->organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk mengubah PR milik unit kerja lain.');
        }

        $isEditable = in_array($pr->status, ['DRAFT', 'SUBMITTED', 'REJECTED']) ||
            ($pr->status === 'APPROVED' && $pr->items->every(fn ($it) => $it->purchaseOrderItems->isEmpty()));

        if (! $isEditable) {
            return back()->with('error', "Purchase Request {$pr->pr_number} tidak dapat diubah karena sudah diproses ke Purchase Order.");
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

            return redirect()->route('procurement.po.show', $po->id)
                ->with('success', "Purchase Order {$po->po_number} berhasil diterbitkan dari konsolidasi PR.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function poIndex(Request $request)
    {
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 15;
        $pos = PurchaseOrder::with(['vendor', 'warehouse', 'creator', 'items.item'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('procurement.po.index', compact('pos'));
    }

    public function poShow($id)
    {
        $po = PurchaseOrder::with([
            'vendor',
            'warehouse',
            'creator',
            'items.item.category',
            'items.purchaseRequestItem.purchaseRequest',
            'goodsReceipts.items.item',
        ])->findOrFail($id);

        return view('procurement.po.show', compact('po'));
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

            return redirect()->route('procurement.po.show', $po->id)
                ->with('success', "Penerimaan barang vendor berhasil diposting ke Stock Ledger (GRN: {$grn->grn_number}).");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
