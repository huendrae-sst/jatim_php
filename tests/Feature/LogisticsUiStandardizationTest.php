<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LogisticsUiStandardizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_warehouse_picking_renders_consistent_ui(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('warehouse.picking.queue'));

        $response->assertStatus(200);
        $response->assertSee('Picking List');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertSee('table-striped', false);
        $response->assertSee('btn-action-icon', false);

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_warehouse_packing_renders_consistent_ui(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('warehouse.packing.queue'));

        $response->assertStatus(200);
        $response->assertSee('Packing List');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertSee('table-striped', false);
        $response->assertSee('btn-action-icon', false);

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_distribution_shipments_renders_consistent_ui(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('distribution.shipments.index'));

        $response->assertStatus(200);
        $response->assertSee('Distribusi & Ekspedisi');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertSee('table-striped', false);
        $response->assertSee('btn-action-icon', false);

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_receiving_branch_renders_consistent_ui(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('receiving.index'));

        $response->assertStatus(200);
        $response->assertSee('Penerimaan Barang Cabang');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertSee('table-striped', false);
        $response->assertSee('btn-action-icon', false);

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_receiving_discrepancies_renders_consistent_ui(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('receiving.discrepancies'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Discrepancy & Klaim');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertSee('table-striped', false);

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }
}
