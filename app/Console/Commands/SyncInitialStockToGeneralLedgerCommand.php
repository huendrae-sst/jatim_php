<?php

namespace App\Console\Commands;

use App\Models\GeneralLedgerEntry;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GeneralLedgerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncInitialStockToGeneralLedgerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jims:sync-initial-stock-gl {--warehouse= : ID Gudang spesifik} {--force : Sinkron ulang meskipun sudah ada entri GL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasikan seluruh saldo awal persediaan gudang dari Stock Ledger ke Buku Besar Keuangan (General Ledger).';

    /**
     * Execute the console command.
     */
    public function handle(GeneralLedgerService $glService): int
    {
        $this->info('Memulai sinkronisasi Saldo Awal Gudang ke Buku Besar Keuangan (General Ledger)...');

        $warehouseId = $this->option('warehouse');
        $force = $this->option('force');

        $warehousesQuery = Warehouse::with('organization')->where('is_active', true);
        if ($warehouseId) {
            $warehousesQuery->where('id', $warehouseId);
        }
        $warehouses = $warehousesQuery->get();

        if ($warehouses->isEmpty()) {
            $this->warn('Tidak ada gudang yang ditemukan.');

            return self::SUCCESS;
        }

        $superAdmin = User::where('role', 'SUPER_ADMIN')->first() ?: User::first();
        $summaryTable = [];
        $totalValuationSynced = 0.0;

        foreach ($warehouses as $wh) {
            $initialStockLedgers = StockLedger::with('item.category')
                ->where('warehouse_id', $wh->id)
                ->where('transaction_type', 'STOCK_INITIAL')
                ->get();

            if ($initialStockLedgers->isEmpty()) {
                $summaryTable[] = [
                    $wh->name,
                    $wh->organization?->name ?? 'Pusat',
                    0,
                    'Rp 0',
                    'Tidak ada saldo awal fisik',
                ];

                continue;
            }

            // Check if already has INITIAL_BALANCE GL entry for this organization
            $hasGlEntry = GeneralLedgerEntry::where('organization_id', $wh->organization_id)
                ->where('reference_type', 'INITIAL_BALANCE')
                ->exists();

            if ($hasGlEntry && ! $force) {
                $existingVal = GeneralLedgerEntry::where('organization_id', $wh->organization_id)
                    ->where('reference_type', 'INITIAL_BALANCE')
                    ->sum('debit');

                $summaryTable[] = [
                    $wh->name,
                    $wh->organization?->name ?? 'Pusat',
                    $initialStockLedgers->count(),
                    'Rp '.number_format($existingVal, 0, ',', '.'),
                    'Sudah tercatat di GL (Skip)',
                ];

                continue;
            }

            if ($hasGlEntry && $force) {
                // Delete old initial balance entries for this organization
                GeneralLedgerEntry::where('organization_id', $wh->organization_id)
                    ->where('reference_type', 'INITIAL_BALANCE')
                    ->delete();
            }

            $itemsPayload = [];
            $whValuation = 0.0;

            foreach ($initialStockLedgers as $isl) {
                if ($isl->item) {
                    $itemsPayload[] = [
                        'item' => $isl->item,
                        'qty_good' => $isl->qty_in,
                        'qty_damaged' => 0,
                        'unit_cost' => (float) $isl->unit_cost,
                    ];
                    $whValuation += ($isl->qty_in * (float) $isl->unit_cost);
                }
            }

            if (! empty($itemsPayload)) {
                $refNo = 'INIT-MIG-'.str_replace(['WH-', '-'], '', $wh->code);
                $glService->recordInitialStockBatchJournal(
                    $wh,
                    $itemsPayload,
                    $refNo,
                    $superAdmin,
                    Carbon::parse('2026-08-01')
                );

                $totalValuationSynced += $whValuation;

                $summaryTable[] = [
                    $wh->name,
                    $wh->organization?->name ?? 'Pusat',
                    count($itemsPayload),
                    'Rp '.number_format($whValuation, 0, ',', '.'),
                    '<fg=green>Berhasil Disinkronkan</>',
                ];
            }
        }

        $this->table(
            ['Gudang', 'Unit Kerja / Cabang', 'Jumlah SKU', 'Nilai Saldo Awal', 'Status GL'],
            $summaryTable
        );

        $this->info('Sinkronisasi selesai! Total valuasi saldo awal tersinkronkan: Rp '.number_format($totalValuationSynced, 0, ',', '.'));

        return self::SUCCESS;
    }
}
