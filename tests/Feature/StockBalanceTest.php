<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockBalanceTest extends TestCase
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

        $cat1 = Category::create([
            'code' => 'CAT-IT',
            'name' => 'Teknologi Informasi',
            'description' => 'Peralatan TI',
        ]);

        $cat2 = Category::create([
            'code' => 'CAT-ATK',
            'name' => 'Alat Tulis Kantor',
            'description' => 'Kebutuhan ATK',
        ]);

        $item1 = Item::create([
            'category_id' => $cat1->id,
            'sku' => 'SKU-SRV-001',
            'name' => 'Server Rack Mount',
            'uom' => 'UNIT',
            'estimated_unit_price' => 25000000,
            'is_active' => true,
        ]);

        $item2 = Item::create([
            'category_id' => $cat2->id,
            'sku' => 'SKU-PPR-001',
            'name' => 'Kertas A4 Sinar Dunia',
            'uom' => 'RIM',
            'estimated_unit_price' => 55000,
            'is_active' => true,
        ]);

        $bal1 = StockBalance::create([
            'warehouse_id' => $wh->id,
            'item_id' => $item1->id,
            'on_hand' => 10,
            'reserved' => 2,
            'damaged' => 1,
            'hold' => 0,
        ]);

        $bal2 = StockBalance::create([
            'warehouse_id' => $wh->id,
            'item_id' => $item2->id,
            'on_hand' => 100,
            'reserved' => 0,
            'damaged' => 0,
            'hold' => 0,
        ]);

        return compact('user', 'org', 'wh', 'cat1', 'cat2', 'item1', 'item2', 'bal1', 'bal2');
    }

    public function test_stock_balances_page_renders_cleanly_without_stock_opname_or_forecasting_buttons(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.balances', ['warehouse_id' => $data['wh']->id]));

        $response->assertStatus(200);
        $response->assertSee('Server Rack Mount');
        $response->assertSee('Kertas A4 Sinar Dunia');

        // Verification: Removed formula and redundant card header
        $response->assertDontSee('Formula Persediaan JIMS:');
        $response->assertDontSee('Rincian Saldo Barang Persediaan');
        $response->assertDontSee('Data Ditemukan');

        // Verification: Info-box widgets are used
        $response->assertSee('info-box');
        $response->assertSee('SKU Terdaftar');
        $response->assertSee('Saldo On Hand');
        $response->assertSee('Stok Bebas (Available)');
        $response->assertSee('Valuasi Persediaan');

        // Verification: Removed stock opname & forecasting buttons must not be in action bar
        $response->assertDontSee('Forecasting & ROP');
        $response->assertDontSee('btn-warning text-white');

        // Verification: Search and Pagination toolbar are present
        $response->assertSee('Cari SKU atau nama item...');
        $response->assertSee('Semua Kategori');
        $response->assertSee('Semua Saldo');

        // Verification: Action buttons with icons exist
        $response->assertSee('bi-eye');
        $response->assertSee('bi-card-list');
        $response->assertSee('Lihat Detail Saldo');
        $response->assertSee('Lihat Kartu Stok');
    }

    public function test_stock_balances_search_filter(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.balances', [
            'warehouse_id' => $data['wh']->id,
            'search' => 'Server',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Server Rack Mount');
        $response->assertDontSee('Kertas A4 Sinar Dunia');
    }

    public function test_stock_balances_category_filter(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.balances', [
            'warehouse_id' => $data['wh']->id,
            'category_id' => $data['cat2']->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Kertas A4 Sinar Dunia');
        $response->assertDontSee('Server Rack Mount');
    }

    public function test_stock_balances_stock_filter_damaged(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.balances', [
            'warehouse_id' => $data['wh']->id,
            'stock_filter' => 'damaged',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Server Rack Mount');
        $response->assertDontSee('Kertas A4 Sinar Dunia');
    }

    public function test_stock_card_page_renders_with_redesigned_layout_and_components(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.stock_card', [
            'itemId' => $data['item1']->id,
            'warehouse_id' => $data['wh']->id,
        ]));

        $response->assertStatus(200);
        // Header card
        $response->assertSee('Server Rack Mount');
        $response->assertSee('SKU-SRV-001');
        $response->assertSee('Penyesuaian Stok');
        $response->assertSee('Kembali');

        // Warehouse selector & Metrics
        $response->assertSee('Lokasi Gudang:');
        $response->assertSee('On Hand (Fisik)');
        $response->assertSee('Stok Bebas (Available)');

        // Ledger table and search toolbar
        $response->assertSee('Histori Buku Besar Stok');
        $response->assertSee('Cari no. referensi atau keterangan...');
        $response->assertSee('Semua Jenis Transaksi');

        // Adjustment Modal
        $response->assertSee('Penyesuaian Stok (Stock Adjustment)');
        $response->assertSee('Tipe Mutasi Penyesuaian');
        $response->assertSee('Simpan Penyesuaian');
    }

    public function test_stock_opname_page_renders_with_redesigned_layout_and_info_boxes(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.stock_opname', [
            'warehouse_id' => $data['wh']->id,
        ]));

        $response->assertStatus(200);

        // Warehouse selector & Kembali button removed
        $response->assertSee('Lokasi Gudang yang Di-Opname:');
        $response->assertDontSee('Kembali ke Saldo Stok');
        $response->assertSee('Ganti Lokasi Gudang');

        // Info-boxes
        $response->assertSee('info-box');
        $response->assertSee('SKU Terhitung');
        $response->assertSee('Item Berselisih');
        $response->assertSee('Net Selisih Qty');
        $response->assertSee('Dampak Valuasi');

        // Table & Search/Filter toolbar
        $response->assertSee('Cari nama item atau SKU...');
        $response->assertSee('Semua Item');
        $response->assertSee('Berselisih');
        $response->assertSee('Cocok');
        $response->assertSee('Set Semua Cocok');
        $response->assertSee('Reset Hitungan');

        // Table Content
        $response->assertSee('Server Rack Mount');
        $response->assertSee('Kertas A4 Sinar Dunia');
        $response->assertSee('Posting Hasil Opname');

        // Period & History link
        $response->assertSee('Periode:');
        $response->assertSee('Maret');
        $response->assertSee('Riwayat Opname');
    }

    public function test_stock_opname_posting_records_session_with_period_and_adjusts_ledger(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->post(route('inventory.stock_opname.store'), [
            'warehouse_id' => $data['wh']->id,
            'period_year' => 2026,
            'period_month' => 3,
            'opname_notes' => 'Pemeriksaan audit fisik triwulan I 2026',
            'counts' => [
                [
                    'item_id' => $data['item1']->id,
                    'system_qty' => 10,
                    'physical_qty' => 12, // +2 surplus
                ],
                [
                    'item_id' => $data['item2']->id,
                    'system_qty' => 100,
                    'physical_qty' => 100, // match
                ],
            ],
        ]);

        $response->assertRedirect(route('inventory.balances', ['warehouse_id' => $data['wh']->id]));
        $response->assertSessionHas('success');

        // Assert StockOpname header record
        $this->assertDatabaseHas('stock_opnames', [
            'warehouse_id' => $data['wh']->id,
            'period_year' => 2026,
            'period_month' => 3,
            'total_items' => 2,
            'discrepancy_items_count' => 1,
            'net_variance_qty' => 2,
            'notes' => 'Pemeriksaan audit fisik triwulan I 2026',
        ]);

        $opname = StockOpname::where('warehouse_id', $data['wh']->id)->first();
        $this->assertNotNull($opname);
        $this->assertStringStartsWith('OPN/2026/03/', $opname->opname_number);
        $this->assertEquals('Maret 2026', $opname->period_formatted);

        // Assert StockOpname items
        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'item_id' => $data['item1']->id,
            'system_qty' => 10,
            'physical_qty' => 12,
            'variance_qty' => 2,
        ]);

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'item_id' => $data['item2']->id,
            'system_qty' => 100,
            'physical_qty' => 100,
            'variance_qty' => 0,
        ]);

        // Assert stock balance adjusted
        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $data['wh']->id,
            'item_id' => $data['item1']->id,
            'on_hand' => 12,
        ]);
    }

    public function test_stock_opname_history_page_renders_with_filters_and_modal(): void
    {
        $data = $this->setupPrerequisites();

        // Create an opname record
        $opname = StockOpname::create([
            'opname_number' => 'OPN/2026/03/9901',
            'warehouse_id' => $data['wh']->id,
            'user_id' => $data['user']->id,
            'period_year' => 2026,
            'period_month' => 3,
            'opname_date' => '2026-03-31',
            'status' => 'POSTED',
            'notes' => 'Berita acara opname Q1',
            'total_items' => 1,
            'discrepancy_items_count' => 0,
            'net_variance_qty' => 0,
            'net_variance_value' => 0,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'item_id' => $data['item1']->id,
            'system_qty' => 10,
            'physical_qty' => 10,
            'variance_qty' => 0,
            'unit_price' => 25000000,
            'variance_value' => 0,
        ]);

        $response = $this->actingAs($data['user'])->get(route('inventory.stock_opname.history', [
            'warehouse_id' => $data['wh']->id,
            'period_year' => '2026',
            'period_month' => '3',
        ]));

        $response->assertStatus(200);
        $response->assertSee('History Stock Opname');
        $response->assertSee('OPN/2026/03/9901');
        $response->assertSee('Maret 2026');
        $response->assertSee('Gudang Utama Surabaya');
        $response->assertSee('POSTED');
        $response->assertSee('Total Sesi Opname');
        $response->assertSee('Total Item Dihitung');
        $response->assertSee('Rincian Hasil Stock Opname');
    }

    public function test_stock_opname_history_show_api(): void
    {
        $data = $this->setupPrerequisites();

        $opname = StockOpname::create([
            'opname_number' => 'OPN/2026/03/9902',
            'warehouse_id' => $data['wh']->id,
            'user_id' => $data['user']->id,
            'period_year' => 2026,
            'period_month' => 3,
            'opname_date' => '2026-03-31',
            'status' => 'POSTED',
            'notes' => 'Audit show api test',
            'total_items' => 1,
            'discrepancy_items_count' => 0,
            'net_variance_qty' => 0,
            'net_variance_value' => 0,
        ]);

        $response = $this->actingAs($data['user'])->get(route('inventory.stock_opname.history.show', ['id' => $opname->id]));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'opname_number' => 'OPN/2026/03/9902',
            'period_year' => 2026,
            'period_month' => 3,
        ]);
    }
}
