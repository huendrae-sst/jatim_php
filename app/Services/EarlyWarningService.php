<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockLedger;
use Carbon\Carbon;

class EarlyWarningService
{
    public function __construct(
        protected ForecastingService $forecastingService
    ) {}

    /**
     * Mengevaluasi kondisi peringatan dini (EWS) untuk suatu item.
     * Dapat dibatasi pada gudang tertentu atau konsolidasi seluruh gudang.
     *
     * @return array<string, mixed>
     */
    public function evaluateItem(Item $item, ?int $warehouseId = null): array
    {
        // 1. Saldo stok (On Hand, Available, Damaged, Reserved, Hold)
        $balanceQuery = StockBalance::where('item_id', $item->id);
        if ($warehouseId && $warehouseId > 0) {
            $balanceQuery->where('warehouse_id', $warehouseId);
        }

        $balances = $balanceQuery->get();
        $onHand = (int) $balances->sum('on_hand');
        $reserved = (int) $balances->sum('reserved');
        $hold = (int) $balances->sum('hold');
        $damaged = (int) $balances->sum('damaged');
        $available = max(0, $onHand - $reserved - $hold - $damaged);

        // 2. Metrik Peramalan & Buffer
        $forecast = $this->forecastingService->getItemForecast($item);
        $dailyDemand = (float) ($forecast['daily_demand'] ?? 0.0);
        $leadTimeDays = (int) ($item->lead_time_days ?: ($forecast['lead_time_days'] ?? 7));
        $reorderPoint = (int) ($item->reorder_point ?: ($forecast['reorder_point'] ?? 30));
        $safetyStock = (int) ($item->safety_stock ?: ($forecast['recommended_safety_stock'] ?? 15));
        $maxStock = (int) ($item->max_stock ?: 200);

        // Days of supply
        $daysOfSupply = $dailyDemand > 0 ? (int) round($available / $dailyDemand) : 999;

        // 3. Cek Mutasi Terakhir (Dead Stock detection: tidak ada mutasi keluar >= 90 hari)
        $lastOutbound = StockLedger::where('item_id', $item->id)
            ->whereIn('transaction_type', ['GOODS_ISSUE', 'TRANSFER_OUT', 'SWITCHING_STOCK'])
            ->when($warehouseId && $warehouseId > 0, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->latest('created_at')
            ->first();

        $daysSinceLastMovement = null;
        if ($lastOutbound && $lastOutbound->created_at) {
            $daysSinceLastMovement = (int) Carbon::parse($lastOutbound->created_at)->diffInDays(now());
        } elseif ($item->created_at) {
            $daysSinceLastMovement = (int) Carbon::parse($item->created_at)->diffInDays(now());
        }

        // 4. Deteksi Multi-Alert
        $alerts = [];

        // Alert A: Stockout / Critical
        if ($available <= 0 && ($dailyDemand > 0 || $onHand == 0)) {
            $alerts[] = 'CRITICAL_STOCKOUT';
        } elseif ($dailyDemand > 0 && $daysOfSupply <= 3) {
            $alerts[] = 'CRITICAL_STOCKOUT';
        }

        // Alert B: High Reorder (ROP breach)
        if ($available > 0 && ($available <= $reorderPoint || ($dailyDemand > 0 && $daysOfSupply <= $leadTimeDays))) {
            $alerts[] = 'HIGH_REORDER';
        }

        // Alert C: Overstock / Excess Stock
        if ($onHand > $maxStock || ($dailyDemand > 0 && $daysOfSupply > 180 && $onHand >= 50)) {
            $alerts[] = 'OVERSTOCK_EXCESS';
        }

        // Alert D: Dead Stock / Slow Moving (Stok mengendap tanpa pengeluaran >= 90 hari)
        if ($onHand > 0 && $daysSinceLastMovement !== null && $daysSinceLastMovement >= 90) {
            $alerts[] = 'DEAD_STOCK';
        }

        // Alert E: Damaged Alert
        if ($damaged > 0) {
            $alerts[] = 'DAMAGED_ALERT';
        }

        // 5. Menentukan Primary Alert & Severity
        $primaryAlert = 'SAFE';
        $severity = 'SAFE';
        $recommendation = 'Kondisi stok normal dan memenuhi batas buffer operasional.';

        if (in_array('CRITICAL_STOCKOUT', $alerts, true)) {
            $primaryAlert = 'CRITICAL_STOCKOUT';
            $severity = 'CRITICAL';
            $recommendation = 'Stok kritis/habis! Segera terbitkan Purchase Request (PR) darurat atau ajukan Switching Stok dari cabang lain.';
        } elseif (in_array('HIGH_REORDER', $alerts, true)) {
            $primaryAlert = 'HIGH_REORDER';
            $severity = 'WARNING';
            $recommendation = 'Stok berada di bawah titik pemesanan (ROP). Segera buat Purchase Request (PR) sebelum safety stock habis.';
        } elseif (in_array('DAMAGED_ALERT', $alerts, true)) {
            $primaryAlert = 'DAMAGED_ALERT';
            $severity = 'WARNING';
            $recommendation = 'Terdapat barang rusak. Lakukan opname investigasi, klaim retur vendor, atau pengajuan afkir/write-off.';
        } elseif (in_array('DEAD_STOCK', $alerts, true)) {
            $primaryAlert = 'DEAD_STOCK';
            $severity = 'INFO';
            $recommendation = "Barang tidak bergerak selama {$daysSinceLastMovement} hari. Evaluasi utilisasi atau diskusikan re-alokasi ke unit kerja lain.";
        } elseif (in_array('OVERSTOCK_EXCESS', $alerts, true)) {
            $primaryAlert = 'OVERSTOCK_EXCESS';
            $severity = 'INFO';
            $recommendation = 'Stok melebihi batas maksimum atau daya tahan simpan > 180 hari. Tahan pengadaan baru dan prioritaskan distribusi.';
        }

        $unitPrice = (float) $item->estimated_unit_price;
        $totalValuation = round($onHand * $unitPrice, 2);

        // 6. Analisis Perbandingan Kecepatan Pemenuhan (PR via Vendor vs Switching via Kurir)
        $leadTimeComparison = null;
        if (in_array('CRITICAL_STOCKOUT', $alerts, true) || in_array('HIGH_REORDER', $alerts, true)) {
            // Jalur 1: PR via Vendor (Vendor -> Gudang Pusat -> Gudang Tujuan)
            $vendorLt = $leadTimeDays;
            $transitToDest = 2; // Estimasi penerimaan & pengiriman Gudang Pusat ke Tujuan (1-2 hari kerja)
            $prTotalDays = $vendorLt + $transitToDest;

            // Jalur 2: Switching Stock via Kurir (Gudang Sumber Surplus -> Kurir Langsung -> Gudang Tujuan)
            $switchingDays = 2; // Estimasi 1-2 hari via kurir langsung

            // Cek ketersediaan stok surplus di gudang lain (Available - Safety Stock > 0)
            if ($item->relationLoaded('stockBalances')) {
                $otherBalances = $item->stockBalances->filter(function ($sb) use ($warehouseId) {
                    return ! $warehouseId || $sb->warehouse_id != $warehouseId;
                });
            } else {
                $otherBalancesQuery = StockBalance::with('warehouse.organization')
                    ->where('item_id', $item->id);
                if ($warehouseId && $warehouseId > 0) {
                    $otherBalancesQuery->where('warehouse_id', '!=', $warehouseId);
                }
                $otherBalances = $otherBalancesQuery->get();
            }

            $bestSource = null;
            $totalSurplus = 0;

            foreach ($otherBalances as $ob) {
                if (! $ob->relationLoaded('warehouse')) {
                    $ob->load('warehouse.organization');
                }

                $avail = (int) $ob->available;
                $surplus = max(0, $avail - $safetyStock);
                if ($surplus > 0) {
                    $totalSurplus += $surplus;
                    if (! $bestSource || $surplus > $bestSource['surplus']) {
                        $bestSource = [
                            'warehouse_id' => $ob->warehouse_id,
                            'warehouse_name' => $ob->warehouse?->name ?? 'Gudang Cabang',
                            'organization_name' => $ob->warehouse?->organization?->name ?? 'Cabang',
                            'surplus' => $surplus,
                        ];
                    }
                }
            }

            $switchingAvailable = ($bestSource !== null && $totalSurplus > 0);
            $daysSaved = $switchingAvailable ? max(1, $prTotalDays - $switchingDays) : 0;

            $leadTimeComparison = [
                'switching_available' => $switchingAvailable,
                'faster_method' => $switchingAvailable ? 'SWITCHING' : 'PR',
                'switching_days' => $switchingDays,
                'switching_days_label' => '1-2 hari kerja',
                'vendor_lead_time' => $vendorLt,
                'transit_days' => $transitToDest,
                'pr_days' => $prTotalDays,
                'days_saved' => $daysSaved,
                'best_source' => $bestSource,
                'total_surplus' => $totalSurplus,
                'source_label' => $bestSource
                    ? "{$bestSource['organization_name']} ({$bestSource['warehouse_name']}, Surplus +{$bestSource['surplus']} {$item->uom})"
                    : 'Tidak ada cabang dengan surplus stok',
            ];
        }

        return [
            'item_id' => $item->id,
            'warehouse_id' => $warehouseId,
            'sku' => $item->sku,
            'name' => $item->name,
            'category_id' => $item->category_id,
            'category_name' => $item->category?->name ?? 'Uncategorized',
            'uom' => $item->uom,
            'on_hand' => $onHand,
            'available' => $available,
            'reserved' => $reserved,
            'damaged' => $damaged,
            'hold' => $hold,
            'min_stock' => (int) $item->min_stock,
            'max_stock' => $maxStock,
            'safety_stock' => $safetyStock,
            'reorder_point' => $reorderPoint,
            'daily_demand' => round($dailyDemand, 2),
            'lead_time_days' => $leadTimeDays,
            'days_of_supply' => $daysOfSupply,
            'days_since_last_movement' => $daysSinceLastMovement,
            'estimated_unit_price' => $unitPrice,
            'total_valuation' => $totalValuation,
            'alerts' => $alerts,
            'primary_alert' => $primaryAlert,
            'severity' => $severity,
            'recommendation' => $recommendation,
            'suggested_reorder_qty' => ($primaryAlert === 'CRITICAL_STOCKOUT' || $primaryAlert === 'HIGH_REORDER')
                ? max(20, $maxStock - $available)
                : 0,
            'lead_time_comparison' => $leadTimeComparison,
        ];
    }

    /**
     * Mengambil daftar evaluasi EWS untuk seluruh item aktif.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllEvaluations(?int $warehouseId = null): array
    {
        $items = Item::with(['category', 'stockBalances.warehouse.organization'])
            ->where('is_active', true)
            ->orderBy('sku')
            ->get();

        $results = [];
        foreach ($items as $item) {
            $results[] = $this->evaluateItem($item, $warehouseId);
        }

        return $results;
    }

    /**
     * Menghitung ringkasan KPI EWS Radar.
     *
     * @return array<string, int|float>
     */
    public function getSummaryMetrics(?int $warehouseId = null): array
    {
        $evaluations = $this->getAllEvaluations($warehouseId);

        $criticalCount = 0;
        $reorderCount = 0;
        $overstockCount = 0;
        $deadStockCount = 0;
        $damagedCount = 0;
        $safeCount = 0;
        $totalAtRiskValuation = 0.0;

        foreach ($evaluations as $e) {
            if (in_array('CRITICAL_STOCKOUT', $e['alerts'], true)) {
                $criticalCount++;
            }
            if (in_array('HIGH_REORDER', $e['alerts'], true)) {
                $reorderCount++;
            }
            if (in_array('OVERSTOCK_EXCESS', $e['alerts'], true)) {
                $overstockCount++;
            }
            if (in_array('DEAD_STOCK', $e['alerts'], true)) {
                $deadStockCount++;
            }
            if (in_array('DAMAGED_ALERT', $e['alerts'], true)) {
                $damagedCount++;
            }
            if (empty($e['alerts'])) {
                $safeCount++;
            } else {
                $totalAtRiskValuation += $e['total_valuation'];
            }
        }

        return [
            'total_items' => count($evaluations),
            'critical_count' => $criticalCount,
            'reorder_count' => $reorderCount,
            'overstock_count' => $overstockCount,
            'dead_stock_count' => $deadStockCount,
            'damaged_count' => $damagedCount,
            'safe_count' => $safeCount,
            'total_alerts' => ($criticalCount + $reorderCount + $overstockCount + $deadStockCount + $damagedCount),
            'total_at_risk_valuation' => round($totalAtRiskValuation, 2),
        ];
    }

    /**
     * Memindai seluruh item dan mengirimkan notifikasi sistem untuk item kritis/waspada.
     *
     * @return int Jumlah isu peringatan yang terkirim
     */
    public function scanAndNotify(): int
    {
        $evaluations = $this->getAllEvaluations();

        $criticalItems = array_filter($evaluations, fn ($e) => $e['primary_alert'] === 'CRITICAL_STOCKOUT');
        $reorderItems = array_filter($evaluations, fn ($e) => $e['primary_alert'] === 'HIGH_REORDER');
        $damagedItems = array_filter($evaluations, fn ($e) => in_array('DAMAGED_ALERT', $e['alerts'], true));

        $totalIssues = count($criticalItems) + count($reorderItems) + count($damagedItems);

        if (count($criticalItems) > 0) {
            $skuList = implode(', ', array_slice(array_column($criticalItems, 'sku'), 0, 5));
            $moreText = count($criticalItems) > 5 ? ' dan '.(count($criticalItems) - 5).' lainnya' : '';

            NotificationService::sendAlert(
                title: 'EWS: '.count($criticalItems).' SKU Mengalami Krisis Stok!',
                message: "Terdapat item dengan stok habis/kritis ({$skuList}{$moreText}). Segera lakukan pengadaan darurat atau switching stok.",
                priority: 'CRITICAL',
                targetRole: 'INVENTORY_OFFICER',
                txType: 'EARLY_WARNING',
                url: route('inventory.early_warning', ['alert_type' => 'CRITICAL_STOCKOUT'])
            );

            NotificationService::sendAlert(
                title: 'EWS: '.count($criticalItems).' SKU Butuh Pengadaan Darurat',
                message: "Terdapat item stok kritis ({$skuList}{$moreText}). Mohon persiapkan penerbitan Purchase Request (PR).",
                priority: 'CRITICAL',
                targetRole: 'PROCUREMENT_OFFICER',
                txType: 'EARLY_WARNING',
                url: route('inventory.early_warning', ['alert_type' => 'CRITICAL_STOCKOUT'])
            );
        }

        if (count($reorderItems) > 0) {
            NotificationService::sendAlert(
                title: 'EWS: '.count($reorderItems).' SKU di Bawah Reorder Point (ROP)',
                message: 'Stok barang mendekati batas buffer safety stock. Disarankan menerbitkan Purchase Request (PR) pengadaan.',
                priority: 'WARNING',
                targetRole: 'INVENTORY_OFFICER',
                txType: 'EARLY_WARNING',
                url: route('inventory.early_warning', ['alert_type' => 'HIGH_REORDER'])
            );
        }

        if (count($damagedItems) > 0) {
            NotificationService::sendAlert(
                title: 'EWS: Terdeteksi Saldo Barang Rusak pada '.count($damagedItems).' SKU',
                message: 'Terdapat barang berstatus rusak (damaged) di gudang/cabang. Mohon lakukan investigasi opname atau penghapusan aset/afkir.',
                priority: 'WARNING',
                targetRole: 'WAREHOUSE_OFFICER',
                txType: 'EARLY_WARNING',
                url: route('inventory.early_warning', ['alert_type' => 'DAMAGED_ALERT'])
            );
        }

        return $totalIssues;
    }
}
