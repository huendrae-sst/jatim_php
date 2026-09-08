<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Organization;
use App\Models\Receiving;
use App\Models\Settlement;
use App\Models\Shipment;
use App\Models\SwitchingStock;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    /**
     * Generate next sequential journal number (e.g. JRN/2026/09/0001)
     */
    public function generateJournalNumber(): string
    {
        $yearMonth = date('Y/m');
        $prefix = "JRN/{$yearMonth}/";

        $lastEntry = GeneralLedgerEntry::where('journal_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        $nextSeq = 1;
        if ($lastEntry) {
            $parts = explode('/', $lastEntry->journal_number);
            $lastSeq = (int) end($parts);
            $nextSeq = $lastSeq + 1;
        }

        return sprintf('%s%04d', $prefix, $nextSeq);
    }

    /**
     * Record a single journal entry row
     */
    public function recordEntry(array $data): GeneralLedgerEntry
    {
        return GeneralLedgerEntry::create([
            'journal_number' => $data['journal_number'],
            'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
            'organization_id' => $data['organization_id'] ?? null,
            'cost_center_code' => $data['cost_center_code'] ?? null,
            'chart_of_account_id' => $data['chart_of_account_id'],
            'account_code' => $data['account_code'],
            'account_name' => $data['account_name'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'description' => $data['description'] ?? null,
            'debit' => $data['debit'] ?? 0,
            'credit' => $data['credit'] ?? 0,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'posted_at' => $data['posted_at'] ?? now(),
        ]);
    }

    /**
     * Resolve corresponding inventory Asset CoA based on Item Category
     */
    public function resolveInventoryCoaForItem(Item $item): ChartOfAccount
    {
        $categoryCode = $item->category?->code;
        $accountCode = match ($categoryCode) {
            'CAT-ATK' => '11301',
            'CAT-CTK', 'CAT-FLM' => '11302',
            'CAT-IT', 'CAT-NET' => '11303',
            'CAT-PRM', 'CAT-SVN' => '11304',
            'CAT-KHZ', 'CAT-SRG', 'CAT-TLR' => '11305',
            default => '11301',
        };

        return ChartOfAccount::where('account_code', $accountCode)->first()
            ?: (ChartOfAccount::where('account_code', '11301')->first()
                ?: ChartOfAccount::where('account_type', 'ASSET')->first());
    }

    /**
     * Automatically record balanced General Ledger journal entries for Initial Stock posting
     */
    public function recordInitialStockBatchJournal(
        Warehouse $warehouse,
        array $postedItems,
        string $refNo,
        ?User $user = null,
        ?Carbon $cutoffDate = null
    ): array {
        return DB::transaction(function () use ($warehouse, $postedItems, $refNo, $user, $cutoffDate) {
            if (ChartOfAccount::count() === 0) {
                return [];
            }

            $journalNumber = $this->generateJournalNumber();
            $transactionDate = $cutoffDate ? $cutoffDate->toDateString() : now()->toDateString();
            $headOffice = Organization::where('type', 'HEAD_OFFICE')->first() ?: Organization::first();
            $rakCoa = ChartOfAccount::where('account_code', '31101')->first()
                ?: (ChartOfAccount::where('account_type', 'EQUITY')->first()
                    ?: ChartOfAccount::first());

            $lossCoa = ChartOfAccount::where('account_code', '51206')->first();

            $createdEntries = [];
            $totalValuation = 0.0;
            $coaDebits = [];

            foreach ($postedItems as $p) {
                $item = $p['item'];
                $qtyGood = (int) ($p['qty_good'] ?? 0);
                $qtyDamaged = (int) ($p['qty_damaged'] ?? 0);
                $unitCost = (float) ($p['unit_cost'] ?? $item->estimated_unit_price);

                if ($qtyGood > 0) {
                    $coa = $this->resolveInventoryCoaForItem($item);
                    $val = $qtyGood * $unitCost;
                    $coaId = $coa->id;
                    if (! isset($coaDebits[$coaId])) {
                        $coaDebits[$coaId] = [
                            'coa' => $coa,
                            'amount' => 0.0,
                            'items_summary' => [],
                        ];
                    }
                    $coaDebits[$coaId]['amount'] += $val;
                    $coaDebits[$coaId]['items_summary'][] = "{$item->sku} ({$qtyGood} {$item->uom})";
                    $totalValuation += $val;
                }

                if ($qtyDamaged > 0 && $lossCoa) {
                    $valDamaged = $qtyDamaged * $unitCost;
                    $lossId = $lossCoa->id;
                    if (! isset($coaDebits[$lossId])) {
                        $coaDebits[$lossId] = [
                            'coa' => $lossCoa,
                            'amount' => 0.0,
                            'items_summary' => [],
                        ];
                    }
                    $coaDebits[$lossId]['amount'] += $valDamaged;
                    $coaDebits[$lossId]['items_summary'][] = "Rusak: {$item->sku} ({$qtyDamaged} {$item->uom})";
                    $totalValuation += $valDamaged;
                }
            }

            if ($totalValuation <= 0) {
                return [];
            }

            $org = $warehouse->organization;
            $costCenter = $org?->cost_center_code ?: 'CC-BRANCH';

            // Create Debit lines
            foreach ($coaDebits as $entry) {
                $coa = $entry['coa'];
                $summaryStr = implode(', ', array_slice($entry['items_summary'], 0, 3));
                if (count($entry['items_summary']) > 3) {
                    $summaryStr .= ' +'.(count($entry['items_summary']) - 3).' barang';
                }

                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $warehouse->organization_id,
                    'cost_center_code' => $costCenter,
                    'chart_of_account_id' => $coa->id,
                    'account_code' => $coa->account_code,
                    'account_name' => $coa->account_name,
                    'reference_type' => 'INITIAL_BALANCE',
                    'reference_number' => $refNo,
                    'description' => "Saldo awal persediaan {$warehouse->name} [{$summaryStr}]",
                    'debit' => $entry['amount'],
                    'credit' => 0,
                    'created_by_user_id' => $user?->id,
                    'posted_at' => now(),
                ]);
            }

            // Create Credit line: RAK Logistik Pusat / Ekuitas Persediaan
            $createdEntries[] = $this->recordEntry([
                'journal_number' => $journalNumber,
                'transaction_date' => $transactionDate,
                'organization_id' => $headOffice?->id,
                'cost_center_code' => $headOffice?->cost_center_code ?: 'CC-KP-LOG',
                'chart_of_account_id' => $rakCoa->id,
                'account_code' => $rakCoa->account_code,
                'account_name' => $rakCoa->account_name,
                'reference_type' => 'INITIAL_BALANCE',
                'reference_number' => $refNo,
                'description' => "Kontra ekuitas saldo awal persediaan {$warehouse->name} (Ref: {$refNo})",
                'debit' => 0,
                'credit' => $totalValuation,
                'created_by_user_id' => $user?->id,
                'posted_at' => now(),
            ]);

            return $createdEntries;
        });
    }

    /**
     * Automatically record balanced General Ledger journal entries for Goods Receipt (PO Receiving)
     */
    public function recordGoodsReceiptJournal(GoodsReceipt $grn, ?User $user = null): array
    {
        return DB::transaction(function () use ($grn, $user) {
            if (ChartOfAccount::count() === 0) {
                return [];
            }

            // Ensure relations are loaded
            $grn->loadMissing(['purchaseOrder.vendor', 'warehouse.organization', 'items.item.category', 'items.purchaseOrderItem']);

            $po = $grn->purchaseOrder;
            $warehouse = $grn->warehouse;
            $vendor = $po?->vendor;

            $vendorPayableCoa = ChartOfAccount::where('account_code', '21101')->first()
                ?: (ChartOfAccount::where('account_type', 'LIABILITY')->first()
                    ?: ChartOfAccount::first());

            $journalNumber = $this->generateJournalNumber();
            $transactionDate = $grn->receipt_date ? $grn->receipt_date->toDateString() : now()->toDateString();

            $org = $warehouse?->organization
                ?: ($po?->warehouse?->organization
                    ?: (Organization::where('type', 'HEAD_OFFICE')->first() ?: Organization::first()));
            $costCenter = $org?->cost_center_code ?: 'CC-KP-LOG';

            $createdEntries = [];
            $totalValuation = 0.0;
            $coaDebits = [];

            foreach ($grn->items as $grnItem) {
                $qtyAccepted = (int) $grnItem->qty_accepted;
                if ($qtyAccepted <= 0) {
                    continue;
                }

                $item = $grnItem->item;
                if (! $item) {
                    continue;
                }

                $poItem = $grnItem->purchaseOrderItem;
                $unitPrice = $poItem ? (float) $poItem->unit_price : (float) $item->estimated_unit_price;
                $lineTotal = $qtyAccepted * $unitPrice;

                $coa = $this->resolveInventoryCoaForItem($item);
                $coaId = $coa->id;

                if (! isset($coaDebits[$coaId])) {
                    $coaDebits[$coaId] = [
                        'coa' => $coa,
                        'amount' => 0.0,
                        'items_summary' => [],
                    ];
                }

                $coaDebits[$coaId]['amount'] += $lineTotal;
                $coaDebits[$coaId]['items_summary'][] = "{$item->sku} ({$qtyAccepted} {$item->uom})";
                $totalValuation += $lineTotal;
            }

            if ($totalValuation <= 0) {
                return [];
            }

            $vendorName = $vendor?->name ?? 'Vendor';
            $poNumber = $po?->po_number ?? '-';

            // Create Debit lines: Inventory Asset
            foreach ($coaDebits as $entry) {
                $coa = $entry['coa'];
                $summaryStr = implode(', ', array_slice($entry['items_summary'], 0, 3));
                if (count($entry['items_summary']) > 3) {
                    $summaryStr .= ' +'.(count($entry['items_summary']) - 3).' barang';
                }

                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $org?->id,
                    'cost_center_code' => $costCenter,
                    'chart_of_account_id' => $coa->id,
                    'account_code' => $coa->account_code,
                    'account_name' => $coa->account_name,
                    'reference_type' => 'GOODS_RECEIPT',
                    'reference_number' => $grn->grn_number,
                    'description' => "Penerimaan pengadaan vendor PO {$poNumber} - {$vendorName} [{$summaryStr}]",
                    'debit' => $entry['amount'],
                    'credit' => 0,
                    'created_by_user_id' => $user?->id ?? $grn->received_by_user_id,
                    'posted_at' => now(),
                ]);
            }

            // Create Credit line: Vendor Payable (Hutang Usaha Rekanan)
            $createdEntries[] = $this->recordEntry([
                'journal_number' => $journalNumber,
                'transaction_date' => $transactionDate,
                'organization_id' => $org?->id,
                'cost_center_code' => $costCenter,
                'chart_of_account_id' => $vendorPayableCoa->id,
                'account_code' => $vendorPayableCoa->account_code,
                'account_name' => $vendorPayableCoa->account_name,
                'reference_type' => 'GOODS_RECEIPT',
                'reference_number' => $grn->grn_number,
                'description' => "Hutang usaha rekanan atas penerimaan barang PO {$poNumber} (GRN: {$grn->grn_number}) - {$vendorName}",
                'debit' => 0,
                'credit' => $totalValuation,
                'created_by_user_id' => $user?->id ?? $grn->received_by_user_id,
                'posted_at' => now(),
            ]);

            return $createdEntries;
        });
    }

    /**
     * Automatically record balanced General Ledger journal entries upon settlement approval
     */
    public function recordSettlementJournal(Settlement $settlement, User $approver): array
    {
        return DB::transaction(function () use ($settlement, $approver) {
            $journalNumber = $this->generateJournalNumber();
            $transactionDate = $settlement->posted_at ? $settlement->posted_at->toDateString() : now()->toDateString();
            $createdEntries = [];

            // 1. Resolve relevant Chart of Accounts
            // Expense CoA for Debit Unit (Cabang)
            $expenseCoa = ChartOfAccount::where('account_code', '51201')->first() // Beban Pemakaian ATK
                ?: ChartOfAccount::where('account_type', 'EXPENSE')->first();

            // Shipping Expense CoA
            $shippingExpenseCoa = ChartOfAccount::where('account_code', '51205')->first() // Beban Ekspedisi
                ?: $expenseCoa;

            // Credit CoAs
            $rakCoa = ChartOfAccount::where('account_code', '31101')->first(); // RAK Logistik Pusat
            $inventoryCoa = ChartOfAccount::where('account_code', '11301')->first(); // Persediaan ATK
            $creditCoa = $rakCoa ?: ($inventoryCoa ?: ChartOfAccount::first());

            $shippingPayableCoa = ChartOfAccount::where('account_code', '21102')->first() // Hutang Ekspedisi
                ?: ($rakCoa ?: $creditCoa);

            $debitOrg = $settlement->debitOrganization;
            $creditOrg = $settlement->creditOrganization;

            $orderNumber = $settlement->order ? $settlement->order->order_number : '-';

            // Entry 1: Debit Beban Pemakaian Logistik (Unit Peminta / Cabang)
            if ($settlement->item_amount > 0 && $expenseCoa && $creditCoa) {
                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $settlement->debit_organization_id,
                    'cost_center_code' => $settlement->debit_cost_center,
                    'chart_of_account_id' => $expenseCoa->id,
                    'account_code' => $expenseCoa->account_code,
                    'account_name' => $expenseCoa->account_name,
                    'reference_type' => 'SETTLEMENT',
                    'reference_number' => $settlement->settlement_number,
                    'description' => "Pembebanan logistik atas Order {$orderNumber} - {$debitOrg?->name}",
                    'debit' => $settlement->item_amount,
                    'credit' => 0,
                    'created_by_user_id' => $approver->id,
                    'posted_at' => now(),
                ]);

                // Entry 2: Kredit RAK / Persediaan Logistik (Unit Penyedia / Kantor Pusat)
                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $settlement->credit_organization_id,
                    'cost_center_code' => $settlement->credit_cost_center,
                    'chart_of_account_id' => $creditCoa->id,
                    'account_code' => $creditCoa->account_code,
                    'account_name' => $creditCoa->account_name,
                    'reference_type' => 'SETTLEMENT',
                    'reference_number' => $settlement->settlement_number,
                    'description' => "Pengeluaran stok / RAK inter-unit atas Order {$orderNumber} ke {$debitOrg?->name}",
                    'debit' => 0,
                    'credit' => $settlement->item_amount,
                    'created_by_user_id' => $approver->id,
                    'posted_at' => now(),
                ]);
            }

            // Entry 3 & 4: Ongkos Kirim (Shipping Amount)
            if ($settlement->shipping_amount > 0 && $shippingExpenseCoa && $shippingPayableCoa) {
                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $settlement->debit_organization_id,
                    'cost_center_code' => $settlement->debit_cost_center,
                    'chart_of_account_id' => $shippingExpenseCoa->id,
                    'account_code' => $shippingExpenseCoa->account_code,
                    'account_name' => $shippingExpenseCoa->account_name,
                    'reference_type' => 'SETTLEMENT',
                    'reference_number' => $settlement->settlement_number,
                    'description' => "Beban ongkos kirim ekspedisi Order {$orderNumber} - {$debitOrg?->name}",
                    'debit' => $settlement->shipping_amount,
                    'credit' => 0,
                    'created_by_user_id' => $approver->id,
                    'posted_at' => now(),
                ]);

                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $settlement->credit_organization_id,
                    'cost_center_code' => $settlement->credit_cost_center,
                    'chart_of_account_id' => $shippingPayableCoa->id,
                    'account_code' => $shippingPayableCoa->account_code,
                    'account_name' => $shippingPayableCoa->account_name,
                    'reference_type' => 'SETTLEMENT',
                    'reference_number' => $settlement->settlement_number,
                    'description' => "Hutang/RAK biaya kurir ekspedisi pengiriman Order {$orderNumber}",
                    'debit' => 0,
                    'credit' => $settlement->shipping_amount,
                    'created_by_user_id' => $approver->id,
                    'posted_at' => now(),
                ]);
            }

            return $createdEntries;
        });
    }

    /**
     * Automatically record balanced General Ledger journal entries for Switching Stock dispatch (transfer out)
     */
    public function recordSwitchingDispatchJournal(
        SwitchingStock $switching,
        ?Shipment $shipment = null,
        ?User $user = null
    ): array {
        return DB::transaction(function () use ($switching, $shipment, $user) {
            if (ChartOfAccount::count() === 0) {
                return [];
            }

            $switching->loadMissing([
                'items.item.category',
                'sourceWarehouse.organization',
                'destinationWarehouse.organization',
                'sourceOrganization',
                'destinationOrganization',
                'item.category',
            ]);

            $sourceOrg = $switching->sourceOrganization ?: $switching->sourceWarehouse?->organization;
            $destOrg = $switching->destinationOrganization ?: $switching->destinationWarehouse?->organization;

            if (! $sourceOrg) {
                return [];
            }

            $costCenter = $sourceOrg->cost_center_code ?: 'CC-BRANCH';

            // RAK CoA (31101)
            $rakCoa = ChartOfAccount::where('account_code', '31101')->first()
                ?: (ChartOfAccount::where('account_type', 'EQUITY')->first() ?: ChartOfAccount::first());

            $refNo = $shipment?->manifest_number ?: ('SW-TRF-'.str_pad((string) $switching->id, 5, '0', STR_PAD_LEFT));
            $transactionDate = $shipment?->dispatched_at
                ? $shipment->dispatched_at->toDateString()
                : ($switching->transferred_at ? $switching->transferred_at->toDateString() : now()->toDateString());

            // Prevent duplicate GL entries
            $alreadyExists = GeneralLedgerEntry::where('reference_type', 'SWITCHING_DISPATCH')
                ->where('reference_number', $refNo)
                ->exists();

            if ($alreadyExists) {
                return [];
            }

            $journalNumber = $this->generateJournalNumber();
            $createdEntries = [];
            $totalValuation = 0.0;
            $coaCredits = [];

            $itemsToProcess = [];
            if ($switching->items->isNotEmpty()) {
                foreach ($switching->items as $swItem) {
                    $itemsToProcess[] = [
                        'item' => $swItem->item,
                        'qty' => $swItem->qty_requested,
                    ];
                }
            } elseif ($switching->item && $switching->qty_requested) {
                $itemsToProcess[] = [
                    'item' => $switching->item,
                    'qty' => $switching->qty_requested,
                ];
            }

            foreach ($itemsToProcess as $p) {
                $item = $p['item'];
                $qty = (int) $p['qty'];
                if (! $item || $qty <= 0) {
                    continue;
                }

                $unitCost = (float) $item->estimated_unit_price;
                $lineTotal = $qty * $unitCost;

                $coa = $this->resolveInventoryCoaForItem($item);
                $coaId = $coa->id;

                if (! isset($coaCredits[$coaId])) {
                    $coaCredits[$coaId] = [
                        'coa' => $coa,
                        'amount' => 0.0,
                        'items_summary' => [],
                    ];
                }

                $coaCredits[$coaId]['amount'] += $lineTotal;
                $coaCredits[$coaId]['items_summary'][] = "{$item->sku} ({$qty} {$item->uom})";
                $totalValuation += $lineTotal;
            }

            if ($totalValuation <= 0) {
                return [];
            }

            $destName = $destOrg?->name ?? 'Cabang Tujuan';

            // Entry 1: Debit RAK Logistik Pusat / Antar Cabang
            $createdEntries[] = $this->recordEntry([
                'journal_number' => $journalNumber,
                'transaction_date' => $transactionDate,
                'organization_id' => $sourceOrg->id,
                'cost_center_code' => $costCenter,
                'chart_of_account_id' => $rakCoa->id,
                'account_code' => $rakCoa->account_code,
                'account_name' => $rakCoa->account_name,
                'reference_type' => 'SWITCHING_DISPATCH',
                'reference_number' => $refNo,
                'description' => "Pengiriman transfer switching persediaan ke {$destName} (Ref: {$refNo})",
                'debit' => $totalValuation,
                'credit' => 0,
                'created_by_user_id' => $user?->id ?? $switching->transferred_by_user_id,
                'posted_at' => now(),
            ]);

            // Entry 2+: Kredit Rekening Persediaan Cabang Asal
            foreach ($coaCredits as $entry) {
                $coa = $entry['coa'];
                $summaryStr = implode(', ', array_slice($entry['items_summary'], 0, 3));
                if (count($entry['items_summary']) > 3) {
                    $summaryStr .= ' +'.(count($entry['items_summary']) - 3).' barang';
                }

                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $sourceOrg->id,
                    'cost_center_code' => $costCenter,
                    'chart_of_account_id' => $coa->id,
                    'account_code' => $coa->account_code,
                    'account_name' => $coa->account_name,
                    'reference_type' => 'SWITCHING_DISPATCH',
                    'reference_number' => $refNo,
                    'description' => "Pengeluaran stok switching transfer ke {$destName} [{$summaryStr}]",
                    'debit' => 0,
                    'credit' => $entry['amount'],
                    'created_by_user_id' => $user?->id ?? $switching->transferred_by_user_id,
                    'posted_at' => now(),
                ]);
            }

            // Optional: Shipping Cost
            if ($shipment && (float) $shipment->shipping_cost > 0) {
                $shippingCost = (float) $shipment->shipping_cost;
                $shippingExpenseCoa = ChartOfAccount::where('account_code', '51205')->first();
                $shippingPayableCoa = ChartOfAccount::where('account_code', '21102')->first() ?: $rakCoa;

                if ($shippingExpenseCoa && $shippingPayableCoa) {
                    $createdEntries[] = $this->recordEntry([
                        'journal_number' => $journalNumber,
                        'transaction_date' => $transactionDate,
                        'organization_id' => $sourceOrg->id,
                        'cost_center_code' => $costCenter,
                        'chart_of_account_id' => $shippingExpenseCoa->id,
                        'account_code' => $shippingExpenseCoa->account_code,
                        'account_name' => $shippingExpenseCoa->account_name,
                        'reference_type' => 'SWITCHING_DISPATCH',
                        'reference_number' => $refNo,
                        'description' => "Beban ekspedisi switching stock ke {$destName} (Resi: {$shipment->tracking_number})",
                        'debit' => $shippingCost,
                        'credit' => 0,
                        'created_by_user_id' => $user?->id ?? $switching->transferred_by_user_id,
                        'posted_at' => now(),
                    ]);

                    $createdEntries[] = $this->recordEntry([
                        'journal_number' => $journalNumber,
                        'transaction_date' => $transactionDate,
                        'organization_id' => $sourceOrg->id,
                        'cost_center_code' => $costCenter,
                        'chart_of_account_id' => $shippingPayableCoa->id,
                        'account_code' => $shippingPayableCoa->account_code,
                        'account_name' => $shippingPayableCoa->account_name,
                        'reference_type' => 'SWITCHING_DISPATCH',
                        'reference_number' => $refNo,
                        'description' => "Hutang biaya kurir ekspedisi switching stock (Resi: {$shipment->tracking_number})",
                        'debit' => 0,
                        'credit' => $shippingCost,
                        'created_by_user_id' => $user?->id ?? $switching->transferred_by_user_id,
                        'posted_at' => now(),
                    ]);
                }
            }

            return $createdEntries;
        });
    }

    /**
     * Automatically record balanced General Ledger journal entries for Switching Stock receipt (transfer in)
     */
    public function recordSwitchingReceiptJournal(
        SwitchingStock $switching,
        ?Receiving $receiving = null,
        ?User $user = null,
        array $receiptItems = []
    ): array {
        return DB::transaction(function () use ($switching, $receiving, $user, $receiptItems) {
            if (ChartOfAccount::count() === 0) {
                return [];
            }

            $switching->loadMissing([
                'items.item.category',
                'destinationWarehouse.organization',
                'sourceWarehouse.organization',
                'destinationOrganization',
                'sourceOrganization',
                'item.category',
                'shipment.receivings.discrepancies',
            ]);

            $destOrg = $switching->destinationOrganization ?: $switching->destinationWarehouse?->organization;
            $sourceOrg = $switching->sourceOrganization ?: $switching->sourceWarehouse?->organization;

            if (! $destOrg) {
                return [];
            }

            $costCenter = $destOrg->cost_center_code ?: 'CC-BRANCH';

            // RAK CoA (31101)
            $rakCoa = ChartOfAccount::where('account_code', '31101')->first()
                ?: (ChartOfAccount::where('account_type', 'EQUITY')->first() ?: ChartOfAccount::first());

            // Loss CoA (51206)
            $lossCoa = ChartOfAccount::where('account_code', '51206')->first();

            $refNo = $receiving?->receiving_number ?: ('SW-RCV-'.str_pad((string) $switching->id, 5, '0', STR_PAD_LEFT));
            $transactionDate = $receiving?->receipt_date
                ? $receiving->receipt_date->toDateString()
                : ($switching->received_at ? $switching->received_at->toDateString() : now()->toDateString());

            // Prevent duplicate GL entries
            $alreadyExists = GeneralLedgerEntry::where('reference_type', 'SWITCHING_RECEIPT')
                ->where('reference_number', $refNo)
                ->exists();

            if ($alreadyExists) {
                return [];
            }

            $journalNumber = $this->generateJournalNumber();
            $createdEntries = [];
            $totalAcceptedValuation = 0.0;
            $totalDamagedValuation = 0.0;
            $coaDebits = [];
            $lossItemsSummary = [];

            // Determine received items and quantities
            $itemsToProcess = [];
            if (! empty($receiptItems)) {
                $itemsToProcess = $receiptItems;
            } elseif ($receiving && $receiving->discrepancies->isNotEmpty()) {
                $discrepancies = $receiving->discrepancies->keyBy('switching_stock_item_id');
                if ($switching->items->isNotEmpty()) {
                    foreach ($switching->items as $swItem) {
                        $disc = $discrepancies->get($swItem->id);
                        $qtyGood = $disc ? (int) $disc->qty_actual : (int) $swItem->qty_requested;
                        $qtyDamaged = $disc && $disc->discrepancy_type === 'DAMAGED' ? (int) $disc->qty_damaged : 0;
                        $itemsToProcess[] = [
                            'item' => $swItem->item,
                            'qty_good' => $qtyGood,
                            'qty_damaged' => $qtyDamaged,
                        ];
                    }
                } elseif ($switching->item) {
                    $disc = $discrepancies->first();
                    $qtyGood = $disc ? (int) $disc->qty_actual : (int) $switching->qty_requested;
                    $qtyDamaged = $disc && $disc->discrepancy_type === 'DAMAGED' ? (int) $disc->qty_damaged : 0;
                    $itemsToProcess[] = [
                        'item' => $switching->item,
                        'qty_good' => $qtyGood,
                        'qty_damaged' => $qtyDamaged,
                    ];
                }
            } else {
                if ($switching->items->isNotEmpty()) {
                    foreach ($switching->items as $swItem) {
                        $itemsToProcess[] = [
                            'item' => $swItem->item,
                            'qty_good' => (int) $swItem->qty_requested,
                            'qty_damaged' => 0,
                        ];
                    }
                } elseif ($switching->item && $switching->qty_requested) {
                    $itemsToProcess[] = [
                        'item' => $switching->item,
                        'qty_good' => (int) $switching->qty_requested,
                        'qty_damaged' => 0,
                    ];
                }
            }

            foreach ($itemsToProcess as $p) {
                $item = $p['item'];
                $qtyGood = (int) ($p['qty_good'] ?? 0);
                $qtyDamaged = (int) ($p['qty_damaged'] ?? 0);

                if (! $item) {
                    continue;
                }

                $unitCost = (float) $item->estimated_unit_price;

                if ($qtyGood > 0) {
                    $lineTotal = $qtyGood * $unitCost;
                    $coa = $this->resolveInventoryCoaForItem($item);
                    $coaId = $coa->id;

                    if (! isset($coaDebits[$coaId])) {
                        $coaDebits[$coaId] = [
                            'coa' => $coa,
                            'amount' => 0.0,
                            'items_summary' => [],
                        ];
                    }

                    $coaDebits[$coaId]['amount'] += $lineTotal;
                    $coaDebits[$coaId]['items_summary'][] = "{$item->sku} ({$qtyGood} {$item->uom})";
                    $totalAcceptedValuation += $lineTotal;
                }

                if ($qtyDamaged > 0) {
                    $damagedTotal = $qtyDamaged * $unitCost;
                    $totalDamagedValuation += $damagedTotal;
                    $lossItemsSummary[] = "{$item->sku} rusak ({$qtyDamaged} {$item->uom})";
                }
            }

            $totalCreditValuation = $totalAcceptedValuation + $totalDamagedValuation;
            if ($totalCreditValuation <= 0) {
                return [];
            }

            $sourceName = $sourceOrg?->name ?? 'Cabang Pengirim';

            // Entry 1+: Debit Rekening Persediaan Cabang Penerima
            foreach ($coaDebits as $entry) {
                $coa = $entry['coa'];
                $summaryStr = implode(', ', array_slice($entry['items_summary'], 0, 3));
                if (count($entry['items_summary']) > 3) {
                    $summaryStr .= ' +'.(count($entry['items_summary']) - 3).' barang';
                }

                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $destOrg->id,
                    'cost_center_code' => $costCenter,
                    'chart_of_account_id' => $coa->id,
                    'account_code' => $coa->account_code,
                    'account_name' => $coa->account_name,
                    'reference_type' => 'SWITCHING_RECEIPT',
                    'reference_number' => $refNo,
                    'description' => "Penerimaan fisik switching persediaan dari {$sourceName} [{$summaryStr}]",
                    'debit' => $entry['amount'],
                    'credit' => 0,
                    'created_by_user_id' => $user?->id ?? $switching->received_by_user_id,
                    'posted_at' => now(),
                ]);
            }

            // Entry 2: Debit Beban Kerusakan Persediaan (jika ada barang rusak)
            if ($totalDamagedValuation > 0 && $lossCoa) {
                $lossSummaryStr = implode(', ', array_slice($lossItemsSummary, 0, 3));
                $createdEntries[] = $this->recordEntry([
                    'journal_number' => $journalNumber,
                    'transaction_date' => $transactionDate,
                    'organization_id' => $destOrg->id,
                    'cost_center_code' => $costCenter,
                    'chart_of_account_id' => $lossCoa->id,
                    'account_code' => $lossCoa->account_code,
                    'account_name' => $lossCoa->account_name,
                    'reference_type' => 'SWITCHING_RECEIPT',
                    'reference_number' => $refNo,
                    'description' => "Selisih/kerusakan barang transit switching stok dari {$sourceName} [{$lossSummaryStr}]",
                    'debit' => $totalDamagedValuation,
                    'credit' => 0,
                    'created_by_user_id' => $user?->id ?? $switching->received_by_user_id,
                    'posted_at' => now(),
                ]);
            }

            // Entry 3: Kredit RAK Logistik Pusat / Antar-Unit
            $createdEntries[] = $this->recordEntry([
                'journal_number' => $journalNumber,
                'transaction_date' => $transactionDate,
                'organization_id' => $destOrg->id,
                'cost_center_code' => $costCenter,
                'chart_of_account_id' => $rakCoa->id,
                'account_code' => $rakCoa->account_code,
                'account_name' => $rakCoa->account_name,
                'reference_type' => 'SWITCHING_RECEIPT',
                'reference_number' => $refNo,
                'description' => "Penerimaan stok transfer switching via RAK dari {$sourceName} (Ref: {$refNo})",
                'debit' => 0,
                'credit' => $totalCreditValuation,
                'created_by_user_id' => $user?->id ?? $switching->received_by_user_id,
                'posted_at' => now(),
            ]);

            return $createdEntries;
        });
    }

    /**
     * Retrieve formatted General Ledger report data with opening balances and running balances
     */
    public function getLedgerReport(array $filters, ?User $currentUser = null): array
    {
        $orgId = $filters['organization_id'] ?? 'ALL';
        $coaId = $filters['chart_of_account_id'] ?? 'ALL';
        $startDate = $filters['start_date'] ?? date('Y-01-01');
        $endDate = $filters['end_date'] ?? date('Y-12-31');
        $search = $filters['search'] ?? null;

        // Isolate branch user
        if ($currentUser && $currentUser->isBranchUser() && $currentUser->organization_id) {
            $orgId = (string) $currentUser->organization_id;
        }

        // 1. Fetch relevant Chart of Accounts
        $coaQuery = ChartOfAccount::query()->where('is_active', true)->orderBy('account_code');
        if ($coaId && $coaId !== 'ALL') {
            $coaQuery->where('id', $coaId);
        }
        $allCoas = $coaQuery->get();

        $ledgerData = [];
        $totalDebitGlobal = 0.0;
        $totalCreditGlobal = 0.0;
        $totalEntriesCount = 0;

        foreach ($allCoas as $coa) {
            // Calculate Opening Balance prior to start_date
            $openingQuery = GeneralLedgerEntry::query()
                ->where('chart_of_account_id', $coa->id)
                ->where('transaction_date', '<', $startDate);

            if ($orgId && $orgId !== 'ALL') {
                $openingQuery->where('organization_id', $orgId);
            }

            $priorDebit = (float) (clone $openingQuery)->sum('debit');
            $priorCredit = (float) (clone $openingQuery)->sum('credit');

            $isDebitNormal = strtoupper($coa->normal_balance) === 'DEBIT';
            $openingBalance = $isDebitNormal ? ($priorDebit - $priorCredit) : ($priorCredit - $priorDebit);

            // Fetch transactions within [startDate, endDate]
            $entriesQuery = GeneralLedgerEntry::with(['organization', 'user'])
                ->where('chart_of_account_id', $coa->id)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->orderBy('transaction_date')
                ->orderBy('id');

            if ($orgId && $orgId !== 'ALL') {
                $entriesQuery->where('organization_id', $orgId);
            }

            if ($search) {
                $entriesQuery->where(function ($q) use ($search) {
                    $q->where('journal_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('cost_center_code', 'like', "%{$search}%")
                        ->orWhereHas('organization', fn ($oq) => $oq->where('name', 'like', "%{$search}%"));
                });
            }

            $entries = $entriesQuery->get();

            // Calculate running balance for each entry
            $runningBalance = $openingBalance;
            $coaDebitTotal = 0.0;
            $coaCreditTotal = 0.0;

            $processedEntries = [];
            foreach ($entries as $entry) {
                $debitVal = (float) $entry->debit;
                $creditVal = (float) $entry->credit;

                if ($isDebitNormal) {
                    $runningBalance = $runningBalance + $debitVal - $creditVal;
                } else {
                    $runningBalance = $runningBalance + $creditVal - $debitVal;
                }

                $coaDebitTotal += $debitVal;
                $coaCreditTotal += $creditVal;

                $processedEntries[] = [
                    'id' => $entry->id,
                    'journal_number' => $entry->journal_number,
                    'transaction_date' => $entry->transaction_date->format('d/m/Y'),
                    'organization_name' => $entry->organization ? $entry->organization->name : 'Global / Head Office',
                    'organization_code' => $entry->organization ? $entry->organization->code : 'KP',
                    'cost_center_code' => $entry->cost_center_code ?: '-',
                    'reference_type' => $entry->reference_type ?: '-',
                    'reference_number' => $entry->reference_number ?: '-',
                    'description' => $entry->description,
                    'debit' => $debitVal,
                    'credit' => $creditVal,
                    'running_balance' => $runningBalance,
                    'created_by' => $entry->user ? $entry->user->name : 'Sistem',
                ];
            }

            $endingBalance = $runningBalance;

            // Only display accounts that have opening balance != 0 or have transactions, OR if user explicitly filtered by this single CoA
            if (count($processedEntries) > 0 || abs($openingBalance) > 0.001 || ($coaId && $coaId !== 'ALL')) {
                $ledgerData[] = [
                    'account' => $coa,
                    'opening_balance' => $openingBalance,
                    'debit_total' => $coaDebitTotal,
                    'credit_total' => $coaCreditTotal,
                    'ending_balance' => $endingBalance,
                    'entries' => $processedEntries,
                ];

                $totalDebitGlobal += $coaDebitTotal;
                $totalCreditGlobal += $coaCreditTotal;
                $totalEntriesCount += count($processedEntries);
            }
        }

        $organizations = Organization::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'type']);
        $allCoaList = ChartOfAccount::where('is_active', true)->orderBy('account_code')->get(['id', 'account_code', 'account_name', 'account_type']);

        $isBalanced = abs($totalDebitGlobal - $totalCreditGlobal) < 0.01;

        return [
            'ledgerData' => $ledgerData,
            'organizations' => $organizations,
            'allCoas' => $allCoaList,
            'totalDebitGlobal' => $totalDebitGlobal,
            'totalCreditGlobal' => $totalCreditGlobal,
            'totalEntriesCount' => $totalEntriesCount,
            'activeAccountsCount' => count($ledgerData),
            'isBalanced' => $isBalanced,
            'filters' => [
                'organization_id' => $orgId,
                'chart_of_account_id' => $coaId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
            ],
            'selectedOrganization' => ($orgId && $orgId !== 'ALL') ? Organization::find($orgId) : null,
            'selectedCoa' => ($coaId && $coaId !== 'ALL') ? ChartOfAccount::find($coaId) : null,
        ];
    }
}
