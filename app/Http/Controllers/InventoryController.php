<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\ForecastingService;
use App\Services\StockLedgerService;
use Exception;
use Illuminate\Http\Request;
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

        $query = StockBalance::with(['warehouse.organization', 'item.category']);

        if ($selectedWarehouseId && $selectedWarehouseId !== 'all') {
            $query->where('warehouse_id', $selectedWarehouseId);
        }

        $balances = $query->get();

        // Summary KPIs
        $totalOnHand = $balances->sum('on_hand');
        $totalReserved = $balances->sum('reserved');
        $totalDamaged = $balances->sum('damaged');
        $totalAvailable = $balances->sum(fn ($b) => $b->available);
        $totalValuation = $balances->sum(fn ($b) => $b->on_hand * (float) $b->item->estimated_unit_price);
        $totalSkuCount = $balances->pluck('item_id')->unique()->count();

        $currentWarehouse = $selectedWarehouseId && $selectedWarehouseId !== 'all'
            ? $warehouses->firstWhere('id', $selectedWarehouseId)
            : null;

        return view('inventory.balances', compact(
            'warehouses',
            'selectedWarehouseId',
            'balances',
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
        $warehouses = Warehouse::where('is_active', true)->get();
        $selectedWarehouseId = $request->warehouse_id ?: $warehouses->first()?->id;

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 20, 25, 50]) ? (int) $request->get('per_page') : 20;
        $ledgers = StockLedger::with(['warehouse', 'creator'])
            ->where('item_id', $item->id)
            ->when($selectedWarehouseId, fn ($q) => $q->where('warehouse_id', $selectedWarehouseId))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $currentBalance = StockBalance::where('item_id', $item->id)
            ->where('warehouse_id', $selectedWarehouseId)
            ->first();

        return view('inventory.stock_card', compact('item', 'warehouses', 'selectedWarehouseId', 'ledgers', 'currentBalance'));
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

    public function forecasting()
    {
        $forecasts = $this->forecastingService->getAllForecasts();

        return view('inventory.forecasting', compact('forecasts'));
    }
}
