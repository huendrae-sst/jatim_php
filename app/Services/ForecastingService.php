<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockLedger;

class ForecastingService
{
    public function getItemForecast(Item $item): array
    {
        // Calculate historical demand from StockLedgers (GOODS_ISSUE / TRANSFER_OUT in the last 6 months)
        $monthlyUsage = StockLedger::where('item_id', $item->id)
            ->whereIn('transaction_type', ['GOODS_ISSUE', 'TRANSFER_OUT'])
            ->where('created_at', '>=', now()->subMonths(6))
            ->get()
            ->groupBy(function ($ledger) {
                return $ledger->created_at->format('Y-m');
            })
            ->map(function ($rows) {
                return $rows->sum('qty_out');
            })
            ->values()
            ->toArray();

        $avgMonthlyDemand = count($monthlyUsage) > 0 ? (array_sum($monthlyUsage) / count($monthlyUsage)) : 45.0;
        $dailyDemand = $avgMonthlyDemand / 30.0;

        $leadTimeDays = $item->lead_time_days ?: 7;
        $leadTimeDemand = round($dailyDemand * $leadTimeDays);

        // Recommended Safety Stock = Z * sqrt(lead_time) * std_dev (approx 1.65 * dailyDemand * sqrt(LT))
        $recommendedSafetyStock = round(1.65 * $dailyDemand * sqrt($leadTimeDays));
        if ($recommendedSafetyStock < 5) {
            $recommendedSafetyStock = 15;
        }

        // Reorder Point = Lead Time Demand + Safety Stock
        $reorderPoint = $leadTimeDemand + $recommendedSafetyStock;

        // Current Available
        $currentAvailable = $item->total_available;

        // Days of Supply remaining
        $daysOfSupply = $dailyDemand > 0 ? round($currentAvailable / $dailyDemand) : 999;

        // Status & Suggested Reorder
        $isBelowReorderPoint = $currentAvailable <= $reorderPoint;
        $suggestedReorderQty = $isBelowReorderPoint ? max(50, ($item->max_stock ?: 200) - $currentAvailable) : 0;

        return [
            'item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'uom' => $item->uom,
            'avg_monthly_demand' => round($avgMonthlyDemand, 1),
            'daily_demand' => round($dailyDemand, 2),
            'lead_time_days' => $leadTimeDays,
            'lead_time_demand' => $leadTimeDemand,
            'recommended_safety_stock' => $recommendedSafetyStock,
            'reorder_point' => $reorderPoint,
            'current_available' => $currentAvailable,
            'days_of_supply' => $daysOfSupply,
            'is_below_rop' => $isBelowReorderPoint,
            'suggested_reorder_qty' => $suggestedReorderQty,
            'risk_level' => $currentAvailable == 0 ? 'CRITICAL_STOCKOUT' : ($isBelowReorderPoint ? 'HIGH_REORDER' : 'SAFE'),
        ];
    }

    public function getAllForecasts(): array
    {
        $items = Item::with('stockBalances')->where('is_active', true)->get();
        $results = [];
        foreach ($items as $item) {
            $results[] = $this->getItemForecast($item);
        }

        return $results;
    }
}
