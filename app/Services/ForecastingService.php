<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockLedger;

class ForecastingService
{
    /**
     * Calculate item demand forecast and ROP with dynamic multi-horizon projection (POC-47).
     * Supported horizons: 1 month, 3 months, 6 months, 12 months.
     */
    public function getItemForecast(Item $item, int $horizonMonths = 6): array
    {
        // Normalize horizon
        $horizonMonths = in_array($horizonMonths, [1, 3, 6, 12], true) ? $horizonMonths : 6;

        // Calculate historical demand from StockLedgers (GOODS_ISSUE / TRANSFER_OUT / PRODUCTION_ISSUE)
        $lookbackMonths = max(12, $horizonMonths * 2);
        $monthlyUsage = StockLedger::where('item_id', $item->id)
            ->whereIn('transaction_type', ['GOODS_ISSUE', 'TRANSFER_OUT', 'PRODUCTION_ISSUE'])
            ->where('created_at', '>=', now()->subMonths($lookbackMonths))
            ->get()
            ->groupBy(fn ($ledger) => $ledger->created_at->format('Y-m'))
            ->map(fn ($rows) => (int) $rows->sum('qty_out'))
            ->values()
            ->toArray();

        $avgMonthlyDemand = count($monthlyUsage) > 0 ? (array_sum($monthlyUsage) / count($monthlyUsage)) : 45.0;
        $dailyDemand = $avgMonthlyDemand / 30.0;

        $leadTimeDays = (int) ($item->lead_time_days ?: 7);
        $leadTimeDemand = (int) round($dailyDemand * $leadTimeDays);

        // Horizon scaling factor for safety buffer (longer horizon needs slightly wider buffer)
        $horizonBufferFactor = $horizonMonths === 1 ? 1.0 : ($horizonMonths === 3 ? 1.2 : ($horizonMonths === 6 ? 1.4 : 1.7));

        // Recommended Safety Stock = Z * sqrt(lead_time) * std_dev
        $recommendedSafetyStock = (int) max(15, round(1.65 * $dailyDemand * sqrt($leadTimeDays) * $horizonBufferFactor));

        // Reorder Point = Lead Time Demand + Safety Stock
        $reorderPoint = $leadTimeDemand + $recommendedSafetyStock;

        // Projected Demand over the selected horizon
        $projectedDemand = (int) round($avgMonthlyDemand * $horizonMonths);

        // Current Available
        $currentAvailable = (int) $item->total_available;

        // Days of Supply remaining
        $daysOfSupply = $dailyDemand > 0 ? (int) round($currentAvailable / $dailyDemand) : 999;

        // Trend calculation: compare last 30-day demand vs prior period
        $recentUsage = (int) StockLedger::where('item_id', $item->id)
            ->whereIn('transaction_type', ['GOODS_ISSUE', 'TRANSFER_OUT', 'PRODUCTION_ISSUE'])
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('qty_out');

        $trend = 'STABLE';
        if ($recentUsage > ($avgMonthlyDemand * 1.15)) {
            $trend = 'UP';
        } elseif ($recentUsage < ($avgMonthlyDemand * 0.85) && $recentUsage > 0) {
            $trend = 'DOWN';
        }

        // Status & Suggested Reorder for the selected horizon
        $isBelowReorderPoint = $currentAvailable <= $reorderPoint;
        $targetStockForHorizon = $projectedDemand + $recommendedSafetyStock;
        $suggestedReorderQty = max(0, $targetStockForHorizon - $currentAvailable);

        // Minimum order batch if reorder is needed
        if ($suggestedReorderQty > 0 && $suggestedReorderQty < 25) {
            $suggestedReorderQty = 25;
        }

        $horizonLabels = [
            1 => '1 Bulan (Operasional)',
            3 => '3 Bulan (Triwulan)',
            6 => '6 Bulan (Semester)',
            12 => '12 Bulan (Tahunan)',
        ];

        return [
            'item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'uom' => $item->uom,
            'category_name' => $item->category?->name ?? '-',
            'horizon_months' => $horizonMonths,
            'horizon_label' => $horizonLabels[$horizonMonths] ?? "{$horizonMonths} Bulan",
            'avg_monthly_demand' => round($avgMonthlyDemand, 1),
            'daily_demand' => round($dailyDemand, 2),
            'projected_demand' => $projectedDemand,
            'projected_horizon_demand' => $projectedDemand,
            'lead_time_days' => $leadTimeDays,
            'lead_time_demand' => $leadTimeDemand,
            'recommended_safety_stock' => $recommendedSafetyStock,
            'reorder_point' => $reorderPoint,
            'current_available' => $currentAvailable,
            'days_of_supply' => $daysOfSupply,
            'is_below_rop' => $isBelowReorderPoint,
            'suggested_reorder_qty' => $suggestedReorderQty,
            'trend' => $trend,
            'risk_level' => $currentAvailable == 0 ? 'CRITICAL_STOCKOUT' : ($isBelowReorderPoint ? 'HIGH_REORDER' : 'SAFE'),
        ];
    }

    public function getAllForecasts(int $horizonMonths = 6): array
    {
        $items = Item::with(['category', 'stockBalances'])->where('is_active', true)->get();
        $results = [];
        foreach ($items as $item) {
            $results[] = $this->getItemForecast($item, $horizonMonths);
        }

        return $results;
    }
}
