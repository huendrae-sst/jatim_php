<?php

namespace App\Console\Commands;

use App\Models\GeneralLedgerEntry;
use App\Models\SwitchingStock;
use App\Models\User;
use App\Services\GeneralLedgerService;
use Illuminate\Console\Command;

class SyncSwitchingStockToGeneralLedgerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jims:sync-switching-stock-gl {--force : Sinkron ulang meskipun sudah ada entri GL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasikan transaksi transfer switching stock (pengiriman & penerimaan) ke Buku Besar Keuangan (General Ledger).';

    /**
     * Execute the console command.
     */
    public function handle(GeneralLedgerService $glService): int
    {
        $this->info('Memulai sinkronisasi Switching Stock ke Buku Besar Keuangan (General Ledger)...');

        $force = $this->option('force');

        $switchings = SwitchingStock::with([
            'items.item.category',
            'sourceWarehouse.organization',
            'destinationWarehouse.organization',
            'sourceOrganization',
            'destinationOrganization',
            'item.category',
            'shipment.receivings.discrepancies',
        ])
            ->whereIn('status', ['TRANSFERRED', 'COMPLETED'])
            ->orderBy('id')
            ->get();

        if ($switchings->isEmpty()) {
            $this->warn('Tidak ada transaksi switching stock berstatus TRANSFERRED atau COMPLETED.');

            return self::SUCCESS;
        }

        $superAdmin = User::where('role', 'SUPER_ADMIN')->first() ?: User::first();
        $summaryTable = [];
        $totalValuationSynced = 0.0;
        $totalProcessed = 0;

        foreach ($switchings as $sw) {
            $shipment = $sw->shipment;
            $dispatchRef = $shipment?->manifest_number ?: ('SW-TRF-'.str_pad((string) $sw->id, 5, '0', STR_PAD_LEFT));
            $sourceName = $sw->sourceOrganization?->name ?: ($sw->sourceWarehouse?->organization?->name ?? 'Cabang Asal');
            $destName = $sw->destinationOrganization?->name ?: ($sw->destinationWarehouse?->organization?->name ?? 'Cabang Tujuan');

            // 1. Sync Dispatch Journal (Pengiriman)
            $hasDispatchGl = GeneralLedgerEntry::where('reference_type', 'SWITCHING_DISPATCH')
                ->where('reference_number', $dispatchRef)
                ->exists();

            if ($hasDispatchGl && $force) {
                GeneralLedgerEntry::where('reference_type', 'SWITCHING_DISPATCH')
                    ->where('reference_number', $dispatchRef)
                    ->delete();
                $hasDispatchGl = false;
            }

            if (! $hasDispatchGl) {
                $dispatchEntries = $glService->recordSwitchingDispatchJournal($sw, $shipment, $superAdmin);
                if (! empty($dispatchEntries)) {
                    $dispatchVal = collect($dispatchEntries)->sum('debit');
                    $totalValuationSynced += $dispatchVal;
                    $totalProcessed++;
                    $summaryTable[] = [
                        $dispatchRef,
                        'PENGIRIMAN (DISPATCH)',
                        $sourceName.' -> '.$destName,
                        'Rp '.number_format($dispatchVal, 0, ',', '.'),
                        $force ? 'Sinkron Ulang Berhasil' : 'Berhasil Dibukukan ke GL',
                    ];
                }
            } else {
                $existingVal = GeneralLedgerEntry::where('reference_type', 'SWITCHING_DISPATCH')
                    ->where('reference_number', $dispatchRef)
                    ->sum('debit');
                $summaryTable[] = [
                    $dispatchRef,
                    'PENGIRIMAN (DISPATCH)',
                    $sourceName.' -> '.$destName,
                    'Rp '.number_format($existingVal, 0, ',', '.'),
                    'Sudah tercatat di GL (Skip)',
                ];
            }

            // 2. Sync Receipt Journal (Penerimaan) if COMPLETED
            if ($sw->status === 'COMPLETED') {
                $receiving = $shipment?->receivings?->first();
                $receiptRef = $receiving?->receiving_number ?: ('SW-RCV-'.str_pad((string) $sw->id, 5, '0', STR_PAD_LEFT));

                $hasReceiptGl = GeneralLedgerEntry::where('reference_type', 'SWITCHING_RECEIPT')
                    ->where('reference_number', $receiptRef)
                    ->exists();

                if ($hasReceiptGl && $force) {
                    GeneralLedgerEntry::where('reference_type', 'SWITCHING_RECEIPT')
                        ->where('reference_number', $receiptRef)
                        ->delete();
                    $hasReceiptGl = false;
                }

                if (! $hasReceiptGl) {
                    $receiptEntries = $glService->recordSwitchingReceiptJournal($sw, $receiving, $superAdmin);
                    if (! empty($receiptEntries)) {
                        $receiptVal = collect($receiptEntries)->sum('debit');
                        $totalValuationSynced += $receiptVal;
                        $totalProcessed++;
                        $summaryTable[] = [
                            $receiptRef,
                            'PENERIMAAN (RECEIPT)',
                            $destName.' <- '.$sourceName,
                            'Rp '.number_format($receiptVal, 0, ',', '.'),
                            $force ? 'Sinkron Ulang Berhasil' : 'Berhasil Dibukukan ke GL',
                        ];
                    }
                } else {
                    $existingReceiptVal = GeneralLedgerEntry::where('reference_type', 'SWITCHING_RECEIPT')
                        ->where('reference_number', $receiptRef)
                        ->sum('debit');
                    $summaryTable[] = [
                        $receiptRef,
                        'PENERIMAAN (RECEIPT)',
                        $destName.' <- '.$sourceName,
                        'Rp '.number_format($existingReceiptVal, 0, ',', '.'),
                        'Sudah tercatat di GL (Skip)',
                    ];
                }
            }
        }

        $this->table(
            ['No. Referensi', 'Jenis Mutasi', 'Rute Transfer', 'Total Valuasi', 'Status'],
            $summaryTable
        );

        $this->info("Sinkronisasi selesai! {$totalProcessed} mutasi switching stock berhasil diposting ke General Ledger dengan total valuasi Rp ".number_format($totalValuationSynced, 0, ',', '.').'.');

        return self::SUCCESS;
    }
}
