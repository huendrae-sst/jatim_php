<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\SwitchingStock;
use App\Models\SwitchingStockItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwitchingStockExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setupPrerequisites(): array
    {
        $superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $warehouseOfficer = User::factory()->create([
            'role' => 'WAREHOUSE_OFFICER',
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

        $cat = Category::create([
            'code' => 'OPS',
            'name' => 'Operasional',
        ]);

        $item = Item::create([
            'category_id' => $cat->id,
            'sku' => 'OPS-001',
            'name' => 'Kertas Thermal ATM',
            'uom' => 'Roll',
            'estimated_unit_price' => 25000,
            'safety_stock' => 20,
            'minimum_order_qty' => 10,
            'is_active' => true,
        ]);

        // Initial balances
        $balanceSource = StockBalance::create([
            'warehouse_id' => $whSource->id,
            'item_id' => $item->id,
            'on_hand' => 50,
            'reserved' => 10,
            'allocated' => 0,
            'in_transit' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        $balanceDest = StockBalance::create([
            'warehouse_id' => $whDest->id,
            'item_id' => $item->id,
            'on_hand' => 5,
            'reserved' => 0,
            'allocated' => 0,
            'in_transit' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        return compact('superAdmin', 'warehouseOfficer', 'orgSource', 'orgDest', 'whSource', 'whDest', 'item', 'balanceSource', 'balanceDest');
    }

    public function test_warehouse_officer_can_dispatch_approved_switching_stock(): void
    {
        $setup = $this->setupPrerequisites();

        $switching = SwitchingStock::create([
            'source_organization_id' => $setup['orgSource']->id,
            'source_warehouse_id' => $setup['whSource']->id,
            'destination_organization_id' => $setup['orgDest']->id,
            'destination_warehouse_id' => $setup['whDest']->id,
            'proposed_by_user_id' => $setup['warehouseOfficer']->id,
            'approved_by_user_id' => $setup['superAdmin']->id,
            'status' => 'APPROVED',
            'qty_requested' => 10,
        ]);

        SwitchingStockItem::create([
            'switching_stock_id' => $switching->id,
            'item_id' => $setup['item']->id,
            'qty_requested' => 10,
        ]);

        $response = $this->actingAs($setup['warehouseOfficer'])
            ->post(route('inventory.switching.dispatch', $switching->id), [
                'tracking_number' => 'RESI-EXP-001',
                'notes' => 'Barang dikirim via kurir internal.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $switching->refresh();
        $this->assertEquals('TRANSFERRED', $switching->status);
        $this->assertEquals('RESI-EXP-001', $switching->tracking_number);
        $this->assertEquals('Barang dikirim via kurir internal.', $switching->transfer_notes);
        $this->assertEquals($setup['warehouseOfficer']->id, $switching->transferred_by_user_id);
        $this->assertNotNull($switching->transferred_at);

        // Check source stock balance: on_hand 50 -> 40, reserved 10 -> 0
        $setup['balanceSource']->refresh();
        $this->assertEquals(40, $setup['balanceSource']->on_hand);
        $this->assertEquals(0, $setup['balanceSource']->reserved);

        // Check stock ledger
        $this->assertDatabaseHas('stock_ledgers', [
            'warehouse_id' => $setup['whSource']->id,
            'item_id' => $setup['item']->id,
            'transaction_type' => 'TRANSFER_OUT',
            'qty_out' => 10,
        ]);
    }

    public function test_cannot_dispatch_unapproved_switching(): void
    {
        $setup = $this->setupPrerequisites();

        $switching = SwitchingStock::create([
            'source_organization_id' => $setup['orgSource']->id,
            'source_warehouse_id' => $setup['whSource']->id,
            'destination_organization_id' => $setup['orgDest']->id,
            'destination_warehouse_id' => $setup['whDest']->id,
            'proposed_by_user_id' => $setup['warehouseOfficer']->id,
            'status' => 'PROPOSED',
            'qty_requested' => 10,
        ]);

        $response = $this->actingAs($setup['warehouseOfficer'])
            ->post(route('inventory.switching.dispatch', $switching->id), [
                'notes' => 'Mencoba kirim sebelum disetujui',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $switching->refresh();
        $this->assertEquals('PROPOSED', $switching->status);
    }

    public function test_warehouse_officer_can_receive_transferred_switching_stock(): void
    {
        $setup = $this->setupPrerequisites();

        $switching = SwitchingStock::create([
            'source_organization_id' => $setup['orgSource']->id,
            'source_warehouse_id' => $setup['whSource']->id,
            'destination_organization_id' => $setup['orgDest']->id,
            'destination_warehouse_id' => $setup['whDest']->id,
            'proposed_by_user_id' => $setup['warehouseOfficer']->id,
            'approved_by_user_id' => $setup['superAdmin']->id,
            'transferred_by_user_id' => $setup['warehouseOfficer']->id,
            'transferred_at' => now(),
            'tracking_number' => 'RESI-123',
            'status' => 'TRANSFERRED',
            'qty_requested' => 10,
        ]);

        SwitchingStockItem::create([
            'switching_stock_id' => $switching->id,
            'item_id' => $setup['item']->id,
            'qty_requested' => 10,
        ]);

        $response = $this->actingAs($setup['warehouseOfficer'])
            ->post(route('inventory.switching.receive', $switching->id), [
                'notes' => 'Diterima dalam kondisi lengkap dan baik.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $switching->refresh();
        $this->assertEquals('COMPLETED', $switching->status);
        $this->assertEquals('Diterima dalam kondisi lengkap dan baik.', $switching->receipt_notes);
        $this->assertEquals($setup['warehouseOfficer']->id, $switching->received_by_user_id);
        $this->assertNotNull($switching->received_at);

        // Check destination stock balance: on_hand 5 -> 15
        $setup['balanceDest']->refresh();
        $this->assertEquals(15, $setup['balanceDest']->on_hand);

        // Check stock ledger
        $this->assertDatabaseHas('stock_ledgers', [
            'warehouse_id' => $setup['whDest']->id,
            'item_id' => $setup['item']->id,
            'transaction_type' => 'TRANSFER_IN',
            'qty_in' => 10,
        ]);
    }

    public function test_cannot_receive_undispatched_switching(): void
    {
        $setup = $this->setupPrerequisites();

        $switching = SwitchingStock::create([
            'source_organization_id' => $setup['orgSource']->id,
            'source_warehouse_id' => $setup['whSource']->id,
            'destination_organization_id' => $setup['orgDest']->id,
            'destination_warehouse_id' => $setup['whDest']->id,
            'proposed_by_user_id' => $setup['warehouseOfficer']->id,
            'status' => 'APPROVED',
            'qty_requested' => 10,
        ]);

        $response = $this->actingAs($setup['warehouseOfficer'])
            ->post(route('inventory.switching.receive', $switching->id), [
                'notes' => 'Mencoba terima sebelum dikirim',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $switching->refresh();
        $this->assertEquals('APPROVED', $switching->status);
    }

    public function test_approvals_view_renders_dispatch_and_receive_controls(): void
    {
        $setup = $this->setupPrerequisites();

        // 1. Test view with APPROVED switching
        $approvedSwitching = SwitchingStock::create([
            'source_organization_id' => $setup['orgSource']->id,
            'source_warehouse_id' => $setup['whSource']->id,
            'destination_organization_id' => $setup['orgDest']->id,
            'destination_warehouse_id' => $setup['whDest']->id,
            'proposed_by_user_id' => $setup['warehouseOfficer']->id,
            'approved_by_user_id' => $setup['superAdmin']->id,
            'status' => 'APPROVED',
            'qty_requested' => 10,
        ]);

        SwitchingStockItem::create([
            'switching_stock_id' => $approvedSwitching->id,
            'item_id' => $setup['item']->id,
            'qty_requested' => 10,
        ]);

        $res1 = $this->actingAs($setup['superAdmin'])
            ->get(route('inventory.switching.approvals', ['switching_id' => $approvedSwitching->id]));

        $res1->assertOk();
        $res1->assertSee('Kirim Transfer Barang');
        $res1->assertSee('Kirim Transfer Antar-Gudang');

        // 2. Test view with TRANSFERRED switching
        $transferredSwitching = SwitchingStock::create([
            'source_organization_id' => $setup['orgSource']->id,
            'source_warehouse_id' => $setup['whSource']->id,
            'destination_organization_id' => $setup['orgDest']->id,
            'destination_warehouse_id' => $setup['whDest']->id,
            'proposed_by_user_id' => $setup['warehouseOfficer']->id,
            'approved_by_user_id' => $setup['superAdmin']->id,
            'transferred_by_user_id' => $setup['warehouseOfficer']->id,
            'transferred_at' => now(),
            'tracking_number' => 'SJ-MLG-009',
            'status' => 'TRANSFERRED',
            'qty_requested' => 10,
        ]);

        SwitchingStockItem::create([
            'switching_stock_id' => $transferredSwitching->id,
            'item_id' => $setup['item']->id,
            'qty_requested' => 10,
        ]);

        $res2 = $this->actingAs($setup['superAdmin'])
            ->get(route('inventory.switching.approvals', ['switching_id' => $transferredSwitching->id]));

        $res2->assertOk();
        $res2->assertSee('Konfirmasi Penerimaan');
        $res2->assertSee('Konfirmasi Penerimaan di Gudang Tujuan');
        $res2->assertSee('SJ-MLG-009');
    }
}
