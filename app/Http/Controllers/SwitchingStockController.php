<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\SwitchingStock;
use App\Models\Warehouse;
use App\Services\SwitchingStockService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SwitchingStockController extends Controller
{
    public function __construct(
        protected SwitchingStockService $switchingStockService
    ) {}

    public function index(Request $request)
    {
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;
        $search = $request->get('search');
        $status = $request->get('status');
        $orgId = $request->get('organization_id');

        $query = SwitchingStock::with([
            'order.requestingOrganization',
            'item',
            'items.item',
            'sourceOrganization',
            'sourceWarehouse',
            'destinationOrganization',
            'destinationWarehouse',
            'proposer',
            'approver',
            'transferredBy',
            'receivedBy',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('items.item', function ($iq) use ($search) {
                    $iq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                })->orWhereHas('item', function ($iq) use ($search) {
                    $iq->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                })->orWhereHas('order', function ($oq) use ($search) {
                    $oq->where('order_number', 'ilike', "%{$search}%");
                })->orWhereHas('sourceOrganization', function ($soq) use ($search) {
                    $soq->where('name', 'ilike', "%{$search}%");
                })->orWhereHas('destinationOrganization', function ($doq) use ($search) {
                    $doq->where('name', 'ilike', "%{$search}%");
                });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($orgId) {
            $query->where(function ($q) use ($orgId) {
                $q->where('source_organization_id', $orgId)
                    ->orWhere('destination_organization_id', $orgId);
            });
        }

        $switchings = $query->latest()->paginate($perPage)->withQueryString();

        $organizations = Organization::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::with('organization')->where('is_active', true)->orderBy('name')->get();
        $items = Item::where('is_active', true)->orderBy('name')->get();

        return view('inventory.switching.index', compact(
            'switchings',
            'organizations',
            'warehouses',
            'items',
            'perPage',
            'search',
            'status',
            'orgId'
        ));
    }

    public function recommendations(Request $request): JsonResponse
    {
        $request->validate([
            'destination_organization_id' => 'required|exists:organizations,id',
            'items' => 'nullable|array|min:1',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.qty' => 'required_with:items|integer|min:1',
            'item_id' => 'nullable|exists:items,id',
            'qty' => 'nullable|integer|min:1',
        ]);

        $destOrgId = (int) $request->destination_organization_id;

        $itemsList = [];
        if ($request->has('items') && is_array($request->items)) {
            foreach ($request->items as $it) {
                if (! empty($it['item_id'])) {
                    $itemsList[] = [
                        'item_id' => (int) $it['item_id'],
                        'qty' => (int) ($it['qty'] ?? 1),
                    ];
                }
            }
        } elseif ($request->filled('item_id')) {
            $itemsList[] = [
                'item_id' => (int) $request->item_id,
                'qty' => (int) ($request->qty ?? 1),
            ];
        }

        if (empty($itemsList)) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan tentukan minimal 1 barang dan jumlah kebutuhan.',
            ], 422);
        }

        $alternatives = $this->switchingStockService->findAlternativeSourcesForMultipleItems($itemsList, $destOrgId);
        $itemRecommendations = $this->switchingStockService->getItemRecommendations($itemsList, $destOrgId);

        $primaryItem = Item::find($itemsList[0]['item_id']);

        return response()->json([
            'success' => true,
            'item' => $primaryItem ? [
                'id' => $primaryItem->id,
                'name' => $primaryItem->name,
                'sku' => $primaryItem->sku,
                'uom' => $primaryItem->uom,
                'safety_stock' => $primaryItem->safety_stock,
            ] : null,
            'total_items_requested' => count($itemsList),
            'recommendations' => $alternatives,
            'item_recommendations' => $itemRecommendations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'destination_organization_id' => 'required|exists:organizations,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id',
            'source_organization_id' => 'required|exists:organizations,id',
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'recommendation_reason' => 'nullable|string|max:500',
            'items' => 'nullable|array|min:1',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.qty_requested' => 'required_with:items|integer|min:1',
            'item_id' => 'nullable|exists:items,id',
            'qty_requested' => 'nullable|integer|min:1',
        ], [
            'destination_organization_id.required' => 'Unit tujuan wajib dipilih.',
            'destination_warehouse_id.required' => 'Gudang tujuan wajib dipilih.',
            'source_organization_id.required' => 'Unit sumber wajib dipilih.',
            'source_warehouse_id.required' => 'Gudang sumber wajib dipilih.',
            'items.min' => 'Minimal harus ada 1 barang yang diajukan.',
            'items.*.item_id.required_with' => 'Barang pada daftar wajib dipilih.',
            'items.*.qty_requested.required_with' => 'Jumlah barang pada daftar wajib diisi.',
            'items.*.qty_requested.min' => 'Jumlah barang minimal 1 unit.',
        ]);

        if (empty($validated['items']) && empty($validated['item_id'])) {
            return redirect()->back()->withInput()->with('error', 'Minimal harus memilih 1 barang yang diajukan.');
        }

        if ($validated['source_warehouse_id'] == $validated['destination_warehouse_id']) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gudang sumber dan gudang tujuan tidak boleh sama.');
        }

        try {
            $switching = $this->switchingStockService->createManualSwitching($validated, Auth::user());
            $switching->loadMissing('items.item');
            $itemCount = $switching->items->count();
            $msg = $itemCount > 1
                ? "Pengajuan manual switching stock #{$switching->id} untuk {$itemCount} jenis barang berhasil dibuat."
                : 'Pengajuan manual switching stock #'.$switching->id.' untuk barang '.($switching->item ? $switching->item->name : 'barang').' berhasil dibuat.';

            return redirect()->back()->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $switching = SwitchingStock::findOrFail($id);

        if ($switching->status !== 'PROPOSED') {
            return redirect()->back()->with('error', 'Hanya pengajuan dengan status PROPOSED yang dapat diedit.');
        }

        $validated = $request->validate([
            'destination_organization_id' => 'required|exists:organizations,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id',
            'source_organization_id' => 'required|exists:organizations,id',
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'recommendation_reason' => 'nullable|string|max:500',
            'items' => 'nullable|array|min:1',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.qty_requested' => 'required_with:items|integer|min:1',
            'item_id' => 'nullable|exists:items,id',
            'qty_requested' => 'nullable|integer|min:1',
        ]);

        if (empty($validated['items']) && empty($validated['item_id'])) {
            return redirect()->back()->withInput()->with('error', 'Minimal harus memilih 1 barang yang diajukan.');
        }

        if ($validated['source_warehouse_id'] == $validated['destination_warehouse_id']) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gudang sumber dan gudang tujuan tidak boleh sama.');
        }

        try {
            $this->switchingStockService->updateSwitching($switching, $validated, Auth::user());

            return redirect()->back()->with('success', "Pengajuan switching stock #{$switching->id} berhasil diperbarui.");
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id): RedirectResponse
    {
        $switching = SwitchingStock::findOrFail($id);

        if ($switching->status !== 'PROPOSED') {
            return redirect()->back()->with('error', 'Hanya pengajuan dengan status PROPOSED yang dapat dihapus.');
        }

        try {
            $this->switchingStockService->deleteSwitching($switching, Auth::user());

            return redirect()->back()->with('success', "Pengajuan switching stock #{$switching->id} berhasil dihapus.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function approve($id): RedirectResponse
    {
        $switching = SwitchingStock::findOrFail($id);
        try {
            $this->switchingStockService->approveSwitching($switching, Auth::user());

            $ref = $switching->order ? "order {$switching->order->order_number}" : "switching manual #{$switching->id}";

            return redirect()->back()->with('success', "Switching stock untuk {$ref} berhasil disetujui & stok unit sumber direservasi.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function approvals(Request $request)
    {
        $statusTab = $request->get('tab', 'pending'); // pending, history, all
        $user = Auth::user();
        $isBranchApprover = $user->isBranchUser() && $user->organization_id;

        $baseApprovalsQuery = SwitchingStock::query();
        if ($isBranchApprover) {
            $baseApprovalsQuery->where(function ($q) use ($user) {
                $q->where('source_organization_id', $user->organization_id)
                    ->orWhere('destination_organization_id', $user->organization_id);
            });
        }

        $pendingCount = (clone $baseApprovalsQuery)->whereIn('status', ['PROPOSED', 'WAITING_APPROVAL'])->count();
        $historyCount = (clone $baseApprovalsQuery)->whereNotIn('status', ['PROPOSED', 'WAITING_APPROVAL'])->count();
        $allCount = (clone $baseApprovalsQuery)->count();

        $query = (clone $baseApprovalsQuery)->with([
            'order.requestingOrganization',
            'item.category',
            'items.item.category',
            'sourceOrganization',
            'sourceWarehouse',
            'destinationOrganization',
            'destinationWarehouse',
            'proposer',
            'approver',
        ]);

        if ($statusTab === 'pending') {
            $query->whereIn('status', ['PROPOSED', 'WAITING_APPROVAL']);
        } elseif ($statusTab === 'history') {
            $query->whereNotIn('status', ['PROPOSED', 'WAITING_APPROVAL']);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('items.item', function ($iq) use ($s) {
                    $iq->where('name', 'ilike', "%{$s}%")
                        ->orWhere('sku', 'ilike', "%{$s}%");
                })->orWhereHas('item', function ($iq) use ($s) {
                    $iq->where('name', 'ilike', "%{$s}%")
                        ->orWhere('sku', 'ilike', "%{$s}%");
                })->orWhereHas('order', function ($oq) use ($s) {
                    $oq->where('order_number', 'ilike', "%{$s}%");
                })->orWhereHas('sourceOrganization', function ($soq) use ($s) {
                    $soq->where('name', 'ilike', "%{$s}%")->orWhere('code', 'ilike', "%{$s}%");
                })->orWhereHas('destinationOrganization', function ($doq) use ($s) {
                    $doq->where('name', 'ilike', "%{$s}%")->orWhere('code', 'ilike', "%{$s}%");
                })->orWhere('recommendation_reason', 'ilike', "%{$s}%")
                    ->orWhere('rejection_reason', 'ilike', "%{$s}%");
            });
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;
        $switchings = $query->latest()->paginate($perPage)->withQueryString();

        // Selected switching stock for detailed approval preview
        $selectedSwitchingId = $request->get('switching_id', $switchings->first()?->id);
        $selectedSwitching = null;

        if ($selectedSwitchingId) {
            $selectedSwitching = SwitchingStock::with([
                'order.requestingOrganization',
                'item.category',
                'items.item.category',
                'sourceOrganization',
                'sourceWarehouse',
                'destinationOrganization',
                'destinationWarehouse',
                'proposer',
                'approver',
                'transferredBy',
                'receivedBy',
            ])->find($selectedSwitchingId);

            if ($selectedSwitching) {
                // Calculate stock balances at source warehouse
                $swItemIds = $selectedSwitching->items->isNotEmpty()
                    ? $selectedSwitching->items->pluck('item_id')->all()
                    : ($selectedSwitching->item_id ? [$selectedSwitching->item_id] : []);

                $stockBalances = StockBalance::where('warehouse_id', $selectedSwitching->source_warehouse_id)
                    ->whereIn('item_id', $swItemIds)
                    ->get()
                    ->keyBy('item_id');

                $selectedSwitching->items_with_stock = $selectedSwitching->items->isNotEmpty()
                    ? $selectedSwitching->items->map(function ($it) use ($stockBalances) {
                        $sb = $stockBalances->get($it->item_id);
                        $available = $sb ? (int) $sb->available : 0;
                        $safetyStock = $it->item ? (int) $it->item->safety_stock : 0;
                        $excess = max(0, $available - $safetyStock);

                        return [
                            'id' => $it->id,
                            'item_id' => $it->item_id,
                            'item' => $it->item,
                            'qty_requested' => $it->qty_requested,
                            'available_stock' => $available,
                            'safety_stock' => $safetyStock,
                            'excess_stock' => $excess,
                            'is_sufficient' => $available >= $it->qty_requested,
                            'is_safe_excess' => $excess >= $it->qty_requested,
                        ];
                    })
                    : collect([
                        [
                            'id' => $selectedSwitching->id,
                            'item_id' => $selectedSwitching->item_id,
                            'item' => $selectedSwitching->item,
                            'qty_requested' => $selectedSwitching->qty_requested,
                            'available_stock' => ($sb = $stockBalances->get($selectedSwitching->item_id)) ? (int) $sb->available : 0,
                            'safety_stock' => $selectedSwitching->item ? (int) $selectedSwitching->item->safety_stock : 0,
                            'excess_stock' => max(0, (($sb ? (int) $sb->available : 0) - ($selectedSwitching->item ? (int) $selectedSwitching->item->safety_stock : 0))),
                            'is_sufficient' => (($sb ? (int) $sb->available : 0) >= $selectedSwitching->qty_requested),
                            'is_safe_excess' => (max(0, (($sb ? (int) $sb->available : 0) - ($selectedSwitching->item ? (int) $selectedSwitching->item->safety_stock : 0))) >= $selectedSwitching->qty_requested),
                        ],
                    ]);
            }
        }

        return view('inventory.switching.approvals', compact(
            'switchings',
            'statusTab',
            'pendingCount',
            'historyCount',
            'allCount',
            'selectedSwitching'
        ));
    }

    public function reject(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:500',
        ]);

        $switching = SwitchingStock::findOrFail($id);

        try {
            $this->switchingStockService->rejectSwitching($switching, $request->rejection_reason, Auth::user());

            $ref = $switching->order ? "order {$switching->order->order_number}" : "switching manual #{$switching->id}";

            return redirect()->back()->with('success', "Pengajuan switching stock untuk {$ref} telah ditolak.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function dispatchTransfer(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'tracking_number' => 'nullable|string|max:100',
        ]);

        $switching = SwitchingStock::findOrFail($id);

        try {
            $this->switchingStockService->dispatchTransfer($switching, $validated, Auth::user());

            $ref = $switching->order ? "Order #{$switching->order->order_number}" : "Switching #{$switching->id}";

            return redirect()->back()->with('success', "Barang transfer switching stock untuk {$ref} telah berhasil dikirim dari gudang asal.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function receiveTransfer(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $switching = SwitchingStock::findOrFail($id);

        try {
            $this->switchingStockService->receiveTransfer($switching, $validated, Auth::user());

            $ref = $switching->order ? "Order #{$switching->order->order_number}" : "Switching #{$switching->id}";

            return redirect()->back()->with('success', "Penerimaan barang switching stock untuk {$ref} telah berhasil dikonfirmasi di gudang tujuan.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
