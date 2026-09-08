<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InitialStockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setupPrerequisites(): array
    {
        $user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $org = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Surabaya',
            'type' => 'MAIN_BRANCH',
            'city' => 'Surabaya',
            'cost_center_code' => 'CC-KC-SBY',
            'is_active' => true,
        ]);

        $wh = Warehouse::create([
            'organization_id' => $org->id,
            'code' => 'WH-SBY-01',
            'name' => 'Gudang Utama Surabaya',
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $cat = Category::create([
            'code' => 'CAT-ATK',
            'name' => 'Alat Tulis Kantor',
            'description' => 'Kebutuhan ATK',
        ]);

        $item1 = Item::create([
            'category_id' => $cat->id,
            'sku' => 'SKU-KRT-001',
            'name' => 'Kertas A4 80gr',
            'uom' => 'RIM',
            'estimated_unit_price' => 55000,
            'is_active' => true,
        ]);

        $item2 = Item::create([
            'category_id' => $cat->id,
            'sku' => 'SKU-BLP-002',
            'name' => 'Pulpen Gel Hitam',
            'uom' => 'PCS',
            'estimated_unit_price' => 7500,
            'is_active' => true,
        ]);

        return compact('user', 'org', 'wh', 'cat', 'item1', 'item2');
    }

    public function test_initial_stock_page_is_accessible_by_authorized_user(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])
            ->get(route('inventory.initial_stock.index'));

        $response->assertStatus(200);
        $response->assertSee('Saldo Awal Gudang');
        $response->assertSee($data['item1']->name);
        $response->assertSee($data['item2']->name);
    }

    public function test_download_template_csv_returns_stream_with_correct_headers_and_items(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])
            ->get(route('inventory.initial_stock.template', ['warehouse_id' => $data['wh']->id]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('SKU', $content);
        $this->assertStringContainsString('Nama Barang', $content);
        $this->assertStringContainsString('Qty Saldo Baik', $content);
        $this->assertStringContainsString('SKU-KRT-001', $content);
        $this->assertStringContainsString('SKU-BLP-002', $content);
    }

    public function test_store_initial_stock_creates_stock_balances_and_initial_ledger(): void
    {
        $data = $this->setupPrerequisites();

        $payload = [
            'warehouse_id' => $data['wh']->id,
            'cutoff_date' => '2026-01-01',
            'notes' => 'Penetapan Saldo Awal Cut-off Migrasi',
            'items' => [
                [
                    'item_id' => $data['item1']->id,
                    'qty_good' => 50,
                    'qty_damaged' => 3,
                    'unit_cost' => 55000,
                ],
                [
                    'item_id' => $data['item2']->id,
                    'qty_good' => 120,
                    'qty_damaged' => 0,
                    'unit_cost' => 7500,
                ],
            ],
        ];

        $response = $this->actingAs($data['user'])
            ->post(route('inventory.initial_stock.store'), $payload);

        $response->assertRedirect(route('inventory.balances', ['warehouse_id' => $data['wh']->id]));
        $response->assertSessionHas('success');

        // Check balances
        $bal1 = StockBalance::where('warehouse_id', $data['wh']->id)
            ->where('item_id', $data['item1']->id)
            ->first();
        $this->assertNotNull($bal1);
        $this->assertEquals(50, $bal1->on_hand);
        $this->assertEquals(3, $bal1->damaged);

        $bal2 = StockBalance::where('warehouse_id', $data['wh']->id)
            ->where('item_id', $data['item2']->id)
            ->first();
        $this->assertNotNull($bal2);
        $this->assertEquals(120, $bal2->on_hand);
        $this->assertEquals(0, $bal2->damaged);

        // Check stock ledgers
        $ledger1 = StockLedger::where('warehouse_id', $data['wh']->id)
            ->where('item_id', $data['item1']->id)
            ->where('transaction_type', 'STOCK_INITIAL')
            ->first();
        $this->assertNotNull($ledger1);
        $this->assertEquals(53, $ledger1->qty_in);
        $this->assertEquals(0, $ledger1->qty_out);
        $this->assertEquals(50, $ledger1->balance_after);
        $this->assertEquals(55000, $ledger1->unit_cost);
        $this->assertEquals(53 * 55000, $ledger1->total_value);
    }

    public function test_import_initial_stock_direct_post(): void
    {
        $data = $this->setupPrerequisites();

        $csvContent = "SKU,Nama Barang,Kategori,Satuan,Qty Saldo Baik,Qty Saldo Rusak,Harga Satuan (Rp),Catatan\n";
        $csvContent .= "SKU-KRT-001,Kertas A4,ATK,RIM,30,2,55000,Migrasi Saldo Awal\n";
        $csvContent .= "SKU-BLP-002,Pulpen Gel,ATK,PCS,80,0,7500,Migrasi Saldo Awal\n";

        $file = UploadedFile::fake()->createWithContent('saldo_awal.csv', $csvContent);

        $response = $this->actingAs($data['user'])
            ->post(route('inventory.initial_stock.import'), [
                'warehouse_id' => $data['wh']->id,
                'cutoff_date' => '2026-01-01',
                'direct_post' => 1,
                'import_notes' => 'Import Direct Saldo Awal',
                'file' => $file,
            ]);

        $response->assertRedirect(route('inventory.balances', ['warehouse_id' => $data['wh']->id]));
        $response->assertSessionHas('success');

        $bal1 = StockBalance::where('warehouse_id', $data['wh']->id)
            ->where('item_id', $data['item1']->id)
            ->first();
        $this->assertNotNull($bal1);
        $this->assertEquals(30, $bal1->on_hand);
        $this->assertEquals(2, $bal1->damaged);
    }

    public function test_import_initial_stock_to_worksheet_preview(): void
    {
        $data = $this->setupPrerequisites();

        $csvContent = "SKU;Nama Barang;Kategori;Satuan;Qty Saldo Baik;Qty Saldo Rusak;Harga Satuan (Rp);Catatan\n";
        $csvContent .= "SKU-KRT-001;Kertas A4;ATK;RIM;45;1;55000;Draft Saldo Awal\n";

        $file = UploadedFile::fake()->createWithContent('saldo_awal_semicolon.csv', $csvContent);

        $response = $this->actingAs($data['user'])
            ->post(route('inventory.initial_stock.import'), [
                'warehouse_id' => $data['wh']->id,
                'cutoff_date' => '2026-01-01',
                'direct_post' => 0,
                'file' => $file,
            ]);

        $response->assertRedirect(route('inventory.initial_stock.index', [
            'warehouse_id' => $data['wh']->id,
            'cutoff_date' => '2026-01-01',
        ]));
        $response->assertSessionHas('imported_rows');
        $response->assertSessionHas('success');
    }

    public function test_initial_stock_history_page_displays_posted_ledgers(): void
    {
        $data = $this->setupPrerequisites();

        // Create an initial stock ledger record
        StockLedger::create([
            'warehouse_id' => $data['wh']->id,
            'item_id' => $data['item1']->id,
            'transaction_type' => 'STOCK_INITIAL',
            'reference_number' => 'INIT/2026/01/9999',
            'qty_in' => 25,
            'qty_out' => 0,
            'balance_after' => 25,
            'unit_cost' => 55000,
            'total_value' => 25 * 55000,
            'notes' => 'Testing initial stock history',
            'created_by_user_id' => $data['user']->id,
        ]);

        $response = $this->actingAs($data['user'])
            ->get(route('inventory.initial_stock.history', ['warehouse_id' => $data['wh']->id]));

        $response->assertStatus(200);
        $response->assertSee('INIT/2026/01/9999');
        $response->assertSee('Testing initial stock history');
        $response->assertSee($data['item1']->name);
    }
}
