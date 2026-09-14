<?php

namespace App\Http\Controllers;

use App\Models\EmbossFile;
use App\Models\Item;
use App\Models\Order;
use App\Models\Organization;
use App\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Services\ProductionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductionController extends Controller
{
    public function __construct(
        protected ProductionService $productionService
    ) {}

    public function index(Request $request)
    {
        $status = $request->get('status');
        $search = $request->get('search');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;

        $query = ProductionOrder::with(['destinationOrganization', 'warehouse', 'creator', 'items.item']);

        if ($status && $status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('production_number', 'like', "%{$search}%")
                    ->orWhereHas('destinationOrganization', fn ($org) => $org->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        $productionOrders = $query->latest()->paginate($perPage)->withQueryString();

        $metrics = [
            'total' => ProductionOrder::count(),
            'planned' => ProductionOrder::where('status', 'PLANNED')->count(),
            'in_production' => ProductionOrder::whereIn('status', ['ISSUED', 'IN_PRODUCTION'])->count(),
            'completed' => ProductionOrder::where('status', 'COMPLETED')->count(),
        ];

        $warehouses = Warehouse::where('is_active', true)->get();
        $organizations = Organization::where('is_active', true)->whereIn('type', ['MAIN_BRANCH', 'SUB_BRANCH'])->orderBy('name')->get();
        $items = Item::where('is_active', true)
            ->whereHas('category', fn ($c) => $c->whereIn('code', ['CAT-ATM', 'CAT-TKN', 'CAT-KUE', 'CAT-CTK']))
            ->get();
        $embossFiles = EmbossFile::where('status', 'COMPLETED')->latest()->limit(20)->get();
        $orders = Order::whereIn('status', ['APPROVED', 'ALLOCATED'])->latest()->limit(20)->get();

        return view('production.index', compact(
            'productionOrders',
            'metrics',
            'status',
            'search',
            'perPage',
            'warehouses',
            'organizations',
            'items',
            'embossFiles',
            'orders'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'destination_organization_id' => 'required|exists:organizations,id',
            'emboss_file_id' => 'nullable|exists:emboss_files,id',
            'order_id' => 'nullable|exists:orders,id',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty_planned' => 'required|integer|min:1',
        ], [
            'items.required' => 'Minimal satu item bahan kartu/token harus dipilih pada bon.',
        ]);

        try {
            $warehouse = Warehouse::findOrFail($request->warehouse_id);
            $destinationOrg = Organization::findOrFail($request->destination_organization_id);
            $embossFile = $request->emboss_file_id ? EmbossFile::find($request->emboss_file_id) : null;
            $order = $request->order_id ? Order::find($request->order_id) : null;

            $prodOrder = $this->productionService->createProductionOrder(
                $warehouse,
                $destinationOrg,
                $request->items,
                Auth::user(),
                $embossFile,
                $order,
                $request->notes
            );

            return redirect()->route('production.show', $prodOrder->id)
                ->with('success', "Bon produksi {$prodOrder->production_number} berhasil dibuat.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $prodOrder = ProductionOrder::with([
            'destinationOrganization',
            'warehouse.organization',
            'embossFile',
            'order',
            'creator',
            'issuer',
            'items.item.category',
        ])->findOrFail($id);

        $manifestData = $this->productionService->getProductionManifestData($prodOrder);

        return view('production.show', compact('prodOrder', 'manifestData'));
    }

    public function issueStock(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.production_order_item_id' => 'required|exists:production_order_items,id',
            'items.*.qty_to_issue' => 'required|integer|min:1',
        ]);

        $prodOrder = ProductionOrder::findOrFail($id);

        try {
            $this->productionService->issueProductionStock($prodOrder, $request->items, Auth::user());

            return back()->with('success', "Pengeluaran bahan bon produksi {$prodOrder->production_number} berhasil diproses (Stok berkurang PRODUCTION_ISSUE).");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function manifest($id)
    {
        $prodOrder = ProductionOrder::with([
            'destinationOrganization',
            'warehouse',
            'embossFile',
            'order',
            'items.item.category',
        ])->findOrFail($id);

        $manifestData = $this->productionService->getProductionManifestData($prodOrder);

        return view('production.manifest', compact('prodOrder', 'manifestData'));
    }

    public function update(Request $request, $id)
    {
        $prodOrder = ProductionOrder::findOrFail($id);
        $request->validate([
            'notes' => 'nullable|string|max:500',
            'target_completion_date' => 'nullable|date',
        ]);

        $prodOrder->update($request->only(['notes', 'target_completion_date']));

        return redirect()->route('production.index')->with('success', "Bon Produksi {$prodOrder->production_number} berhasil diperbarui.");
    }

    public function destroy($id)
    {
        $prodOrder = ProductionOrder::findOrFail($id);

        if (in_array($prodOrder->status, ['COMPLETED', 'IN_PRODUCTION'], true)) {
            return redirect()->route('production.index')->with('error', 'Bon Produksi yang sedang atau telah diproses tidak dapat dihapus.');
        }

        $prodNum = $prodOrder->production_number;
        $prodOrder->items()->delete();
        $prodOrder->delete();

        return redirect()->route('production.index')->with('success', "Bon Produksi {$prodNum} berhasil dibatalkan dan dihapus.");
    }
}
