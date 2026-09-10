<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementPrEwsItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setupScenario(): array
    {
        $admin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $org = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Surabaya',
            'type' => 'MAIN_BRANCH',
            'city' => 'Surabaya',
            'is_active' => true,
        ]);

        $branchUser = User::factory()->create([
            'role' => 'BRANCH_OPERATIONS',
            'organization_id' => $org->id,
        ]);

        $warehouse = Warehouse::create([
            'organization_id' => $org->id,
            'code' => 'WH-SBY-01',
            'name' => 'Gudang Cabang Surabaya',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'CAT-TI',
            'name' => 'Teknologi Informasi',
        ]);

        // Item 1: Stockout (on_hand 0, ROP 20, max_stock 100)
        $itemStockout = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-PR-OUT-01',
            'name' => 'Toner HP LaserJet M528',
            'uom' => 'UNIT',
            'reorder_point' => 20,
            'max_stock' => 100,
            'estimated_unit_price' => 750000,
            'is_active' => true,
        ]);
        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $itemStockout->id,
            'on_hand' => 0,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        // Item 2: ROP breach (on_hand 10 <= ROP 25, max_stock 80)
        $itemRop = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-PR-ROP-02',
            'name' => 'Kertas A4 80gr',
            'uom' => 'RIM',
            'reorder_point' => 25,
            'max_stock' => 80,
            'estimated_unit_price' => 55000,
            'is_active' => true,
        ]);
        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $itemRop->id,
            'on_hand' => 10,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        // Item 3: Overstock (on_hand 250 > max_stock 100) -> Should NOT be included
        $itemOverstock = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-PR-OVR-03',
            'name' => 'Map Folder Plastik',
            'uom' => 'PCS',
            'reorder_point' => 20,
            'max_stock' => 100,
            'estimated_unit_price' => 15000,
            'is_active' => true,
        ]);
        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $itemOverstock->id,
            'on_hand' => 250,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        // Item 4: Safe / Normal Stock (on_hand 60 > ROP 20) -> Should NOT be included
        $itemSafe = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-PR-SAF-04',
            'name' => 'Bolpoin Gel Hitam',
            'uom' => 'BOX',
            'reorder_point' => 20,
            'max_stock' => 100,
            'estimated_unit_price' => 35000,
            'is_active' => true,
        ]);
        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $itemSafe->id,
            'on_hand' => 60,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        return compact('admin', 'branchUser', 'org', 'warehouse', 'itemStockout', 'itemRop', 'itemOverstock', 'itemSafe');
    }

    public function test_guest_cannot_access_pr_ews_items(): void
    {
        $response = $this->getJson(route('procurement.pr.ews_items'));
        $response->assertStatus(401);
    }

    public function test_validation_requires_organization_id(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])
            ->getJson(route('procurement.pr.ews_items'));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_returns_only_items_with_stockout_and_rop_breach_for_organization(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])
            ->getJson(route('procurement.pr.ews_items', [
                'organization_id' => $data['org']->id,
            ]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'organization_id' => $data['org']->id,
                'warehouse_id' => $data['warehouse']->id,
                'total_items' => 2,
            ]);

        $items = $response->json('items');
        $this->assertCount(2, $items);

        $itemIds = array_column($items, 'item_id');
        $this->assertContains($data['itemStockout']->id, $itemIds);
        $this->assertContains($data['itemRop']->id, $itemIds);
        $this->assertNotContains($data['itemOverstock']->id, $itemIds);
        $this->assertNotContains($data['itemSafe']->id, $itemIds);

        // Check stockout item details
        $stockoutResult = collect($items)->firstWhere('item_id', $data['itemStockout']->id);
        $this->assertSame('CRITICAL_STOCKOUT', $stockoutResult['primary_alert']);
        $this->assertSame(100, $stockoutResult['qty_requested']); // max_stock (100) - available (0)
        $this->assertSame(750000.0, (float) $stockoutResult['estimated_unit_price']);

        // Check ROP item details
        $ropResult = collect($items)->firstWhere('item_id', $data['itemRop']->id);
        $this->assertSame('HIGH_REORDER', $ropResult['primary_alert']);
        $this->assertSame(70, $ropResult['qty_requested']); // max_stock (80) - available (10)
    }

    public function test_branch_user_is_scoped_to_their_organization(): void
    {
        $data = $this->setupScenario();

        $otherOrg = Organization::create([
            'code' => 'KC-MLG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'SUB_BRANCH',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        // Branch user passes otherOrg ID in query, but controller scopes to user's branch organization
        $response = $this->actingAs($data['branchUser'])
            ->getJson(route('procurement.pr.ews_items', [
                'organization_id' => $otherOrg->id,
            ]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'organization_id' => $data['org']->id,
                'total_items' => 2,
            ]);
    }
}
