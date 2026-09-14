<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $maker;

    protected User $checker;

    protected Warehouse $warehouse;

    protected Item $item;

    protected StockBalance $stockBalance;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'code' => 'KP-001',
            'name' => 'Kantor Pusat',
            'type' => 'HEAD_OFFICE',
            'cost_center_code' => 'CC-KP-001',
            'is_active' => true,
        ]);

        $this->maker = User::create([
            'name' => 'Inventory Maker',
            'email' => 'maker@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'INVENTORY_OFFICER',
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        $this->checker = User::create([
            'name' => 'Inventory Checker',
            'email' => 'checker@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'WAREHOUSE_ADMIN',
            'organization_id' => $org->id,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'GD-RKT',
            'name' => 'Gudang Logistik Pusat SIER',
            'organization_id' => $org->id,
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'CAT-ATM',
            'name' => 'Kartu ATM',
            'description' => 'Kartu ATM perbankan',
        ]);

        $this->item = Item::create([
            'category_id' => $category->id,
            'sku' => 'ATM-INST-001',
            'barcode' => '8991001021',
            'name' => 'Kartu ATM Instan Chip GPN Bank Jatim',
            'uom' => 'PCS',
            'estimated_unit_price' => 15000,
            'is_active' => true,
        ]);

        $this->stockBalance = StockBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'on_hand' => 100,
            'reserved' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);
    }

    public function test_maker_submits_adjustment_creates_pending_adjustment_without_affecting_stock(): void
    {
        // Maker submits +20 units adjustment
        $response = $this->actingAs($this->maker)->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'qty_diff' => 20,
            'transaction_type' => 'ADJUSTMENT_PLUS',
            'notes' => 'Ditemukan kelebihan fisik saat stock opname kuartal',
        ]);

        $response->assertSessionHas('success');

        // Check adjustment record created
        $adjustment = StockAdjustment::first();
        $this->assertNotNull($adjustment);
        $this->assertEquals('PENDING_APPROVAL', $adjustment->status);
        $this->assertEquals(20, $adjustment->qty_diff);
        $this->assertEquals(100, $adjustment->qty_before);
        $this->assertEquals(120, $adjustment->qty_after);
        $this->assertEquals($this->maker->id, $adjustment->created_by_user_id);

        // Physical stock MUST NOT be changed yet
        $this->stockBalance->refresh();
        $this->assertEquals(100, $this->stockBalance->on_hand);

        // Stock ledger MUST NOT have any adjustment entry yet
        $ledgerCount = StockLedger::where('reference_number', $adjustment->adjustment_number)->count();
        $this->assertEquals(0, $ledgerCount);
    }

    public function test_checker_approves_adjustment_updates_physical_stock_and_ledger(): void
    {
        // Maker submits adjustment -15 units
        $this->actingAs($this->maker)->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'qty_diff' => -15,
            'transaction_type' => 'ADJUSTMENT_MINUS',
            'notes' => 'Penyesuaian selisih kartu rusak saat pengujian',
        ]);

        $adjustment = StockAdjustment::first();

        // Checker approves
        $response = $this->actingAs($this->checker)->post(route('inventory.adjustments.approve', $adjustment->id));
        $response->assertSessionHas('success');

        $adjustment->refresh();
        $this->assertEquals('APPROVED', $adjustment->status);
        $this->assertEquals($this->checker->id, $adjustment->approved_by_user_id);
        $this->assertNotNull($adjustment->approved_at);

        // StockBalance on_hand must now be 85
        $this->stockBalance->refresh();
        $this->assertEquals(85, $this->stockBalance->on_hand);

        // StockLedger must have transaction entry
        $ledger = StockLedger::where('reference_number', $adjustment->adjustment_number)->first();
        $this->assertNotNull($ledger);
        $this->assertEquals('ADJUSTMENT_MINUS', $ledger->transaction_type);
        $this->assertEquals(15, $ledger->qty_out);
        $this->assertEquals(85, $ledger->balance_after);
    }

    public function test_checker_rejects_adjustment_leaves_stock_untouched(): void
    {
        // Maker submits adjustment +50 units
        $this->actingAs($this->maker)->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'qty_diff' => 50,
            'transaction_type' => 'ADJUSTMENT_PLUS',
            'notes' => 'Perhitungan ulang fisik gudang',
        ]);

        $adjustment = StockAdjustment::first();

        // Checker rejects
        $response = $this->actingAs($this->checker)->post(route('inventory.adjustments.reject', $adjustment->id), [
            'reason' => 'Data selisih fisik belum diverifikasi oleh tim audit internal.',
        ]);

        $response->assertSessionHas('success');

        $adjustment->refresh();
        $this->assertEquals('REJECTED', $adjustment->status);
        $this->assertEquals('Data selisih fisik belum diverifikasi oleh tim audit internal.', $adjustment->rejection_reason);

        // StockBalance remains untouched (100)
        $this->stockBalance->refresh();
        $this->assertEquals(100, $this->stockBalance->on_hand);

        // StockLedger has no entry
        $this->assertFalse(StockLedger::where('reference_number', $adjustment->adjustment_number)->exists());
    }

    public function test_negative_stock_adjustment_is_prevented(): void
    {
        // On hand is 100, attempting to reduce by 150 (would result in -50)
        $response = $this->actingAs($this->maker)->post(route('inventory.adjustments.store'), [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'qty_diff' => -150,
            'transaction_type' => 'ADJUSTMENT_MINUS',
            'notes' => 'Pencatatan selisih besar',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, StockAdjustment::count());
    }
}
