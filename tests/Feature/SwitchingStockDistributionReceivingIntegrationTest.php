<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Courier;
use App\Models\Discrepancy;
use App\Models\Item;
use App\Models\Organization;
use App\Models\Receiving;
use App\Models\Shipment;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\SwitchingStock;
use App\Models\SwitchingStockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SwitchingStockDistributionReceivingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $warehouseOfficer;

    protected User $branchOfficer;

    protected Organization $orgSource;

    protected Organization $orgDest;

    protected Warehouse $whSource;

    protected Warehouse $whDest;

    protected Item $item;

    protected Courier $courier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $this->warehouseOfficer = User::factory()->create([
            'role' => 'WAREHOUSE_OFFICER',
        ]);

        $this->orgSource = Organization::create([
            'code' => 'KC-GUBENG',
            'name' => 'Kantor Cabang Gubeng',
            'type' => 'MAIN_BRANCH',
            'city' => 'Surabaya',
            'cost_center_code' => 'CC-GUBENG',
            'is_active' => true,
        ]);

        $this->orgDest = Organization::create([
            'code' => 'KC-MALANG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'MAIN_BRANCH',
            'city' => 'Malang',
            'cost_center_code' => 'CC-MALANG',
            'is_active' => true,
        ]);

        $this->branchOfficer = User::factory()->create([
            'role' => 'BRANCH_OFFICER',
            'organization_id' => $this->orgDest->id,
        ]);

        $this->whSource = Warehouse::create([
            'organization_id' => $this->orgSource->id,
            'code' => 'WH-GUBENG',
            'name' => 'Gudang Cabang Gubeng',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $this->whDest = Warehouse::create([
            'organization_id' => $this->orgDest->id,
            'code' => 'WH-MALANG',
            'name' => 'Gudang Cabang Malang',
            'type' => 'BRANCH_STORAGE',
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'OPS',
            'name' => 'Operasional Kantor',
        ]);

        $this->item = Item::create([
            'category_id' => $category->id,
            'sku' => 'OPS-PASSBOOK-01',
            'name' => 'Buku Tabungan Simpeda',
            'uom' => 'Buku',
            'estimated_unit_price' => 5000,
            'safety_stock' => 50,
            'minimum_order_qty' => 10,
            'is_active' => true,
        ]);

        $this->courier = Courier::create([
            'code' => 'JNE',
            'name' => 'JNE Express',
            'service_types' => ['REGULER', 'YES'],
            'sla_days' => 2,
            'is_active' => true,
        ]);

        // Stock at source: on_hand 100, reserved 20
        StockBalance::create([
            'warehouse_id' => $this->whSource->id,
            'item_id' => $this->item->id,
            'on_hand' => 100,
            'reserved' => 20,
            'allocated' => 0,
            'in_transit' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);

        // Stock at destination: on_hand 10
        StockBalance::create([
            'warehouse_id' => $this->whDest->id,
            'item_id' => $this->item->id,
            'on_hand' => 10,
            'reserved' => 0,
            'allocated' => 0,
            'in_transit' => 0,
            'hold' => 0,
            'damaged' => 0,
        ]);
    }

    protected function createApprovedSwitching(int $qty = 20): SwitchingStock
    {
        $switching = SwitchingStock::create([
            'transfer_number' => 'SW-TEST-'.rand(1000, 9999),
            'source_organization_id' => $this->orgSource->id,
            'source_warehouse_id' => $this->whSource->id,
            'destination_organization_id' => $this->orgDest->id,
            'destination_warehouse_id' => $this->whDest->id,
            'item_id' => $this->item->id,
            'qty_requested' => $qty,
            'proposed_by_user_id' => $this->superAdmin->id,
            'approved_by_user_id' => $this->superAdmin->id,
            'status' => 'APPROVED',
            'recommendation_reason' => 'Pemenuhan stok cabang Malang',
        ]);

        SwitchingStockItem::create([
            'switching_stock_id' => $switching->id,
            'item_id' => $this->item->id,
            'qty_requested' => $qty,
            'qty_transferred' => 0,
            'qty_received' => 0,
        ]);

        return $switching;
    }

    public function test_ready_switching_stock_appears_in_distribution_shipments_page(): void
    {
        $switching = $this->createApprovedSwitching(20);

        $response = $this->actingAs($this->warehouseOfficer)
            ->get(route('distribution.shipments.index'));

        $response->assertStatus(200);
        $response->assertSee('Transfer Switching Stock Siap Dikirim');
        $response->assertSee($this->whSource->name);
        $response->assertSee($this->orgDest->name);
        $response->assertSee('Switching #'.$switching->id);
    }

    public function test_can_dispatch_switching_stock_via_distribution_module(): void
    {
        $switching = $this->createApprovedSwitching(20);

        $response = $this->actingAs($this->warehouseOfficer)
            ->post(route('distribution.shipments.store'), [
                'switching_stock_id' => $switching->id,
                'courier_id' => $this->courier->id,
                'service_type' => 'REGULER',
                'tracking_number' => 'AWB-SW-998877',
                'shipping_cost' => 75000,
                'eta_date' => now()->addDays(2)->format('Y-m-d'),
                'koli_count' => 2,
                'total_weight_kg' => 5.5,
                'notes' => 'Pengiriman buku tabungan antar-cabang',
            ]);

        $response->assertRedirect(route('distribution.shipments.index'));
        $response->assertSessionHas('success');

        // Verify Shipment record
        $shipment = Shipment::where('switching_stock_id', $switching->id)->first();
        $this->assertNotNull($shipment);
        $this->assertStringStartsWith('MNF/', $shipment->manifest_number);
        $this->assertEquals('AWB-SW-998877', $shipment->tracking_number);
        $this->assertEquals('IN_TRANSIT', $shipment->status);
        $this->assertEquals($this->whSource->id, $shipment->origin_warehouse_id);
        $this->assertEquals($this->orgDest->id, $shipment->destination_organization_id);

        // Verify Switching Stock status
        $switching->refresh();
        $this->assertEquals('TRANSFERRED', $switching->status);
        $this->assertEquals($shipment->id, $switching->shipment_id);
        $this->assertEquals($this->warehouseOfficer->id, $switching->transferred_by_user_id);
        $this->assertNotNull($switching->transferred_at);

        // Verify Ledger Mutation at Source Warehouse (TRANSFER_OUT)
        $ledger = StockLedger::where('warehouse_id', $this->whSource->id)
            ->where('item_id', $this->item->id)
            ->where('transaction_type', 'TRANSFER_OUT')
            ->latest('id')
            ->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(20, $ledger->qty_out);
        $this->assertEquals(0, $ledger->qty_in);

        // Verify Source StockBalance on_hand decreased
        $sourceBal = StockBalance::where('warehouse_id', $this->whSource->id)->where('item_id', $this->item->id)->first();
        $this->assertEquals(80, $sourceBal->on_hand);
    }

    public function test_switching_stock_shipment_appears_in_receiving_incoming_tab(): void
    {
        $switching = $this->createApprovedSwitching(20);

        // Dispatch via OrderFulfillmentService
        /** @var OrderFulfillmentService $fulfillmentService */
        $fulfillmentService = app(OrderFulfillmentService::class);
        $shipment = $fulfillmentService->createSwitchingShipment(
            $switching,
            $this->courier->id,
            'REGULER',
            'AWB-INCOMING-123',
            50000,
            now()->addDays(2)->format('Y-m-d'),
            $this->warehouseOfficer
        );

        $response = $this->actingAs($this->branchOfficer)
            ->get(route('receiving.index', ['tab' => 'incoming']));

        $response->assertStatus(200);
        $response->assertSee($shipment->manifest_number);
        $response->assertSee('TRANSFER SWITCHING');
        $response->assertSee($this->whSource->name);
    }

    public function test_can_confirm_receipt_of_switching_shipment_in_receiving_module(): void
    {
        $switching = $this->createApprovedSwitching(20);
        $switchingItem = $switching->items->first();

        /** @var OrderFulfillmentService $fulfillmentService */
        $fulfillmentService = app(OrderFulfillmentService::class);
        $shipment = $fulfillmentService->createSwitchingShipment(
            $switching,
            $this->courier->id,
            'REGULER',
            'AWB-RCV-001',
            50000,
            now()->addDays(2)->format('Y-m-d'),
            $this->warehouseOfficer
        );

        // Access confirm form
        $formResponse = $this->actingAs($this->branchOfficer)
            ->get(route('receiving.confirm.form', $shipment->id));
        $formResponse->assertStatus(200);
        $formResponse->assertSee($shipment->manifest_number);
        $formResponse->assertSee($this->item->name);
        $formResponse->assertSee('switching_stock_item_id');

        // Submit confirm receipt: 20 good, 0 damaged, 0 missing
        $postResponse = $this->actingAs($this->branchOfficer)
            ->post(route('receiving.confirm.store', $shipment->id), [
                'pod_signature' => 'Budi Santoso (Petugas Cabang Malang)',
                'notes' => 'Barang diterima lengkap dan rapi',
                'items' => [
                    [
                        'switching_stock_item_id' => $switchingItem->id,
                        'qty_good' => 20,
                        'qty_damaged' => 0,
                        'qty_missing' => 0,
                    ],
                ],
            ]);

        $postResponse->assertRedirect(route('receiving.index'));
        $postResponse->assertSessionHas('success');

        // Verify Receiving record
        $receiving = Receiving::where('shipment_id', $shipment->id)->first();
        $this->assertNotNull($receiving);
        $this->assertStringStartsWith('RCV/', $receiving->receiving_number);
        $this->assertEquals('RECEIVED_FULL', $receiving->status);
        $this->assertEquals($switching->id, $receiving->switching_stock_id);

        // Verify Shipment and SwitchingStock transitioned to DELIVERED / COMPLETED
        $shipment->refresh();
        $this->assertEquals('DELIVERED', $shipment->status);

        $switching->refresh();
        $this->assertEquals('COMPLETED', $switching->status);
        $this->assertEquals($this->branchOfficer->id, $switching->received_by_user_id);
        $this->assertNotNull($switching->received_at);

        // Verify Destination Warehouse Stock Balance increased (+20)
        $destBal = StockBalance::where('warehouse_id', $this->whDest->id)->where('item_id', $this->item->id)->first();
        $this->assertEquals(30, $destBal->on_hand); // 10 initial + 20 received

        // Verify Stock Ledger Mutation at Destination Warehouse (TRANSFER_IN)
        $destLedger = StockLedger::where('warehouse_id', $this->whDest->id)
            ->where('item_id', $this->item->id)
            ->where('transaction_type', 'TRANSFER_IN')
            ->latest('id')
            ->first();

        $this->assertNotNull($destLedger);
        $this->assertEquals(20, $destLedger->qty_in);
        $this->assertEquals(0, $destLedger->qty_out);
    }

    public function test_discrepancy_created_if_switching_items_damaged_or_missing(): void
    {
        $switching = $this->createApprovedSwitching(20);
        $switchingItem = $switching->items->first();

        /** @var OrderFulfillmentService $fulfillmentService */
        $fulfillmentService = app(OrderFulfillmentService::class);
        $shipment = $fulfillmentService->createSwitchingShipment(
            $switching,
            $this->courier->id,
            'REGULER',
            'AWB-DISCREPANCY-001',
            50000,
            now()->addDays(2)->format('Y-m-d'),
            $this->warehouseOfficer
        );

        // Receive: 15 good, 3 damaged, 2 missing
        $response = $this->actingAs($this->branchOfficer)
            ->post(route('receiving.confirm.store', $shipment->id), [
                'pod_signature' => 'Budi Santoso',
                'notes' => 'Terdapat buku basah dan paket sobek',
                'berita_acara_pdf' => UploadedFile::fake()->create('berita_acara_rusak.pdf', 100, 'application/pdf'),
                'items' => [
                    [
                        'switching_stock_item_id' => $switchingItem->id,
                        'qty_good' => 15,
                        'qty_damaged' => 3,
                        'qty_missing' => 2,
                    ],
                ],
            ]);

        $response->assertRedirect(route('receiving.index'));

        // Receiving record status DISCREPANCY
        $receiving = Receiving::where('shipment_id', $shipment->id)->first();
        $this->assertNotNull($receiving);
        $this->assertEquals('DISCREPANCY', $receiving->status);

        // Discrepancies created
        $discrepancies = Discrepancy::where('receiving_id', $receiving->id)->get();
        $this->assertCount(2, $discrepancies);

        $damaged = $discrepancies->where('discrepancy_type', 'DAMAGED')->first();
        $this->assertNotNull($damaged);
        $this->assertEquals(3, $damaged->qty_damaged);
        $this->assertEquals($switchingItem->id, $damaged->switching_stock_item_id);

        $missing = $discrepancies->where('discrepancy_type', 'MISSING')->first();
        $this->assertNotNull($missing);
        $this->assertEquals(2, $missing->qty_damaged);

        // Destination warehouse on_hand increased only by qty_good (15)
        $destBal = StockBalance::where('warehouse_id', $this->whDest->id)->where('item_id', $this->item->id)->first();
        $this->assertEquals(25, $destBal->on_hand); // 10 initial + 15 good
    }
}
