<?php

namespace App\Console\Commands;

use App\Services\EarlyWarningService;
use Illuminate\Console\Command;

class ScanEarlyWarningCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:ews-scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pindai kondisi persediaan barang untuk Early Warning System (EWS) dan kirim notifikasi jika terdeteksi anomali.';

    /**
     * Execute the console command.
     */
    public function handle(EarlyWarningService $earlyWarningService): int
    {
        $this->info('Memulai pemindaian Early Warning System (EWS) persediaan...');

        $metrics = $earlyWarningService->getSummaryMetrics();

        $this->table(
            ['Metrik EWS', 'Jumlah SKU'],
            [
                ['Total SKU Teranalisis', $metrics['total_items']],
                ['Kritis / Stockout', $metrics['critical_count']],
                ['Perlu Reorder (ROP)', $metrics['reorder_count']],
                ['Overstock / Excess', $metrics['overstock_count']],
                ['Dead Stock (>= 90 hari)', $metrics['dead_stock_count']],
                ['Terdapat Stok Rusak', $metrics['damaged_count']],
                ['Kondisi Aman', $metrics['safe_count']],
            ]
        );

        $notifiedCount = $earlyWarningService->scanAndNotify();

        $this->info("Pemindaian selesai. {$notifiedCount} peringatan telah didistribusikan ke sistem notifikasi.");

        return Command::SUCCESS;
    }
}
