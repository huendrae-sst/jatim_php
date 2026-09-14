<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\AuditTrailService;
use App\Services\ForecastingService;
use App\Services\NotificationService;
use App\Services\StockLedgerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            $selectedWarehouseId = ($isBranch && $user->warehouse_id) ? (string) $user->warehouse_id : $warehouses->first()?->id;
        } elseif (strtolower((string) $selectedWarehouseId) === 'all') {
            $selectedWarehouseId = 'all';
        }

        $baseQuery = StockBalance::with(['warehouse.organization', 'item.category']);

        if ($selectedWarehouseId && strtolower((string) $selectedWarehouseId) !== 'all') {
            $baseQuery->where('warehouse_id', (int) $selectedWarehouseId);
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

        $currentWarehouse = ($selectedWarehouseId && strtolower((string) $selectedWarehouseId) !== 'all')
            ? $warehouses->firstWhere('id', (int) $selectedWarehouseId)
            : null;

        $pendingAdjustments = StockAdjustment::with(['warehouse', 'item', 'creator', 'approver'])
            ->latest()
            ->get();

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
            'currentWarehouse',
            'pendingAdjustments'
        ));
    }

    public function stockCard($itemId, Request $request)
    {
        $item = Item::with('category')->findOrFail($itemId);
        $warehouses = Warehouse::with('organization')->where('is_active', true)->orderBy('type')->orderBy('name')->get();

        $rawWarehouseId = $request->get('warehouse_id');
        if ($rawWarehouseId !== null && strtolower((string) $rawWarehouseId) === 'all') {
            $selectedWarehouseId = 'ALL';
        } else {
            $selectedWarehouseId = $rawWarehouseId ?: ($warehouses->first()?->id ? (string) $warehouses->first()->id : 'ALL');
        }

        $search = $request->get('search');
        $transactionType = $request->get('transaction_type');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 20, 25, 50]) ? (int) $request->get('per_page') : 10;

        $query = StockLedger::with(['warehouse.organization', 'creator'])
            ->where('item_id', $item->id)
            ->when($selectedWarehouseId && strtoupper((string) $selectedWarehouseId) !== 'ALL', fn ($q) => $q->where('warehouse_id', (int) $selectedWarehouseId));

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

        if (strtoupper((string) $selectedWarehouseId) === 'ALL') {
            $balances = StockBalance::where('item_id', $item->id)->get();
            $currentBalance = (object) [
                'on_hand' => (int) $balances->sum('on_hand'),
                'reserved' => (int) $balances->sum('reserved'),
                'hold' => (int) $balances->sum('hold'),
                'damaged' => (int) $balances->sum('damaged'),
                'available' => (int) $balances->sum('available'),
            ];
            $currentWarehouse = null;
        } else {
            $currentBalance = StockBalance::where('item_id', $item->id)
                ->where('warehouse_id', (int) $selectedWarehouseId)
                ->first();
            $currentWarehouse = $warehouses->firstWhere('id', (int) $selectedWarehouseId);
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
            'notes' => 'required|string|min:3',
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        $item = Item::findOrFail($request->item_id);

        try {
            $sb = StockBalance::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->first();
            $qtyBefore = (int) ($sb?->on_hand ?? 0);
            $qtyDiff = (int) $request->qty_diff;
            $qtyAfter = $qtyBefore + $qtyDiff;

            // POC-14: Mencegah stok negatif
            if ($qtyAfter < 0) {
                return back()->with('error', "Penyesuaian stok ditolak: stok akhir tidak boleh negatif ({$qtyBefore} + {$qtyDiff} = {$qtyAfter}).");
            }

            // POC-17: Maker mengajukan penyesuaian (status PENDING_APPROVAL, stok belum berubah)
            $adjNumber = 'ADJ/'.date('Y/m').'/'.sprintf('%04d', StockAdjustment::count() + 1);

            $adjustment = StockAdjustment::create([
                'adjustment_number' => $adjNumber,
                'warehouse_id' => $warehouse->id,
                'item_id' => $item->id,
                'qty_diff' => $qtyDiff,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'transaction_type' => $request->transaction_type,
                'notes' => $request->notes,
                'status' => 'PENDING_APPROVAL',
                'created_by_user_id' => Auth::id(),
            ]);

            AuditTrailService::log('SUBMIT_STOCK_ADJUSTMENT', $adjustment, null, $adjustment->toArray(), Auth::user());

            $diffSign = $qtyDiff > 0 ? '+' : '';
            NotificationService::sendActionRequired(
                "Persetujuan Penyesuaian Stok {$adjustment->adjustment_number}",
                "Pengajuan penyesuaian stok untuk {$item->name} di gudang {$warehouse->name} ({$diffSign}{$qtyDiff} {$item->uom}) menunggu persetujuan Checker.",
                'INVENTORY_OFFICER',
                $warehouse->organization_id,
                'STOCK_ADJUSTMENT',
                $adjustment->id,
                '/inventory/stock-balances'
            );

            return back()->with('success', "Pengajuan penyesuaian stok {$adjustment->adjustment_number} berhasil dikirim (Maker-Checker). Saldo fisik akan diperbarui setelah Checker menyetujui.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function adjustmentApprove(Request $request, $id)
    {
        $adjustment = StockAdjustment::with(['warehouse', 'item', 'creator'])->findOrFail($id);
        $user = Auth::user();

        if ($adjustment->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Penyesuaian stok ini sudah diproses sebelumnya.');
        }

        // Segregation of Duties: Maker tidak boleh approve sendiri kecuali Super Admin
        if ($adjustment->created_by_user_id === $user->id && ! $user->isSuperAdmin()) {
            return back()->with('error', 'Segregation of Duties: Pembuat pengajuan tidak boleh menyetujui penyesuaian stok sendiri.');
        }

        try {
            DB::transaction(function () use ($adjustment, $user) {
                // Eksekusi perubahan stok fisik & stock ledger
                $this->stockLedgerService->adjustStock(
                    $adjustment->warehouse,
                    $adjustment->item,
                    $adjustment->qty_diff,
                    $adjustment->transaction_type,
                    $adjustment->adjustment_number,
                    $adjustment->notes,
                    $user
                );

                $adjustment->status = 'APPROVED';
                $adjustment->approved_by_user_id = $user->id;
                $adjustment->approved_at = now();
                $adjustment->save();

                AuditTrailService::log('APPROVE_STOCK_ADJUSTMENT', $adjustment, null, [
                    'status' => 'APPROVED',
                    'approved_by' => $user->name,
                    'qty_before' => $adjustment->qty_before,
                    'qty_after' => $adjustment->qty_after,
                ], $user);

                NotificationService::sendUser(
                    $adjustment->created_by_user_id,
                    "Penyesuaian Stok {$adjustment->adjustment_number} Disetujui",
                    "Pengajuan penyesuaian stok untuk {$adjustment->item->name} telah disetujui oleh {$user->name} dan saldo fisik telah diperbarui.",
                    'INFORMATION',
                    'INFO',
                    'STOCK_ADJUSTMENT',
                    $adjustment->id,
                    '/inventory/stock-balances'
                );
            });

            return back()->with('success', "Penyesuaian stok {$adjustment->adjustment_number} berhasil disetujui dan saldo fisik telah diperbarui.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function adjustmentReject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $adjustment = StockAdjustment::with(['warehouse', 'item'])->findOrFail($id);
        $user = Auth::user();

        if ($adjustment->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Penyesuaian stok ini sudah diproses sebelumnya.');
        }

        try {
            $adjustment->status = 'REJECTED';
            $adjustment->approved_by_user_id = $user->id;
            $adjustment->rejection_reason = $request->reason;
            $adjustment->save();

            AuditTrailService::log('REJECT_STOCK_ADJUSTMENT', $adjustment, null, [
                'status' => 'REJECTED',
                'reason' => $request->reason,
            ], $user);

            NotificationService::sendUser(
                $adjustment->created_by_user_id,
                "Penyesuaian Stok {$adjustment->adjustment_number} Ditolak",
                "Pengajuan penyesuaian stok untuk {$adjustment->item->name} ditolak oleh {$user->name}. Alasan: {$request->reason}",
                'INFORMATION',
                'WARNING',
                'STOCK_ADJUSTMENT',
                $adjustment->id,
                '/inventory/stock-balances'
            );

            return back()->with('success', "Pengajuan penyesuaian stok {$adjustment->adjustment_number} berhasil ditolak. Saldo stok fisik tetap utuh.");
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
        $horizon = (int) $request->get('horizon', 6);
        if (! in_array($horizon, [1, 3, 6, 12])) {
            $horizon = 6;
        }
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
            $f = $this->forecastingService->getItemForecast($item, $horizon);

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
            'horizon',
            'perPage',
            'totalSkuCount',
            'criticalCount',
            'reorderCount',
            'safeCount',
            'totalSuggestedReorderQty'
        ));
    }

    /**
     * Stock Reconciliation Audit Engine (POC-15)
     */
    public function reconciliation(Request $request)
    {
        $warehouseId = $request->get('warehouse_id');
        $search = $request->get('search');
        $statusFilter = $request->get('status'); // BALANCED, DISCREPANCY

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        // Default to first central or active warehouse if not specified
        $selectedWarehouse = null;
        if ($warehouseId) {
            $selectedWarehouse = Warehouse::find($warehouseId);
        } else {
            $selectedWarehouse = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first() ?? $warehouses->first();
            $warehouseId = $selectedWarehouse?->id;
        }

        $itemsQuery = Item::with('category')->where('is_active', true);
        if ($search) {
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }
        $items = $itemsQuery->orderBy('name')->get();

        $reconciliationData = [];
        $balancedCount = 0;
        $discrepancyCount = 0;

        foreach ($items as $item) {
            $balance = StockBalance::where('warehouse_id', $warehouseId)
                ->where('item_id', $item->id)
                ->first();

            $systemOnHand = $balance ? (int) $balance->on_hand : 0;

            // Compute ledger transactions
            $ledgers = StockLedger::where('warehouse_id', $warehouseId)
                ->where('item_id', $item->id)
                ->get();

            $totalIn = (int) $ledgers->sum('qty_in');
            $totalOut = (int) $ledgers->sum('qty_out');
            $calculatedClosing = $totalIn - $totalOut;

            // Breakdown
            $incomingProc = (int) $ledgers->where('transaction_type', 'PROCUREMENT_RECEIPT')->sum('qty_in');
            $incomingTransfer = (int) $ledgers->whereIn('transaction_type', ['TRANSFER_IN', 'GOODS_RECEIPT_UNIT'])->sum('qty_in');
            $incomingReturn = (int) $ledgers->where('transaction_type', 'RETURN_IN')->sum('qty_in');
            $incomingStockInitial = (int) $ledgers->where('transaction_type', 'STOCK_INITIAL')->sum('qty_in');

            $outgoingIssue = (int) $ledgers->where('transaction_type', 'GOODS_ISSUE')->sum('qty_out');
            $outgoingTransfer = (int) $ledgers->where('transaction_type', 'TRANSFER_OUT')->sum('qty_out');
            $outgoingReturn = (int) $ledgers->where('transaction_type', 'RETURN_OUT')->sum('qty_out');
            $outgoingProduction = (int) $ledgers->where('transaction_type', 'PRODUCTION_ISSUE')->sum('qty_out');
            $outgoingDestroyed = (int) $ledgers->where('transaction_type', 'DESTROYED')->sum('qty_out');

            $adjustments = (int) $ledgers->whereIn('transaction_type', ['STOCK_ADJUSTMENT', 'STOCK_OPNAME'])->sum(fn ($l) => $l->qty_in - $l->qty_out);

            $diff = $systemOnHand - $calculatedClosing;
            $isBalanced = ($diff === 0);

            if ($isBalanced) {
                $balancedCount++;
            } else {
                $discrepancyCount++;
            }

            if ($statusFilter === 'BALANCED' && ! $isBalanced) {
                continue;
            }
            if ($statusFilter === 'DISCREPANCY' && $isBalanced) {
                continue;
            }

            $reconciliationData[] = [
                'item' => $item,
                'system_on_hand' => $systemOnHand,
                'calculated_closing' => $calculatedClosing,
                'diff' => $diff,
                'is_balanced' => $isBalanced,
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'breakdown' => [
                    'initial' => $incomingStockInitial,
                    'procurement' => $incomingProc,
                    'transfer_in' => $incomingTransfer,
                    'return_in' => $incomingReturn,
                    'issue' => $outgoingIssue,
                    'transfer_out' => $outgoingTransfer,
                    'return_out' => $outgoingReturn,
                    'production' => $outgoingProduction,
                    'destroyed' => $outgoingDestroyed,
                    'adjustments' => $adjustments,
                ],
                'last_transaction' => $ledgers->sortByDesc('created_at')->first(),
            ];
        }

        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50], true) ? (int) $request->get('per_page') : 10;
        $currentPage = Paginator::resolveCurrentPage();
        $totalCount = count($reconciliationData);
        $currentItems = array_slice($reconciliationData, ($currentPage - 1) * $perPage, $perPage);
        $paginatedReconciliation = new LengthAwarePaginator(
            $currentItems,
            $totalCount,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $allItems = Item::where('is_active', true)->orderBy('name')->get();

        return view('inventory.reconciliation', [
            'warehouses' => $warehouses,
            'selectedWarehouse' => $selectedWarehouse,
            'reconciliationData' => $paginatedReconciliation,
            'totalAuditCount' => $totalCount,
            'balancedCount' => $balancedCount,
            'discrepancyCount' => $discrepancyCount,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'warehouseId' => $warehouseId,
            'perPage' => $perPage,
            'allItems' => $allItems,
        ]);
    }

    /**
     * Inquiry Historical Movement (POC-16)
     */
    public function historicalMovement(Request $request)
    {
        $search = $request->get('search');
        $warehouseId = $request->get('warehouse_id');
        $transactionType = $request->get('transaction_type');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $perPage = in_array((int) $request->get('per_page'), [10, 25, 50, 100], true) ? (int) $request->get('per_page') : 20;

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        $query = StockLedger::with(['warehouse', 'item.category', 'creator']);

        if ($warehouseId && $warehouseId !== 'ALL') {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($transactionType && $transactionType !== 'ALL') {
            $query->where('transaction_type', $transactionType);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Active matched item if search matched SKU or name directly
        $matchedItem = null;
        if ($search) {
            $matchedItem = Item::where('sku', $search)
                ->orWhere('barcode', $search)
                ->orWhere('name', 'like', "%{$search}%")
                ->first();

            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($iq) use ($search) {
                        $iq->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        // CSV export
        if ($request->get('export') === 'csv') {
            $filename = 'historical_movement_'.date('Ymd_His').'.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($query) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Waktu', 'Gudang', 'SKU', 'Nama Barang', 'Tipe Transaksi', 'No Referensi', 'Masuk', 'Keluar', 'Saldo Akhir', 'Catatan', 'Petugas']);

                $query->chunk(500, function ($rows) use ($file) {
                    foreach ($rows as $r) {
                        fputcsv($file, [
                            $r->id,
                            $r->created_at->format('Y-m-d H:i:s'),
                            $r->warehouse?->name,
                            $r->item?->sku,
                            $r->item?->name,
                            $r->transaction_type,
                            $r->reference_number,
                            $r->qty_in,
                            $r->qty_out,
                            $r->balance_after,
                            $r->notes,
                            $r->creator?->name,
                        ]);
                    }
                });
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        $movements = $query->latest('id')->paginate($perPage)->withQueryString();

        // Multi-location distribution matrix if single item is being tracked
        $locationBalances = [];
        if ($matchedItem) {
            $locationBalances = StockBalance::with('warehouse')
                ->where('item_id', $matchedItem->id)
                ->get();
        }

        $transactionTypes = [
            'PROCUREMENT_RECEIPT' => 'Penerimaan Pengadaan',
            'GOODS_ISSUE' => 'Pengeluaran Order Cabang',
            'GOODS_RECEIPT_UNIT' => 'Penerimaan Unit Kerja',
            'TRANSFER_OUT' => 'Transfer Out (Switching)',
            'TRANSFER_IN' => 'Transfer In (Switching)',
            'RETURN_OUT' => 'Pengeluaran Retur',
            'RETURN_IN' => 'Penerimaan Retur',
            'PRODUCTION_ISSUE' => 'Pengeluaran Bon Produksi',
            'DESTROYED' => 'Pemusnahan Resmi',
            'STOCK_ADJUSTMENT' => 'Penyesuaian Fisik',
            'STOCK_OPNAME' => 'Hasil Stock Opname',
            'STOCK_INITIAL' => 'Saldo Awal',
        ];

        return view('inventory.historical_movement', compact(
            'movements',
            'warehouses',
            'matchedItem',
            'locationBalances',
            'transactionTypes',
            'search',
            'warehouseId',
            'transactionType',
            'dateFrom',
            'dateTo',
            'perPage'
        ));
    }

    /**
     * Update Min & Max Stock Limits per Warehouse (POC-44)
     */
    public function updateStockLimits(Request $request, $balanceId)
    {
        $request->validate([
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
        ]);

        $balance = StockBalance::with(['warehouse', 'item'])->findOrFail($balanceId);

        $balance->update([
            'min_stock' => $request->min_stock !== null && $request->min_stock !== '' ? (int) $request->min_stock : null,
            'max_stock' => $request->max_stock !== null && $request->max_stock !== '' ? (int) $request->max_stock : null,
        ]);

        AuditTrailService::log('UPDATE_WAREHOUSE_STOCK_LIMITS', $balance, null, [
            'warehouse' => $balance->warehouse?->name,
            'item' => $balance->item?->name,
            'min_stock' => $balance->min_stock,
            'max_stock' => $balance->max_stock,
        ], Auth::user());

        return back()->with('success', "Batas pagu stok untuk {$balance->item->name} di {$balance->warehouse->name} berhasil diperbarui (Min: {$balance->min_stock}, Max: {$balance->max_stock}).");
    }
}
