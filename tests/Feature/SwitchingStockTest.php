<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\SwitchingStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchingStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setupPrerequisites(): array
    {
        $user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $orgSource = Organization::create([
            'code' => 'KC-MLG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'MAIN_BRANCH',
            'city' => 'Malang',
            'cost_center_code' => 'CC-KC-MLG',
            'is_active' => true,
        ]);

        $orgDest = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Utama Surabaya',
            'type' => 'MAIN_BRANCH',
            'city' => 'Surabaya',
            'cost_center_code' => 'CC-KC-SBY',
            'is_active' => true,
        ]);

        $whSource = Warehouse::create([
            'organization_id' => $orgSource->id,
            'code' => 'WH-MLG',
            'name' => 'Gudang Cabang Malang',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $whDest = Warehouse::create([
            'organization_id' => $orgDest->id,
            'code' => 'WH-SBY',
            'name' => 'Gudang Cabang Surabaya',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'CAT-ATK',
            'name' => 'Alat Tulis Kantor',
        ]);

        $item = Item::create([
            'category_id' => $category->id,
            'sku' => 'ATK-KTS-A470',
            'name' => 'Kertas HVS A4 70gr',
            'uom' => 'Rim',
            'safety_stock' => 10,
            'min_order_qty' => 5,
            'lead_time_days' => 3,
            'estimated_unit_price' => 50000,
            'is_active' => true,
        ]);

        // Stock at source: on_hand=30, available=30. Since safety_stock=10, excess is 20!
        StockBalance::create([
            'warehouse_id' => $whSource->id,
            'item_id' => $item->id,
            'on_hand' => 30,
            'allocated' => 0,
            'reserved' => 0,
            'available' => 30,
        ]);

        return compact('user', 'orgSource', 'orgDest', 'whSource', 'whDest', 'item');
    }

    public function test_switching_stocks_index_page_displays_data(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.switching.index'));

        $response->assertStatus(200);
        $response->assertSee('Daftar Switching Stock Antar-Unit');
        $response->assertSee('Tambah Switching Stock');
    }

    public function test_system_recommendations_endpoint_returns_branches_above_safety_stock(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->getJson(route('inventory.switching.recommendations', [
            'item_id' => $data['item']->id,
            'qty' => 5,
            'destination_organization_id' => $data['orgDest']->id,
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'item' => [
                'id' => $data['item']->id,
                'safety_stock' => 10,
            ],
        ]);

        // Check recommendation contains Malang warehouse with excess stock = 20
        $json = $response->json();
        $this->assertCount(1, $json['recommendations']);
        $this->assertEquals($data['whSource']->id, $json['recommendations'][0]['warehouse_id']);
        $this->assertEquals(20, $json['recommendations'][0]['excess_stock']);
    }

    public function test_manual_switching_stock_can_be_created_without_order(): void
    {
        $data = $this->setupPrerequisites();

        $payload = [
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'item_id' => $data['item']->id,
            'qty_requested' => 8,
            'recommendation_reason' => 'Kebutuhan mendesak operasional cabang Surabaya',
        ];

        $response = $this->actingAs($data['user'])->post(route('inventory.switching.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('switching_stocks', [
            'item_id' => $data['item']->id,
            'source_organization_id' => $data['orgSource']->id,
            'destination_organization_id' => $data['orgDest']->id,
            'qty_requested' => 8,
            'status' => 'PROPOSED',
            'order_id' => null,
        ]);
    }

    public function test_manual_switching_stock_can_be_updated_when_proposed(): void
    {
        $data = $this->setupPrerequisites();

        $switching = SwitchingStock::create([
            'order_id' => null,
            'item_id' => $data['item']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'qty_requested' => 5,
            'proposed_by_user_id' => $data['user']->id,
            'status' => 'PROPOSED',
            'recommendation_reason' => 'Permintaan awal',
        ]);

        $updatePayload = [
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'item_id' => $data['item']->id,
            'qty_requested' => 12,
            'recommendation_reason' => 'Revisi peningkatan kebutuhan menjadi 12',
        ];

        $response = $this->actingAs($data['user'])->put(route('inventory.switching.update', $switching->id), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('switching_stocks', [
            'id' => $switching->id,
            'qty_requested' => 12,
            'recommendation_reason' => 'Revisi peningkatan kebutuhan menjadi 12',
        ]);
    }

    public function test_manual_switching_stock_can_be_deleted_when_proposed(): void
    {
        $data = $this->setupPrerequisites();

        $switching = SwitchingStock::create([
            'order_id' => null,
            'item_id' => $data['item']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'qty_requested' => 5,
            'proposed_by_user_id' => $data['user']->id,
            'status' => 'PROPOSED',
        ]);

        $response = $this->actingAs($data['user'])->delete(route('inventory.switching.destroy', $switching->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('switching_stocks', [
            'id' => $switching->id,
        ]);
    }

    public function test_manual_switching_stock_can_be_approved_and_reserves_stock(): void
    {
        $data = $this->setupPrerequisites();

        $switching = SwitchingStock::create([
            'order_id' => null,
            'item_id' => $data['item']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'qty_requested' => 5,
            'proposed_by_user_id' => $data['user']->id,
            'status' => 'PROPOSED',
        ]);

        $response = $this->actingAs($data['user'])->post(route('inventory.switching.approve', $switching->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('switching_stocks', [
            'id' => $switching->id,
            'status' => 'APPROVED',
            'approved_by_user_id' => $data['user']->id,
        ]);

        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $data['whSource']->id,
            'item_id' => $data['item']->id,
            'reserved' => 5,
        ]);

        $balance = StockBalance::where('warehouse_id', $data['whSource']->id)
            ->where('item_id', $data['item']->id)
            ->first();
        $this->assertEquals(25, $balance->available);
    }

    public function test_manual_switching_stock_can_be_created_with_multiple_items(): void
    {
        $data = $this->setupPrerequisites();

        $secondCategory = Category::firstOrCreate(
            ['code' => 'ATK'],
            ['name' => 'Alat Tulis Kantor', 'slug' => 'alat-tulis-kantor']
        );

        $secondItem = Item::firstOrCreate(
            ['sku' => 'ATK-KRT-A4'],
            [
                'name' => 'Kertas HVS A4 80gr',
                'category_id' => $secondCategory->id,
                'uom' => 'Rim',
                'safety_stock' => 5,
                'is_active' => true,
            ]
        );

        $payload = [
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'recommendation_reason' => 'Permohonan switching multi-barang operasional cabang',
            'items' => [
                [
                    'item_id' => $data['item']->id,
                    'qty_requested' => 12,
                ],
                [
                    'item_id' => $secondItem->id,
                    'qty_requested' => 7,
                ],
            ],
        ];

        $response = $this->actingAs($data['user'])->post(route('inventory.switching.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $switching = SwitchingStock::latest('id')->first();
        $this->assertNotNull($switching);
        $this->assertEquals(19, $switching->qty_requested);
        $this->assertEquals(2, $switching->items()->count());

        $this->assertDatabaseHas('switching_stock_items', [
            'switching_stock_id' => $switching->id,
            'item_id' => $data['item']->id,
            'qty_requested' => 12,
        ]);

        $this->assertDatabaseHas('switching_stock_items', [
            'switching_stock_id' => $switching->id,
            'item_id' => $secondItem->id,
            'qty_requested' => 7,
        ]);
    }

    public function test_manual_switching_stock_with_multiple_items_can_be_updated(): void
    {
        $data = $this->setupPrerequisites();

        $secondCategory = Category::firstOrCreate(
            ['code' => 'ATK'],
            ['name' => 'Alat Tulis Kantor', 'slug' => 'alat-tulis-kantor']
        );

        $secondItem = Item::firstOrCreate(
            ['sku' => 'ATK-KRT-A4'],
            [
                'name' => 'Kertas HVS A4 80gr',
                'category_id' => $secondCategory->id,
                'uom' => 'Rim',
                'safety_stock' => 5,
                'is_active' => true,
            ]
        );

        $switching = SwitchingStock::create([
            'order_id' => null,
            'item_id' => $data['item']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'qty_requested' => 5,
            'proposed_by_user_id' => $data['user']->id,
            'status' => 'PROPOSED',
        ]);

        $switching->items()->create([
            'item_id' => $data['item']->id,
            'qty_requested' => 5,
        ]);

        $updatePayload = [
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'recommendation_reason' => 'Perubahan kuantitas dan penambahan item kedua',
            'items' => [
                [
                    'item_id' => $data['item']->id,
                    'qty_requested' => 15,
                ],
                [
                    'item_id' => $secondItem->id,
                    'qty_requested' => 8,
                ],
            ],
        ];

        $response = $this->actingAs($data['user'])->put(route('inventory.switching.update', $switching->id), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $switching->refresh();
        $this->assertEquals(23, $switching->qty_requested);
        $this->assertEquals(2, $switching->items()->count());

        $this->assertDatabaseHas('switching_stock_items', [
            'switching_stock_id' => $switching->id,
            'item_id' => $data['item']->id,
            'qty_requested' => 15,
        ]);

        $this->assertDatabaseHas('switching_stock_items', [
            'switching_stock_id' => $switching->id,
            'item_id' => $secondItem->id,
            'qty_requested' => 8,
        ]);
    }

    public function test_manual_switching_stock_with_multiple_items_reserves_stock_for_all_items_on_approval(): void
    {
        $data = $this->setupPrerequisites();

        $secondCategory = Category::firstOrCreate(
            ['code' => 'ATK'],
            ['name' => 'Alat Tulis Kantor', 'slug' => 'alat-tulis-kantor']
        );

        $secondItem = Item::firstOrCreate(
            ['sku' => 'ATK-KRT-A4'],
            [
                'name' => 'Kertas HVS A4 80gr',
                'category_id' => $secondCategory->id,
                'uom' => 'Rim',
                'safety_stock' => 5,
                'is_active' => true,
            ]
        );

        // Setup stock for second item at source warehouse: 20 on_hand, 0 reserved, 20 available
        StockBalance::create([
            'warehouse_id' => $data['whSource']->id,
            'item_id' => $secondItem->id,
            'on_hand' => 20,
            'reserved' => 0,
        ]);

        $switching = SwitchingStock::create([
            'order_id' => null,
            'item_id' => $data['item']->id,
            'source_organization_id' => $data['orgSource']->id,
            'source_warehouse_id' => $data['whSource']->id,
            'destination_organization_id' => $data['orgDest']->id,
            'destination_warehouse_id' => $data['whDest']->id,
            'qty_requested' => 15,
            'proposed_by_user_id' => $data['user']->id,
            'status' => 'PROPOSED',
        ]);

        $switching->items()->createMany([
            [
                'item_id' => $data['item']->id,
                'qty_requested' => 10,
            ],
            [
                'item_id' => $secondItem->id,
                'qty_requested' => 5,
            ],
        ]);

        $response = $this->actingAs($data['user'])->post(route('inventory.switching.approve', $switching->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $switching->refresh();
        $this->assertEquals('APPROVED', $switching->status);

        // Check stock reserved for item 1
        $balance1 = StockBalance::where('warehouse_id', $data['whSource']->id)
            ->where('item_id', $data['item']->id)
            ->first();
        $this->assertEquals(10, $balance1->reserved);
        $this->assertEquals(20, $balance1->available);

        // Check stock reserved for item 2
        $balance2 = StockBalance::where('warehouse_id', $data['whSource']->id)
            ->where('item_id', $secondItem->id)
            ->first();
        $this->assertEquals(5, $balance2->reserved);
        $this->assertEquals(15, $balance2->available);
    }

    public function test_system_recommendations_endpoint_returns_branches_for_multiple_items(): void
    {
        $data = $this->setupPrerequisites();

        $secondCategory = Category::firstOrCreate(
            ['code' => 'ATK'],
            ['name' => 'Alat Tulis Kantor', 'slug' => 'alat-tulis-kantor']
        );

        $secondItem = Item::firstOrCreate(
            ['sku' => 'ATK-KRT-A4'],
            [
                'name' => 'Kertas HVS A4 80gr',
                'category_id' => $secondCategory->id,
                'uom' => 'Rim',
                'safety_stock' => 5,
                'is_active' => true,
            ]
        );

        StockBalance::create([
            'warehouse_id' => $data['whSource']->id,
            'item_id' => $secondItem->id,
            'on_hand' => 20,
            'reserved' => 0,
            'available' => 20,
        ]);

        $response = $this->actingAs($data['user'])->getJson(route('inventory.switching.recommendations', [
            'destination_organization_id' => $data['orgDest']->id,
            'items' => [
                ['item_id' => $data['item']->id, 'qty' => 10],
                ['item_id' => $secondItem->id, 'qty' => 5],
            ],
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'total_items_requested' => 2,
        ]);

        $json = $response->json();
        $this->assertCount(1, $json['recommendations']);
        $this->assertEquals($data['whSource']->id, $json['recommendations'][0]['warehouse_id']);
        $this->assertTrue($json['recommendations'][0]['is_all_covered']);
        $this->assertEquals(2, $json['recommendations'][0]['fully_covered_count']);
        $this->assertEquals(2, $json['recommendations'][0]['total_items_count']);
        $this->assertCount(2, $json['recommendations'][0]['items']);
        $this->assertCount(2, $json['item_recommendations']);
        $this->assertTrue($json['item_recommendations'][0]['has_recommendation']);
        $this->assertTrue($json['item_recommendations'][1]['has_recommendation']);
    }

    public function test_system_recommendations_handles_item_without_stock_surplus_and_marks_no_recommendation(): void
    {
        $data = $this->setupPrerequisites();

        $thirdItem = Item::create([
            'category_id' => $data['item']->category_id,
            'sku' => 'ATK-NO-STOCK',
            'name' => 'Barang Tanpa Stok Cabang',
            'uom' => 'Pcs',
            'safety_stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($data['user'])->getJson(route('inventory.switching.recommendations', [
            'destination_organization_id' => $data['orgDest']->id,
            'items' => [
                ['item_id' => $data['item']->id, 'qty' => 5],
                ['item_id' => $thirdItem->id, 'qty' => 3],
            ],
        ]));

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertCount(2, $json['item_recommendations']);

        // Item 1 has recommendation
        $this->assertEquals($data['item']->id, $json['item_recommendations'][0]['item_id']);
        $this->assertTrue($json['item_recommendations'][0]['has_recommendation']);
        $this->assertNotEmpty($json['item_recommendations'][0]['sources']);

        // Item 3 has NO recommendation
        $this->assertEquals($thirdItem->id, $json['item_recommendations'][1]['item_id']);
        $this->assertFalse($json['item_recommendations'][1]['has_recommendation']);
        $this->assertEmpty($json['item_recommendations'][1]['sources']);
    }

    public function test_system_recommendations_per_cabang_terpadu_requires_at_least_one_fully_covered_item(): void
    {
        $data = $this->setupPrerequisites();

        // whSource has item with on_hand 50, reserved 0, safety_stock 30 -> excess is 20
        // Request qty: 50 (greater than excess 20), so fully_covered_count will be 0
        $response = $this->actingAs($data['user'])->getJson(route('inventory.switching.recommendations', [
            'destination_organization_id' => $data['orgDest']->id,
            'items' => [
                ['item_id' => $data['item']->id, 'qty' => 50],
            ],
        ]));

        $response->assertStatus(200);
        $json = $response->json();

        // Recommendations (per cabang terpadu) must be empty because 0 items are fully covered
        $this->assertEmpty($json['recommendations']);

        // But item_recommendations can still report partial surplus source
        $this->assertNotEmpty($json['item_recommendations']);
        $this->assertNotEmpty($json['item_recommendations'][0]['sources']);

        // Now request qty: 10 (less than excess 20), so fully_covered_count is 1
        $response2 = $this->actingAs($data['user'])->getJson(route('inventory.switching.recommendations', [
            'destination_organization_id' => $data['orgDest']->id,
            'items' => [
                ['item_id' => $data['item']->id, 'qty' => 10],
            ],
        ]));

        $response2->assertStatus(200);
        $json2 = $response2->json();

        // Recommendations (per cabang terpadu) must now include whSource
        $this->assertCount(1, $json2['recommendations']);
        $this->assertGreaterThanOrEqual(1, $json2['recommendations'][0]['fully_covered_count']);
    }
}
