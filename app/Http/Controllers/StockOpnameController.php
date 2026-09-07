<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Warehouse;
use App\Services\StockLedgerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockOpnameController extends Controller
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    public function index(Request $request)
    {
        $warehouses = Warehouse::with('organization')->where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $selectedWarehouseId = (int) ($request->get('warehouse_id') ?: $warehouses->first()?->id);

        $currentWarehouse = $warehouses->firstWhere('id', $selectedWarehouseId) ?? $warehouses->first();
        $selectedWarehouseId = $currentWarehouse?->id;

        $items = Item::with(['category', 'stockBalances' => function ($q) use ($selectedWarehouseId) {
            $q->where('warehouse_id', $selectedWarehouseId);
        }])->where('is_active', true)->get();

        return view('inventory.stock_opname', compact('warehouses', 'selectedWarehouseId', 'currentWarehouse', 'items'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'counts' => 'required|array|min:1',
            'counts.*.item_id' => 'required|exists:items,id',
            'counts.*.physical_qty' => 'required|integer|min:0',
            'counts.*.system_qty' => 'required|integer|min:0',
            'opname_notes' => 'required|string',
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        $user = Auth::user();
        $refNo = 'OPN/'.date('Y/m').'/'.sprintf('%04d', rand(100, 999));

        try {
            $adjustedCount = 0;
            foreach ($request->counts as $entry) {
                $item = Item::findOrFail($entry['item_id']);
                $physicalQty = (int) $entry['physical_qty'];
                $systemQty = (int) $entry['system_qty'];
                $diff = $physicalQty - $systemQty;

                if ($diff !== 0) {
                    $this->stockLedgerService->adjustStock(
                        $warehouse,
                        $item,
                        $diff,
                        'STOCK_OPNAME',
                        $refNo,
                        "Stock Opname {$refNo}: Selisih fisik {$physicalQty} vs sistem {$systemQty} ({$request->opname_notes})",
                        $user
                    );
                    $adjustedCount++;
                }
            }

            return redirect()->route('inventory.balances', ['warehouse_id' => $warehouse->id])
                ->with('success', "Stock Opname {$refNo} berhasil disimpan. {$adjustedCount} item dengan selisih telah disesuaikan dan diposting ke Stock Ledger.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
