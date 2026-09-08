<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\EarlyWarningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EarlyWarningSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setupScenario(): array
    {
        $org = Organization::create([
            'code' => 'KP-SBY',
            'name' => 'Kantor Pusat Surabaya',
            'type' => 'HEAD_OFFICE',
        ]);

        $warehouse = Warehouse::create([
            'organization_id' => $org->id,
            'code' => 'WH-PST-01',
            'name' => 'Gudang Pusat Logistik',
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'organization_id' => $org->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $category = Category::create([
            'code' => 'CAT-IT',
            'name' => 'Teknologi Informasi',
            'description' => 'Perangkat TI',
        ]);

        // Item 1: Stockout (0 Available)
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

        // Item 2: Reorder Needed (Stok 10 <= ROP 25)
        $itemReorder = Item::create([
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
            'item_id' => $itemReorder->id,
            'on_hand' => 10,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        // Item 3: Overstock (Stok 250 > max_stock 100)
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

        // Item 4: Damaged Alert (Damaged 5 > 0)
        $itemDamaged = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-DMG-04',
            'name' => 'Monitor LED 24 Inch',
            'uom' => 'UNIT',
            'reorder_point' => 10,
            'max_stock' => 50,
            'estimated_unit_price' => 1800000,
            'is_active' => true,
        ]);
        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $itemDamaged->id,
            'on_hand' => 20,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 5,
        ]);

        // Item 5: Dead Stock (On Hand 30, no outbound for > 90 days)
        $itemDeadStock = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-DED-05',
            'name' => 'Kabel Serial RS232 Legacy',
            'uom' => 'PCS',
            'reorder_point' => 5,
            'max_stock' => 50,
            'estimated_unit_price' => 50000,
            'is_active' => true,
            'created_at' => Carbon::now()->subDays(120),
        ]);
        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $itemDeadStock->id,
            'on_hand' => 30,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
            'created_at' => Carbon::now()->subDays(120),
        ]);
        StockLedger::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $itemDeadStock->id,
            'transaction_type' => 'GOODS_ISSUE',
            'qty_in' => 0,
            'qty_out' => 2,
            'balance_after' => 30,
            'created_at' => Carbon::now()->subDays(100),
        ]);

        return compact('admin', 'warehouse', 'category', 'itemStockout', 'itemReorder', 'itemOverstock', 'itemDamaged', 'itemDeadStock');
    }

    public function test_early_warning_service_correctly_classifies_risk_types(): void
    {
        $data = $this->setupScenario();
        $service = app(EarlyWarningService::class);

        // 1. Stockout check
        $evalStockout = $service->evaluateItem($data['itemStockout']);
        $this->assertContains('CRITICAL_STOCKOUT', $evalStockout['alerts']);
        $this->assertEquals('CRITICAL', $evalStockout['severity']);

        // 2. Reorder check
        $evalReorder = $service->evaluateItem($data['itemReorder']);
        $this->assertContains('HIGH_REORDER', $evalReorder['alerts']);
        $this->assertEquals('WARNING', $evalReorder['severity']);

        // 3. Overstock check
        $evalOverstock = $service->evaluateItem($data['itemOverstock']);
        $this->assertContains('OVERSTOCK_EXCESS', $evalOverstock['alerts']);

        // 4. Damaged check
        $evalDamaged = $service->evaluateItem($data['itemDamaged']);
        $this->assertContains('DAMAGED_ALERT', $evalDamaged['alerts']);

        // 5. Dead stock check
        $evalDeadStock = $service->evaluateItem($data['itemDeadStock']);
        $this->assertContains('DEAD_STOCK', $evalDeadStock['alerts']);
    }

    public function test_early_warning_page_renders_successfully(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('inventory.early_warning'));

        $response->assertStatus(200);
        $response->assertSee('Radar Peringatan Dini Persediaan Barang');
        $response->assertSee('SKU-OUT-01');
        $response->assertSee('SKU-ROP-02');
        $response->assertSee('SKU-OVR-03');
        $response->assertSee('Kritis / Stockout');
        $response->assertSee('Reorder (ROP)');
        $response->assertSee('Overstock');
        $response->assertSee('Dead Stock');
        $response->assertSee('Stok Rusak');
    }

    public function test_early_warning_filter_by_alert_type(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('inventory.early_warning', [
            'alert_type' => 'CRITICAL_STOCKOUT',
        ]));

        $response->assertStatus(200);
        $response->assertSee('SKU-OUT-01');
        $response->assertDontSee('SKU-OVR-03');
    }

    public function test_early_warning_scan_action_creates_system_alerts(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->post(route('inventory.early_warning.scan'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify notifications were created
        $this->assertTrue(
            Notification::where('type', 'ALERT')
                ->where('title', 'like', '%EWS:%')
                ->exists()
        );
    }

    public function test_early_warning_export_csv(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('inventory.early_warning.csv'));

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('SKU-OUT-01', $content);
        $this->assertStringContainsString('Mouse Wireless Ergonomic', $content);
        $this->assertStringContainsString('CRITICAL_STOCKOUT', $content);
    }

    public function test_console_command_inventory_ews_scan_runs_successfully(): void
    {
        $this->setupScenario();

        $exitCode = Artisan::call('inventory:ews-scan');

        $this->assertEquals(0, $exitCode);
    }
}
