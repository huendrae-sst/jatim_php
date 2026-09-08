<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\SwitchingStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchingStockApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setupPrerequisites(): array
    {
        $superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $approver = User::factory()->create([
            'role' => 'SWITCHING_APPROVER',
        ]);

        $unauthorizedUser = User::factory()->create([
            'role' => 'COURIER',
        ]);

        $orgSource = Organization::create([
            'code' => 'KC-MLG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'MAIN_BRANCH',
            'city' => 'Malang',
            'cost_center_code' => 'CC-KC-MLG',
            'is_active' => true,
        ]);

        $orgDest = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Utama Surabaya',
            'type' => 'MAIN_BRANCH',
            'city' => 'Surabaya',
            'cost_center_code' => 'CC-KC-SBY',
            'is_active' => true,
        ]);

        $whSource = Warehouse::create([
            'organization_id' => $orgSource->id,
            'code' => 'WH-MLG',
            'name' => 'Gudang Cabang Malang',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $whDest = Warehouse::create([
            'organization_id' => $orgDest->id,
            'code' => 'WH-SBY',
            'name' => 'Gudang Cabang Surabaya',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'CAT-ATK',
            'name' => 'Alat Tulis Kantor',
        ]);

        $item = Item::create([
            'category_id' => $category->id,
            'sku' => 'ATK-KTS-A470',
            'name' => 'Kertas HVS A4 70gr',
            'uom' => 'Rim',
            'safety_stock' => 10,
            'min_order_qty' => 5,
            'lead_time_days' => 3,
            'estimated_unit_price' => 50000,
            'is_active' => true,
        ]);

        // Stock at source: on_hand=30, available=30
        $balance = StockBalance::create([
            'warehouse_id' => $whSource->id,
            'item_id' => $item->id,
            'on_hand' => 30,
            'allocated' => 0,
            'reserved' => 0,
            'available' => 30,
        ]);

        $switching = SwitchingStock::create([
            'item_id' => $item->id,
            'source_organization_id' => $orgSource->id,
            'source_warehouse_id' => $whSource->id,
            'destination_organization_id' => $orgDest->id,
            'destination_warehouse_id' => $whDest->id,
            'qty_requested' => 5,
            'proposed_by_user_id' => $superAdmin->id,
            'status' => 'PROPOSED',
            'recommendation_reason' => 'Stok di cabang Surabaya menipis',
        ]);

        $switching->items()->create([
            'item_id' => $item->id,
            'qty_requested' => 5,
        ]);

        return compact('superAdmin', 'approver', 'unauthorizedUser', 'orgSource', 'orgDest', 'whSource', 'whDest', 'item', 'switching', 'balance');
    }

    public function test_approver_can_view_switching_stocks_approvals_page(): void
    {
        $data = $this->setupPrerequisites();

        $response = $this->actingAs($data['approver'])->get(route('inventory.switching.approvals'));

        $response->assertStatus(200);
        $response->assertSee('Persetujuan Switching Stock');
        $response->assertSee('Menunggu Persetujuan');
        $response->assertSee('Kantor Cabang Malang');
        $response->assertSee('Kantor Cabang Utama Surabaya');
        $response->assertSee('Kertas HVS A4 70gr');
    }

    public function test_filter_tabs_render_correctly(): void
    {
        $data = $this->setupPrerequisites();

        $responsePending = $this->actingAs($data['approver'])->get(route('inventory.switching.approvals', ['tab' => 'pending']));
        $responsePending->assertStatus(200);
        $responsePending->assertSee('#'.$data['switching']->id);

        $responseHistory = $this->actingAs($data['approver'])->get(route('inventory.switching.approvals', ['tab' => 'history']));
        $responseHistory->assertStatus(200);
        $responseHistory->assertSee('Tidak Ada Switching Stock Dalam Antrean');
    }

    public function test_approver_can_approve_switching_stock(): void
    {
        $data = $this->setupPrerequisites();
        $switching = $data['switching'];

        $response = $this->actingAs($data['approver'])->post(route('inventory.switching.approve', $switching->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $switching->refresh();
        $this->assertEquals('APPROVED', $switching->status);
        $this->assertEquals($data['approver']->id, $switching->approved_by_user_id);

        // Verify stock is reserved
        $balance = StockBalance::where('warehouse_id', $data['whSource']->id)
            ->where('item_id', $data['item']->id)
            ->first();

        $this->assertEquals(5, $balance->reserved);
    }

    public function test_approver_can_reject_switching_stock_with_reason(): void
    {
        $data = $this->setupPrerequisites();
        $switching = $data['switching'];

        $response = $this->actingAs($data['approver'])->post(route('inventory.switching.reject', $switching->id), [
            'rejection_reason' => 'Stok gudang sumber dialokasikan untuk kegiatan audit internal.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $switching->refresh();
        $this->assertEquals('REJECTED', $switching->status);
        $this->assertEquals($data['approver']->id, $switching->approved_by_user_id);
        $this->assertEquals('Stok gudang sumber dialokasikan untuk kegiatan audit internal.', $switching->rejection_reason);
    }

    public function test_rejection_requires_valid_reason(): void
    {
        $data = $this->setupPrerequisites();
        $switching = $data['switching'];

        $response = $this->actingAs($data['approver'])->post(route('inventory.switching.reject', $switching->id), [
            'rejection_reason' => 'No',
        ]);

        $response->assertSessionHasErrors('rejection_reason');
    }
}
