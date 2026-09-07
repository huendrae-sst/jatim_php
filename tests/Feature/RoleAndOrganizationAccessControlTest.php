<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class RoleAndOrganizationAccessControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_branch_user_dashboard_and_sidebar_access(): void
    {
        $branchUser = User::where('email', 'requester.sby@bankjatim.co.id')->firstOrFail();

        $response = $this->actingAs($branchUser)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Budi Santoso');
        $response->assertSee('Requester Cabang/Capem');
        $response->assertSee('KC-SBY');
        $response->assertSee('Pagu Anggaran KC-SBY');
        $response->assertSee('Kantor Cabang Utama Surabaya');

        // Sidebar visible items
        $response->assertSee('Permintaan & Order', false);
        $response->assertSee('Penerimaan & QC', false);
        $response->assertSee('Stock Balances (SSoT)');

        // Sidebar hidden items for regular branch requester
        $response->assertDontSee('Master Data Terpadu');
        $response->assertDontSee('Antrean Picking');
        $response->assertDontSee('Settlement Alokasi Biaya');
        $response->assertDontSee('Konsolidasi PR & Vendor', false);
        $response->assertDontSee(route('orders.approvals'), false); // Requester cannot see approvals link in sidebar
    }

    public function test_branch_user_order_index_is_scoped_to_own_organization(): void
    {
        $sbyUser = User::where('email', 'requester.sby@bankjatim.co.id')->firstOrFail();
        $mlgOrg = Organization::where('code', 'KC-MLG')->firstOrFail();

        // Create an order belonging to Malang
        $mlgUser = User::where('email', 'requester.mlg@bankjatim.co.id')->firstOrFail();
        $mlgOrder = Order::create([
            'order_number' => 'ORD-TEST-MLG-001',
            'requesting_organization_id' => $mlgOrg->id,
            'created_by_user_id' => $mlgUser->id,
            'status' => 'SUBMITTED',
            'total_items' => 10,
            'total_estimated_value' => 500000,
        ]);

        $response = $this->actingAs($sbyUser)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertDontSee('ORD-TEST-MLG-001');
    }

    public function test_branch_order_approver_approvals_are_scoped_to_own_branch(): void
    {
        $sbyApprover = User::where('email', 'approver.sby@bankjatim.co.id')->firstOrFail();
        $mlgOrg = Organization::where('code', 'KC-MLG')->firstOrFail();
        $mlgUser = User::where('email', 'requester.mlg@bankjatim.co.id')->firstOrFail();

        // Create pending order for Malang
        $mlgOrder = Order::create([
            'order_number' => 'ORD-PENDING-MLG-999',
            'requesting_organization_id' => $mlgOrg->id,
            'created_by_user_id' => $mlgUser->id,
            'status' => 'WAITING_APPROVAL',
            'total_items' => 5,
            'total_estimated_value' => 200000,
        ]);

        $response = $this->actingAs($sbyApprover)->get(route('orders.approvals'));

        $response->assertStatus(200);
        $response->assertDontSee('ORD-PENDING-MLG-999');
    }

    public function test_warehouse_user_sees_warehouse_operations_in_sidebar(): void
    {
        $warehouseUser = User::where('email', 'warehouse@bankjatim.co.id')->firstOrFail();

        $response = $this->actingAs($warehouseUser)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Joko Purwanto');
        $response->assertSee('Warehouse Officer');

        // Sidebar visible items for warehouse
        $response->assertSee('Gudang & Distribusi', false);
        $response->assertSee('Antrean Picking');
        $response->assertSee('Antrean Packing');
        $response->assertSee('Stock Balances (SSoT)');
        $response->assertSee('Stock Opname Fisik');

        // Sidebar hidden items
        $response->assertDontSee('Purchase Request (PR)');
        $response->assertDontSee('Settlement Alokasi Biaya');
    }

    public function test_super_admin_has_full_access_to_all_modules(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Super Administrator');
        $response->assertSee('Master Data Terpadu');
        $response->assertSee('Permintaan & Order', false);
        $response->assertSee('Gudang & Distribusi', false);
        $response->assertSee('Pengadaan (Procurement)');
        $response->assertSee('Settlement Alokasi Biaya');
        $response->assertSee('Audit Trail Sistem');
        $response->assertSee('Laporan & Rekapitulasi', false);
    }
}
