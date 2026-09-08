<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeTransactionDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_transaction_data_command_wipes_transactions_and_resets_balances_while_preserving_masters(): void
    {
        $org = Organization::create([
            'code' => 'KC-TEST',
            'name' => 'Kantor Cabang Uji',
            'type' => 'MAIN_BRANCH',
            'cost_center_code' => 'CC-TEST',
            'is_active' => true,
        ]);

        $wh = Warehouse::create([
            'organization_id' => $org->id,
            'code' => 'WH-TEST',
            'name' => 'Gudang Uji',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Petugas Logistik',
            'email' => 'logistik.test@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'WAREHOUSE_OFFICER',
            'organization_id' => $org->id,
            'warehouse_id' => $wh->id,
            'is_active' => true,
        ]);

        $cat = Category::create([
            'code' => 'CAT-TEST',
            'name' => 'Kategori Uji',
        ]);

        $item = Item::create([
            'category_id' => $cat->id,
            'sku' => 'TEST-001',
            'name' => 'Barang Uji Coba',
            'uom' => 'PCS',
            'estimated_unit_price' => 50000,
            'is_active' => true,
        ]);

        StockBalance::create([
            'warehouse_id' => $wh->id,
            'item_id' => $item->id,
            'on_hand' => 100,
            'reserved' => 10,
            'damaged' => 5,
        ]);

        StockLedger::create([
            'warehouse_id' => $wh->id,
            'item_id' => $item->id,
            'transaction_type' => 'PROCUREMENT_RECEIPT',
            'reference_number' => 'REF-001',
            'qty_in' => 100,
            'qty_out' => 0,
            'balance_after' => 100,
            'unit_cost' => 50000,
            'total_value' => 5000000,
        ]);

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR/2026/09/9999',
            'organization_id' => $org->id,
            'created_by_user_id' => $user->id,
            'procurement_method' => 'DIRECT',
            'purpose' => 'Pengadaan Uji',
            'estimated_total_cost' => 500000,
            'status' => 'APPROVED',
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_id' => $item->id,
            'qty_requested' => 10,
            'qty_approved' => 10,
            'estimated_unit_price' => 50000,
            'estimated_subtotal' => 500000,
        ]);

        $order = Order::create([
            'order_number' => 'ORD/2026/09/9999',
            'requesting_organization_id' => $org->id,
            'requesting_warehouse_id' => $wh->id,
            'created_by_user_id' => $user->id,
            'priority' => 'NORMAL',
            'total_items' => 1,
            'total_estimated_value' => 250000,
            'status' => 'COMPLETED',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $item->id,
            'qty_requested' => 5,
            'qty_approved' => 5,
            'unit_price_ref' => 50000,
            'subtotal_ref' => 250000,
        ]);

        $this->assertDatabaseCount('purchase_requests', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_ledgers', 1);

        $this->artisan('transactions:purge --force')
            ->assertSuccessful();

        // Transaction tables must be empty
        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('purchase_request_items', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('stock_ledgers', 0);

        // Master records preserved
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('warehouses', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('items', 1);

        // Stock balance reset to 0
        $balance = StockBalance::where('warehouse_id', $wh->id)->where('item_id', $item->id)->first();
        $this->assertNotNull($balance);
        $this->assertEquals(0, $balance->on_hand);
        $this->assertEquals(0, $balance->reserved);
        $this->assertEquals(0, $balance->damaged);
    }
}
