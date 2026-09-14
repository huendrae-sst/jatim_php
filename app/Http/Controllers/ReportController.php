<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Settlement;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\GeneralLedgerService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected GeneralLedgerService $generalLedgerService
    ) {}

    public function index()
    {
        $warehouses = Warehouse::all();
        $categories = Category::all();
        $currentYear = (int) date('Y');

        return view('reports.index', compact('warehouses', 'categories', 'currentYear'));
    }

    public function stockValuation(Request $request)
    {
        $warehouseId = $request->warehouse_id;
        $categoryId = $request->category_id;

        $balances = StockBalance::with(['warehouse.organization', 'item.category'])
            ->when($warehouseId && strtolower((string) $warehouseId) !== 'all', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->when($categoryId && strtolower((string) $categoryId) !== 'all', fn ($q) => $q->whereHas('item', fn ($i) => $i->where('category_id', $categoryId)))
            ->get();

        $totalValuation = $balances->sum(fn ($b) => $b->on_hand * $b->item->estimated_unit_price);

        return view('reports.stock_valuation', compact('balances', 'totalValuation', 'warehouseId', 'categoryId'));
    }

    public function exportStockValuationCsv(Request $request): StreamedResponse
    {
        $balances = StockBalance::with(['warehouse.organization', 'item.category'])->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="jims_stock_valuation_'.date('Ymd_His').'.csv"',
        ];

        return response()->stream(function () use ($balances) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Gudang / Unit', 'SKU', 'Nama Barang', 'Kategori', 'Satuan', 'On Hand', 'Reserved', 'Damaged', 'Available', 'Harga Satuan (Rp)', 'Total Valuasi (Rp)']);

            foreach ($balances as $b) {
                fputcsv($handle, [
                    $b->warehouse->name,
                    $b->item->sku,
                    $b->item->name,
                    $b->item->category->name,
                    $b->item->uom,
                    $b->on_hand,
                    $b->reserved,
                    $b->damaged,
                    $b->available,
                    $b->item->estimated_unit_price,
                    $b->on_hand * $b->item->estimated_unit_price,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function procurementCoverage()
    {
        $pos = PurchaseOrder::with(['vendor', 'warehouse', 'items.item', 'items.purchaseRequestItem.purchaseRequest.organization'])->latest()->get();

        return view('reports.procurement_coverage', compact('pos'));
    }

    public function settlementsReport()
    {
        $settlements = Settlement::with(['order.requestingOrganization', 'debitOrganization', 'creditOrganization', 'creator', 'approver'])->latest()->get();
        $totalSettled = $settlements->where('status', 'POSTED')->sum('total_amount');

        return view('reports.settlements_report', compact('settlements', 'totalSettled'));
    }

    public function generalLedger(Request $request)
    {
        $filters = [
            'organization_id' => $request->get('organization_id', 'ALL'),
            'chart_of_account_id' => $request->get('chart_of_account_id', 'ALL'),
            'start_date' => $request->get('start_date', date('Y-01-01')),
            'end_date' => $request->get('end_date', date('Y-12-31')),
            'search' => $request->get('search'),
        ];

        $report = $this->generalLedgerService->getLedgerReport($filters, auth()->user());

        return view('reports.general_ledger', $report);
    }

    public function exportGeneralLedgerCsv(Request $request): StreamedResponse
    {
        $filters = [
            'organization_id' => $request->get('organization_id', 'ALL'),
            'chart_of_account_id' => $request->get('chart_of_account_id', 'ALL'),
            'start_date' => $request->get('start_date', date('Y-01-01')),
            'end_date' => $request->get('end_date', date('Y-12-31')),
            'search' => $request->get('search'),
        ];

        $report = $this->generalLedgerService->getLedgerReport($filters, auth()->user());

        $orgLabel = $report['selectedOrganization'] ? preg_replace('/[^A-Za-z0-9_]/', '_', $report['selectedOrganization']->name) : 'GLOBAL';
        $filename = 'buku_besar_'.strtolower($orgLabel).'_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['KODE AKUN', 'NAMA AKUN', 'TANGGAL', 'NO. JURNAL', 'NO. REFERENSI', 'CABANG / UNIT', 'COST CENTER', 'KETERANGAN', 'DEBIT (RP)', 'KREDIT (RP)', 'SALDO BERJALAN (RP)']);

            foreach ($report['ledgerData'] as $data) {
                $account = $data['account'];
                // Opening balance row
                fputcsv($handle, [
                    $account->account_code,
                    $account->account_name,
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    'SALDO AWAL PERIODE',
                    0,
                    0,
                    $data['opening_balance'],
                ]);

                foreach ($data['entries'] as $entry) {
                    fputcsv($handle, [
                        $account->account_code,
                        $account->account_name,
                        $entry['transaction_date'],
                        $entry['journal_number'],
                        $entry['reference_number'],
                        $entry['organization_name'],
                        $entry['cost_center_code'],
                        $entry['description'],
                        $entry['debit'],
                        $entry['credit'],
                        $entry['running_balance'],
                    ]);
                }

                // Subtotal row
                fputcsv($handle, [
                    $account->account_code,
                    $account->account_name,
                    '-',
                    '-',
                    '-',
                    '-',
                    '-',
                    'TOTAL MUTASI & SALDO AKHIR',
                    $data['debit_total'],
                    $data['credit_total'],
                    $data['ending_balance'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Laporan Matriks Sebaran Stok Wilayah & Cabang (POC-49)
     */
    public function stockDistribution(Request $request)
    {
        $warehouseId = $request->get('warehouse_id');
        $categoryId = $request->get('category_id');
        $search = $request->get('search');
        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));

        $warehouses = Warehouse::with('organization')->where('is_active', true)->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        $itemsQuery = Item::with(['category', 'stockBalances.warehouse.organization'])->where('is_active', true);

        if ($categoryId && $categoryId !== 'ALL') {
            $itemsQuery->where('category_id', $categoryId);
        }

        if ($search) {
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $items = $itemsQuery->orderBy('name')->get();

        $selectedWarehouse = $warehouseId && $warehouseId !== 'ALL' ? Warehouse::find($warehouseId) : null;

        $matrixData = [];
        $totalValuationAll = 0.0;
        $totalQtyAll = 0;

        foreach ($items as $item) {
            $balancesQuery = StockBalance::where('item_id', $item->id);
            if ($selectedWarehouse) {
                $balancesQuery->where('warehouse_id', $selectedWarehouse->id);
            }
            $balances = $balancesQuery->with('warehouse.organization')->get();

            $warehouseBreakdown = [];
            $itemTotalOnHand = 0;
            $itemTotalValuation = 0.0;

            foreach ($balances as $b) {
                $wh = $b->warehouse;
                if (! $wh) {
                    continue;
                }

                $ledgers = StockLedger::where('item_id', $item->id)
                    ->where('warehouse_id', $wh->id)
                    ->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate)
                    ->get();

                $totalIn = (int) $ledgers->sum('qty_in');
                $totalOut = (int) $ledgers->sum('qty_out');
                $destroyed = (int) $ledgers->where('transaction_type', 'DESTROYED')->sum('qty_out');
                $initialStock = (int) $ledgers->where('transaction_type', 'STOCK_INITIAL')->sum('qty_in');
                $onHand = (int) $b->on_hand;
                $valuation = $onHand * (float) $item->estimated_unit_price;

                $itemTotalOnHand += $onHand;
                $itemTotalValuation += $valuation;

                $warehouseBreakdown[] = [
                    'warehouse' => $wh,
                    'on_hand' => $onHand,
                    'available' => (int) $b->available,
                    'damaged' => (int) $b->damaged,
                    'initial' => $initialStock,
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'destroyed' => $destroyed,
                    'valuation' => $valuation,
                    'stock_status' => $b->stock_status,
                ];
            }

            $totalValuationAll += $itemTotalValuation;
            $totalQtyAll += $itemTotalOnHand;

            $matrixData[] = [
                'item' => $item,
                'total_on_hand' => $itemTotalOnHand,
                'total_valuation' => $itemTotalValuation,
                'warehouses' => $warehouseBreakdown,
            ];
        }

        return view('reports.stock_distribution', compact(
            'warehouses',
            'categories',
            'matrixData',
            'totalValuationAll',
            'totalQtyAll',
            'warehouseId',
            'categoryId',
            'search',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Export Laporan Sebaran Stok ke format CSV (POC-48 & POC-49)
     */
    public function exportStockDistributionCsv(Request $request): StreamedResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $categoryId = $request->get('category_id');
        $search = $request->get('search');
        $startDate = $request->get('start_date', date('Y-01-01'));
        $endDate = $request->get('end_date', date('Y-m-d'));

        $itemsQuery = Item::with(['category', 'stockBalances.warehouse.organization'])->where('is_active', true);
        if ($categoryId && $categoryId !== 'ALL') {
            $itemsQuery->where('category_id', $categoryId);
        }
        if ($search) {
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }
        $items = $itemsQuery->orderBy('name')->get();

        $selectedWarehouse = $warehouseId && $warehouseId !== 'ALL' ? Warehouse::find($warehouseId) : null;
        $filename = 'laporan_sebaran_stok_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($items, $selectedWarehouse, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Nama Barang', 'Kategori', 'Satuan', 'Gudang / Cabang', 'Tipe Gudang', 'Saldo Awal', 'Total Masuk', 'Total Keluar', 'Pemusnahan', 'Saldo Akhir On Hand', 'Tersedia', 'Rusak', 'Harga Satuan (Rp)', 'Total Valuasi (Rp)', 'Status']);

            foreach ($items as $item) {
                $balancesQuery = StockBalance::where('item_id', $item->id);
                if ($selectedWarehouse) {
                    $balancesQuery->where('warehouse_id', $selectedWarehouse->id);
                }
                $balances = $balancesQuery->with('warehouse.organization')->get();

                foreach ($balances as $b) {
                    $wh = $b->warehouse;
                    if (! $wh) {
                        continue;
                    }

                    $ledgers = StockLedger::where('item_id', $item->id)
                        ->where('warehouse_id', $wh->id)
                        ->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate)
                        ->get();

                    $totalIn = (int) $ledgers->sum('qty_in');
                    $totalOut = (int) $ledgers->sum('qty_out');
                    $destroyed = (int) $ledgers->where('transaction_type', 'DESTROYED')->sum('qty_out');
                    $initialStock = (int) $ledgers->where('transaction_type', 'STOCK_INITIAL')->sum('qty_in');
                    $onHand = (int) $b->on_hand;
                    $valuation = $onHand * (float) $item->estimated_unit_price;

                    fputcsv($handle, [
                        $item->sku,
                        $item->name,
                        $item->category?->name ?? '-',
                        $item->uom,
                        $wh->name,
                        $wh->type,
                        $initialStock,
                        $totalIn,
                        $totalOut,
                        $destroyed,
                        $onHand,
                        $b->available,
                        $b->damaged,
                        $item->estimated_unit_price,
                        $valuation,
                        $b->stock_status,
                    ]);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }
}
