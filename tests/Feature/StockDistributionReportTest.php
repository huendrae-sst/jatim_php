<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockDistributionReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Item $item;

    private Warehouse $whPusat;

    private Warehouse $whCabang;

    protected function setUp(): void
    {
        parent::setUp();

        $orgPusat = Organization::create([
            'code' => 'KP-SBY',
            'name' => 'Kantor Pusat Surabaya',
            'type' => 'HEAD_OFFICE',
        ]);

        $orgCabang = Organization::create([
            'code' => 'KC-MLG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'MAIN_BRANCH',
        ]);

        $this->whPusat = Warehouse::create([
            'organization_id' => $orgPusat->id,
            'code' => 'WH-PST-01',
            'name' => 'Gudang Pusat SIER',
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $this->whCabang = Warehouse::create([
            'organization_id' => $orgCabang->id,
            'code' => 'WH-CAB-01',
            'name' => 'Gudang Cabang Malang',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'organization_id' => $orgPusat->id,
            'warehouse_id' => $this->whPusat->id,
        ]);

        $category = Category::create([
            'code' => 'CAT-LOG',
            'name' => 'Logistik Kantor',
        ]);

        $this->item = Item::create([
            'category_id' => $category->id,
            'sku' => 'SKU-LOG-01',
            'name' => 'Amplop Coklat Folio',
            'uom' => 'PACK',
            'estimated_unit_price' => 25000,
            'is_active' => true,
        ]);

        // Balances
        StockBalance::create([
            'item_id' => $this->item->id,
            'warehouse_id' => $this->whPusat->id,
            'on_hand' => 100,
            'reserved' => 0,
            'damaged' => 0,
        ]);

        StockBalance::create([
            'item_id' => $this->item->id,
            'warehouse_id' => $this->whCabang->id,
            'on_hand' => 50,
            'reserved' => 0,
            'damaged' => 0,
        ]);

        // Ledgers for Pusat
        StockLedger::create([
            'item_id' => $this->item->id,
            'warehouse_id' => $this->whPusat->id,
            'transaction_type' => 'STOCK_INITIAL',
            'reference_type' => 'STOCK_OPNAME',
            'reference_id' => 1,
            'qty_in' => 120,
            'qty_out' => 0,
            'balance_after' => 120,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        StockLedger::create([
            'item_id' => $this->item->id,
            'warehouse_id' => $this->whPusat->id,
            'transaction_type' => 'DESTROYED',
            'reference_type' => 'DISPOSAL',
            'reference_id' => 1,
            'qty_in' => 0,
            'qty_out' => 20,
            'balance_after' => 100,
            'created_at' => Carbon::now()->subDays(2),
        ]);
    }

    public function test_stock_distribution_report_page_renders(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.stock_distribution'));

        $response->assertStatus(200);
        $response->assertSee('Matriks Sebaran');
        $response->assertSee('Amplop Coklat Folio');
        $response->assertSee('Gudang Pusat SIER');
        $response->assertSee('Gudang Cabang Malang');
    }

    public function test_stock_distribution_csv_export(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.stock_distribution.csv'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Stream content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('SKU', $content);
        $this->assertStringContainsString('Nama Barang', $content);
        $this->assertStringContainsString('Gudang / Cabang', $content);
        $this->assertStringContainsString('Saldo Akhir On Hand', $content);
        $this->assertStringContainsString('SKU-LOG-01', $content);
    }
}
