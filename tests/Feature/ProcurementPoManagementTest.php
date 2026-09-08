<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Tests\TestCase;

class ProcurementPoManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_po_index_renders_successfully_with_consistent_ui(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('procurement.po.index'));

        $response->assertStatus(200);
        $response->assertSee('Purchase Orders (PO)');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertDontSee('Konsolidasikan PR Baru');
        $response->assertSee('table-striped', false);
        $response->assertSee('openViewModal');
        $response->assertDontSee('bi-box-arrow-up-right', false);
        $response->assertSee('Menampilkan');
        $response->assertSee('Baris per halaman');

        // Ensure no info-box or old prototype classes
        $response->assertDontSee('<div class="info-box', false);
        $response->assertDontSee('small-box', false);
        $response->assertDontSee('rounded-2xl', false);
    }

    public function test_po_show_route_is_removed(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        // GET /procurement/po/{id} should return 405 Method Not Allowed as show route is removed and only PUT/DELETE exist
        $response = $this->actingAs($admin)->get('/procurement/po/1');

        $response->assertStatus(405);
    }

    public function test_po_index_search_and_filter(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $vendor = Vendor::where('is_active', true)->first();
        $warehouse = Warehouse::where('is_active', true)->first();
        $item = Item::first();

        $po = PurchaseOrder::create([
            'po_number' => 'PO/TEST/8888',
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'created_by_user_id' => $admin->id,
            'order_date' => now(),
            'expected_delivery_date' => now()->addDays(5),
            'subtotal' => 2000000,
            'tax_amount' => 220000,
            'total_amount' => 2220000,
            'status' => 'ISSUED',
            'notes' => 'PO Khusus Test',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'qty_ordered' => 10,
            'qty_received' => 0,
            'unit_price' => 200000,
            'subtotal' => 2000000,
        ]);

        // Access index without filter
        $res = $this->actingAs($admin)->get(route('procurement.po.index'));
        $res->assertStatus(200);
        $res->assertSee('PO/TEST/8888');

        // Access index with status filter
        $resFilter = $this->actingAs($admin)->get(route('procurement.po.index', ['status' => 'ISSUED']));
        $resFilter->assertStatus(200);
        $resFilter->assertSee('PO/TEST/8888');

        // Access index with search
        $resSearch = $this->actingAs($admin)->get(route('procurement.po.index', ['search' => '8888']));
        $resSearch->assertStatus(200);
        $resSearch->assertSee('PO/TEST/8888');
    }

    public function test_po_index_renders_action_buttons_print_edit_delete(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $vendor = Vendor::where('is_active', true)->first();
        $warehouse = Warehouse::where('is_active', true)->first();
        $item = Item::first();

        $po = PurchaseOrder::create([
            'po_number' => 'PO/TEST/9999',
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'created_by_user_id' => $admin->id,
            'order_date' => now(),
            'expected_delivery_date' => now()->addDays(5),
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
            'status' => 'ISSUED',
            'notes' => 'Test PO Actions',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'qty_ordered' => 5,
            'qty_received' => 0,
            'unit_price' => 200000,
            'subtotal' => 1000000,
        ]);

        $response = $this->actingAs($admin)->get(route('procurement.po.index'));

        $response->assertStatus(200);
        $response->assertSee('bi-printer', false);
        $response->assertSee('bi-pencil-square', false);
        $response->assertSee('bi-trash', false);
        $response->assertSee('openEditModal');
        $response->assertSee('openDeleteModal');
        $response->assertSee(route('procurement.po.print', $po->id));
    }

    public function test_po_print_renders_official_bank_jatim_template(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $vendor = Vendor::where('is_active', true)->first();
        $warehouse = Warehouse::where('is_active', true)->first();
        $item = Item::first();

        $po = PurchaseOrder::create([
            'po_number' => 'PO/PRINT/7777',
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'created_by_user_id' => $admin->id,
            'order_date' => now(),
            'expected_delivery_date' => now()->addDays(3),
            'subtotal' => 5000000,
            'tax_amount' => 550000,
            'total_amount' => 5550000,
            'status' => 'ISSUED',
            'notes' => 'Catatan Pengiriman Khusus',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'qty_ordered' => 10,
            'qty_received' => 0,
            'unit_price' => 500000,
            'subtotal' => 5000000,
        ]);

        $response = $this->actingAs($admin)->get(route('procurement.po.print', $po->id));

        $response->assertStatus(200);
        $response->assertSee('PT BANK PEMBANGUNAN DAERAH JAWA TIMUR TBK');
        $response->assertSee('PO/PRINT/7777');
        $response->assertSee($vendor->name);
        $response->assertSee($warehouse->name);
        $response->assertSee($item->name);
        $response->assertSee('Catatan Pengiriman Khusus');
        $response->assertSee('window.print()', false);
    }

    public function test_po_can_be_updated_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $vendor1 = Vendor::where('is_active', true)->first();
        $vendor2 = Vendor::where('is_active', true)->skip(1)->first() ?? $vendor1;
        $warehouse = Warehouse::where('is_active', true)->first();
        $item = Item::first();

        $po = PurchaseOrder::create([
            'po_number' => 'PO/EDIT/1111',
            'vendor_id' => $vendor1->id,
            'warehouse_id' => $warehouse->id,
            'created_by_user_id' => $admin->id,
            'order_date' => now(),
            'expected_delivery_date' => now()->addDays(5),
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
            'status' => 'ISSUED',
            'notes' => 'Catatan Lama',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'qty_ordered' => 5,
            'qty_received' => 0,
            'unit_price' => 200000,
            'subtotal' => 1000000,
        ]);

        $newDate = now()->addDays(10)->format('Y-m-d');
        $response = $this->actingAs($admin)->put(route('procurement.po.update', $po->id), [
            'vendor_id' => $vendor2->id,
            'warehouse_id' => $warehouse->id,
            'expected_delivery_date' => $newDate,
            'notes' => 'Catatan Baru Telah Diperbarui',
            'items' => [
                [
                    'id' => $poItem->id,
                    'unit_price' => 300000, // 5 * 300,000 = 1,500,000
                ],
            ],
        ]);

        $response->assertRedirect(route('procurement.po.index'));
        $response->assertSessionHas('success');

        $po->refresh();
        $poItem->refresh();

        $this->assertEquals($vendor2->id, $po->vendor_id);
        $this->assertEquals('Catatan Baru Telah Diperbarui', $po->notes);
        $this->assertEquals(300000, $poItem->unit_price);
        $this->assertEquals(1500000, $poItem->subtotal);
        $this->assertEquals(1500000, $po->subtotal);
        $this->assertEquals(165000, $po->tax_amount); // 11% of 1,500,000
        $this->assertEquals(1665000, $po->total_amount);
    }

    public function test_po_can_be_deleted_and_rolls_back_pr_items(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $vendor = Vendor::where('is_active', true)->first();
        $warehouse = Warehouse::where('is_active', true)->first();
        $item = Item::first();
        $org = Organization::first();

        // Create PR
        $pr = PurchaseRequest::create([
            'pr_number' => 'PR/ROLLBACK/001',
            'organization_id' => $org->id,
            'created_by_user_id' => $admin->id,
            'procurement_method' => 'TENDER',
            'purpose' => 'Test Rollback PR',
            'status' => 'FULLY_ORDERED',
            'budget_status' => 'VALIDATED',
            'estimated_total_cost' => 1000000,
        ]);

        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_id' => $item->id,
            'qty_requested' => 10,
            'qty_approved' => 10,
            'qty_ordered' => 10,
            'estimated_unit_price' => 100000,
            'estimated_subtotal' => 1000000,
        ]);

        // Create PO
        $po = PurchaseOrder::create([
            'po_number' => 'PO/ROLLBACK/001',
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'created_by_user_id' => $admin->id,
            'order_date' => now(),
            'expected_delivery_date' => now()->addDays(5),
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
            'status' => 'ISSUED',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'purchase_request_item_id' => $prItem->id,
            'item_id' => $item->id,
            'qty_ordered' => 10,
            'qty_received' => 0,
            'unit_price' => 100000,
            'subtotal' => 1000000,
        ]);

        // Send Delete request
        $response = $this->actingAs($admin)->delete(route('procurement.po.destroy', $po->id));

        $response->assertRedirect(route('procurement.po.index'));
        $response->assertSessionHas('success');

        // Verify PO and PO item are deleted
        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
        $this->assertDatabaseMissing('purchase_order_items', ['id' => $poItem->id]);

        // Verify PR item qty_ordered rolled back to 0
        $prItem->refresh();
        $this->assertEquals(0, $prItem->qty_ordered);

        // Verify PR status reset to APPROVED
        $pr->refresh();
        $this->assertEquals('APPROVED', $pr->status);
    }

    public function test_po_deletion_prevented_if_goods_receipt_exists(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $vendor = Vendor::where('is_active', true)->first();
        $warehouse = Warehouse::where('is_active', true)->first();
        $item = Item::first();

        $po = PurchaseOrder::create([
            'po_number' => 'PO/GR/9999',
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'created_by_user_id' => $admin->id,
            'order_date' => now(),
            'expected_delivery_date' => now()->addDays(5),
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
            'status' => 'PARTIAL_RECEIVED',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'qty_ordered' => 10,
            'qty_received' => 5,
            'unit_price' => 100000,
            'subtotal' => 1000000,
        ]);

        GoodsReceipt::create([
            'grn_number' => 'GRN/TEST/001',
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'received_by_user_id' => $admin->id,
            'receipt_date' => now(),
            'vendor_delivery_note' => 'SJ/001',
            'status' => 'RECEIVED',
        ]);

        $response = $this->actingAs($admin)->delete(route('procurement.po.destroy', $po->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
    }
}
