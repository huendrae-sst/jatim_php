<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ForecastingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiHorizonForecastingTest extends TestCase
{
    use RefreshDatabase;

    private Item $item;

    private User $admin;

    private ForecastingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'code' => 'KP-SBY',
            'name' => 'Kantor Pusat Surabaya',
            'type' => 'HEAD_OFFICE',
        ]);

        $warehouse = Warehouse::create([
            'organization_id' => $org->id,
            'code' => 'WH-PST-01',
            'name' => 'Gudang Pusat Logistik SIER',
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'organization_id' => $org->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $category = Category::create([
            'code' => 'CAT-LOG',
            'name' => 'Logistik Operasional',
        ]);

        $this->item = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-KERTAS-A4',
            'name' => 'Kertas HVS A4 80gr PaperOne',
            'uom' => 'RIM',
            'lead_time_days' => 7,
            'reorder_point' => 30,
            'min_stock' => 20,
            'max_stock' => 500,
            'estimated_unit_price' => 55000,
            'is_active' => true,
        ]);

        StockBalance::create([
            'item_id' => $this->item->id,
            'warehouse_id' => $warehouse->id,
            'on_hand' => 40,
            'reserved' => 0,
            'damaged' => 0,
        ]);

        // Create 3 months historical outbound transactions
        $now = Carbon::now();
        for ($i = 1; $i <= 3; $i++) {
            StockLedger::create([
                'item_id' => $this->item->id,
                'warehouse_id' => $warehouse->id,
                'transaction_type' => 'OUTBOUND_ORDER',
                'reference_type' => 'ORDER',
                'reference_id' => 100 + $i,
                'qty_in' => 0,
                'qty_out' => 30, // 30 units/month -> ~1 unit/day
                'balance_after' => 40,
                'notes' => "Pengeluaran bulan ke-{$i}",
                'created_at' => $now->copy()->subMonths($i),
            ]);
        }

        $this->service = app(ForecastingService::class);
    }

    public function test_forecasting_across_multiple_horizons(): void
    {
        // 1 Month Horizon (Operasional)
        $f1 = $this->service->getItemForecast($this->item, 1);
        $this->assertEquals(1, $f1['horizon_months']);
        $this->assertGreaterThan(0, $f1['avg_monthly_demand']);
        $this->assertGreaterThanOrEqual(1, $f1['projected_horizon_demand']);

        // 3 Months Horizon (Triwulan)
        $f3 = $this->service->getItemForecast($this->item, 3);
        $this->assertEquals(3, $f3['horizon_months']);
        $this->assertGreaterThan($f1['projected_horizon_demand'], $f3['projected_horizon_demand']);

        // 6 Months Horizon (Semester)
        $f6 = $this->service->getItemForecast($this->item, 6);
        $this->assertEquals(6, $f6['horizon_months']);
        $this->assertGreaterThan($f3['projected_horizon_demand'], $f6['projected_horizon_demand']);

        // 12 Months Horizon (Tahunan)
        $f12 = $this->service->getItemForecast($this->item, 12);
        $this->assertEquals(12, $f12['horizon_months']);
        $this->assertGreaterThan($f6['projected_horizon_demand'], $f12['projected_horizon_demand']);
    }

    public function test_suggested_reorder_scales_with_horizon_when_below_rop(): void
    {
        // Set available stock below ROP
        $this->item->stockBalances()->update([
            'on_hand' => 10,
        ]);

        $f1 = $this->service->getItemForecast($this->item, 1);
        $f6 = $this->service->getItemForecast($this->item, 6);

        // When reorder is needed, suggested qty for 6-month horizon should be higher than 1-month horizon
        $this->assertGreaterThanOrEqual($f1['suggested_reorder_qty'], $f6['suggested_reorder_qty']);
        $this->assertContains($f6['trend'], ['UP', 'DOWN', 'STABLE']);
    }

    public function test_forecasting_web_route_with_horizon_query(): void
    {
        $response = $this->actingAs($this->admin)->get(route('inventory.forecasting', [
            'horizon' => 3,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Proyeksi (3 Bln)');
        $response->assertSee('SKU-KERTAS-A4');
    }
}
