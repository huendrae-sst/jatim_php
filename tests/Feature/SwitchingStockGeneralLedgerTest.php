<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Courier;
use App\Models\GeneralLedgerEntry;
use App\Models\Item;
use App\Models\Organization;
use App\Models\Receiving;
use App\Models\StockBalance;
use App\Models\SwitchingStock;
use App\Models\SwitchingStockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchingStockGeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Organization $orgSource;

    protected Organization $orgDest;

    protected Warehouse $whSource;

    protected Warehouse $whDest;

    protected Item $item;

    protected Courier $courier;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Chart of Accounts
        ChartOfAccount::create([
            'account_code' => '11301',
            'account_name' => 'Persediaan Alat Tulis Kantor (ATK)',
            'account_type' => 'ASSET',
        ]);
        ChartOfAccount::create([
            'account_code' => '21102',
            'account_name' => 'Hutang Biaya Ekspedisi & Distribusi',
            'account_type' => 'LIABILITY',
        ]);
        ChartOfAccount::create([
            'account_code' => '31101',
            'account_name' => 'RAK Logistik Pusat',
            'account_type' => 'EQUITY',
        ]);
        ChartOfAccount::create([
            'account_code' => '51205',
            'account_name' => 'Beban Jasa Ekspedisi & Pengiriman Logistik',
            'account_type' => 'EXPENSE',
        ]);
        ChartOfAccount::create([
            'account_code' => '51206',
            'account_name' => 'Beban Kerusakan & Selisih Persediaan (Loss)',
            'account_type' => 'EXPENSE',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $this->orgSource = Organization::create([
            'code' => 'KC-GUBENG',
            'name' => 'Kantor Cabang Gubeng',
            'type' => 'MAIN_BRANCH',
            'city' => 'Surabaya',
            'cost_center_code' => 'CC-GUBENG',
            'is_active' => true,
        ]);

        $this->orgDest = Organization::create([
            'code' => 'KC-MALANG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'MAIN_BRANCH',
            'city' => 'Malang',
            'cost_center_code' => 'CC-MALANG',
            'is_active' => true,
        ]);

        $this->whSource = Warehouse::create([
            'organization_id' => $this->orgSource->id,
            'code' => 'WH-GUBENG',
            'name' => 'Gudang Gubeng',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $this->whDest = Warehouse::create([
            'organization_id' => $this->orgDest->id,
            'code' => 'WH-MALANG',
            'name' => 'Gudang Malang',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $cat = Category::create([
            'code' => 'CAT-ATK',
            'name' => 'Alat Tulis Kantor',
        ]);

        $this->item = Item::create([
            'sku' => 'ATK-KTS-A4',
            'name' => 'Kertas HVS A4 80gr',
            'category_id' => $cat->id,
            'uom' => 'RIM',
            'min_stock' => 10,
            'max_stock' => 100,
            'safety_stock' => 5,
            'estimated_unit_price' => 50000,
            'is_active' => true,
        ]);

        StockBalance::create([
            'warehouse_id' => $this->whSource->id,
            'item_id' => $this->item->id,
            'on_hand' => 50,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
            'available' => 50,
        ]);

        StockBalance::create([
            'warehouse_id' => $this->whDest->id,
            'item_id' => $this->item->id,
            'on_hand' => 10,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
            'available' => 10,
        ]);

        $this->courier = Courier::create([
            'code' => 'EXP-JNE',
            'name' => 'JNE Express',
            'service_type' => 'REGULER',
            'is_active' => true,
        ]);
    }

    public function test_records_balanced_general_ledger_entries_upon_dispatch_and_receipt(): void
    {
        $switching = SwitchingStock::create([
            'source_organization_id' => $this->orgSource->id,
            'source_warehouse_id' => $this->whSource->id,
            'destination_organization_id' => $this->orgDest->id,
            'destination_warehouse_id' => $this->whDest->id,
            'status' => 'APPROVED',
            'qty_requested' => 10,
            'proposed_by_user_id' => $this->admin->id,
            'approved_by_user_id' => $this->admin->id,
        ]);

        $switchingItem = SwitchingStockItem::create([
            'switching_stock_id' => $switching->id,
            'item_id' => $this->item->id,
            'qty_requested' => 10,
        ]);

        $service = app(OrderFulfillmentService::class);

        // 1. Dispatch
        $shipment = $service->createSwitchingShipment(
            $switching,
            $this->courier->id,
            'REGULER',
            'RESI-GL-001',
            50000,
            now()->addDays(2)->toDateString(),
            $this->admin,
            1,
            2.5,
            'Pengiriman transfer antar cabang'
        );

        // Check GL Dispatch entries
        $dispatchGl = GeneralLedgerEntry::where('reference_type', 'SWITCHING_DISPATCH')
            ->where('reference_number', $shipment->manifest_number)
            ->get();

        $this->assertNotEmpty($dispatchGl);
        // Valuasi barang: 10 * 50.000 = 500.000 + Ongkir 50.000 = 550.000
        $totalDebitDispatch = $dispatchGl->sum('debit');
        $totalCreditDispatch = $dispatchGl->sum('credit');
        $this->assertEquals(550000, $totalDebitDispatch);
        $this->assertEquals(550000, $totalCreditDispatch);

        // 2. Receipt
        $service->processReceiving(
            $shipment,
            [
                [
                    'switching_stock_item_id' => $switchingItem->id,
                    'qty_good' => 10,
                    'qty_damaged' => 0,
                    'qty_missing' => 0,
                ],
            ],
            'Penerima Malang',
            'Barang diterima lengkap',
            $this->admin
        );

        $receiving = Receiving::where('shipment_id', $shipment->id)->first();
        $this->assertNotNull($receiving);

        $receiptGl = GeneralLedgerEntry::where('reference_type', 'SWITCHING_RECEIPT')
            ->where('reference_number', $receiving->receiving_number)
            ->get();

        $this->assertNotEmpty($receiptGl);
        $totalDebitReceipt = $receiptGl->sum('debit');
        $totalCreditReceipt = $receiptGl->sum('credit');
        $this->assertEquals(500000, $totalDebitReceipt);
        $this->assertEquals(500000, $totalCreditReceipt);

        // Persediaan Aset (11301) should be debited in Malang
        $persediaanDebit = $receiptGl->where('account_code', '11301')->first();
        $this->assertNotNull($persediaanDebit);
        $this->assertEquals(500000, $persediaanDebit->debit);
        $this->assertEquals($this->orgDest->id, $persediaanDebit->organization_id);

        // RAK (31101) should be credited in Malang
        $rakCredit = $receiptGl->where('account_code', '31101')->first();
        $this->assertNotNull($rakCredit);
        $this->assertEquals(500000, $rakCredit->credit);
    }

    public function test_records_loss_journal_when_received_items_have_damaged_quantity(): void
    {
        $switching = SwitchingStock::create([
            'source_organization_id' => $this->orgSource->id,
            'source_warehouse_id' => $this->whSource->id,
            'destination_organization_id' => $this->orgDest->id,
            'destination_warehouse_id' => $this->whDest->id,
            'status' => 'APPROVED',
            'qty_requested' => 10,
            'proposed_by_user_id' => $this->admin->id,
            'approved_by_user_id' => $this->admin->id,
        ]);

        $switchingItem = SwitchingStockItem::create([
            'switching_stock_id' => $switching->id,
            'item_id' => $this->item->id,
            'qty_requested' => 10,
        ]);

        $service = app(OrderFulfillmentService::class);

        $shipment = $service->createSwitchingShipment(
            $switching,
            $this->courier->id,
            'REGULER',
            'RESI-GL-002',
            0,
            now()->addDays(2)->toDateString(),
            $this->admin,
            1,
            2.5,
            'Pengiriman transfer'
        );

        // 8 good, 2 damaged
        $service->processReceiving(
            $shipment,
            [
                [
                    'switching_stock_item_id' => $switchingItem->id,
                    'qty_good' => 8,
                    'qty_damaged' => 2,
                    'qty_missing' => 0,
                ],
            ],
            'Penerima Malang',
            'Ada 2 rim rusak kena air',
            $this->admin
        );

        $receiving = Receiving::where('shipment_id', $shipment->id)->first();
        $receiptGl = GeneralLedgerEntry::where('reference_type', 'SWITCHING_RECEIPT')
            ->where('reference_number', $receiving->receiving_number)
            ->get();

        $this->assertNotEmpty($receiptGl);
        // Debit: Persediaan 8 * 50.000 = 400.000
        $persediaanEntry = $receiptGl->where('account_code', '11301')->first();
        $this->assertEquals(400000, $persediaanEntry->debit);

        // Debit: Beban Kerusakan 2 * 50.000 = 100.000
        $lossEntry = $receiptGl->where('account_code', '51206')->first();
        $this->assertEquals(100000, $lossEntry->debit);

        // Kredit: RAK 10 * 50.000 = 500.000
        $rakEntry = $receiptGl->where('account_code', '31101')->first();
        $this->assertEquals(500000, $rakEntry->credit);

        // Total Debit == Total Credit
        $this->assertEquals($receiptGl->sum('debit'), $receiptGl->sum('credit'));
    }

    public function test_artisan_command_syncs_switching_stock_to_general_ledger(): void
    {
        $switching = SwitchingStock::create([
            'source_organization_id' => $this->orgSource->id,
            'source_warehouse_id' => $this->whSource->id,
            'destination_organization_id' => $this->orgDest->id,
            'destination_warehouse_id' => $this->whDest->id,
            'status' => 'COMPLETED',
            'qty_requested' => 5,
            'proposed_by_user_id' => $this->admin->id,
            'approved_by_user_id' => $this->admin->id,
            'transferred_at' => now()->subDay(),
            'received_at' => now(),
        ]);

        SwitchingStockItem::create([
            'switching_stock_id' => $switching->id,
            'item_id' => $this->item->id,
            'qty_requested' => 5,
        ]);

        $this->artisan('jims:sync-switching-stock-gl')
            ->assertSuccessful();

        $dispatchRef = 'SW-TRF-'.str_pad((string) $switching->id, 5, '0', STR_PAD_LEFT);
        $receiptRef = 'SW-RCV-'.str_pad((string) $switching->id, 5, '0', STR_PAD_LEFT);

        $this->assertTrue(GeneralLedgerEntry::where('reference_type', 'SWITCHING_DISPATCH')->where('reference_number', $dispatchRef)->exists());
        $this->assertTrue(GeneralLedgerEntry::where('reference_type', 'SWITCHING_RECEIPT')->where('reference_number', $receiptRef)->exists());
    }
}
