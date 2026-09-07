<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\ItemConversion;
use App\Models\User;
use Tests\TestCase;

class MasterItemManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_master_items_page_can_be_rendered_with_kpi_and_action_buttons(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.items'));

        $response->assertStatus(200);
        $response->assertSee('Master Data Barang');
        $response->assertDontSee('small-box', false);
        $response->assertSee('openViewModal');
        $response->assertSee('openEditModal');
        $response->assertSee('openDeleteModal');
    }

    public function test_new_master_item_can_be_stored(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $category = Category::first();

        $response = $this->actingAs($admin)->post(route('master.items.store'), [
            'category_id' => $category->id,
            'sku' => 'TEST-SKU-999',
            'barcode' => '899999999999',
            'name' => 'Kertas HVS A4 Test',
            'uom' => 'RIM',
            'specification' => '75 gsm putih bersih',
            'estimated_unit_price' => 60000,
            'min_stock' => 10,
            'safety_stock' => 20,
            'reorder_point' => 30,
            'max_stock' => 500,
            'lead_time_days' => 4,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'sku' => 'TEST-SKU-999',
            'name' => 'Kertas HVS A4 Test',
            'uom' => 'RIM',
        ]);
    }

    public function test_master_item_can_be_updated(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $item = Item::first();

        $response = $this->actingAs($admin)->put(route('master.items.update', $item->id), [
            'category_id' => $item->category_id,
            'sku' => $item->sku,
            'name' => 'Updated Item Name',
            'uom' => 'BOX',
            'specification' => 'Updated specifications',
            'estimated_unit_price' => 125000,
            'min_stock' => 15,
            'safety_stock' => 25,
            'reorder_point' => 40,
            'max_stock' => 600,
            'lead_time_days' => 7,
            'is_active' => '0',
        ]);

        $response->assertRedirect();
        $item->refresh();

        $this->assertEquals('Updated Item Name', $item->name);
        $this->assertEquals('BOX', $item->uom);
        $this->assertEquals(125000, $item->estimated_unit_price);
        $this->assertFalse($item->is_active);
    }

    public function test_master_item_can_be_deleted(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $category = Category::first();

        $item = Item::create([
            'category_id' => $category->id,
            'sku' => 'TEMP-SKU-DELETE-1',
            'name' => 'Item to be Deleted',
            'uom' => 'PCS',
            'estimated_unit_price' => 10000,
            'min_stock' => 5,
            'max_stock' => 100,
            'safety_stock' => 10,
            'reorder_point' => 15,
            'lead_time_days' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('master.items.destroy', $item->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('items', [
            'id' => $item->id,
            'sku' => 'TEMP-SKU-DELETE-1',
        ]);
    }

    public function test_master_items_page_renders_tabbed_kategori_and_satuan(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.items'));

        $response->assertStatus(200);
        $response->assertSee('Master Barang');
        $response->assertSee('Kategori Barang');
        $response->assertSee('Satuan Unit (UOM)');
        $response->assertSee('btn-danger fw-bold fs-8 shadow-xs', false);
        $response->assertSee('Cari');
        $response->assertSee('Tambah Kategori Baru');
        $response->assertSee('Tambah Satuan Baru');
    }

    public function test_category_can_be_stored_and_deleted(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->post(route('master.categories.store'), [
            'code' => 'CAT-TEST-NEW',
            'name' => 'Kategori Baru Percobaan',
            'description' => 'Deskripsi untuk kategori baru',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('categories', [
            'code' => 'CAT-TEST-NEW',
            'name' => 'Kategori Baru Percobaan',
        ]);

        $cat = Category::where('code', 'CAT-TEST-NEW')->first();
        $delResponse = $this->actingAs($admin)->delete(route('master.categories.destroy', $cat->id));
        $delResponse->assertRedirect();
        $delResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', [
            'id' => $cat->id,
        ]);
    }

    public function test_uom_can_be_stored(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->post(route('master.uoms.store'), [
            'code' => 'TABUNG',
            'name' => 'Tabung Gas / Silinder',
            'type' => 'Khusus',
            'description' => 'Satuan untuk tabung pemadam api dan gas',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check if appears in UOM tab
        $viewResponse = $this->actingAs($admin)->get(route('master.items', ['tab' => 'uoms']));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('TABUNG');
        $viewResponse->assertSee('openEditUomModal');
        $viewResponse->assertSee('openDeleteUomModal');
    }

    public function test_uom_can_be_updated(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->put(route('master.uoms.update', 'PCS'), [
            'code' => 'PCS',
            'name' => 'Pieces Satuan Diperbarui',
            'type' => 'Kuantitas',
            'description' => 'Deskripsi satuan PCS diperbarui',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $viewResponse = $this->actingAs($admin)->get(route('master.items', ['tab' => 'uoms']));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Pieces Satuan Diperbarui');
    }

    public function test_uom_can_be_deleted_when_unused(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        // Create an unused UOM
        $this->actingAs($admin)->post(route('master.uoms.store'), [
            'code' => 'DRUM',
            'name' => 'Drum Minyak / Oli',
            'type' => 'Cairan',
            'description' => 'Wadah drum besar',
        ]);

        $delResponse = $this->actingAs($admin)->delete(route('master.uoms.destroy', 'DRUM'));
        $delResponse->assertRedirect();
        $delResponse->assertSessionHas('success');

        $viewResponse = $this->actingAs($admin)->get(route('master.items', ['tab' => 'uoms']));
        $viewResponse->assertStatus(200);
        $viewResponse->assertDontSee('Drum Minyak / Oli');
    }

    public function test_uom_cannot_be_deleted_when_used_by_items(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        // Try deleting PCS which is used by seeded items
        $delResponse = $this->actingAs($admin)->delete(route('master.uoms.destroy', 'PCS'));
        $delResponse->assertRedirect();
        $delResponse->assertSessionHas('error');
    }

    public function test_conversions_tab_renders_with_actions(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.items', ['tab' => 'conversions']));

        $response->assertStatus(200);
        $response->assertSee('Konversi Satuan');
        $response->assertSee('Tambah Konversi Satuan');
        $response->assertSee('openViewConversionModal');
        $response->assertSee('openEditConversionModal');
        $response->assertSee('openDeleteConversionModal');
    }

    public function test_item_conversion_can_be_stored(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->post(route('master.conversions.store'), [
            'item_id' => null,
            'from_uom' => 'KARTON',
            'conversion_factor' => 24,
            'to_uom' => 'PCS',
            'description' => '1 Karton berisi 24 Pcs',
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('item_conversions', [
            'from_uom' => 'KARTON',
            'conversion_factor' => 24,
            'to_uom' => 'PCS',
        ]);
    }

    public function test_item_conversion_can_be_updated(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $conv = ItemConversion::first();

        $response = $this->actingAs($admin)->put(route('master.conversions.update', $conv->id), [
            'item_id' => $conv->item_id,
            'from_uom' => $conv->from_uom,
            'conversion_factor' => 8,
            'to_uom' => $conv->to_uom,
            'description' => 'Diperbarui menjadi 8',
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('item_conversions', [
            'id' => $conv->id,
            'conversion_factor' => 8,
            'description' => 'Diperbarui menjadi 8',
        ]);
    }

    public function test_item_conversion_can_be_deleted(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $conv = ItemConversion::create([
            'item_id' => null,
            'from_uom' => 'PACK_TEST',
            'conversion_factor' => 10,
            'to_uom' => 'PCS',
            'description' => 'Testing deletion',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('master.conversions.destroy', $conv->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('item_conversions', [
            'id' => $conv->id,
        ]);
    }

    public function test_categories_tab_supports_search_and_status_filtering(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.items', [
            'tab' => 'categories',
            'cat_search' => 'ATK',
            'cat_status' => 'WITH_ITEMS',
        ]));

        $response->assertStatus(200);
        $response->assertSee('cat_search');
        $response->assertSee('cat_status');
    }

    public function test_uoms_tab_supports_search_and_type_filtering(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.items', [
            'tab' => 'uoms',
            'uom_search' => 'PCS',
            'uom_type' => 'Kuantitas',
            'uom_usage' => 'USED',
        ]));

        $response->assertStatus(200);
        $response->assertSee('uom_search');
        $response->assertSee('uom_type');
        $response->assertSee('uom_usage');
    }

    public function test_conversions_tab_supports_search_and_item_filtering(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.items', [
            'tab' => 'conversions',
            'conv_search' => 'BOX',
            'conv_item_id' => 'global',
            'conv_status' => 'ACTIVE',
        ]));

        $response->assertStatus(200);
        $response->assertSee('conv_search');
        $response->assertSee('conv_item_id');
        $response->assertSee('conv_status');
    }
}
