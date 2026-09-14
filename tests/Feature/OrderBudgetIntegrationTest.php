<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderBudgetIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $branchUser;

    protected User $approver;

    protected Organization $branchOrg;

    protected Item $item;

    protected Budget $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchOrg = Organization::create([
            'code' => 'KC-TEST',
            'name' => 'Kantor Cabang Test',
            'type' => 'MAIN_BRANCH',
            'cost_center_code' => 'CC-KC-TEST',
            'is_active' => true,
        ]);

        $this->branchUser = User::create([
            'name' => 'Branch Maker User',
            'email' => 'branch_maker@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'BRANCH_USER',
            'organization_id' => $this->branchOrg->id,
            'is_active' => true,
        ]);

        $this->approver = User::create([
            'name' => 'Order Approver User',
            'email' => 'order_checker@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'SUPER_ADMIN',
            'organization_id' => $this->branchOrg->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'CAT-TEST',
            'name' => 'Kategori Test',
            'description' => 'Kategori untuk unit test',
        ]);

        $this->item = Item::create([
            'category_id' => $category->id,
            'sku' => 'TEST-ITEM-001',
            'barcode' => '8999999901',
            'name' => 'Buku Tabungan Test',
            'uom' => 'BUKU',
            'estimated_unit_price' => 10000,
            'is_active' => true,
        ]);

        // Branch budget: Plafon Rp 1.000.000, committed 0, realized 0
        $this->budget = Budget::create([
            'organization_id' => $this->branchOrg->id,
            'cost_center_code' => 'CC-KC-TEST',
            'year' => now()->year,
            'allocated_amount' => 1000000,
            'committed_amount' => 0,
            'realized_amount' => 0,
            'is_active' => true,
        ]);
    }

    public function test_order_within_budget_is_not_flagged_overbudget(): void
    {
        // Order 50 books @ Rp 10.000 = Rp 500.000 (50% utilization)
        $response = $this->actingAs($this->branchUser)->post(route('orders.store'), [
            'organization_id' => $this->branchOrg->id,
            'priority' => 'NORMAL',
            'required_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Permintaan rutin warkat',
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 50],
            ],
        ]);

        $response->assertRedirect(route('orders.index'));

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->budget->id, $order->budget_id);
        $this->assertFalse((bool) $order->is_overbudget);
        $this->assertEquals(50.0, (float) $order->projected_utilization);
    }

    public function test_order_exceeding_budget_is_flagged_overbudget(): void
    {
        // Order 150 books @ Rp 10.000 = Rp 1.500.000 (150% utilization > 100%)
        $response = $this->actingAs($this->branchUser)->post(route('orders.store'), [
            'organization_id' => $this->branchOrg->id,
            'priority' => 'HIGH',
            'required_date' => now()->addDays(2)->toDateString(),
            'notes' => 'Permintaan mendesak ekspansi',
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 150],
            ],
        ]);

        $response->assertRedirect(route('orders.index'));

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $this->assertTrue((bool) $order->is_overbudget);
        $this->assertEquals(150.0, (float) $order->projected_utilization);
    }

    public function test_approving_order_increases_committed_amount_and_stores_dispensation(): void
    {
        // Create an overbudget order
        $this->actingAs($this->branchUser)->post(route('orders.store'), [
            'organization_id' => $this->branchOrg->id,
            'priority' => 'HIGH',
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 120], // Rp 1.200.000 (120%)
            ],
        ]);

        $order = Order::latest()->first();
        $this->assertEquals('SUBMITTED', $order->status);

        // Approve with dispensation reason
        $dispensationReason = 'Dispensasi disetujui Pimpinan Cabang untuk kebutuhan pembukaan kantor kas baru.';
        $response = $this->actingAs($this->approver)->post(route('orders.approve', $order->id), [
            'overbudget_approval_reason' => $dispensationReason,
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('ALLOCATED', $order->status);
        $this->assertEquals($dispensationReason, $order->overbudget_approval_reason);

        // Committed amount should now be Rp 1.200.000
        $this->budget->refresh();
        $this->assertEquals(1200000, (float) $this->budget->committed_amount);
    }

    public function test_rejecting_approved_order_releases_committed_budget(): void
    {
        // Create and approve an order
        $this->actingAs($this->branchUser)->post(route('orders.store'), [
            'organization_id' => $this->branchOrg->id,
            'priority' => 'NORMAL',
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 50], // Rp 500.000
            ],
        ]);

        $order = Order::latest()->first();
        $this->actingAs($this->approver)->post(route('orders.approve', $order->id));

        $this->budget->refresh();
        $this->assertEquals(500000, (float) $this->budget->committed_amount);

        // Reject order after approval
        $this->actingAs($this->approver)->post(route('orders.reject', $order->id), [
            'reason' => 'Dibatalkan atas permintaan unit kerja.',
        ]);

        $order->refresh();
        $this->assertEquals('REJECTED', $order->status);

        // Committed amount should be released back to 0
        $this->budget->refresh();
        $this->assertEquals(0, (float) $this->budget->committed_amount);
    }
}
