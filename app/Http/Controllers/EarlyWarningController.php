<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Warehouse;
use App\Services\EarlyWarningService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EarlyWarningController extends Controller
{
    public function __construct(
        protected EarlyWarningService $earlyWarningService
    ) {}

    public function index(Request $request)
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
            $selectedWarehouseId = ($isBranch && $user->warehouse_id) ? (string) $user->warehouse_id : 'all';
        }

        $warehouseFilterId = ($selectedWarehouseId && $selectedWarehouseId !== 'all') ? (int) $selectedWarehouseId : null;

        // KPI Summary
        $summary = $this->earlyWarningService->getSummaryMetrics($warehouseFilterId);

        // Fetch evaluations
        $allEvaluations = $this->earlyWarningService->getAllEvaluations($warehouseFilterId);

        // Search & Filters
        $search = trim((string) $request->get('search'));
        $categoryId = $request->get('category_id');
        $alertType = $request->get('alert_type');
        $severity = $request->get('severity');

        $filtered = array_filter($allEvaluations, function ($item) use ($search, $categoryId, $alertType, $severity) {
            if ($search !== '') {
                $matchSku = stripos($item['sku'], $search) !== false;
                $matchName = stripos($item['name'], $search) !== false;
                if (! $matchSku && ! $matchName) {
                    return false;
                }
            }

            if ($categoryId && $categoryId !== 'ALL') {
                if ((string) $item['category_id'] !== (string) $categoryId) {
                    return false;
                }
            }

            if ($alertType && $alertType !== 'ALL') {
                if ($alertType === 'SAFE') {
                    if (! empty($item['alerts'])) {
                        return false;
                    }
                } else {
                    if (! in_array($alertType, $item['alerts'], true)) {
                        return false;
                    }
                }
            }

            if ($severity && $severity !== 'ALL') {
                if ($item['severity'] !== $severity) {
                    return false;
                }
            }

            return true;
        });

        // Re-index array
        $filtered = array_values($filtered);

        // Pagination
        $perPage = in_array((int) $request->get('per_page'), [5, 10, 15, 20, 25, 50], true) ? (int) $request->get('per_page') : 10;
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;
        $itemsForCurrentPage = array_slice($filtered, $offset, $perPage);

        $warnings = new LengthAwarePaginator(
            $itemsForCurrentPage,
            count($filtered),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $categories = Category::orderBy('name')->get();
        $currentWarehouse = $warehouseFilterId ? $warehouses->firstWhere('id', $warehouseFilterId) : null;

        return view('inventory.early_warning', compact(
            'warnings',
            'summary',
            'warehouses',
            'currentWarehouse',
            'selectedWarehouseId',
            'categories',
            'search',
            'categoryId',
            'alertType',
            'severity',
            'perPage'
        ));
    }

    public function scan(Request $request)
    {
        $issuesCount = $this->earlyWarningService->scanAndNotify();

        return back()->with('success', "Pemindaian EWS berhasil. Terdeteksi {$issuesCount} indikator peringatan dan notifikasi sistem telah diteruskan ke pihak terkait.");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $selectedWarehouseId = $request->get('warehouse_id');
        $warehouseFilterId = ($selectedWarehouseId && $selectedWarehouseId !== 'all') ? (int) $selectedWarehouseId : null;

        $evaluations = $this->earlyWarningService->getAllEvaluations($warehouseFilterId);

        $filename = 'EWS_Persediaan_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($evaluations) {
            $handle = fopen('php://output', 'w');

            // BOM untuk UTF-8 Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header kolom
            fputcsv($handle, [
                'SKU',
                'Nama Barang',
                'Kategori',
                'Satuan',
                'Stok On Hand',
                'Stok Bebas (Available)',
                'Stok Rusak (Damaged)',
                'Safety Stock',
                'Reorder Point (ROP)',
                'Max Stock',
                'Laju Konsumsi/Hari',
                'Daya Tahan (Hari)',
                'Status Peringatan Utama',
                'Keparahan (Severity)',
                'Rekomendasi Tindakan',
                'Saran Kuantitas Reorder',
                'Estimasi Nilai Total (Rp)',
            ]);

            foreach ($evaluations as $e) {
                fputcsv($handle, [
                    $e['sku'],
                    $e['name'],
                    $e['category_name'],
                    $e['uom'],
                    $e['on_hand'],
                    $e['available'],
                    $e['damaged'],
                    $e['safety_stock'],
                    $e['reorder_point'],
                    $e['max_stock'],
                    $e['daily_demand'],
                    $e['days_of_supply'] >= 999 ? 'Tidak Terbatas' : $e['days_of_supply'],
                    $e['primary_alert'],
                    $e['severity'],
                    $e['recommendation'],
                    $e['suggested_reorder_qty'],
                    number_format($e['total_valuation'], 2, ',', '.'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
