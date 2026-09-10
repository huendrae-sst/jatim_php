<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Settlement;
use App\Models\StockBalance;
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
}
