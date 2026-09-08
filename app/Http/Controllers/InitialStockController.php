<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\AuditTrailService;
use App\Services\StockLedgerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InitialStockController extends Controller
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

        $cutoffDate = $request->get('cutoff_date', Carbon::now()->toDateString());

        $items = Item::with(['category', 'stockBalances' => function ($q) use ($selectedWarehouseId) {
            $q->where('warehouse_id', $selectedWarehouseId);
        }])->where('is_active', true)->orderBy('sku')->get();

        $categories = Category::orderBy('name')->get();

        return view('inventory.initial_stock', compact(
            'warehouses',
            'selectedWarehouseId',
            'currentWarehouse',
            'items',
            'categories',
            'cutoffDate'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'cutoff_date' => 'required|date',
            'notes' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty_good' => 'required|integer|min:0',
            'items.*.qty_damaged' => 'nullable|integer|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        $user = Auth::user();
        $cutoffDate = Carbon::parse($request->cutoff_date);

        // Generate Reference Number: INIT/YYYY/MM/XXXX
        do {
            $refNo = sprintf('INIT/%s/%s/%04d', $cutoffDate->format('Y'), $cutoffDate->format('m'), rand(1000, 9999));
        } while (StockLedger::where('reference_number', $refNo)->exists());

        try {
            DB::beginTransaction();

            $postedCount = 0;
            $totalValuation = 0;

            foreach ($request->items as $row) {
                $item = Item::findOrFail($row['item_id']);
                $qtyGood = (int) ($row['qty_good'] ?? 0);
                $qtyDamaged = (int) ($row['qty_damaged'] ?? 0);
                $unitCost = (float) ($row['unit_cost'] ?? $item->estimated_unit_price);

                if ($qtyGood > 0 || $qtyDamaged > 0) {
                    $ledger = $this->stockLedgerService->postInitialStock(
                        $warehouse,
                        $item,
                        $qtyGood,
                        $qtyDamaged,
                        $unitCost,
                        $refNo,
                        $request->notes,
                        $user
                    );

                    $postedCount++;
                    $totalValuation += $ledger->total_value;
                }
            }

            AuditTrailService::log(
                'CREATE_INITIAL_STOCK',
                $warehouse,
                null,
                ['ref_no' => $refNo, 'posted_sku' => $postedCount, 'total_valuation' => $totalValuation],
                $user
            );

            DB::commit();

            return redirect()->route('inventory.balances', ['warehouse_id' => $warehouse->id])
                ->with('success', "Saldo awal gudang {$warehouse->name} berhasil diposting ({$postedCount} SKU, Ref: {$refNo}).");
        } catch (Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Gagal memposting saldo awal: '.$e->getMessage());
        }
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
        $whName = $warehouse ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $warehouse->code) : 'Semua';
        $filename = 'Template_Saldo_Awal_'.$whName.'_'.date('Ymd').'.csv';

        $items = Item::with('category')->where('is_active', true)->orderBy('sku')->get();

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header columns
            fputcsv($handle, [
                'SKU',
                'Nama Barang',
                'Kategori',
                'Satuan',
                'Qty Saldo Baik',
                'Qty Saldo Rusak',
                'Harga Satuan (Rp)',
                'Catatan',
            ]);

            foreach ($items as $it) {
                fputcsv($handle, [
                    $it->sku,
                    $it->name,
                    $it->category?->name ?? '-',
                    $it->uom,
                    0,
                    0,
                    (int) $it->estimated_unit_price,
                    'Saldo awal cut-off migrasi',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'cutoff_date' => 'required|date',
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'direct_post' => 'nullable|boolean',
            'import_notes' => 'nullable|string',
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        $user = Auth::user();
        $cutoffDate = Carbon::parse($request->cutoff_date);
        $directPost = $request->boolean('direct_post');
        $notes = $request->input('import_notes') ?: 'Impor Saldo Awal Excel/CSV';

        $file = $request->file('file');
        $path = $file->getRealPath();

        $rows = [];
        $errors = [];
        $line = 0;

        if (($handle = fopen($path, 'r')) !== false) {
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $firstLine = fgets($handle);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);
            if ($bom === "\xEF\xBB\xBF") {
                fread($handle, 3);
            }

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $line++;
                if ($line === 1) {
                    continue;
                }

                $sku = trim($data[0] ?? '');
                if (empty($sku)) {
                    continue;
                }

                $item = Item::where('sku', $sku)->first();
                if (! $item) {
                    $errors[] = "Baris {$line}: SKU '{$sku}' tidak terdaftar di sistem.";

                    continue;
                }

                $qtyGood = max(0, (int) preg_replace('/[^0-9]/', '', $data[4] ?? 0));
                $qtyDamaged = max(0, (int) preg_replace('/[^0-9]/', '', $data[5] ?? 0));
                $unitCost = (float) preg_replace('/[^0-9.]/', '', $data[6] ?? 0);
                if ($unitCost <= 0) {
                    $unitCost = (float) $item->estimated_unit_price;
                }

                $rows[] = [
                    'item' => $item,
                    'qty_good' => $qtyGood,
                    'qty_damaged' => $qtyDamaged,
                    'unit_cost' => $unitCost,
                ];
            }
            fclose($handle);
        }

        if (count($errors) > 0) {
            return back()->withErrors($errors)->withInput();
        }

        if (count($rows) === 0) {
            return back()->with('error', 'Tidak ada data valid yang dapat diimpor dari file tersebut.')->withInput();
        }

        if ($directPost) {
            do {
                $refNo = sprintf('INIT/%s/%s/%04d', $cutoffDate->format('Y'), $cutoffDate->format('m'), rand(1000, 9999));
            } while (StockLedger::where('reference_number', $refNo)->exists());

            try {
                DB::beginTransaction();
                $postedCount = 0;
                $totalValuation = 0;

                foreach ($rows as $r) {
                    if ($r['qty_good'] > 0 || $r['qty_damaged'] > 0) {
                        $ledger = $this->stockLedgerService->postInitialStock(
                            $warehouse,
                            $r['item'],
                            $r['qty_good'],
                            $r['qty_damaged'],
                            $r['unit_cost'],
                            $refNo,
                            $notes,
                            $user
                        );
                        $postedCount++;
                        $totalValuation += $ledger->total_value;
                    }
                }

                AuditTrailService::log(
                    'IMPORT_INITIAL_STOCK',
                    $warehouse,
                    null,
                    ['ref_no' => $refNo, 'posted_sku' => $postedCount, 'total_valuation' => $totalValuation],
                    $user
                );

                DB::commit();

                return redirect()->route('inventory.balances', ['warehouse_id' => $warehouse->id])
                    ->with('success', "Impor berhasil! {$postedCount} SKU saldo awal telah diposting ke gudang {$warehouse->name} (Ref: {$refNo}).");
            } catch (Exception $e) {
                DB::rollBack();

                return back()->with('error', 'Gagal memproses impor saldo awal: '.$e->getMessage());
            }
        }

        $importedData = array_map(function ($r) {
            return [
                'item_id' => $r['item']->id,
                'qty_good' => $r['qty_good'],
                'qty_damaged' => $r['qty_damaged'],
                'unit_cost' => $r['unit_cost'],
            ];
        }, $rows);

        return redirect()->route('inventory.initial_stock.index', ['warehouse_id' => $warehouse->id, 'cutoff_date' => $cutoffDate->toDateString()])
            ->with('imported_rows', $importedData)
            ->with('success', count($rows).' data barang dari file CSV berhasil dimuat ke lembar kerja. Silakan periksa dan klik "Simpan & Posting Saldo Awal".');
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

        $selectedWarehouseId = $request->get('warehouse_id', 'all');
        $search = $request->get('search');
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 25, 50]) ? (int) $request->get('per_page') : 10;

        $query = StockLedger::with(['warehouse.organization', 'item.category', 'creator'])
            ->where('transaction_type', 'STOCK_INITIAL');

        if ($selectedWarehouseId && $selectedWarehouseId !== 'all') {
            $query->where('warehouse_id', $selectedWarehouseId);
        } elseif ($isBranch && $user->warehouse_id) {
            $query->where('warehouse_id', $user->warehouse_id);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        $historyLedgers = $query->latest()->paginate($perPage)->withQueryString();

        return view('inventory.initial_stock_history', compact(
            'warehouses',
            'selectedWarehouseId',
            'historyLedgers',
            'search',
            'perPage'
        ));
    }
}
