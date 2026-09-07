<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Settlement;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
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
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($categoryId, fn ($q) => $q->whereHas('item', fn ($i) => $i->where('category_id', $categoryId)))
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
}
