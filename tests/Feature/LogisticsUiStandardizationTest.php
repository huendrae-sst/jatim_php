<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Order;
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

        // Ensure "Buka Antrean Packing" button is removed
        $response->assertDontSee('Buka Antrean Packing');

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_warehouse_picking_confirmation_stays_on_picking_page(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $order = Order::first();
        $this->assertNotNull($order);
        $order->update(['status' => 'ALLOCATED']);

        $response = $this->actingAs($user)->post(route('warehouse.picking.process', $order->id));
        $response->assertRedirect(route('warehouse.picking.queue'));
        $response->assertSessionHas('success');
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

        // Ensure "Buka Modul Distribusi" button is removed
        $response->assertDontSee('Buka Modul Distribusi');

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_warehouse_packing_confirmation_stays_on_packing_page(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $order = Order::first();
        $this->assertNotNull($order);
        $order->update(['status' => 'PICKING']);

        $response = $this->actingAs($user)->post(route('warehouse.packing.process', $order->id), [
            'koli_count' => 2,
            'total_weight_kg' => 10.5,
            'dimensions_cm' => '40x30x20',
        ]);
        $response->assertRedirect(route('warehouse.packing.queue'));
        $response->assertSessionHas('success');
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
        $response->assertSee('Terbitkan Manifest');

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_distribution_shipment_creation_stays_on_shipments_page(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $order = Order::first();
        $this->assertNotNull($order);
        $order->update(['status' => 'READY_TO_SHIP']);
        $courier = Courier::where('is_active', true)->first();
        $this->assertNotNull($courier);

        $response = $this->actingAs($user)->post(route('distribution.shipments.store'), [
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'service_type' => 'REGULER',
            'tracking_number' => 'TEST-RESI-12345',
            'shipping_cost' => 100000,
            'eta_date' => now()->addDays(2)->format('Y-m-d'),
        ]);
        $response->assertRedirect(route('distribution.shipments.index'));
        $response->assertSessionHas('success');
    }

    public function test_receiving_branch_renders_consistent_ui(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('receiving.index'));

        $response->assertStatus(200);
        $response->assertSee('Penerimaan Barang Cabang');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertSee('table-striped', false);
        $response->assertSee('nav nav-tabs', false);
        $response->assertSee('Pengiriman Menuju Cabang (In-Transit)');
        $response->assertSee('Histori Penerimaan Cabang');
        $response->assertSee('branchReceivingManager');

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_receiving_branch_history_tab_renders_successfully(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('receiving.index', ['tab' => 'history']));

        $response->assertStatus(200);
        $response->assertSee('Penerimaan Barang Cabang');
        $response->assertSee('Histori Penerimaan Cabang');
        $response->assertSee('No. Penerimaan');
        $response->assertSee('Petugas Penerima');
    }

    public function test_receiving_discrepancies_renders_consistent_ui(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('receiving.discrepancies'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Discrepancy & Klaim');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertSee('table-striped', false);
        $response->assertSee('info-box', false);
        $response->assertSee('Total Laporan Selisih');
        $response->assertSee('discrepancyManager');

        // Ensure old prototype styling is removed
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_branch_user_receiving_and_discrepancies_access_with_organization_scoping(): void
    {
        $branchUser = User::where('role', 'REQUESTER_CABANG')->whereNotNull('organization_id')->firstOrFail();

        // Branch user accessing incoming shipments tab
        $responseIncoming = $this->actingAs($branchUser)->get(route('receiving.index'));
        $responseIncoming->assertStatus(200);

        // Branch user accessing history tab
        $responseHistory = $this->actingAs($branchUser)->get(route('receiving.index', ['tab' => 'history']));
        $responseHistory->assertStatus(200);

        // Branch user accessing discrepancies
        $responseDiscrepancies = $this->actingAs($branchUser)->get(route('receiving.discrepancies'));
        $responseDiscrepancies->assertStatus(200);

        // Admin filtering by organization_id
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();
        $responseAdminFiltered = $this->actingAs($admin)->get(route('receiving.index', ['organization_id' => $branchUser->organization_id]));
        $responseAdminFiltered->assertStatus(200);

        $responseAdminDiscrepancies = $this->actingAs($admin)->get(route('receiving.discrepancies', ['organization_id' => $branchUser->organization_id]));
        $responseAdminDiscrepancies->assertStatus(200);
    }
}
