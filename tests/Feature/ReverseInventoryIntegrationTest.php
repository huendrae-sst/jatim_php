<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Courier;
use App\Models\InventoryReturn;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockDestruction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseInventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $centralOrg;

    protected Organization $branchOrg;

    protected Warehouse $centralWarehouse;

    protected Warehouse $branchWarehouse;

    protected Item $item;

    protected Courier $courier;

    protected User $branchUser;

    protected User $approverUser;

    protected User $centralWarehouseUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralOrg = Organization::create([
            'code' => 'KP-SBY',
            'name' => 'Kantor Pusat Bank Jatim Surabaya',
            'type' => 'HEAD_OFFICE',
            'cost_center_code' => 'CC-KP-001',
            'is_active' => true,
        ]);

        $this->branchOrg = Organization::create([
            'code' => 'KC-MLG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'MAIN_BRANCH',
            'cost_center_code' => 'CC-KC-002',
            'is_active' => true,
        ]);

        $this->centralWarehouse = Warehouse::create([
            'code' => 'GD-RKT',
            'name' => 'Gudang Rungkut Logistik Pusat',
            'organization_id' => $this->centralOrg->id,
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $this->branchWarehouse = Warehouse::create([
            'code' => 'GD-MLG',
            'name' => 'Gudang Persediaan KC Malang',
            'organization_id' => $this->branchOrg->id,
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'CAT-ATM',
            'name' => 'Kartu ATM & Debit',
            'description' => 'Kartu Instan & Chip',
        ]);

        $this->item = Item::create([
            'category_id' => $category->id,
            'sku' => 'ATM-INST-GPN',
            'barcode' => '8991001099',
            'name' => 'Kartu ATM Instan GPN Jatim',
            'uom' => 'PCS',
            'min_stock' => 50,
            'estimated_unit_price' => 15000,
            'is_active' => true,
        ]);

        $this->courier = Courier::create([
            'code' => 'JNE',
            'name' => 'JNE Express Logistik',
            'is_active' => true,
        ]);

        $this->branchUser = User::create([
            'name' => 'Staff Logistik Cabang Malang',
            'email' => 'branch_malang@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'REQUESTER_CABANG',
            'organization_id' => $this->branchOrg->id,
            'is_active' => true,
        ]);

        $this->approverUser = User::create([
            'name' => 'Manager Logistik Approver',
            'email' => 'approver_logistik@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'ORDER_APPROVER',
            'organization_id' => $this->centralOrg->id,
            'is_active' => true,
        ]);

        $this->centralWarehouseUser = User::create([
            'name' => 'Petugas Gudang Rungkut',
            'email' => 'warehouse_rungkut@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'WAREHOUSE_OFFICER',
            'organization_id' => $this->centralOrg->id,
            'is_active' => true,
        ]);

        // Seed initial balances
        StockBalance::create([
            'warehouse_id' => $this->branchWarehouse->id,
            'item_id' => $this->item->id,
            'on_hand' => 100,
            'allocated' => 0,
            'available' => 100,
            'damaged' => 20,
            'min_stock' => 10,
            'max_stock' => 200,
        ]);

        StockBalance::create([
            'warehouse_id' => $this->centralWarehouse->id,
            'item_id' => $this->item->id,
            'on_hand' => 500,
            'allocated' => 0,
            'available' => 500,
            'damaged' => 5,
            'min_stock' => 100,
            'max_stock' => 1000,
        ]);
    }

    public function test_can_create_return_request_from_branch(): void
    {
        $response = $this->actingAs($this->branchUser)->post(route('returns.store'), [
            'origin_warehouse_id' => $this->branchWarehouse->id,
            'destination_warehouse_id' => $this->centralWarehouse->id,
            'reason' => 'DAMAGED_ON_ARRIVAL',
            'reason_details' => 'Kartu ATM cacat cetak chip tidak terbaca mesin EDC',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty_returned' => 15,
                    'condition' => 'DAMAGED',
                    'notes' => 'Chip unreadable',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_returns', [
            'origin_warehouse_id' => $this->branchWarehouse->id,
            'destination_warehouse_id' => $this->centralWarehouse->id,
            'status' => 'REQUESTED',
            'reason' => 'DAMAGED_ON_ARRIVAL',
        ]);

        $return = InventoryReturn::latest()->first();
        $this->assertNotNull($return);
        $this->assertStringStartsWith('RET/', $return->return_number);
        $this->assertCount(1, $return->items);
        $this->assertEquals(15, $return->items->first()->qty_returned);
    }

    public function test_full_return_workflow_lifecycle(): void
    {
        // 1. Create Return Request
        $this->actingAs($this->branchUser)->post(route('returns.store'), [
            'origin_warehouse_id' => $this->branchWarehouse->id,
            'destination_warehouse_id' => $this->centralWarehouse->id,
            'reason' => 'EXCESS_STOCK',
            'reason_details' => 'Barang berlebih di cabang (Overstock) kondisi baik',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty_returned' => 25,
                    'condition' => 'GOOD',
                    'notes' => 'Kemasan utuh',
                ],
            ],
        ]);

        $return = InventoryReturn::latest()->first();
        $this->assertNotNull($return);
        $this->assertEquals('REQUESTED', $return->status);

        // 2. Approver Approves Return
        $approveResponse = $this->actingAs($this->approverUser)->post(route('returns.approve', $return), [
            'notes' => 'Disetujui untuk dikirim ke GD-RKT via JNE',
        ]);
        $approveResponse->assertRedirect();
        $return->refresh();
        $this->assertEquals('APPROVED', $return->status);
        $this->assertEquals($this->approverUser->id, $return->approved_by_user_id);

        // 3. Branch Dispatches / Ships Return
        $branchBalanceBefore = StockBalance::where('warehouse_id', $this->branchWarehouse->id)
            ->where('item_id', $this->item->id)
            ->first();
        $this->assertEquals(100, $branchBalanceBefore->on_hand);

        $shipResponse = $this->actingAs($this->branchUser)->post(route('returns.ship', $return), [
            'courier_name' => 'JNE Reguler',
            'tracking_number' => 'JNE-RET-88992211',
        ]);
        $shipResponse->assertRedirect();
        $return->refresh();
        $this->assertEquals('SHIPPED', $return->status);
        $this->assertEquals('JNE-RET-88992211', $return->tracking_number);

        // Check branch stock deduction & ledger
        $branchBalanceAfter = StockBalance::where('warehouse_id', $this->branchWarehouse->id)
            ->where('item_id', $this->item->id)
            ->first();
        $this->assertEquals(75, $branchBalanceAfter->on_hand); // 100 - 25

        $this->assertDatabaseHas('stock_ledgers', [
            'warehouse_id' => $this->branchWarehouse->id,
            'item_id' => $this->item->id,
            'transaction_type' => 'RETURN_OUT',
            'reference_number' => $return->return_number,
            'qty_out' => 25,
        ]);

        // 4. Central Warehouse Receives & Verifies Stock
        $centralBalanceBefore = StockBalance::where('warehouse_id', $this->centralWarehouse->id)
            ->where('item_id', $this->item->id)
            ->first();
        $this->assertEquals(500, $centralBalanceBefore->on_hand);

        $firstReturnItem = $return->items->first();
        $receiveResponse = $this->actingAs($this->centralWarehouseUser)->post(route('returns.receive', $return), [
            'items' => [
                [
                    'return_item_id' => $firstReturnItem->id,
                    'qty_good' => 25,
                    'qty_damaged' => 0,
                ],
            ],
        ]);
        $receiveResponse->assertRedirect();
        $return->refresh();
        $this->assertEquals('RECEIVED', $return->status);
        $this->assertNotNull($return->received_at);

        // Check central stock addition & ledger
        $centralBalanceAfter = StockBalance::where('warehouse_id', $this->centralWarehouse->id)
            ->where('item_id', $this->item->id)
            ->first();
        $this->assertEquals(525, $centralBalanceAfter->on_hand); // 500 + 25

        $this->assertDatabaseHas('stock_ledgers', [
            'warehouse_id' => $this->centralWarehouse->id,
            'item_id' => $this->item->id,
            'transaction_type' => 'RETURN_IN',
            'reference_number' => $return->return_number,
            'qty_in' => 25,
        ]);
    }

    public function test_full_destruction_workflow_and_berita_acara_generation(): void
    {
        // 1. Create Stock Destruction Request
        $destructResponse = $this->actingAs($this->centralWarehouseUser)->post(route('destructions.store'), [
            'warehouse_id' => $this->centralWarehouse->id,
            'reason' => 'DAMAGED_UNUSABLE',
            'reason_details' => 'Kartu ATM kadaluarsa chip versi lama yang tidak compliant dengan regulasi BI',
            'witness_name_1' => 'Ahmad Santoso',
            'witness_title_1' => 'Kepala Gudang SIER',
            'witness_name_2' => 'Siti Rahmawati',
            'witness_title_2' => 'Staff SKAI Audit Internal',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 5,
                    'condition_notes' => 'Kadaluarsa dan rusak magnetik',
                ],
            ],
        ]);

        $destructResponse->assertRedirect();
        $destruction = StockDestruction::latest()->first();
        $this->assertNotNull($destruction);
        $this->assertStringStartsWith('DST/', $destruction->destruction_number);
        $this->assertStringStartsWith('BA-DST/', $destruction->berita_acara_number);
        $this->assertEquals('REQUESTED', $destruction->status);
        $this->assertEquals('Ahmad Santoso', $destruction->witness_name_1);
        $this->assertEquals('Siti Rahmawati', $destruction->witness_name_2);

        // 2. Approver Approves Destruction
        $approveResponse = $this->actingAs($this->approverUser)->post(route('destructions.approve', $destruction), [
            'notes' => 'Disetujui pemusnahan resmi sesuai SOP bank',
        ]);
        $approveResponse->assertRedirect();
        $destruction->refresh();
        $this->assertEquals('APPROVED', $destruction->status);
        $this->assertEquals($this->approverUser->id, $destruction->approved_by_user_id);

        // 3. Execute Destruction
        $balanceBefore = StockBalance::where('warehouse_id', $this->centralWarehouse->id)
            ->where('item_id', $this->item->id)
            ->first();
        $this->assertEquals(5, $balanceBefore->damaged);

        $executeResponse = $this->actingAs($this->centralWarehouseUser)->post(route('destructions.execute', $destruction), [
            'execution_notes' => 'Pemusnahan selesai disaksikan oleh kedua saksi, kartu dicacah menjadi serbuk',
        ]);
        $executeResponse->assertRedirect();
        $destruction->refresh();
        $this->assertEquals('EXECUTED', $destruction->status);
        $this->assertNotNull($destruction->executed_at);

        // Damaged stock was 5, 5 was destroyed, damaged becomes 0
        $balanceAfter = StockBalance::where('warehouse_id', $this->centralWarehouse->id)
            ->where('item_id', $this->item->id)
            ->first();
        $this->assertEquals(0, $balanceAfter->damaged);

        // Verify ledger entry DESTROYED
        $this->assertDatabaseHas('stock_ledgers', [
            'warehouse_id' => $this->centralWarehouse->id,
            'item_id' => $this->item->id,
            'transaction_type' => 'DESTROYED',
            'reference_number' => $destruction->berita_acara_number,
            'qty_out' => 5,
        ]);

        // 4. View Berita Acara Pemusnahan (BA-DST)
        $baResponse = $this->actingAs($this->centralWarehouseUser)->get(route('destructions.berita_acara', $destruction));
        $baResponse->assertStatus(200);
        $baResponse->assertSee($destruction->berita_acara_number);
        $baResponse->assertSee('BERITA ACARA PEMUSNAHAN BARANG');
        $baResponse->assertSee('Ahmad Santoso');
        $baResponse->assertSee('Siti Rahmawati');
    }
}
