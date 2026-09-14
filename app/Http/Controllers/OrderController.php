<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Warehouse;
use App\Services\AuditTrailService;
use App\Services\OrderFulfillmentService;
use App\Services\SwitchingStockService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        protected OrderFulfillmentService $orderFulfillmentService,
        protected SwitchingStockService $switchingStockService
    ) {}

    public function index(Request $request)
    {
        $currentTab = $request->get('tab', 'open');
        $search = $request->get('search');
        $status = $request->get('status');
        $organizationId = $request->get('organization_id');
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;

        // Base Query
        $query = Order::with(['requestingOrganization', 'requester', 'approver', 'items.item', 'shipment']);

        // Scope to Branch if branch user
        if ($isBranch) {
            $query->where('requesting_organization_id', $user->organization_id);
            $organizationId = (string) $user->organization_id;
        } elseif ($organizationId && $organizationId !== 'ALL') {
            $query->where('requesting_organization_id', $organizationId);
        }

        // Tab Filtering directly on Database
        if ($currentTab === 'open') {
            $query->whereNotIn('status', ['COMPLETED', 'RECEIVED', 'REJECTED', 'CANCELLED']);
        } elseif ($currentTab === 'completed') {
            $query->whereIn('status', ['COMPLETED', 'RECEIVED']);
        }

        // Status Filter
        if ($status && $status !== 'ALL') {
            $query->where('status', $status);
        }

        // Live Search Query
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('requestingOrganization', function ($org) use ($search) {
                        $org->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $allowedSorts = ['created_at', 'order_number', 'total_estimated_value', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest();
        }

        $orders = $query->paginate($perPage)->withQueryString();

        // Real Database KPI Aggregates (scoped to branch if branch user)
        $aggregateQuery = Order::query();
        if ($isBranch) {
            $aggregateQuery->where('requesting_organization_id', $user->organization_id);
        }

        $totalOrdersCount = (clone $aggregateQuery)->count();
        $openOrdersCount = (clone $aggregateQuery)->whereNotIn('status', ['COMPLETED', 'RECEIVED', 'REJECTED', 'CANCELLED'])->count();
        $completedOrdersCount = (clone $aggregateQuery)->whereIn('status', ['COMPLETED', 'RECEIVED'])->count();
        $averageOrderValue = (float) ((clone $aggregateQuery)->avg('total_estimated_value') ?? 0);
        $deliveredMonthCount = (clone $aggregateQuery)->whereIn('status', ['COMPLETED', 'RECEIVED'])
            ->where('updated_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        $itemsCatalog = Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'uom', 'estimated_unit_price']);
        $organizations = $isBranch
            ? Organization::where('id', $user->organization_id)->get()
            : Organization::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        return view('orders.index', compact(
            'orders',
            'currentTab',
            'search',
            'status',
            'organizationId',
            'sortBy',
            'sortDir',
            'perPage',
            'totalOrdersCount',
            'openOrdersCount',
            'completedOrdersCount',
            'averageOrderValue',
            'deliveredMonthCount',
            'itemsCatalog',
            'organizations',
            'warehouses'
        ));
    }

    public function create()
    {
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;

        $organizations = $isBranch
            ? Organization::where('id', $user->organization_id)->get()
            : Organization::where('is_active', true)->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        $items = Item::with('stockBalances')->where('is_active', true)->get();
        $budgets = Budget::where('year', now()->year)->where('is_active', true)->get()->keyBy('organization_id');

        return view('orders.create', compact('organizations', 'warehouses', 'items', 'budgets'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $isBranch = $user->isBranchUser() && $user->organization_id;
        $orgId = $isBranch ? $user->organization_id : (int) $request->organization_id;

        $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'priority' => 'required|string',
            'required_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        try {
            $order = $this->orderFulfillmentService->createOrder(
                (int) $orgId,
                $request->warehouse_id ? (int) $request->warehouse_id : null,
                $request->priority,
                $request->required_date,
                $request->notes ?? '',
                $request->items,
                $user
            );

            return redirect()->route('orders.index')
                ->with('success', "Order {$order->order_number} berhasil dibuat dan diajukan.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function approvals(Request $request)
    {
        $statusTab = $request->get('tab', 'pending'); // pending, history, all
        $user = Auth::user();
        $isBranchApprover = $user->isBranchUser() && $user->organization_id;

        $baseApprovalsQuery = Order::query();
        if ($isBranchApprover) {
            $baseApprovalsQuery->where('requesting_organization_id', $user->organization_id);
        }

        $pendingCount = (clone $baseApprovalsQuery)->whereIn('status', ['SUBMITTED', 'WAITING_APPROVAL'])->count();
        $historyCount = (clone $baseApprovalsQuery)->whereNotIn('status', ['SUBMITTED', 'WAITING_APPROVAL'])->count();
        $allCount = (clone $baseApprovalsQuery)->count();

        $query = (clone $baseApprovalsQuery)->with(['requestingOrganization', 'requester', 'approver']);

        if ($statusTab === 'pending') {
            $query->whereIn('status', ['SUBMITTED', 'WAITING_APPROVAL']);
        } elseif ($statusTab === 'history') {
            $query->whereNotIn('status', ['SUBMITTED', 'WAITING_APPROVAL']);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%{$s}%")
                    ->orWhereHas('requestingOrganization', fn ($oq) => $oq->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('requester', fn ($rq) => $rq->where('name', 'like', "%{$s}%"));
            });
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;
        $orders = $query->latest()->paginate($perPage)->withQueryString();

        // Selected order for detailed approval preview
        $selectedOrderId = $request->get('order_id', $orders->first()?->id);
        $selectedOrder = null;
        $switchingRecommendations = [];
        $timeline = [];

        if ($selectedOrderId) {
            $selectedOrder = Order::with([
                'requestingOrganization',
                'requester',
                'approver',
                'items.item.stockBalances',
                'allocations.warehouse',
                'picking.picker',
                'packing.packer',
                'shipment.dispatcher',
                'shipment.courier',
                'receiving.receiver',
                'settlement.creator',
                'switchingStocks.sourceWarehouse.organization',
                'auditLogs.user',
            ])->find($selectedOrderId);

            if ($selectedOrder) {
                $switchingRecommendations = $this->switchingStockService->getSwitchingRecommendations($selectedOrder);
                $timeline = $selectedOrder->getWorkflowTimeline();
            }
        }

        return view('orders.approvals', compact(
            'orders',
            'statusTab',
            'pendingCount',
            'historyCount',
            'allCount',
            'selectedOrder',
            'switchingRecommendations',
            'timeline'
        ));
    }

    public function approvalDetail($id)
    {
        return redirect()->route('orders.approvals', ['order_id' => $id]);
    }

    public function show($id)
    {
        return redirect()->route('orders.index');
    }

    public function print($id)
    {
        $order = Order::with([
            'requestingOrganization',
            'requestingWarehouse',
            'requester',
            'approver',
            'items.item.category',
        ])->findOrFail($id);

        return view('orders.print', compact('order'));
    }

    public function update(Request $request, $id)
    {
        $order = Order::with('items')->findOrFail($id);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $order->requesting_organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk mengubah pesanan milik unit kerja lain.');
        }

        $rules = [
            'notes' => 'nullable|string|max:1000',
        ];

        $isEditable = in_array($order->status, ['DRAFT', 'SUBMITTED', 'WAITING_APPROVAL']);
        if ($isEditable) {
            $rules['priority'] = 'required|in:NORMAL,HIGH,URGENT';
            $rules['required_date'] = 'nullable|date';
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.item_id'] = 'required|exists:items,id';
            $rules['items.*.qty'] = 'required|integer|min:1';
        }

        $validated = $request->validate($rules);

        $oldValues = [
            'priority' => $order->priority,
            'required_date' => $order->required_date?->format('Y-m-d'),
            'notes' => $order->notes,
            'total_items' => $order->total_items,
            'total_estimated_value' => (float) $order->total_estimated_value,
        ];

        if ($isEditable) {
            $order->priority = $validated['priority'];
            $order->required_date = $validated['required_date'] ?? null;

            $totalItems = 0;
            $totalEstValue = 0;

            $order->items()->delete();
            foreach ($validated['items'] as $it) {
                $itemModel = Item::findOrFail($it['item_id']);
                $qty = (int) $it['qty'];
                $price = (float) $itemModel->estimated_unit_price;
                $subtotal = $qty * $price;
                $totalItems += $qty;
                $totalEstValue += $subtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $itemModel->id,
                    'qty_requested' => $qty,
                    'qty_approved' => 0,
                    'qty_allocated' => 0,
                    'unit_price_ref' => $price,
                    'subtotal_ref' => $subtotal,
                ]);
            }

            $order->total_items = $totalItems;
            $order->total_estimated_value = $totalEstValue;
        }

        if (array_key_exists('notes', $validated)) {
            $order->notes = $validated['notes'];
        }

        $order->save();

        AuditTrailService::log('UPDATE_ORDER', $order, $oldValues, [
            'priority' => $order->priority,
            'required_date' => $order->required_date?->format('Y-m-d'),
            'notes' => $order->notes,
            'total_items' => $order->total_items,
            'total_estimated_value' => (float) $order->total_estimated_value,
        ], Auth::user());

        return redirect()->route('orders.index')->with('success', "Data order {$order->order_number} berhasil diperbarui.");
    }

    public function destroy($id)
    {
        $order = Order::with(['items', 'allocations', 'shipment'])->findOrFail($id);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $order->requesting_organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk menghapus pesanan milik unit kerja lain.');
        }

        if (in_array($order->status, ['COMPLETED', 'RECEIVED', 'IN_TRANSIT'])) {
            return back()->with('error', "Order {$order->order_number} tidak dapat dihapus karena sudah dalam proses pengiriman atau telah selesai.");
        }

        try {
            DB::transaction(function () use ($order) {
                // Release committed budget if previously approved
                if (in_array($order->status, ['APPROVED', 'ALLOCATED', 'PICKING', 'PACKING', 'READY_TO_SHIP'])) {
                    $currentYear = (int) date('Y');
                    $budget = $order->budget ?: Budget::where('organization_id', $order->requesting_organization_id)->where('year', $currentYear)->first();
                    if ($budget) {
                        $budget->committed_amount = max(0, (float) $budget->committed_amount - (float) $order->total_estimated_value);
                        $budget->save();
                    }
                }

                AuditTrailService::log('DELETE_ORDER', $order, [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_items' => $order->total_items,
                    'total_estimated_value' => (float) $order->total_estimated_value,
                ], null, Auth::user());

                $order->items()->delete();
                $order->allocations()->delete();
                $order->delete();
            });

            return redirect()->route('orders.index')
                ->with('success', "Order {$order->order_number} berhasil dihapus dari sistem.");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal menghapus order: '.$e->getMessage());
        }
    }

    public function approve(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $user = Auth::user();

        if ($user->isBranchUser() && $user->organization_id && $order->requesting_organization_id !== $user->organization_id) {
            return back()->with('error', 'Anda tidak memiliki wewenang untuk menyetujui pesanan milik unit kerja lain.');
        }

        try {
            $overbudgetReason = $request->input('overbudget_approval_reason');
            $this->orderFulfillmentService->approveOrder($order, Auth::user(), $overbudgetReason);

            return redirect()->back()
                ->with('success', "Order {$order->order_number} berhasil disetujui & alokasi stok telah direservasi.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);

        try {
            $this->orderFulfillmentService->rejectOrder($order, Auth::user(), $request->reason);

            return redirect()->back()
                ->with('success', "Order {$order->order_number} berhasil ditolak.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function proposeSwitching(Request $request, $id)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $order = Order::findOrFail($id);
        $item = Item::findOrFail($request->item_id);

        try {
            $this->switchingStockService->proposeSwitching(
                $order,
                $item,
                (int) $request->source_warehouse_id,
                (int) $request->qty,
                $request->notes ?? 'Switching stock suggestion',
                Auth::user()
            );

            return redirect()->route('orders.index')
                ->with('success', 'Proposal switching stock berhasil diajukan ke unit penyedia.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
