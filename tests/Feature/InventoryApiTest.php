<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Warehouse;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_api_health_endpoint(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'UP',
                'system' => 'Bank Jatim JIMS API Gateway',
            ]);
    }

    public function test_api_scan_barcode_for_item_sku(): void
    {
        $item = Item::where('sku', 'IT-TNR-001')->first();
        $this->assertNotNull($item);

        $response = $this->getJson('/api/v1/scan-barcode?code='.$item->sku);
        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'type' => 'ITEM',
                'data' => [
                    'sku' => 'IT-TNR-001',
                ],
            ]);
    }

    public function test_api_stock_balance_query(): void
    {
        $wh = Warehouse::first();
        $item = Item::first();

        $response = $this->getJson("/api/v1/stock/balance/{$wh->id}/{$item->sku}");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'warehouse' => ['id', 'code', 'name'],
                'item' => ['id', 'sku', 'name', 'uom'],
                'stock' => ['on_hand', 'reserved', 'hold', 'damaged', 'available'],
            ]);
    }

    public function test_api_dashboard_kpis(): void
    {
        $response = $this->getJson('/api/v1/dashboard/kpi');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'valuation' => ['total', 'available', 'reserved'],
                'counters' => ['orders_in_transit', 'orders_waiting_approval', 'ready_to_ship', 'discrepancies'],
                'timestamp',
            ]);
    }
}
