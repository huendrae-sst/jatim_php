<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSidebarMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Warehouse $wh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'code' => 'KP-TEST',
            'name' => 'Kantor Pusat Uji',
            'type' => 'HEAD_OFFICE',
            'cost_center_code' => 'CC-KP-TEST',
            'is_active' => true,
        ]);

        $this->wh = Warehouse::create([
            'organization_id' => $this->org->id,
            'code' => 'WH-TEST',
            'name' => 'Gudang Pusat Uji',
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);
    }

    protected function makeUser(string $role, string $email): User
    {
        return User::create([
            'name' => 'User '.$role,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'organization_id' => $this->org->id,
            'warehouse_id' => $this->wh->id,
            'is_active' => true,
        ]);
    }

    public function test_requester_cabang_sidebar_access(): void
    {
        $user = $this->makeUser('REQUESTER_CABANG', 'requester@bankjatim.co.id');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        // Visible
        $response->assertSee(route('orders.index'), false);
        $response->assertSee(route('receiving.index'), false);
        $response->assertSee(route('receiving.discrepancies'), false);
        $response->assertSee(route('inventory.balances'), false);
        $response->assertSee(route('procurement.pr.index'), false);

        // Hidden
        $response->assertDontSee(route('orders.approvals'), false);
        $response->assertDontSee(route('warehouse.picking.queue'), false);
        $response->assertDontSee(route('warehouse.packing.queue'), false);
        $response->assertDontSee(route('distribution.shipments.index'), false);
        $response->assertDontSee(route('receiving.po.index'), false);
        $response->assertDontSee(route('inventory.initial_stock.index'), false);
        $response->assertDontSee(route('inventory.stock_opname'), false);
        $response->assertDontSee(route('procurement.po.index'), false);
        $response->assertDontSee(route('procurement.approvals.pr'), false);
        $response->assertDontSee(route('procurement.consolidation.index'), false);
        $response->assertDontSee(route('finance.settlements.index'), false);
        $response->assertDontSee(route('master.users'), false);
    }

    public function test_order_approver_sidebar_access(): void
    {
        $user = $this->makeUser('ORDER_APPROVER', 'approver@bankjatim.co.id');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        // Visible
        $response->assertSee(route('orders.index'), false);
        $response->assertSee(route('orders.approvals'), false);
        $response->assertSee(route('receiving.index'), false);
        $response->assertSee(route('inventory.balances'), false);
        $response->assertSee(route('inventory.switching.index'), false);

        // Hidden
        $response->assertDontSee(route('warehouse.picking.queue'), false);
        $response->assertDontSee(route('receiving.po.index'), false);
        $response->assertDontSee(route('procurement.pr.index'), false);
        $response->assertDontSee(route('finance.settlements.index'), false);
    }

    public function test_distribution_officer_sidebar_access(): void
    {
        $user = $this->makeUser('DISTRIBUTION_OFFICER', 'distribusi@bankjatim.co.id');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        // Visible
        $response->assertSee(route('distribution.shipments.index'), false);
        $response->assertSee(route('master.vendors'), false);

        // Hidden
        $response->assertDontSee(route('warehouse.picking.queue'), false);
        $response->assertDontSee(route('warehouse.packing.queue'), false);
        $response->assertDontSee(route('orders.index'), false);
        $response->assertDontSee(route('receiving.po.index'), false);
        $response->assertDontSee(route('finance.settlements.index'), false);
    }

    public function test_procurement_officer_sidebar_access(): void
    {
        $user = $this->makeUser('PROCUREMENT_OFFICER', 'proc.officer@bankjatim.co.id');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        // Visible
        $response->assertSee(route('procurement.pr.index'), false);
        $response->assertSee(route('procurement.consolidation.index'), false);
        $response->assertSee(route('procurement.po.index'), false);
        $response->assertSee(route('receiving.po.index'), false);
        $response->assertSee(route('inventory.early_warning'), false);
        $response->assertSee(route('inventory.forecasting'), false);
        $response->assertSee(route('master.items'), false);
        $response->assertSee(route('master.vendors'), false);
        $response->assertSee(route('reports.procurement_coverage'), false);

        // Hidden
        $response->assertDontSee(route('procurement.approvals.pr'), false);
        $response->assertDontSee(route('procurement.approvals.po'), false);
        $response->assertDontSee(route('warehouse.picking.queue'), false);
        $response->assertDontSee(route('finance.settlements.index'), false);
    }

    public function test_procurement_approver_sidebar_access(): void
    {
        $user = $this->makeUser('PROCUREMENT_APPROVER', 'proc.approver@bankjatim.co.id');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        // Visible
        $response->assertSee(route('procurement.pr.index'), false);
        $response->assertSee(route('procurement.approvals.pr'), false);
        $response->assertSee(route('procurement.po.index'), false);
        $response->assertSee(route('procurement.approvals.po'), false);

        // Hidden
        $response->assertDontSee(route('procurement.consolidation.index'), false);
        $response->assertDontSee(route('warehouse.picking.queue'), false);
    }

    public function test_finance_officer_sidebar_access(): void
    {
        $user = $this->makeUser('FINANCE_OFFICER', 'finance@bankjatim.co.id');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        // Visible
        $response->assertSee(route('finance.settlements.index'), false);
        $response->assertSee(route('master.budgets'), false);
        $response->assertSee(route('master.accounting'), false);
        $response->assertSee(route('reports.settlements'), false);
        $response->assertSee(route('reports.stock_valuation'), false);

        // Hidden
        $response->assertDontSee(route('orders.index'), false);
        $response->assertDontSee(route('warehouse.picking.queue'), false);
        $response->assertDontSee(route('procurement.pr.index'), false);
        $response->assertDontSee(route('reports.procurement_coverage'), false);
    }

    public function test_user_admin_sidebar_access(): void
    {
        $user = $this->makeUser('USER_ADMIN', 'useradmin@bankjatim.co.id');

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        // Visible
        $response->assertSee(route('master.users'), false);
        $response->assertSee(route('master.organizations'), false);
        $response->assertSee(route('master.accounting'), false);
        $response->assertSee(route('audit.index'), false);

        // Hidden
        $response->assertDontSee(route('orders.index'), false);
        $response->assertDontSee(route('procurement.pr.index'), false);
        $response->assertDontSee(route('finance.settlements.index'), false);
        $response->assertDontSee(route('inventory.balances'), false);
    }
}
