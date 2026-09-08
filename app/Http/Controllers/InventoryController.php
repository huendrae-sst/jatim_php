<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\ForecastingService;
use App\Services\StockLedgerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    public function __construct(
        protected StockLedgerService $stockLedgerService,
        protected ForecastingService $forecastingService
    ) {}

    public function stockBalances(Request $request)
    {
        $user = Auth::user();
        $isBranch = $user && $user->isBranchUser();

        $warehousesQuery = Warehouse::with('organization')->where('is_active', true)->orderBy('type')->orderBy('name');
        if ($isBranch && $user->warehouse_id) {
            $warehouses = $warehousesQuery->where('id', $user->warehouse_id)->get();
        } else {
            $warehouses = $warehousesQuery->get();
        }

        $selectedWarehouseId = $request->get('warehouse_id');

        if ($selectedWarehouseId === null) {
            $selectedWarehouseId = ($isBranch && $user->warehouse_id) ? $user->warehouse_id : $warehouses->first()?->id;
        }

        $baseQuery = StockBalance::with(['warehouse.organization', 'item.category']);

        if ($selectedWarehouseId && $selectedWarehouseId !== 'all') {
            $baseQuery->where('warehouse_id', $selectedWarehouseId);
        }

        // Summary KPIs
        $kpiBalances = (clone $baseQuery)->get();
        $totalOnHand = $kpiBalances->sum('on_hand');
        $totalReserved = $kpiBalances->sum('reserved');
        $totalDamaged = $kpiBalances->sum('damaged');
        $totalAvailable = $kpiBalances->sum(fn ($b) => $b->available);
        $totalValuation = $kpiBalances->sum(fn ($b) => $b->on_hand * (float) $b->item->estimated_unit_price);
        $totalSkuCount = $kpiBalances->pluck('item_id')->unique()->count();

        // Search & Filters
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $stockFilter = $request->get('stock_filter');

        $query = clone $baseQuery;

        if ($search) {
            $query->whereHas('item', function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId && $categoryId !== 'ALL') {
            $query->whereHas('item', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if ($stockFilter) {
            if ($stockFilter === 'available') {
                $query->whereRaw('(on_hand - reserved - hold - damaged) > 0');
            } elseif ($stockFilter === 'damaged') {
                $query->where('damaged', '>', 0);
            } elseif ($stockFilter === 'reserved') {
                $query->where('reserved', '>', 0);
            }
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 20, 25, 50]) ? (int) $request->get('per_page') : 10;
        $balances = $query->paginate($perPage)->withQueryString();

        $categories = Category::orderBy('name')->get();

        $currentWarehouse = $selectedWarehouseId && $selectedWarehouseId !== 'all'
            ? $warehouses->firstWhere('id', $selectedWarehouseId)
            : null;

        return view('inventory.balances', compact(
            'warehouses',
            'selectedWarehouseId',
            'balances',
            'categories',
            'search',
            'categoryId',
            'stockFilter',
            'perPage',
            'totalOnHand',
            'totalReserved',
            'totalDamaged',
            'totalAvailable',
            'totalValuation',
            'totalSkuCount',
            'currentWarehouse'
        ));
    }

    public function stockCard($itemId, Request $request)
    {
        $item = Item::with('category')->findOrFail($itemId);
        $warehouses = Warehouse::with('organization')->where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $selectedWarehouseId = $request->warehouse_id ?: $warehouses->first()?->id;

        $search = $request->get('search');
        $transactionType = $request->get('transaction_type');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 20, 25, 50]) ? (int) $request->get('per_page') : 10;

        $query = StockLedger::with(['warehouse.organization', 'creator'])
            ->where('item_id', $item->id)
            ->when($selectedWarehouseId && $selectedWarehouseId !== 'ALL', fn ($q) => $q->where('warehouse_id', $selectedWarehouseId));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($transactionType && $transactionType !== 'ALL') {
            $query->where('transaction_type', $transactionType);
        }

        $ledgers = $query->latest()
            ->paginate($perPage)
            ->withQueryString();

        if ($selectedWarehouseId === 'ALL') {
            $currentBalance = (object) [
                'on_hand' => StockBalance::where('item_id', $item->id)->sum('on_hand'),
                'reserved' => StockBalance::where('item_id', $item->id)->sum('reserved'),
                'hold' => StockBalance::where('item_id', $item->id)->sum('hold'),
                'damaged' => StockBalance::where('item_id', $item->id)->sum('damaged'),
                'available' => StockBalance::where('item_id', $item->id)->sum('available'),
            ];
            $currentWarehouse = null;
        } else {
            $currentBalance = StockBalance::where('item_id', $item->id)
                ->where('warehouse_id', $selectedWarehouseId)
                ->first();
            $currentWarehouse = $warehouses->firstWhere('id', $selectedWarehouseId);
        }

        return view('inventory.stock_card', compact(
            'item',
            'warehouses',
            'selectedWarehouseId',
            'currentWarehouse',
            'ledgers',
            'currentBalance',
            'search',
            'transactionType',
            'perPage'
        ));
    }

    public function adjustmentStore(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
            'qty_diff' => 'required|integer',
            'transaction_type' => 'required|string',
            'notes' => 'required|string',
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        $item = Item::findOrFail($request->item_id);

        try {
            $this->stockLedgerService->adjustStock(
                $warehouse,
                $item,
                (int) $request->qty_diff,
                $request->transaction_type,
                'ADJ/'.date('Ymd/His'),
                $request->notes,
                Auth::user()
            );

            return back()->with('success', 'Penyesuaian stok berhasil dicatat ke Stock Ledger.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function forecasting(?Request $request = null)
    {
        $request = $request ?? request();
        $search = $request->get('search');
        $categoryId = $request->get('category_id');
        $riskLevel = $request->get('risk_level');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 20, 25, 50]) ? (int) $request->get('per_page') : 10;

        $query = Item::with(['category', 'stockBalances'])->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($categoryId && $categoryId !== 'ALL') {
            $query->where('category_id', $categoryId);
        }

        $items = $query->orderBy('sku')->get();

        $allForecasts = [];
        $criticalCount = 0;
        $reorderCount = 0;
        $safeCount = 0;
        $totalSuggestedReorderQty = 0;

        foreach ($items as $item) {
            $f = $this->forecastingService->getItemForecast($item);

            if ($f['risk_level'] === 'CRITICAL_STOCKOUT') {
                $criticalCount++;
            } elseif ($f['risk_level'] === 'HIGH_REORDER') {
                $reorderCount++;
            } else {
                $safeCount++;
            }
            $totalSuggestedReorderQty += $f['suggested_reorder_qty'];

            if ($riskLevel && $riskLevel !== 'ALL') {
                if ($f['risk_level'] !== $riskLevel) {
                    continue;
                }
            }
            $allForecasts[] = $f;
        }

        $totalSkuCount = count($items);

        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;
        $itemsForCurrentPage = array_slice($allForecasts, $offset, $perPage);

        $forecasts = new LengthAwarePaginator(
            $itemsForCurrentPage,
            count($allForecasts),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $categories = Category::orderBy('name')->get();

        return view('inventory.forecasting', compact(
            'forecasts',
            'categories',
            'search',
            'categoryId',
            'riskLevel',
            'perPage',
            'totalSkuCount',
            'criticalCount',
            'reorderCount',
            'safeCount',
            'totalSuggestedReorderQty'
        ));
    }
}
