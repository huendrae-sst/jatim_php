<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Tests\TestCase;

class PoReceivingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_po_receiving_page_renders_successfully(): void
    {
        $warehouseUser = User::where('role', 'WAREHOUSE_OFFICER')->first()
            ?? User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($warehouseUser)->get(route('receiving.po.index'));

        $response->assertStatus(200);
        $response->assertSee('Penerimaan Barang PO');
        $response->assertSee('Antrean Penerimaan PO');
        $response->assertSee('Riwayat Dokumen Penerimaan (GRN)');
        $response->assertSee('poReceivingManager');
        $response->assertSee(route('receiving.po.index'));

        // Ensure info boxes are removed
        $response->assertDontSee('<div class="info-box', false);
        $response->assertDontSee('PO Siap Diterima');
        $response->assertDontSee('Total GRN Terbit');

        // Ensure compact icon button is used
        $response->assertSee('btn-action-icon', false);
        $response->assertSee('bi-box-arrow-in-down', false);
    }

    public function test_po_receiving_history_tab_renders_successfully(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('receiving.po.index', ['tab' => 'history']));

        $response->assertStatus(200);
        $response->assertSee('Riwayat Dokumen Penerimaan (GRN)');
        $response->assertSee('No. Surat Jalan Vendor');
        $response->assertSee('Petugas QC');
    }

    public function test_can_process_goods_receipt_from_vendor_po(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $vendor = Vendor::where('is_active', true)->first();
        $warehouse = Warehouse::where('is_active', true)->first();
        $item = Item::first();

        $po = PurchaseOrder::create([
            'po_number' => 'PO/TEST/RCV/0001',
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'created_by_user_id' => $user->id,
            'approved_by_user_id' => $user->id,
            'order_date' => now(),
            'expected_delivery_date' => now()->addDays(3),
            'subtotal' => 500000,
            'tax_amount' => 55000,
            'total_amount' => 555000,
            'status' => 'ISSUED',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_price' => 25000,
            'subtotal' => 500000,
        ]);

        $initialBalance = StockBalance::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'item_id' => $item->id],
            ['on_hand' => 0, 'reserved' => 0, 'allocated' => 0, 'in_transit' => 0, 'hold' => 0, 'damaged' => 0]
        );
        $initialQty = $initialBalance->on_hand;

        $response = $this->actingAs($user)->post(route('receiving.po.receive', $po->id), [
            'vendor_delivery_note_number' => 'SJ-VENDOR-9988',
            'qc_notes' => 'Kondisi kemasan rapi dan tersegel',
            'items' => [
                [
                    'po_item_id' => $poItem->id,
                    'qty_accepted' => 15,
                    'qty_rejected' => 2,
                    'notes' => '15 lolos QC, 2 rusak di dus',
                ],
            ],
        ]);

        $response->assertRedirect(route('receiving.po.index', ['tab' => 'history']));
        $response->assertSessionHas('success');

        // Check GoodsReceipt created
        $this->assertDatabaseHas('goods_receipts', [
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouse->id,
            'vendor_delivery_note_number' => 'SJ-VENDOR-9988',
            'status' => 'RECEIVED',
        ]);

        $grn = GoodsReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($grn);

        // Check GoodsReceiptItem
        $this->assertDatabaseHas('goods_receipt_items', [
            'goods_receipt_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'item_id' => $item->id,
            'qty_received' => 17,
            'qty_accepted' => 15,
            'qty_rejected' => 2,
        ]);

        // Check PO Item updated
        $poItem->refresh();
        $this->assertEquals(15, $poItem->qty_received);
        $this->assertEquals(5, $poItem->outstanding_qty);

        // Check PO status is PARTIAL_RECEIVED because 15 < 20
        $po->refresh();
        $this->assertEquals('PARTIAL_RECEIVED', $po->status);

        // Check StockLedger has PROCUREMENT_RECEIPT
        $this->assertDatabaseHas('stock_ledgers', [
            'warehouse_id' => $warehouse->id,
            'item_id' => $item->id,
            'transaction_type' => 'PROCUREMENT_RECEIPT',
            'qty_in' => 15,
        ]);

        // Check StockBalance updated
        $initialBalance->refresh();
        $this->assertEquals($initialQty + 15, $initialBalance->on_hand);
    }

    public function test_receiving_requires_valid_delivery_note_and_quantities(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $vendor = Vendor::where('is_active', true)->first();
        $warehouse = Warehouse::where('is_active', true)->first();
        $item = Item::first();

        $po = PurchaseOrder::create([
            'po_number' => 'PO/TEST/VAL/0002',
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'created_by_user_id' => $user->id,
            'order_date' => now(),
            'expected_delivery_date' => now()->addDays(3),
            'subtotal' => 100000,
            'tax_amount' => 11000,
            'total_amount' => 111000,
            'status' => 'ISSUED',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id' => $item->id,
            'qty_ordered' => 10,
            'qty_received' => 0,
            'unit_price' => 10000,
            'subtotal' => 100000,
        ]);

        // Missing delivery note
        $response = $this->actingAs($user)->post(route('receiving.po.receive', $po->id), [
            'vendor_delivery_note_number' => '',
            'items' => [
                [
                    'po_item_id' => $poItem->id,
                    'qty_accepted' => 5,
                ],
            ],
        ]);
        $response->assertSessionHasErrors('vendor_delivery_note_number');

        // Total received exceeds outstanding
        $responseOver = $this->actingAs($user)->post(route('receiving.po.receive', $po->id), [
            'vendor_delivery_note_number' => 'SJ-12345',
            'items' => [
                [
                    'po_item_id' => $poItem->id,
                    'qty_accepted' => 15, // > 10
                ],
            ],
        ]);
        $responseOver->assertSessionHas('error');
    }
}
