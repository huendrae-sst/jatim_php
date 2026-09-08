<?php

namespace App\Console\Commands;

use App\Models\GeneralLedgerEntry;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\GeneralLedgerService;
use Illuminate\Console\Command;

class SyncGoodsReceiptToGeneralLedgerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jims:sync-goods-receipt-gl {--force : Sinkron ulang meskipun sudah ada entri GL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasikan seluruh penerimaan barang vendor (Goods Receipt / PO) ke Buku Besar Keuangan (General Ledger).';

    /**
     * Execute the console command.
     */
    public function handle(GeneralLedgerService $glService): int
    {
        $this->info('Memulai sinkronisasi Penerimaan PO (Goods Receipt) ke Buku Besar Keuangan (General Ledger)...');

        $force = $this->option('force');

        $goodsReceipts = GoodsReceipt::with([
            'purchaseOrder.vendor',
            'warehouse.organization',
            'items.item.category',
            'items.purchaseOrderItem',
        ])->orderBy('receipt_date')->get();

        if ($goodsReceipts->isEmpty()) {
            $this->warn('Tidak ada data penerimaan barang (Goods Receipt) yang ditemukan.');

            return self::SUCCESS;
        }

        $superAdmin = User::where('role', 'SUPER_ADMIN')->first() ?: User::first();
        $summaryTable = [];
        $totalValuationSynced = 0.0;
        $totalProcessed = 0;

        foreach ($goodsReceipts as $grn) {
            $totalAccepted = $grn->items->sum('qty_accepted');
            if ($totalAccepted <= 0) {
                $summaryTable[] = [
                    $grn->grn_number,
                    $grn->purchaseOrder?->po_number ?? '-',
                    $grn->purchaseOrder?->vendor?->name ?? '-',
                    0,
                    'Rp 0',
                    'Tidak ada barang diterima (Skip)',
                ];

                continue;
            }

            $hasGlEntry = GeneralLedgerEntry::where('reference_type', 'GOODS_RECEIPT')
                ->where('reference_number', $grn->grn_number)
                ->exists();

            if ($hasGlEntry && ! $force) {
                $existingVal = GeneralLedgerEntry::where('reference_type', 'GOODS_RECEIPT')
                    ->where('reference_number', $grn->grn_number)
                    ->sum('debit');

                $summaryTable[] = [
                    $grn->grn_number,
                    $grn->purchaseOrder?->po_number ?? '-',
                    $grn->purchaseOrder?->vendor?->name ?? '-',
                    $grn->items->count(),
                    'Rp '.number_format($existingVal, 0, ',', '.'),
                    'Sudah tercatat di GL (Skip)',
                ];

                continue;
            }

            if ($hasGlEntry && $force) {
                GeneralLedgerEntry::where('reference_type', 'GOODS_RECEIPT')
                    ->where('reference_number', $grn->grn_number)
                    ->delete();
            }

            $entries = $glService->recordGoodsReceiptJournal($grn, $superAdmin);

            if (! empty($entries)) {
                $valuation = collect($entries)->sum('debit');
                $totalValuationSynced += $valuation;
                $totalProcessed++;

                $summaryTable[] = [
                    $grn->grn_number,
                    $grn->purchaseOrder?->po_number ?? '-',
                    $grn->purchaseOrder?->vendor?->name ?? '-',
                    $grn->items->count(),
                    'Rp '.number_format($valuation, 0, ',', '.'),
                    $force ? 'Sinkron Ulang Berhasil' : 'Berhasil Dibukukan ke GL',
                ];
            }
        }

        $this->table(
            ['No. GRN', 'No. PO', 'Vendor', 'Item Diterima', 'Total Valuasi', 'Status'],
            $summaryTable
        );

        $this->info("Sinkronisasi selesai! {$totalProcessed} GRN berhasil diposting ke General Ledger dengan total valuasi Rp ".number_format($totalValuationSynced, 0, ',', '.').'.');

        return self::SUCCESS;
    }
}
