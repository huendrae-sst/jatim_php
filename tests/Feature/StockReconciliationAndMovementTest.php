<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReconciliationAndMovementTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected Warehouse $warehouse;

    protected Item $itemA;

    protected Item $itemB;

    protected User $inventoryUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'code' => 'KP-SBY',
            'name' => 'Kantor Pusat Bank Jatim',
            'type' => 'HEAD_OFFICE',
            'cost_center_code' => 'CC-KP-001',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'GD-RKT',
            'name' => 'Gudang Rungkut Logistik Pusat',
            'organization_id' => $this->org->id,
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'CAT-ATM',
            'name' => 'Kartu ATM',
            'description' => 'Persediaan Kartu',
        ]);

        $this->itemA = Item::create([
            'category_id' => $category->id,
            'sku' => 'ATM-DEBIT-001',
            'barcode' => '8993001001',
            'name' => 'Kartu Debit Reguler',
            'uom' => 'PCS',
            'min_stock' => 50,
            'estimated_unit_price' => 10000,
            'is_active' => true,
        ]);

        $this->itemB = Item::create([
            'category_id' => $category->id,
            'sku' => 'ATM-PRIORITY-002',
            'barcode' => '8993001002',
            'name' => 'Kartu Debit Priority Black',
            'uom' => 'PCS',
            'min_stock' => 20,
            'estimated_unit_price' => 25000,
            'is_active' => true,
        ]);

        $this->inventoryUser = User::create([
            'name' => 'Inventory Audit Officer',
            'email' => 'inventory_audit@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'INVENTORY_OFFICER',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);
    }

    public function test_stock_reconciliation_audit_engine_detects_balanced_and_discrepant_stock(): void
    {
        // 1. Item A: Perfectly Balanced
        // Initial stock 100, Incoming PO 50, Outgoing issue 30 -> Closing should be 120
        StockBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemA->id,
            'on_hand' => 120,
            'available' => 120,
            'damaged' => 0,
        ]);

        StockLedger::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemA->id,
            'transaction_type' => 'STOCK_INITIAL',
            'reference_number' => 'INIT-001',
            'qty_in' => 100,
            'qty_out' => 0,
            'balance_after' => 100,
            'unit_cost' => 10000,
            'total_value' => 1000000,
        ]);

        StockLedger::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemA->id,
            'transaction_type' => 'PROCUREMENT_RECEIPT',
            'reference_number' => 'PO-001',
            'qty_in' => 50,
            'qty_out' => 0,
            'balance_after' => 150,
            'unit_cost' => 10000,
            'total_value' => 500000,
        ]);

        StockLedger::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemA->id,
            'transaction_type' => 'GOODS_ISSUE',
            'reference_number' => 'ORD-001',
            'qty_in' => 0,
            'qty_out' => 30,
            'balance_after' => 120,
            'unit_cost' => 10000,
            'total_value' => 300000,
        ]);

        // 2. Item B: Discrepancy (on_hand says 50, but ledgers calculate 45 -> Diff +5)
        StockBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemB->id,
            'on_hand' => 50,
            'available' => 50,
            'damaged' => 0,
        ]);

        StockLedger::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemB->id,
            'transaction_type' => 'STOCK_INITIAL',
            'reference_number' => 'INIT-002',
            'qty_in' => 45,
            'qty_out' => 0,
            'balance_after' => 45,
            'unit_cost' => 25000,
            'total_value' => 1125000,
        ]);

        // Access reconciliation page
        $response = $this->actingAs($this->inventoryUser)->get(route('inventory.reconciliation', [
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Rekonsiliasi Stok');
        $response->assertSee('ATM-DEBIT-001');
        $response->assertSee('ATM-PRIORITY-002');
        // Item A should be BALANCED
        $response->assertSee('BALANCED');
        // Item B should show DISCREPANCY
        $response->assertSee('DISCREPANCY');

        // Test filter by BALANCED only
        $responseBalanced = $this->actingAs($this->inventoryUser)->get(route('inventory.reconciliation', [
            'warehouse_id' => $this->warehouse->id,
            'status' => 'BALANCED',
        ]));
        $responseBalanced->assertStatus(200);
        $responseBalanced->assertSee('ATM-DEBIT-001');
        $responseBalanced->assertDontSee('ATM-PRIORITY-002');
    }

    public function test_historical_movement_inquiry_and_csv_export(): void
    {
        StockBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemA->id,
            'on_hand' => 500,
            'available' => 500,
            'damaged' => 0,
        ]);

        StockLedger::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemA->id,
            'transaction_type' => 'PROCUREMENT_RECEIPT',
            'reference_number' => 'PO-JATIM-88229',
            'qty_in' => 500,
            'qty_out' => 0,
            'balance_after' => 500,
            'unit_cost' => 10000,
            'total_value' => 5000000,
            'notes' => 'Penerimaan batch PO Vendor Gemalto',
            'created_by_user_id' => $this->inventoryUser->id,
        ]);

        // Search by reference number
        $response = $this->actingAs($this->inventoryUser)->get(route('inventory.movement_inquiry', [
            'search' => 'PO-JATIM-88229',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Inquiry Historical Movement');
        $response->assertSee('PO-JATIM-88229');
        $response->assertSee('ATM-DEBIT-001');

        // Search by SKU to see multi-warehouse matrix
        $skuResponse = $this->actingAs($this->inventoryUser)->get(route('inventory.movement_inquiry', [
            'search' => 'ATM-DEBIT-001',
        ]));
        $skuResponse->assertStatus(200);
        $skuResponse->assertSee('Pelacakan Posisi Fisik Terkini (Multi-Lokasi)');

        // Test CSV export stream
        $csvResponse = $this->actingAs($this->inventoryUser)->get(route('inventory.movement_inquiry', [
            'search' => 'PO-JATIM-88229',
            'export' => 'csv',
        ]));

        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('content-type'));
    }

    public function test_can_update_warehouse_specific_stock_pagu_and_limits(): void
    {
        $balance = StockBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->itemA->id,
            'on_hand' => 25,
            'available' => 25,
            'damaged' => 0,
            'min_stock' => 10,
            'max_stock' => 100,
        ]);

        // Initial status: on_hand is 25, min is 10, max is 100 -> OPTIMAL
        $this->assertEquals('OPTIMAL', $balance->stock_status);

        // Update limits: set min_stock to 30 (so 25 is CRITICAL_LOW) and max_stock to 200
        $response = $this->actingAs($this->inventoryUser)->post(route('inventory.balances.limits', $balance->id), [
            'min_stock' => 30,
            'max_stock' => 200,
        ]);

        $response->assertRedirect();
        $balance->refresh();

        $this->assertEquals(30, $balance->min_stock);
        $this->assertEquals(200, $balance->max_stock);
        // Now on_hand (25) < min_stock (30) -> CRITICAL_LOW
        $this->assertEquals('CRITICAL_LOW', $balance->stock_status);

        // Test overstock: on_hand 250 > max_stock 200
        $balance->update(['on_hand' => 250, 'available' => 250]);
        $this->assertEquals('OVERSTOCK', $balance->stock_status);
    }
}
