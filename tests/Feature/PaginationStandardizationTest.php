<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PaginationStandardizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_default_per_page_is_10_across_modules(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();

        $routesToTest = [
            route('orders.index'),
            route('finance.settlements.index'),
            route('inventory.balances'),
            route('inventory.switching.index'),
            route('inventory.stock_opname.history'),
            route('distribution.shipments.index'),
            route('receiving.index'),
            route('receiving.discrepancies'),
            route('procurement.pr.index'),
            route('procurement.po.index'),
            route('audit.index'),
            route('notifications.index'),
        ];

        foreach ($routesToTest as $route) {
            $response = $this->actingAs($admin)->get($route);
            $response->assertStatus(200);

            // Assert "Baris per halaman:" exists in the response
            $response->assertSee('Baris per halaman:');
            // Assert that per_page=10 is selected
            $response->assertSee('per_page=10', false);
            $response->assertSee('selected', false);
        }
    }

    public function test_custom_per_page_can_be_selected(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('orders.index', ['per_page' => 25]));
        $response->assertStatus(200);
        $this->assertEquals(25, $response->viewData('orders')->perPage());
    }

    public function test_pagination_navigation_icons_render_correctly(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();

        // Query with per_page=5 to guarantee multiple pages (total 9 open orders)
        $response = $this->actingAs($admin)->get(route('orders.index', ['per_page' => 5]));
        $response->assertStatus(200);

        // Assert Bootstrap Icons for first, prev, next, last are present
        $response->assertSee('bi bi-chevron-double-left', false);
        $response->assertSee('bi bi-chevron-left', false);
        $response->assertSee('bi bi-chevron-right', false);
        $response->assertSee('bi bi-chevron-double-right', false);

        // Assert conflicting classes are NOT present
        $response->assertDontSee('fa-angles-left', false);
        $response->assertDontSee('fa-angles-right', false);
    }
}
