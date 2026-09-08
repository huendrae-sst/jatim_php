<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerEntry;
use App\Models\Item;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GeneralLedgerReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_super_admin_can_view_general_ledger_report(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('reports.general_ledger'));

        $response->assertStatus(200);
        $response->assertSee('Buku Besar (General Ledger)');
        $response->assertSee('Total Mutasi Debit');
        $response->assertSee('Total Mutasi Kredit');
        $response->assertSee('Balanced (Seimbang)');
    }

    public function test_general_ledger_filter_by_branch_organization(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();
        $kcSurabaya = Organization::where('code', 'KC-SBY')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('reports.general_ledger', [
            'organization_id' => $kcSurabaya->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('KC Surabaya');
    }

    public function test_general_ledger_filter_by_specific_coa(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();
        $atkExpenseCoa = ChartOfAccount::where('account_code', '51201')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('reports.general_ledger', [
            'chart_of_account_id' => $atkExpenseCoa->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('51201');
        $response->assertSee('Beban Pemakaian ATK & Kertas Cabang');
    }

    public function test_export_general_ledger_csv(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('reports.general_ledger.csv'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertTrue(str_contains($response->headers->get('Content-Disposition'), 'attachment; filename="buku_besar_'));
    }

    public function test_branch_user_scoped_to_own_organization(): void
    {
        $branchUser = User::where('role', 'REQUESTER_CABANG')->whereNotNull('organization_id')->firstOrFail();

        $response = $this->actingAs($branchUser)->get(route('reports.general_ledger'));

        $response->assertStatus(200);
        $response->assertSee($branchUser->organization->name);
    }

    public function test_settlement_approval_automatically_records_double_entry_in_general_ledger(): void
    {
        $approver = User::where('role', 'FINANCE_APPROVER')->firstOrFail();
        $order = Order::where('status', 'RECEIVED')->first() ?: Order::firstOrFail();

        // Create draft settlement
        $settlementService = app(SettlementService::class);
        $settlement = $settlementService->createSettlementForOrder($order, $approver);

        $initialLedgerCount = GeneralLedgerEntry::count();

        // Approve and post settlement
        $settlementService->approveAndPostSettlement($settlement, $approver);

        $this->assertEquals('POSTED', $settlement->fresh()->status);
        $this->assertGreaterThan($initialLedgerCount, GeneralLedgerEntry::count());

        // Verify debit and credit are balanced for this settlement
        $settlementEntries = GeneralLedgerEntry::where('reference_number', $settlement->settlement_number)->get();
        $this->assertNotEmpty($settlementEntries);

        $totalDebit = (float) $settlementEntries->sum('debit');
        $totalCredit = (float) $settlementEntries->sum('credit');

        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals((float) $settlement->total_amount, $totalDebit);
    }

    public function test_initial_stock_posting_automatically_records_general_ledger_entries(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();
        $wh = Warehouse::where('is_active', true)->firstOrFail();
        $item = Item::where('is_active', true)->firstOrFail();

        $initialGlCount = GeneralLedgerEntry::count();

        $payload = [
            'warehouse_id' => $wh->id,
            'cutoff_date' => '2026-01-01',
            'notes' => 'Test posting saldo awal otomatis ke GL',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty_good' => 10,
                    'qty_damaged' => 0,
                    'unit_cost' => (float) $item->estimated_unit_price,
                ],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('inventory.initial_stock.store'), $payload);
        $response->assertRedirect();

        $this->assertGreaterThan($initialGlCount, GeneralLedgerEntry::count());

        $latestGlDebit = GeneralLedgerEntry::where('reference_type', 'INITIAL_BALANCE')
            ->where('organization_id', $wh->organization_id)
            ->where('debit', '>', 0)
            ->latest('id')
            ->first();

        $this->assertNotNull($latestGlDebit);
        $this->assertGreaterThan(0, $latestGlDebit->debit);

        // Also assert that the journal number has balanced debits and credits
        $batchEntries = GeneralLedgerEntry::where('journal_number', $latestGlDebit->journal_number)->get();
        $this->assertEquals((float) $batchEntries->sum('debit'), (float) $batchEntries->sum('credit'));
    }

    public function test_sync_initial_stock_command_generates_gl_entries(): void
    {
        $exitCode = Artisan::call('jims:sync-initial-stock-gl', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        // Verify that entries with reference_type INITIAL_BALANCE exist and are balanced
        $entries = GeneralLedgerEntry::where('reference_type', 'INITIAL_BALANCE')->get();
        $this->assertNotEmpty($entries);

        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
    }

    public function test_goods_receipt_posting_automatically_records_general_ledger_entries(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();
        $po = PurchaseOrder::with(['items.item', 'vendor'])->whereIn('status', ['ISSUED', 'VENDOR_PROCESS', 'IN_DELIVERY', 'PARTIAL_RECEIVED'])->first();

        if (! $po) {
            // Create a test PO if none matches status
            $vendor = Vendor::first();
            $wh = Warehouse::first();
            $item = Item::first();
            $po = PurchaseOrder::create([
                'po_number' => 'PO/2026/TEST/0001',
                'vendor_id' => $vendor->id,
                'warehouse_id' => $wh->id,
                'created_by_user_id' => $admin->id,
                'order_date' => now()->toDateString(),
                'status' => 'ISSUED',
                'subtotal' => 100000,
                'total_amount' => 100000,
            ]);
            $poItem = PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'item_id' => $item->id,
                'qty_ordered' => 10,
                'qty_received' => 0,
                'unit_price' => 10000,
                'subtotal' => 100000,
            ]);
        } else {
            $poItem = $po->items->first(fn ($i) => $i->outstanding_qty > 0) ?: $po->items->first();
            if ($poItem->outstanding_qty <= 0) {
                $poItem->qty_received = 0;
                $poItem->save();
            }
        }

        $initialGlCount = GeneralLedgerEntry::where('reference_type', 'GOODS_RECEIPT')->count();

        $response = $this->actingAs($admin)->post(route('receiving.po.receive', $po->id), [
            'vendor_delivery_note_number' => 'SJ-TEST-GL-001',
            'items' => [
                [
                    'po_item_id' => $poItem->id,
                    'qty_accepted' => 2,
                    'qty_rejected' => 0,
                    'notes' => 'Barang diterima lengkap lolos QC',
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertGreaterThan($initialGlCount, GeneralLedgerEntry::where('reference_type', 'GOODS_RECEIPT')->count());

        $latestGrnEntry = GeneralLedgerEntry::where('reference_type', 'GOODS_RECEIPT')
            ->latest('id')
            ->firstOrFail();

        $this->assertNotNull($latestGrnEntry);

        // Verify that the journal has balanced debits and credits
        $journalEntries = GeneralLedgerEntry::where('journal_number', $latestGrnEntry->journal_number)->get();
        $this->assertEquals((float) $journalEntries->sum('debit'), (float) $journalEntries->sum('credit'));
        $this->assertGreaterThan(0, (float) $journalEntries->sum('debit'));
    }

    public function test_sync_goods_receipt_command_generates_gl_entries(): void
    {
        $exitCode = Artisan::call('jims:sync-goods-receipt-gl', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        $entries = GeneralLedgerEntry::where('reference_type', 'GOODS_RECEIPT')->get();
        $this->assertNotEmpty($entries);

        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertGreaterThan(0, $totalDebit);
    }
}
