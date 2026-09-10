<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Services\NotificationService;
use App\Services\StockLedgerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    public function __construct(
        protected StockLedgerService $stockLedgerService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $isBranch = $user && method_exists($user, 'isBranchUser') && $user->isBranchUser();

        $warehousesQuery = Warehouse::with('organization')->where('is_active', true)->orderBy('type')->orderBy('name');
        if ($isBranch && $user->warehouse_id) {
            $warehouses = $warehousesQuery->where('id', $user->warehouse_id)->get();
        } else {
            $warehouses = $warehousesQuery->get();
        }

        $selectedWarehouseId = (int) ($request->get('warehouse_id') ?: $warehouses->first()?->id);
        $currentWarehouse = $warehouses->firstWhere('id', $selectedWarehouseId) ?? $warehouses->first();
        $selectedWarehouseId = $currentWarehouse?->id;

        $selectedYear = (int) ($request->get('period_year', date('Y')));
        $selectedMonth = (int) ($request->get('period_month', date('n')));

        $years = range((int) date('Y') - 2, (int) date('Y') + 1);
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $items = Item::with(['category', 'stockBalances' => function ($q) use ($selectedWarehouseId) {
            $q->where('warehouse_id', $selectedWarehouseId);
        }])->where('is_active', true)->get();

        return view('inventory.stock_opname', compact(
            'warehouses',
            'selectedWarehouseId',
            'currentWarehouse',
            'items',
            'selectedYear',
            'selectedMonth',
            'years',
            'months'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'period_year' => 'nullable|integer|min:2000|max:2099',
            'period_month' => 'nullable|integer|min:1|max:12',
            'counts' => 'required|array|min:1',
            'counts.*.item_id' => 'required|exists:items,id',
            'counts.*.physical_qty' => 'required|integer|min:0',
            'counts.*.system_qty' => 'required|integer|min:0',
            'opname_notes' => 'required|string',
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        $user = Auth::user();

        $periodYear = (int) ($request->input('period_year') ?: date('Y'));
        $periodMonth = (int) ($request->input('period_month') ?: date('n'));

        // Generate unique reference number: OPN/{YYYY}/{MM}/{XXXX}
        do {
            $refNo = sprintf('OPN/%04d/%02d/%04d', $periodYear, $periodMonth, rand(1000, 9999));
        } while (StockOpname::where('opname_number', $refNo)->exists());

        try {
            DB::beginTransaction();

            $stockOpname = StockOpname::create([
                'opname_number' => $refNo,
                'warehouse_id' => $warehouse->id,
                'user_id' => $user?->id,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'opname_date' => now()->toDateString(),
                'status' => 'POSTED',
                'notes' => $request->opname_notes,
                'total_items' => count($request->counts),
                'discrepancy_items_count' => 0,
                'net_variance_qty' => 0,
                'net_variance_value' => 0,
            ]);

            $adjustedCount = 0;
            $netVarianceQty = 0;
            $netVarianceValue = 0;

            foreach ($request->counts as $entry) {
                $item = Item::findOrFail($entry['item_id']);
                $physicalQty = (int) $entry['physical_qty'];
                $systemQty = (int) $entry['system_qty'];
                $diff = $physicalQty - $systemQty;
                $unitPrice = (float) $item->estimated_unit_price;
                $varianceVal = $diff * $unitPrice;

                StockOpnameItem::create([
                    'stock_opname_id' => $stockOpname->id,
                    'item_id' => $item->id,
                    'system_qty' => $systemQty,
                    'physical_qty' => $physicalQty,
                    'variance_qty' => $diff,
                    'unit_price' => $unitPrice,
                    'variance_value' => $varianceVal,
                ]);

                if ($diff !== 0) {
                    $this->stockLedgerService->adjustStock(
                        $warehouse,
                        $item,
                        $diff,
                        'STOCK_OPNAME',
                        $refNo,
                        "Stock Opname {$refNo} Periode {$stockOpname->period_formatted}: Selisih fisik {$physicalQty} vs sistem {$systemQty} ({$request->opname_notes})",
                        $user
                    );
                    $adjustedCount++;
                }

                $netVarianceQty += $diff;
                $netVarianceValue += $varianceVal;
            }

            $stockOpname->update([
                'discrepancy_items_count' => $adjustedCount,
                'net_variance_qty' => $netVarianceQty,
                'net_variance_value' => $netVarianceValue,
            ]);

            DB::commit();

            if ($adjustedCount > 0) {
                NotificationService::sendAlert(
                    "Stock Opname {$refNo} Selesai dengan {$adjustedCount} Selisih Stok",
                    "Stock opname di {$warehouse->name} selesai dengan {$adjustedCount} item selisih stok (Net Variansi: {$netVarianceQty} unit). Penyesuaian telah diposting ke Stock Ledger.",
                    'HIGH',
                    'INVENTORY_OFFICER',
                    $warehouse->organization_id,
                    'STOCK_OPNAME',
                    $stockOpname->id,
                    "/inventory/stock-opname/history/{$stockOpname->id}"
                );

                NotificationService::sendAlert(
                    "Temuan Selisih Stock Opname {$refNo} ({$warehouse->name})",
                    "Terdapat selisih fisik vs sistem pada {$adjustedCount} item di {$warehouse->name}. Catatan: {$request->opname_notes}",
                    'WARNING',
                    'AUDITOR',
                    null,
                    'STOCK_OPNAME',
                    $stockOpname->id,
                    "/inventory/stock-opname/history/{$stockOpname->id}"
                );
            } else {
                NotificationService::sendRole(
                    'INVENTORY_OFFICER',
                    "Stock Opname {$refNo} Selesai (Sesuai)",
                    "Stock opname di {$warehouse->name} telah selesai diverifikasi tanpa selisih stok fisik vs sistem.",
                    $warehouse->organization_id,
                    'INFORMATION',
                    'INFO',
                    'STOCK_OPNAME',
                    $stockOpname->id,
                    "/inventory/stock-opname/history/{$stockOpname->id}"
                );
            }

            return redirect()->route('inventory.balances', ['warehouse_id' => $warehouse->id])
                ->with('success', "Stock Opname {$refNo} Periode {$stockOpname->period_formatted} berhasil disimpan. {$adjustedCount} item dengan selisih telah disesuaikan dan diposting ke Stock Ledger.");
        } catch (Exception $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        }
    }

    public function history(Request $request)
    {
        $user = Auth::user();
        $isBranch = $user && method_exists($user, 'isBranchUser') && $user->isBranchUser();

        $warehousesQuery = Warehouse::with('organization')->where('is_active', true)->orderBy('type')->orderBy('name');
        if ($isBranch && $user->warehouse_id) {
            $warehouses = $warehousesQuery->where('id', $user->warehouse_id)->get();
        } else {
            $warehouses = $warehousesQuery->get();
        }

        $selectedWarehouseId = $request->get('warehouse_id');
        if ($selectedWarehouseId === null || strtolower((string) $selectedWarehouseId) === 'all') {
            $selectedWarehouseId = ($selectedWarehouseId === null && $isBranch && $user->warehouse_id) ? (string) $user->warehouse_id : 'all';
        }

        $selectedYear = $request->get('period_year', (string) date('Y'));
        $selectedMonth = $request->get('period_month', '');

        $years = range((int) date('Y') - 2, (int) date('Y') + 1);
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $baseQuery = StockOpname::with(['warehouse.organization', 'user']);

        if ($selectedWarehouseId && strtolower((string) $selectedWarehouseId) !== 'all') {
            $baseQuery->where('warehouse_id', (int) $selectedWarehouseId);
        }

        if ($selectedYear && $selectedYear !== 'all') {
            $baseQuery->where('period_year', (int) $selectedYear);
        }

        if ($selectedMonth && $selectedMonth !== 'all') {
            $baseQuery->where('period_month', (int) $selectedMonth);
        }

        // Search in opname number or notes
        $search = $request->get('search');
        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('opname_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Metrics for current filtered scope
        $kpiQuery = clone $baseQuery;
        $totalSessions = (clone $kpiQuery)->count();
        $totalItemsAudited = (clone $kpiQuery)->sum('total_items');
        $totalDiscrepancies = (clone $kpiQuery)->sum('discrepancy_items_count');
        $netVarianceVal = (clone $kpiQuery)->sum('net_variance_value');

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 20, 25, 50]) ? (int) $request->get('per_page') : 10;
        $opnames = $baseQuery->with('items.item.category')->latest('id')->paginate($perPage)->withQueryString();

        $currentWarehouse = ($selectedWarehouseId && $selectedWarehouseId !== 'all')
            ? $warehouses->firstWhere('id', (int) $selectedWarehouseId)
            : null;

        return view('inventory.stock_opname_history', compact(
            'opnames',
            'warehouses',
            'selectedWarehouseId',
            'currentWarehouse',
            'selectedYear',
            'selectedMonth',
            'years',
            'months',
            'search',
            'perPage',
            'totalSessions',
            'totalItemsAudited',
            'totalDiscrepancies',
            'netVarianceVal'
        ));
    }

    public function showHistory($id)
    {
        $opname = StockOpname::with([
            'warehouse.organization',
            'user',
            'items.item.category',
        ])->findOrFail($id);

        return response()->json($opname);
    }
}
