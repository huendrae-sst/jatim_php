<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Discrepancy;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Settlement;
use App\Models\Shipment;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\SwitchingStock;
use App\Models\Warehouse;
use App\Services\EarlyWarningService;
use App\Services\ForecastingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __construct(
        protected ForecastingService $forecastingService,
        protected EarlyWarningService $earlyWarningService
    ) {}

    public function index()
    {
        $user = Auth::user();

        // 1. Key Metrics & Valuation Breakdown
        $totalItems = Item::where('is_active', true)->count();
        $totalBranches = Organization::whereIn('type', ['MAIN_BRANCH', 'SUB_BRANCH'])->count();
        $totalWarehouses = Warehouse::where('is_active', true)->count();

        $allBalances = StockBalance::with('item')->get();
        $totalStockValue = $allBalances->sum(fn ($sb) => $sb->on_hand * ($sb->item->estimated_unit_price ?? 0));
        $availableStockValue = $allBalances->sum(fn ($sb) => $sb->available * ($sb->item->estimated_unit_price ?? 0));
        $reservedStockValue = $allBalances->sum(fn ($sb) => $sb->reserved * ($sb->item->estimated_unit_price ?? 0));
        $damagedStockValue = $allBalances->sum(fn ($sb) => $sb->damaged * ($sb->item->estimated_unit_price ?? 0));

        // 2. Budget Utilization Metrics
        $currentYear = (int) date('Y');
        $totalBudgetPagu = Budget::where('year', $currentYear)->sum('allocated_amount');
        $totalBudgetCommitted = Budget::where('year', $currentYear)->sum('committed_amount');
        $totalBudgetRealized = Budget::where('year', $currentYear)->sum('realized_amount');
        $totalBudgetAvailable = max(0, $totalBudgetPagu - $totalBudgetCommitted - $totalBudgetRealized);
        $budgetUtilization = $totalBudgetPagu > 0 ? round((($totalBudgetCommitted + $totalBudgetRealized) / $totalBudgetPagu) * 100, 1) : 0;

        // 3. Operational Flow Counters
        $pendingPrCount = PurchaseRequest::whereIn('status', ['SUBMITTED', 'WAITING_APPROVAL'])->count();
        $approvedPrPoolCount = PurchaseRequest::where('status', 'APPROVED')->count();
        $activePoCount = PurchaseOrder::whereIn('status', ['ISSUED', 'VENDOR_PROCESS', 'IN_DELIVERY', 'PARTIAL_RECEIVED'])->count();

        $pendingOrderApprovals = Order::where('status', 'WAITING_APPROVAL')->count();
        $allocatedOrders = Order::where('status', 'ALLOCATED')->count();
        $pickingOrders = Order::where('status', 'PICKING')->count();
        $readyToShipOrders = Order::where('status', 'READY_TO_SHIP')->count();
        $inTransitShipments = Shipment::where('status', 'IN_TRANSIT')->count();
        $deliveredCount = Shipment::where('status', 'DELIVERED')->count();
        $discrepancyReports = Discrepancy::where('resolution_status', 'REPORTED')->count();
        $pendingSettlements = Settlement::where('status', 'WAITING_APPROVAL')->count();
        $pendingSwitching = SwitchingStock::where('status', 'PROPOSED')->count();

        // 4. Forecast Risk Items & EWS Summary
        $forecasts = $this->forecastingService->getAllForecasts();
        $criticalRiskItems = array_filter($forecasts, fn ($f) => $f['risk_level'] !== 'SAFE');
        $ewsSummary = $this->earlyWarningService->getSummaryMetrics();
        $budgetAlertSummary = $this->earlyWarningService->getBudgetAlertSummary($currentYear);
        $criticalBudgets = array_values(array_filter(
            $this->earlyWarningService->getBudgetEvaluations($currentYear),
            fn ($b) => $b['risk_level'] !== 'SAFE'
        ));

        // 5. Chart 1: Stock Valuation by Category
        $categoryValuations = Category::with('items.stockBalances')->get()->map(function ($cat) {
            $val = $cat->items->sum(fn ($it) => $it->stockBalances->sum('on_hand') * (float) $it->estimated_unit_price);
            $qty = $cat->items->sum(fn ($it) => $it->stockBalances->sum('on_hand'));

            return [
                'name' => $cat->name,
                'value' => (float) $val,
                'qty' => $qty,
            ];
        })->filter(fn ($c) => $c['value'] > 0)->values();

        // 6. Chart 2: Branch Budget Performance
        $branchBudgets = Budget::with('organization')->where('year', $currentYear)->get()->map(function ($b) {
            return [
                'name' => $b->organization->code ?? 'KP',
                'allocated' => (float) $b->allocated_amount,
                'realized' => (float) ($b->committed_amount + $b->realized_amount),
                'available' => (float) $b->available_amount,
                'rate' => $b->utilization_percentage,
            ];
        });

        // 7. Chart 3: Stock Movement In vs Out Velocity
        $movementIn = StockLedger::where('transaction_type', 'PROCUREMENT_RECEIPT')->sum('qty_in');
        $movementOut = StockLedger::where('transaction_type', 'GOODS_ISSUE')->sum('qty_out');
        $movementBranch = StockLedger::where('transaction_type', 'GOODS_RECEIPT_UNIT')->sum('qty_in');

        // 8. Recent Orders
        $isBranch = $user->isBranchUser();
        $isWarehouse = $user->isWarehouseUser();
        $isProcurement = $user->isProcurementUser();
        $isFinance = $user->isFinanceUser();
        $isAuditor = $user->isAuditor();
        $isManagement = $user->isManagement();
        $isSuperAdmin = $user->isSuperAdmin();

        // Branch-specific metrics
        $branchBudget = null;
        $branchBudgetPagu = 0;
        $branchBudgetCommitted = 0;
        $branchBudgetRealized = 0;
        $branchBudgetAvailable = 0;
        $branchBudgetUtilization = 0;

        $branchTotalOrders = 0;
        $branchPendingOrders = 0;
        $branchApprovedOrders = 0;
        $branchInTransit = 0;
        $branchDelivered = 0;
        $branchOnHand = 0;
        $branchAvailable = 0;
        $branchSkuCount = 0;
        $branchStockValuation = 0;

        if ($isBranch && $user->organization_id) {
            $branchBudget = Budget::where('organization_id', $user->organization_id)->where('year', $currentYear)->first();
            if ($branchBudget) {
                $branchBudgetPagu = (float) $branchBudget->allocated_amount;
                $branchBudgetCommitted = (float) $branchBudget->committed_amount;
                $branchBudgetRealized = (float) $branchBudget->realized_amount;
                $branchBudgetAvailable = (float) $branchBudget->available_amount;
                $branchBudgetUtilization = $branchBudget->utilization_percentage;
            }

            $branchTotalOrders = Order::where('requesting_organization_id', $user->organization_id)->count();
            $branchPendingOrders = Order::where('requesting_organization_id', $user->organization_id)->whereIn('status', ['DRAFT', 'SUBMITTED', 'WAITING_APPROVAL'])->count();
            $branchApprovedOrders = Order::where('requesting_organization_id', $user->organization_id)->whereIn('status', ['APPROVED', 'ALLOCATED', 'PICKING', 'READY_TO_SHIP'])->count();

            $branchInTransit = Shipment::whereHas('order', fn ($q) => $q->where('requesting_organization_id', $user->organization_id))
                ->whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'])->count();
            $branchDelivered = Shipment::whereHas('order', fn ($q) => $q->where('requesting_organization_id', $user->organization_id))
                ->where('status', 'DELIVERED')->count();

            $branchStockQuery = StockBalance::with('item');
            if ($user->warehouse_id) {
                $branchStockQuery->where('warehouse_id', $user->warehouse_id);
            } else {
                $branchStockQuery->whereHas('warehouse', fn ($w) => $w->where('organization_id', $user->organization_id));
            }
            $branchBalances = $branchStockQuery->get();
            $branchOnHand = $branchBalances->sum('on_hand');
            $branchAvailable = $branchBalances->sum('available');
            $branchSkuCount = $branchBalances->pluck('item_id')->unique()->count();
            $branchStockValuation = $branchBalances->sum(fn ($sb) => $sb->on_hand * ($sb->item->estimated_unit_price ?? 0));
        }

        // 8. Recent Orders (Scoped to Branch for branch users)
        $ordersQuery = Order::with(['requestingOrganization', 'requester', 'items.item']);
        if ($isBranch && $user->organization_id) {
            $ordersQuery->where('requesting_organization_id', $user->organization_id);
        }
        $recentOrders = $ordersQuery->latest()->limit(7)->get();

        // 9. Recent Notifications
        $notifications = Notification::forUser($user)
            ->latest()
            ->limit(5)
            ->get();

        // 10. Chart 3: Monthly Inbound vs Outbound Stock Movements (Last 6 Months)
        $movementStartDate = now()->subMonths(5)->startOfMonth();
        $movementQuery = StockLedger::where('created_at', '>=', $movementStartDate);

        if ($isBranch && $user->organization_id) {
            if ($user->warehouse_id) {
                $movementQuery->where('warehouse_id', $user->warehouse_id);
            } else {
                $movementQuery->whereHas('warehouse', fn ($w) => $w->where('organization_id', $user->organization_id));
            }
        }
        $recentLedgers = $movementQuery->get();

        $monthlyMovements = collect(range(5, 0))->map(function ($i) use ($recentLedgers) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $matching = $recentLedgers->filter(fn ($l) => $l->created_at->format('Y-m') === $key);

            return [
                'month' => $date->translatedFormat('M Y'),
                'in' => (int) $matching->sum('qty_in'),
                'out' => (int) $matching->sum('qty_out'),
            ];
        })->values();

        // 11. Chart 4: Top 5 Fast-Moving Items
        $fastMovingQuery = StockLedger::with('item')->where('qty_out', '>', 0);
        if ($isBranch && $user->organization_id) {
            if ($user->warehouse_id) {
                $fastMovingQuery->where('warehouse_id', $user->warehouse_id);
            } else {
                $fastMovingQuery->whereHas('warehouse', fn ($w) => $w->where('organization_id', $user->organization_id));
            }
        }
        $topFastMoving = $fastMovingQuery->get()
            ->groupBy('item_id')
            ->map(function ($rows) {
                $item = $rows->first()->item;

                return [
                    'name' => $item ? Str::limit($item->name, 22) : 'Item #'.$rows->first()->item_id,
                    'sku' => $item->sku ?? '-',
                    'qty' => (int) $rows->sum('qty_out'),
                    'uom' => $item->uom ?? 'Unit',
                ];
            })
            ->sortByDesc('qty')
            ->take(5)
            ->values();

        // Fallback to top items by on-hand balance if no outbound movement recorded yet
        if ($topFastMoving->isEmpty()) {
            $balanceFallbackQuery = StockBalance::with('item');
            if ($isBranch && $user->organization_id) {
                if ($user->warehouse_id) {
                    $balanceFallbackQuery->where('warehouse_id', $user->warehouse_id);
                } else {
                    $balanceFallbackQuery->whereHas('warehouse', fn ($w) => $w->where('organization_id', $user->organization_id));
                }
            }
            $topFastMoving = $balanceFallbackQuery->get()
                ->groupBy('item_id')
                ->map(function ($rows) {
                    $item = $rows->first()->item;

                    return [
                        'name' => $item ? Str::limit($item->name, 22) : 'Item #'.$rows->first()->item_id,
                        'sku' => $item->sku ?? '-',
                        'qty' => (int) $rows->sum('on_hand'),
                        'uom' => $item->uom ?? 'Unit',
                    ];
                })
                ->filter(fn ($item) => $item['qty'] > 0)
                ->sortByDesc('qty')
                ->take(5)
                ->values();
        }

        return view('dashboard.index', compact(
            'user',
            'isBranch',
            'isWarehouse',
            'isProcurement',
            'isFinance',
            'isAuditor',
            'isManagement',
            'isSuperAdmin',
            'branchBudgetPagu',
            'branchBudgetCommitted',
            'branchBudgetRealized',
            'branchBudgetAvailable',
            'branchBudgetUtilization',
            'branchTotalOrders',
            'branchPendingOrders',
            'branchApprovedOrders',
            'branchInTransit',
            'branchDelivered',
            'branchOnHand',
            'branchAvailable',
            'branchSkuCount',
            'branchStockValuation',
            'totalItems',
            'totalBranches',
            'totalWarehouses',
            'totalStockValue',
            'availableStockValue',
            'reservedStockValue',
            'damagedStockValue',
            'totalBudgetPagu',
            'totalBudgetCommitted',
            'totalBudgetRealized',
            'totalBudgetAvailable',
            'budgetUtilization',
            'pendingPrCount',
            'approvedPrPoolCount',
            'activePoCount',
            'pendingOrderApprovals',
            'allocatedOrders',
            'pickingOrders',
            'readyToShipOrders',
            'inTransitShipments',
            'deliveredCount',
            'discrepancyReports',
            'pendingSettlements',
            'pendingSwitching',
            'criticalRiskItems',
            'categoryValuations',
            'branchBudgets',
            'movementIn',
            'movementOut',
            'movementBranch',
            'monthlyMovements',
            'topFastMoving',
            'recentOrders',
            'notifications',
            'ewsSummary',
            'budgetAlertSummary',
            'criticalBudgets'
        ));
    }
}
