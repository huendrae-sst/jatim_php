<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setupPrerequisites(): array
    {
        $user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
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
            'lead_time_days' => 7,
            'is_active' => true,
        ]);

        $item2 = Item::create([
            'category_id' => $cat2->id,
            'sku' => 'SKU-PPR-001',
            'name' => 'Kertas A4 Sinar Dunia',
            'uom' => 'RIM',
            'lead_time_days' => 3,
            'is_active' => true,
        ]);

        return compact('user', 'cat1', 'cat2', 'item1', 'item2');
    }

    public function test_forecasting_page_renders_cleanly_without_notice_or_pr_button(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.forecasting'));

        $response->assertStatus(200);
        $response->assertSee('Analisis Kebutuhan Stok per SKU');
        $response->assertSee('SKU-SRV-001');
        $response->assertSee('SKU-PPR-001');

        // Verification: Removed notice and PR button must not appear
        $response->assertDontSee('Aturan Forecasting Bank Jatim:');
        $response->assertDontSee('Buat Purchase Request Baru');

        // Verification: Info-Boxes are present
        $response->assertSee('info-box');
        $response->assertSee('Total SKU Dianalisis');
        $response->assertSee('Kritis / Stockout');
        $response->assertSee('Perlu Reorder Segera');
        $response->assertSee('Kondisi Stok Aman');

        // Verification: Search and Pagination toolbar are present
        $response->assertSee('Cari SKU atau nama item...');
        $response->assertSee('Semua Kategori');
        $response->assertSee('Semua Risiko');
    }

    public function test_forecasting_search_filter(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.forecasting', ['search' => 'Server']));

        $response->assertStatus(200);
        $response->assertSee('Server Rack Mount');
        $response->assertDontSee('Kertas A4 Sinar Dunia');
    }

    public function test_forecasting_category_filter(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.forecasting', ['category_id' => $data['cat2']->id]));

        $response->assertStatus(200);
        $response->assertSee('Kertas A4 Sinar Dunia');
        $response->assertDontSee('Server Rack Mount');
    }

    public function test_forecasting_risk_level_filter(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['user'])->get(route('inventory.forecasting', ['risk_level' => 'CRITICAL_STOCKOUT']));

        $response->assertStatus(200);
        // Both items have 0 stock available initially so both are CRITICAL_STOCKOUT
        $response->assertSee('STOCKOUT');
    }
}
