<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\StockOpname;
use App\Models\SwitchingStock;
use App\Models\Warehouse;
use Carbon\Carbon;

class EssReportService
{
    public function __construct(
        protected ForecastingService $forecastingService
    ) {}

    /**
     * 1. Laporan Valuasi Aset & Realisasi Anggaran Persediaan (Capital & Budget Absorption)
     */
    public function getValuationBudgetReport(array $filters): array
    {
        $year = (int) ($filters['period_year'] ?? date('Y'));
        $warehouseId = $filters['warehouse_id'] ?? 'all';
        $categoryId = $filters['category_id'] ?? 'all';

        // 1. Stock balances query
        $balancesQuery = StockBalance::with(['warehouse.organization', 'item.category']);
        if ($warehouseId && strtolower((string) $warehouseId) !== 'all') {
            $balancesQuery->where('warehouse_id', (int) $warehouseId);
        }
        if ($categoryId && strtolower((string) $categoryId) !== 'all') {
            $balancesQuery->whereHas('item', fn ($q) => $q->where('category_id', $categoryId));
        }

        $balances = $balancesQuery->get();
        $totalValuation = (float) $balances->sum(fn ($b) => $b->on_hand * (float) $b->item->estimated_unit_price);
        $totalOnHandUnits = (int) $balances->sum('on_hand');

        // 2. Budget query for the period year
        $budgets = Budget::with('organization')->where('year', $year)->get();
        $totalBudgetAllocated = (float) $budgets->sum('allocated_amount');
        $totalBudgetCommitted = (float) $budgets->sum('committed_amount');
        $totalBudgetRealized = (float) $budgets->sum('realized_amount');
        $totalBudgetAvailable = max(0, $totalBudgetAllocated - $totalBudgetCommitted - $totalBudgetRealized);
        $budgetUtilizationRate = $totalBudgetAllocated > 0
            ? round((($totalBudgetRealized + $totalBudgetCommitted) / $totalBudgetAllocated) * 100, 2)
            : 0;

        // Group valuations by organization
        $orgValuations = $balances->groupBy(fn ($b) => $b->warehouse->organization_id ?? 0)
            ->map(fn ($rows) => [
                'valuation' => (float) $rows->sum(fn ($b) => $b->on_hand * (float) $b->item->estimated_unit_price),
                'on_hand' => (int) $rows->sum('on_hand'),
                'sku_count' => $rows->pluck('item_id')->unique()->count(),
            ]);

        // Combined breakdown rows by organization
        $organizations = Organization::where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $tableData = $organizations->map(function ($org) use ($budgets, $orgValuations) {
            $budget = $budgets->firstWhere('organization_id', $org->id);
            $stockData = $orgValuations->get($org->id, ['valuation' => 0.0, 'on_hand' => 0, 'sku_count' => 0]);

            $allocated = (float) ($budget?->allocated_amount ?? 0);
            $realized = (float) ($budget?->realized_amount ?? 0);
            $committed = (float) ($budget?->committed_amount ?? 0);
            $used = $realized + $committed;
            $remaining = max(0, $allocated - $used);
            $utilization = $allocated > 0 ? round(($used / $allocated) * 100, 1) : 0;

            return [
                'organization' => $org,
                'allocated_amount' => $allocated,
                'realized_amount' => $realized,
                'remaining_budget' => $remaining,
                'utilization_rate' => $utilization,
                'stock_valuation' => $stockData['valuation'],
                'on_hand_units' => $stockData['on_hand'],
                'sku_count' => $stockData['sku_count'],
            ];
        });

        return [
            'year' => $year,
            'warehouseId' => $warehouseId,
            'categoryId' => $categoryId,
            'totalValuation' => $totalValuation,
            'totalOnHandUnits' => $totalOnHandUnits,
            'totalBudgetAllocated' => $totalBudgetAllocated,
            'totalBudgetRealized' => $totalBudgetRealized,
            'totalBudgetAvailable' => $totalBudgetAvailable,
            'budgetUtilizationRate' => $budgetUtilizationRate,
            'tableData' => $tableData,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }

    /**
     * 2. Laporan Efisiensi Biaya & Penghematan Switching (Cost Saving & Capital Optimization)
     */
    public function getCostSavingReport(array $filters): array
    {
        $year = (int) ($filters['period_year'] ?? date('Y'));
        $sourceWhId = $filters['source_warehouse_id'] ?? 'all';
        $destinationWhId = $filters['destination_warehouse_id'] ?? 'all';

        $query = SwitchingStock::with([
            'sourceWarehouse.organization',
            'destinationWarehouse.organization',
            'items.item',
            'item',
        ])
            ->whereYear('created_at', $year);

        if ($sourceWhId && strtolower((string) $sourceWhId) !== 'all') {
            $query->where('source_warehouse_id', (int) $sourceWhId);
        }
        if ($destinationWhId && strtolower((string) $destinationWhId) !== 'all') {
            $query->where('destination_warehouse_id', (int) $destinationWhId);
        }

        $allSwitchings = $query->latest()->get();

        // Calculate Cost Savings
        $totalCostSaved = 0.0;
        $totalUnitsSwitched = 0;
        $totalCompleted = 0;
        $leadTimes = [];

        $tableData = $allSwitchings->map(function ($sw) use (&$totalCostSaved, &$totalUnitsSwitched, &$totalCompleted, &$leadTimes) {
            $itemSavings = 0.0;
            $itemsCount = 0;
            $unitsCount = 0;

            if ($sw->items->isNotEmpty()) {
                foreach ($sw->items as $swItem) {
                    $unitPrice = (float) ($swItem->item?->estimated_unit_price ?? 0);
                    $itemSavings += $swItem->qty_requested * $unitPrice;
                    $unitsCount += $swItem->qty_requested;
                    $itemsCount++;
                }
            } elseif ($sw->item) {
                $unitPrice = (float) $sw->item->estimated_unit_price;
                $itemSavings += $sw->qty_requested * $unitPrice;
                $unitsCount += $sw->qty_requested;
                $itemsCount = 1;
            }

            // Lead time calculation
            $durationDays = null;
            if ($sw->received_at && $sw->transferred_at) {
                $durationDays = max(1, (int) Carbon::parse($sw->transferred_at)->diffInDays(Carbon::parse($sw->received_at)));
                $leadTimes[] = $durationDays;
            } elseif ($sw->received_at && $sw->created_at) {
                $durationDays = max(1, (int) Carbon::parse($sw->created_at)->diffInDays(Carbon::parse($sw->received_at)));
                $leadTimes[] = $durationDays;
            }

            if (in_array($sw->status, ['RECEIVED', 'APPROVED', 'DISPATCHED'])) {
                $totalCostSaved += $itemSavings;
                $totalUnitsSwitched += $unitsCount;
                if ($sw->status === 'RECEIVED') {
                    $totalCompleted++;
                }
            }

            return [
                'switching' => $sw,
                'items_count' => $itemsCount,
                'units_count' => $unitsCount,
                'savings_amount' => $itemSavings,
                'duration_days' => $durationDays,
                'vendor_lead_time_days' => 14, // Vendor standard SLA benchmark
            ];
        });

        $avgLeadTimeDays = count($leadTimes) > 0 ? round(array_sum($leadTimes) / count($leadTimes), 1) : 2.0;

        return [
            'year' => $year,
            'sourceWhId' => $sourceWhId,
            'destinationWhId' => $destinationWhId,
            'totalCostSaved' => $totalCostSaved,
            'totalUnitsSwitched' => $totalUnitsSwitched,
            'totalCompleted' => $totalCompleted,
            'totalTransactions' => $allSwitchings->count(),
            'avgLeadTimeDays' => $avgLeadTimeDays,
            'vendorBenchmarkDays' => 14,
            'tableData' => $tableData,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * 3. Laporan Perputaran Persediaan & Produktivitas Aset (Inventory Turnover / ITO)
     */
    public function getInventoryTurnoverReport(array $filters): array
    {
        $categoryId = $filters['category_id'] ?? 'all';
        $warehouseId = $filters['warehouse_id'] ?? 'all';

        $itemsQuery = Item::with('category')->where('is_active', true);
        if ($categoryId && strtolower((string) $categoryId) !== 'all') {
            $itemsQuery->where('category_id', $categoryId);
        }
        $items = $itemsQuery->get();

        // 12-month outbound transactions
        $outbounds = StockLedger::whereIn('transaction_type', ['GOODS_ISSUE', 'TRANSFER_OUT', 'SWITCHING_STOCK'])
            ->where('created_at', '>=', now()->subDays(365))
            ->when($warehouseId && strtolower((string) $warehouseId) !== 'all', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->selectRaw('item_id, SUM(qty_out) as total_qty_out')
            ->groupBy('item_id')
            ->pluck('total_qty_out', 'item_id');

        // Current stock balances
        $balancesQuery = StockBalance::query();
        if ($warehouseId && strtolower((string) $warehouseId) !== 'all') {
            $balancesQuery->where('warehouse_id', (int) $warehouseId);
        }
        $balances = $balancesQuery->selectRaw('item_id, SUM(on_hand) as total_on_hand')
            ->groupBy('item_id')
            ->pluck('total_on_hand', 'item_id');

        $totalCogsIssued = 0.0;
        $totalAvgValuation = 0.0;
        $totalIdleValuation = 0.0;

        $tableData = $items->map(function ($item) use ($outbounds, $balances, &$totalCogsIssued, &$totalAvgValuation, &$totalIdleValuation) {
            $onHand = (int) ($balances[$item->id] ?? 0);
            $unitPrice = (float) $item->estimated_unit_price;
            $stockValuation = $onHand * $unitPrice;
            $qtyOut = (int) ($outbounds[$item->id] ?? 0);
            $annualCogs = $qtyOut * $unitPrice;

            // ITO = Annual Outbound / Average Stock Value
            $ito = $stockValuation > 0 ? round($annualCogs / $stockValuation, 2) : ($qtyOut > 0 ? 12.0 : 0.0);
            $doi = $ito > 0 ? (int) round(365 / $ito) : 999;

            $velocity = match (true) {
                $ito >= 4.0 => 'FAST_MOVING',
                $ito >= 1.0 => 'MEDIUM_MOVING',
                default => 'SLOW_MOVING',
            };

            if ($velocity === 'SLOW_MOVING' && $stockValuation > 0) {
                $totalIdleValuation += $stockValuation;
            }

            $totalCogsIssued += $annualCogs;
            $totalAvgValuation += $stockValuation;

            return [
                'item' => $item,
                'on_hand' => $onHand,
                'stock_valuation' => $stockValuation,
                'annual_qty_out' => $qtyOut,
                'annual_cogs' => $annualCogs,
                'ito' => $ito,
                'doi' => $doi,
                'velocity' => $velocity,
            ];
        })->sortByDesc('annual_cogs')->values();

        $overallIto = $totalAvgValuation > 0 ? round($totalCogsIssued / $totalAvgValuation, 2) : 0.0;
        $overallDoi = $overallIto > 0 ? (int) round(365 / $overallIto) : 999;

        return [
            'categoryId' => $categoryId,
            'warehouseId' => $warehouseId,
            'overallIto' => $overallIto,
            'overallDoi' => $overallDoi,
            'totalCogsIssued' => $totalCogsIssued,
            'totalAvgValuation' => $totalAvgValuation,
            'totalIdleValuation' => $totalIdleValuation,
            'tableData' => $tableData,
            'categories' => Category::orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * 4. Peta Risiko Ketahanan Logistik Jaringan (Executive Risk Heatmap & Network Resilience)
     */
    public function getRiskHeatmapReport(array $filters): array
    {
        $city = $filters['city'] ?? 'all';
        $type = $filters['type'] ?? 'all';
        $riskFilter = $filters['risk_status'] ?? 'all';

        $whQuery = Warehouse::with('organization')->where('is_active', true);
        if ($city && strtolower((string) $city) !== 'all') {
            $whQuery->whereHas('organization', fn ($q) => $q->where('city', $city));
        }
        if ($type && strtolower((string) $type) !== 'all') {
            $whQuery->where('type', $type);
        }
        $warehouses = $whQuery->orderBy('type')->orderBy('name')->get();

        // Stock balances by warehouse
        $allBalances = StockBalance::with('item')->get()->groupBy('warehouse_id');

        $safeCount = 0;
        $warningCount = 0;
        $criticalCount = 0;

        $tableData = $warehouses->map(function ($wh) use ($allBalances, &$safeCount, &$warningCount, &$criticalCount) {
            $balances = $allBalances->get($wh->id, collect());
            $whValuation = (float) $balances->sum(fn ($b) => $b->on_hand * (float) $b->item->estimated_unit_price);

            $criticalSkus = 0;
            $reorderSkus = 0;
            $safeSkus = 0;

            foreach ($balances as $b) {
                $avail = $b->available;
                $rop = $b->item->reorder_point ?: 30;

                if ($avail <= 0) {
                    $criticalSkus++;
                } elseif ($avail <= $rop) {
                    $reorderSkus++;
                } else {
                    $safeSkus++;
                }
            }

            $managedSkus = $balances->count();
            $resilienceScore = $managedSkus > 0
                ? round((($safeSkus + ($reorderSkus * 0.5)) / $managedSkus) * 100, 1)
                : 100.0;

            $riskStatus = match (true) {
                $resilienceScore < 60 || $criticalSkus >= 3 => 'KRITIS',
                $resilienceScore < 85 || $reorderSkus >= 5 => 'WASPADA',
                default => 'AMAN',
            };

            if ($riskStatus === 'AMAN') {
                $safeCount++;
            } elseif ($riskStatus === 'WASPADA') {
                $warningCount++;
            } else {
                $criticalCount++;
            }

            return [
                'warehouse' => $wh,
                'managed_skus' => $managedSkus,
                'critical_skus' => $criticalSkus,
                'reorder_skus' => $reorderSkus,
                'safe_skus' => $safeSkus,
                'resilience_score' => $resilienceScore,
                'risk_status' => $riskStatus,
                'total_valuation' => $whValuation,
            ];
        });

        if ($riskFilter && strtolower((string) $riskFilter) !== 'all') {
            $tableData = $tableData->filter(fn ($row) => $row['risk_status'] === strtoupper($riskFilter))->values();
        }

        $cities = Organization::whereNotNull('city')->distinct()->pluck('city')->sort()->values();

        return [
            'city' => $city,
            'type' => $type,
            'riskFilter' => $riskFilter,
            'totalWarehouses' => $warehouses->count(),
            'safeCount' => $safeCount,
            'warningCount' => $warningCount,
            'criticalCount' => $criticalCount,
            'tableData' => $tableData,
            'cities' => $cities,
        ];
    }

    /**
     * 5. Laporan Kinerja Layanan & SLA Distribusi (Order Fulfillment & Service Level Rate)
     */
    public function getServiceLevelReport(array $filters): array
    {
        $year = (int) ($filters['period_year'] ?? date('Y'));
        $orgId = $filters['organization_id'] ?? 'all';

        $ordersQuery = Order::with(['requestingOrganization', 'items'])
            ->whereYear('created_at', $year);

        if ($orgId && strtolower((string) $orgId) !== 'all') {
            $ordersQuery->where('requesting_organization_id', (int) $orgId);
        }

        $orders = $ordersQuery->latest()->get();

        $totalOrders = $orders->count();
        $completedOrders = 0;
        $onTimeOrders = 0;
        $fulfillmentDurations = [];

        $tableData = $orders->map(function ($order) use (&$completedOrders, &$onTimeOrders, &$fulfillmentDurations) {
            $isCompleted = in_array($order->status, ['COMPLETED', 'DELIVERED', 'RECEIVED']);
            if ($isCompleted) {
                $completedOrders++;
            }

            $durationDays = null;
            if ($order->completed_at && $order->submitted_at) {
                $durationDays = max(1, (int) Carbon::parse($order->submitted_at)->diffInDays(Carbon::parse($order->completed_at)));
                $fulfillmentDurations[] = $durationDays;
            }

            // On-time SLA check against required_date
            $isOnTime = false;
            if ($order->required_date && $order->completed_at) {
                $isOnTime = Carbon::parse($order->completed_at)->lte(Carbon::parse($order->required_date)->endOfDay());
            } elseif ($order->required_date && ! $isCompleted) {
                $isOnTime = now()->lte(Carbon::parse($order->required_date)->endOfDay());
            } else {
                $isOnTime = true;
            }

            if ($isOnTime && $isCompleted) {
                $onTimeOrders++;
            }

            $totalRequested = (int) $order->items->sum('qty_requested');
            $totalFulfilled = (int) $order->items->sum(fn ($i) => $i->qty_received ?: ($i->qty_shipped ?: $i->qty_allocated));
            $itemFillRate = $totalRequested > 0 ? round(($totalFulfilled / $totalRequested) * 100, 1) : 100;

            return [
                'order' => $order,
                'total_requested' => $totalRequested,
                'total_fulfilled' => $totalFulfilled,
                'fill_rate' => $itemFillRate,
                'duration_days' => $durationDays,
                'is_on_time' => $isOnTime,
                'status' => $order->status,
            ];
        });

        $overallFillRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 100;
        $onTimeDeliveryRate = $completedOrders > 0 ? round(($onTimeOrders / $completedOrders) * 100, 1) : 100;
        $avgFulfillmentDays = count($fulfillmentDurations) > 0 ? round(array_sum($fulfillmentDurations) / count($fulfillmentDurations), 1) : 2.5;

        return [
            'year' => $year,
            'orgId' => $orgId,
            'totalOrders' => $totalOrders,
            'completedOrders' => $completedOrders,
            'overallFillRate' => $overallFillRate,
            'onTimeDeliveryRate' => $onTimeDeliveryRate,
            'avgFulfillmentDays' => $avgFulfillmentDays,
            'tableData' => $tableData,
            'organizations' => Organization::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * 6. Laporan Akuntabilitas, Kerugian Aset & Kepatuhan Audit (Audit Compliance & Shrinkage)
     */
    public function getAuditComplianceReport(array $filters): array
    {
        $year = (int) ($filters['period_year'] ?? date('Y'));
        $whId = $filters['warehouse_id'] ?? 'all';

        $opnamesQuery = StockOpname::with(['warehouse.organization', 'user'])
            ->where('period_year', $year);

        if ($whId && strtolower((string) $whId) !== 'all') {
            $opnamesQuery->where('warehouse_id', (int) $whId);
        }

        $opnames = $opnamesQuery->latest('opname_date')->get();

        $totalSessions = $opnames->count();
        $postedSessions = $opnames->where('status', 'POSTED')->count();
        $complianceRate = $totalSessions > 0 ? round(($postedSessions / $totalSessions) * 100, 1) : 100;

        $totalItemsCounted = (int) $opnames->sum('total_items');
        $totalDiscrepancyItems = (int) $opnames->sum('discrepancy_items_count');
        $totalNetVarianceValue = (float) $opnames->sum('net_variance_value');

        // Damaged stock valuation from current balances
        $balancesDamaged = StockBalance::with('item')
            ->where('damaged', '>', 0)
            ->when($whId && strtolower((string) $whId) !== 'all', fn ($q) => $q->where('warehouse_id', (int) $whId))
            ->get();
        $totalDamagedValuation = (float) $balancesDamaged->sum(fn ($b) => $b->damaged * (float) $b->item->estimated_unit_price);

        $accuracyRate = $totalItemsCounted > 0
            ? round((($totalItemsCounted - $totalDiscrepancyItems) / $totalItemsCounted) * 100, 1)
            : 100;

        return [
            'year' => $year,
            'warehouseId' => $whId,
            'complianceRate' => $complianceRate,
            'accuracyRate' => $accuracyRate,
            'totalNetVarianceValue' => $totalNetVarianceValue,
            'totalDamagedValuation' => $totalDamagedValuation,
            'totalSessions' => $totalSessions,
            'postedSessions' => $postedSessions,
            'tableData' => $opnames,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * 7. Laporan Prediktif & Proyeksi Anggaran Pengadaan (Predictive CapEx/OpEx Demand)
     */
    public function getPredictiveBudgetReport(array $filters): array
    {
        $horizonMonths = (int) ($filters['horizon_months'] ?? 6);
        if (! in_array($horizonMonths, [3, 6, 12], true)) {
            $horizonMonths = 6;
        }
        $categoryId = $filters['category_id'] ?? 'all';

        $itemsQuery = Item::with('category')->where('is_active', true);
        if ($categoryId && strtolower((string) $categoryId) !== 'all') {
            $itemsQuery->where('category_id', $categoryId);
        }
        $items = $itemsQuery->get();

        $totalProjectedBudget = 0.0;
        $itemsRequiringProcurement = 0;
        $totalDailyDemandAll = 0.0;
        $categoryTotals = [];

        $tableData = $items->map(function ($item) use ($horizonMonths, &$totalProjectedBudget, &$itemsRequiringProcurement, &$totalDailyDemandAll, &$categoryTotals) {
            $forecast = $this->forecastingService->getItemForecast($item);
            $dailyDemand = (float) ($forecast['daily_demand'] ?? 1.5);
            $totalDailyDemandAll += $dailyDemand;

            $horizonDays = $horizonMonths * 30;
            $projectedDemandUnits = (int) round($dailyDemand * $horizonDays);

            $availableStock = $item->total_available;
            $safetyStock = (int) ($item->safety_stock ?: ($forecast['recommended_safety_stock'] ?? 15));

            // Net Shortage = Projected Demand + Safety Stock - Current Available
            $recommendedPurchaseQty = max(0, ($projectedDemandUnits + $safetyStock) - $availableStock);
            $unitPrice = (float) $item->estimated_unit_price;
            $estimatedBudget = $recommendedPurchaseQty * $unitPrice;

            if ($recommendedPurchaseQty > 0) {
                $itemsRequiringProcurement++;
                $totalProjectedBudget += $estimatedBudget;

                $catName = $item->category?->name ?? 'Lainnya';
                $categoryTotals[$catName] = ($categoryTotals[$catName] ?? 0.0) + $estimatedBudget;
            }

            return [
                'item' => $item,
                'available_stock' => $availableStock,
                'daily_demand' => $dailyDemand,
                'projected_demand' => $projectedDemandUnits,
                'safety_stock' => $safetyStock,
                'recommended_qty' => $recommendedPurchaseQty,
                'estimated_budget' => $estimatedBudget,
                'status' => $recommendedPurchaseQty > 0 ? 'PERLU_PENGADAAN' : 'STOK_MEMADAI',
            ];
        })->sortByDesc('estimated_budget')->values();

        arsort($categoryTotals);
        $topCategoryName = count($categoryTotals) > 0 ? array_key_first($categoryTotals) : '-';
        $topCategoryAmount = count($categoryTotals) > 0 ? reset($categoryTotals) : 0.0;

        return [
            'horizonMonths' => $horizonMonths,
            'categoryId' => $categoryId,
            'totalProjectedBudget' => $totalProjectedBudget,
            'itemsRequiringProcurement' => $itemsRequiringProcurement,
            'totalItemsEvaluated' => $items->count(),
            'topCategoryName' => $topCategoryName,
            'topCategoryAmount' => $topCategoryAmount,
            'tableData' => $tableData,
            'categories' => Category::orderBy('name')->get(),
        ];
    }
}
