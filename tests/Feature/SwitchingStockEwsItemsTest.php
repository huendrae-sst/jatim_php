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

class SwitchingStockEwsItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setupScenario(): array
    {
        $user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $org = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Utama Surabaya',
            'type' => 'MAIN_BRANCH',
            'city' => 'Surabaya',
            'is_active' => true,
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

        // Item 1: Stockout (on_hand 0, ROP 20)
        $itemStockout = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-OUT-01',
            'name' => 'Mouse Wireless Ergonomic',
            'uom' => 'UNIT',
            'reorder_point' => 20,
            'max_stock' => 100,
            'estimated_unit_price' => 150000,
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

        // Item 2: ROP breach (on_hand 10 <= ROP 25)
        $itemRop = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-ROP-02',
            'name' => 'Keyboard Mechanical',
            'uom' => 'UNIT',
            'reorder_point' => 25,
            'max_stock' => 80,
            'estimated_unit_price' => 350000,
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
            'sku' => 'SKU-OVR-03',
            'name' => 'Kabel LAN Cat 6',
            'uom' => 'ROLL',
            'reorder_point' => 20,
            'max_stock' => 100,
            'estimated_unit_price' => 450000,
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

        // Item 4: Safe / Normal Stock (on_hand 50 > ROP 20) -> Should NOT be included
        $itemSafe = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-SAF-04',
            'name' => 'Flashdisk 64GB',
            'uom' => 'UNIT',
            'reorder_point' => 20,
            'max_stock' => 100,
            'estimated_unit_price' => 85000,
            'is_active' => true,
        ]);
        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $itemSafe->id,
            'on_hand' => 50,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        return compact('user', 'warehouse', 'itemStockout', 'itemRop', 'itemOverstock', 'itemSafe');
    }

    public function test_guest_cannot_access_switching_stock_ews_items(): void
    {
        $response = $this->getJson(route('inventory.switching.ews_items'));
        $response->assertStatus(401);
    }

    public function test_validation_requires_destination_warehouse_id(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['user'])
            ->getJson(route('inventory.switching.ews_items'));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_warehouse_id']);
    }

    public function test_returns_only_items_with_stockout_and_rop_breach(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['user'])
            ->getJson(route('inventory.switching.ews_items', [
                'destination_warehouse_id' => $data['warehouse']->id,
            ]));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'warehouse_id' => $data['warehouse']->id,
                'total_items' => 2,
            ]);

        $responseData = $response->json();
        $returnedItemIds = array_column($responseData['items'], 'item_id');

        // Item Stockout and ROP breach must be included
        $this->assertContains($data['itemStockout']->id, $returnedItemIds);
        $this->assertContains($data['itemRop']->id, $returnedItemIds);

        // Overstock and Safe items must NOT be included
        $this->assertNotContains($data['itemOverstock']->id, $returnedItemIds);
        $this->assertNotContains($data['itemSafe']->id, $returnedItemIds);

        // Check quantity requested is positive
        foreach ($responseData['items'] as $it) {
            $this->assertGreaterThan(0, $it['qty_requested']);
        }
    }

    public function test_modal_renders_tambah_barang_ews_button(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['user'])
            ->get(route('inventory.switching.index'));

        $response->assertStatus(200);
        $response->assertSee('Tambah Barang EWS', false);
        $response->assertSee('generateEwsItems', false);
        $response->assertSee('openCreateModal', false);
    }
}
