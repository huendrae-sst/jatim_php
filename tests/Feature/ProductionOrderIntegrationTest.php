<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\ProductionOrder;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOrderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $centralOrg;

    protected Organization $branchOrg;

    protected Warehouse $warehouse;

    protected Item $cardItem;

    protected Item $tokenItem;

    protected User $productionUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centralOrg = Organization::create([
            'code' => 'KP-SBY',
            'name' => 'Kantor Pusat Bank Jatim Surabaya',
            'type' => 'HEAD_OFFICE',
            'cost_center_code' => 'CC-KP-001',
            'address' => 'Jl. Basuki Rahmat No. 98-104 Surabaya',
            'is_active' => true,
        ]);

        $this->branchOrg = Organization::create([
            'code' => 'KC-BWI',
            'name' => 'Kantor Cabang Banyuwangi',
            'type' => 'MAIN_BRANCH',
            'cost_center_code' => 'CC-KC-003',
            'address' => 'Jl. Dr. Wahidin Sudirohusodo No. 10 Banyuwangi',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'GD-RKT',
            'name' => 'Gudang Rungkut Logistik Pusat',
            'organization_id' => $this->centralOrg->id,
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $cardCategory = Category::create([
            'code' => 'CAT-ATM',
            'name' => 'Kartu ATM & Chip',
            'description' => 'Bahan Baku Kartu Personalisasi',
        ]);

        $tokenCategory = Category::create([
            'code' => 'CAT-TKN',
            'name' => 'Security Hard Token',
            'description' => 'Token Otentikasi Internet Banking',
        ]);

        $this->cardItem = Item::create([
            'category_id' => $cardCategory->id,
            'sku' => 'CARD-BLANK-GPN',
            'barcode' => '8992001001',
            'name' => 'Blank Card White Chip GPN',
            'uom' => 'PCS',
            'min_stock' => 100,
            'estimated_unit_price' => 12500,
            'is_active' => true,
        ]);

        $this->tokenItem = Item::create([
            'category_id' => $tokenCategory->id,
            'sku' => 'TKN-VASCO-DIGI',
            'barcode' => '8992002002',
            'name' => 'Hard Token Vasco Digipass',
            'uom' => 'UNIT',
            'min_stock' => 50,
            'estimated_unit_price' => 175000,
            'is_active' => true,
        ]);

        $this->productionUser = User::create([
            'name' => 'Officer Personalisasi & Cetak Kartu',
            'email' => 'perso_officer@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'WAREHOUSE_OFFICER',
            'organization_id' => $this->centralOrg->id,
            'is_active' => true,
        ]);

        // Stock in central warehouse
        StockBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->cardItem->id,
            'on_hand' => 500,
            'allocated' => 0,
            'available' => 500,
            'damaged' => 0,
            'min_stock' => 100,
            'max_stock' => 2000,
        ]);

        StockBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->tokenItem->id,
            'on_hand' => 20,
            'allocated' => 0,
            'available' => 20,
            'damaged' => 0,
            'min_stock' => 10,
            'max_stock' => 100,
        ]);
    }

    public function test_can_create_production_order_and_generate_rekap_manifest(): void
    {
        $response = $this->actingAs($this->productionUser)->post(route('production.store'), [
            'warehouse_id' => $this->warehouse->id,
            'destination_organization_id' => $this->branchOrg->id,
            'notes' => 'Bon produksi cetak kartu nasabah batch KC Banyuwangi',
            'items' => [
                [
                    'item_id' => $this->cardItem->id,
                    'qty_planned' => 200,
                    'notes' => 'Cetak chip GPN batch 1',
                ],
                [
                    'item_id' => $this->tokenItem->id,
                    'qty_planned' => 15,
                    'notes' => 'Aktivasi nasabah korporat',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_orders', [
            'warehouse_id' => $this->warehouse->id,
            'destination_organization_id' => $this->branchOrg->id,
            'status' => 'PLANNED',
        ]);

        $prodOrder = ProductionOrder::latest()->first();
        $this->assertNotNull($prodOrder);
        $this->assertStringStartsWith('PROD/', $prodOrder->production_number);
        $this->assertCount(2, $prodOrder->items);
        $this->assertEquals(215, $prodOrder->total_planned_qty);

        // Test Rekap Manifest view containing recipient branch database address
        $manifestResponse = $this->actingAs($this->productionUser)->get(route('production.manifest', $prodOrder));
        $manifestResponse->assertStatus(200);
        $manifestResponse->assertSee($prodOrder->production_number);
        $manifestResponse->assertSee('REKAP MANIFEST PRODUKSI');
        $manifestResponse->assertSee('Kantor Cabang Banyuwangi');
        $manifestResponse->assertSee('Jl. Dr. Wahidin Sudirohusodo No. 10 Banyuwangi');
        $manifestResponse->assertSee('Blank Card White Chip GPN');
        $manifestResponse->assertSee('200');
    }

    public function test_issue_production_stock_deducts_warehouse_stock_and_records_ledger(): void
    {
        // 1. Create order
        $this->actingAs($this->productionUser)->post(route('production.store'), [
            'warehouse_id' => $this->warehouse->id,
            'destination_organization_id' => $this->branchOrg->id,
            'notes' => 'Bon produksi',
            'items' => [
                [
                    'item_id' => $this->cardItem->id,
                    'qty_planned' => 100,
                ],
            ],
        ]);

        $prodOrder = ProductionOrder::latest()->first();
        $prodItem = $prodOrder->items->first();

        // 2. Issue stock for production
        $issueResponse = $this->actingAs($this->productionUser)->post(route('production.issue_stock', $prodOrder), [
            'items' => [
                [
                    'production_order_item_id' => $prodItem->id,
                    'qty_to_issue' => 100,
                ],
            ],
        ]);

        $issueResponse->assertRedirect();
        $prodOrder->refresh();
        $prodItem->refresh();

        // Status becomes COMPLETED because fully issued
        $this->assertEquals('COMPLETED', $prodOrder->status);
        $this->assertEquals(100, $prodItem->qty_issued);

        // Stock in warehouse should be deducted from 500 to 400
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->cardItem->id)
            ->first();
        $this->assertEquals(400, $balance->on_hand);
        $this->assertEquals(400, $balance->available);

        // Verify stock ledger has PRODUCTION_ISSUE
        $this->assertDatabaseHas('stock_ledgers', [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->cardItem->id,
            'transaction_type' => 'PRODUCTION_ISSUE',
            'reference_number' => $prodOrder->production_number,
            'qty_out' => 100,
            'balance_after' => 400,
        ]);
    }

    public function test_over_issue_protection_blocks_issue_exceeding_planned_qty(): void
    {
        // Create order with planned 50
        $this->actingAs($this->productionUser)->post(route('production.store'), [
            'warehouse_id' => $this->warehouse->id,
            'destination_organization_id' => $this->branchOrg->id,
            'items' => [
                [
                    'item_id' => $this->cardItem->id,
                    'qty_planned' => 50,
                ],
            ],
        ]);

        $prodOrder = ProductionOrder::latest()->first();
        $prodItem = $prodOrder->items->first();

        // Try to issue 51 (exceeds planned 50) -> should be blocked and return error in session
        $issueResponse = $this->actingAs($this->productionUser)->post(route('production.issue_stock', $prodOrder), [
            'items' => [
                [
                    'production_order_item_id' => $prodItem->id,
                    'qty_to_issue' => 51,
                ],
            ],
        ]);

        $issueResponse->assertSessionHas('error');
        $prodItem->refresh();
        $this->assertEquals(0, $prodItem->qty_issued);

        // Stock should remain unchanged
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->cardItem->id)
            ->first();
        $this->assertEquals(500, $balance->on_hand);
    }

    public function test_over_issue_protection_blocks_issue_exceeding_available_warehouse_stock(): void
    {
        // Token item has only 20 in warehouse
        // Create order with planned 30 (more than on hand)
        $this->actingAs($this->productionUser)->post(route('production.store'), [
            'warehouse_id' => $this->warehouse->id,
            'destination_organization_id' => $this->branchOrg->id,
            'items' => [
                [
                    'item_id' => $this->tokenItem->id,
                    'qty_planned' => 30,
                ],
            ],
        ]);

        $prodOrder = ProductionOrder::latest()->first();
        $prodItem = $prodOrder->items->first();

        // Try to issue 25 (less than planned 30, but exceeds warehouse available 20) -> should fail
        $issueResponse = $this->actingAs($this->productionUser)->post(route('production.issue_stock', $prodOrder), [
            'items' => [
                [
                    'production_order_item_id' => $prodItem->id,
                    'qty_to_issue' => 25,
                ],
            ],
        ]);

        $issueResponse->assertSessionHas('error');
        $prodItem->refresh();
        $this->assertEquals(0, $prodItem->qty_issued);

        // Stock remains 20
        $balance = StockBalance::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->tokenItem->id)
            ->first();
        $this->assertEquals(20, $balance->on_hand);
    }
}
