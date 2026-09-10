<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockOpname;
use App\Models\SwitchingStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveSupportSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setupScenario(): array
    {
        $org = Organization::create([
            'code' => 'KP-SBY',
            'name' => 'Kantor Pusat Surabaya',
            'type' => 'HEAD_OFFICE',
            'city' => 'Surabaya',
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
            'code' => 'CAT-LOG',
            'name' => 'Logistik Operasional',
            'description' => 'Supplies Logistik',
        ]);

        $item = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-LOG-01',
            'name' => 'Slip Transaksi Kasir',
            'uom' => 'RIM',
            'reorder_point' => 30,
            'max_stock' => 200,
            'estimated_unit_price' => 50000,
            'is_active' => true,
        ]);

        StockBalance::create([
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'on_hand' => 100,
            'reserved' => 10,
            'hold' => 0,
            'damaged' => 5,
        ]);

        Budget::create([
            'organization_id' => $org->id,
            'cost_center_code' => 'CC-KP-01',
            'year' => (int) date('Y'),
            'allocated_amount' => 500000000,
            'committed_amount' => 50000000,
            'realized_amount' => 100000000,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-2026-0001',
            'requesting_organization_id' => $org->id,
            'requesting_warehouse_id' => $warehouse->id,
            'created_by_user_id' => $admin->id,
            'status' => 'COMPLETED',
            'submitted_at' => now()->subDays(3),
            'completed_at' => now()->subDay(),
            'required_date' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'qty_requested' => 10,
            'qty_approved' => 10,
            'qty_shipped' => 10,
            'qty_received' => 10,
        ]);

        SwitchingStock::create([
            'source_organization_id' => $org->id,
            'source_warehouse_id' => $warehouse->id,
            'destination_organization_id' => $org->id,
            'destination_warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'qty_requested' => 20,
            'proposed_by_user_id' => $admin->id,
            'status' => 'RECEIVED',
            'transferred_at' => now()->subDays(2),
            'received_at' => now()->subDay(),
        ]);

        StockOpname::create([
            'opname_number' => 'OPN-2026-0001',
            'warehouse_id' => $warehouse->id,
            'user_id' => $admin->id,
            'period_year' => (int) date('Y'),
            'period_month' => (int) date('n'),
            'opname_date' => now()->toDateString(),
            'total_items' => 1,
            'discrepancy_items_count' => 0,
            'net_variance_qty' => 0,
            'net_variance_value' => 0,
            'status' => 'POSTED',
        ]);

        return compact('admin', 'warehouse', 'category', 'item');
    }

    public function test_sidebar_contains_ess_menu_and_all_seven_sublinks(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Executive Support (ESS)');
        $response->assertSee(route('ess.valuation_budget'), false);
        $response->assertSee(route('ess.cost_saving'), false);
        $response->assertSee(route('ess.inventory_turnover'), false);
        $response->assertSee(route('ess.risk_heatmap'), false);
        $response->assertSee(route('ess.service_level'), false);
        $response->assertSee(route('ess.audit_compliance'), false);
        $response->assertSee(route('ess.predictive_budget'), false);
    }

    public function test_valuation_budget_report_page_and_csv_export(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('ess.valuation_budget'));
        $response->assertStatus(200);
        $response->assertSee('Valuasi Stok On-Hand');
        $response->assertSee('Pagu Anggaran');
        $response->assertSee('Realisasi Belanja');

        $csv = $this->actingAs($data['admin'])->get(route('ess.valuation_budget.csv'));
        $csv->assertStatus(200);
        $this->assertTrue(str_contains($csv->headers->get('content-type'), 'text/csv'));
    }

    public function test_cost_saving_report_page_and_csv_export(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('ess.cost_saving'));
        $response->assertStatus(200);
        $response->assertSee('Total Cost Savings');
        $response->assertSee('Barang Terdistribusi');
        $response->assertSee('Kecepatan Pemenuhan');

        $csv = $this->actingAs($data['admin'])->get(route('ess.cost_saving.csv'));
        $csv->assertStatus(200);
        $this->assertTrue(str_contains($csv->headers->get('content-type'), 'text/csv'));
    }

    public function test_inventory_turnover_report_page_and_csv_export(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('ess.inventory_turnover'));
        $response->assertStatus(200);
        $response->assertSee('Inventory Turnover (ITO)');
        $response->assertSee('Days of Inventory (DOI)');
        $response->assertSee('Modal Tertahan');

        $csv = $this->actingAs($data['admin'])->get(route('ess.inventory_turnover.csv'));
        $csv->assertStatus(200);
        $this->assertTrue(str_contains($csv->headers->get('content-type'), 'text/csv'));
    }

    public function test_risk_heatmap_report_page_and_csv_export(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('ess.risk_heatmap'));
        $response->assertStatus(200);
        $response->assertSee('Gudang / Cabang');
        $response->assertSee('Ketahanan Aman');
        $response->assertSee('Kritis / Rentan');

        $csv = $this->actingAs($data['admin'])->get(route('ess.risk_heatmap.csv'));
        $csv->assertStatus(200);
        $this->assertTrue(str_contains($csv->headers->get('content-type'), 'text/csv'));
    }

    public function test_service_level_report_page_and_csv_export(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('ess.service_level'));
        $response->assertStatus(200);
        $response->assertSee('Order Fill Rate');
        $response->assertSee('Ketepatan Waktu (SLA)');

        $csv = $this->actingAs($data['admin'])->get(route('ess.service_level.csv'));
        $csv->assertStatus(200);
        $this->assertTrue(str_contains($csv->headers->get('content-type'), 'text/csv'));
    }

    public function test_audit_compliance_report_page_and_csv_export(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('ess.audit_compliance'));
        $response->assertStatus(200);
        $response->assertSee('Kepatuhan Opname');
        $response->assertSee('Akurasi Saldo Fisik');

        $csv = $this->actingAs($data['admin'])->get(route('ess.audit_compliance.csv'));
        $csv->assertStatus(200);
        $this->assertTrue(str_contains($csv->headers->get('content-type'), 'text/csv'));
    }

    public function test_predictive_budget_report_page_and_csv_export(): void
    {
        $data = $this->setupScenario();

        $response = $this->actingAs($data['admin'])->get(route('ess.predictive_budget'));
        $response->assertStatus(200);
        $response->assertSee('Proyeksi Anggaran');
        $response->assertSee('SKU Perlu Pengadaan');

        $csv = $this->actingAs($data['admin'])->get(route('ess.predictive_budget.csv'));
        $csv->assertStatus(200);
        $this->assertTrue(str_contains($csv->headers->get('content-type'), 'text/csv'));
    }
}
