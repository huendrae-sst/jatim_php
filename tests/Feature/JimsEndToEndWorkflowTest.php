<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Item;
use App\Models\Order;
use App\Models\StockLedger;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\OrderFulfillmentService;
use App\Services\ProcurementService;
use App\Services\SettlementService;
use App\Services\StockLedgerService;
use App\Services\SwitchingStockService;
use Tests\TestCase;

class JimsEndToEndWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    /**
     * Skenario A: Multi-PR Consolidation into 1 PO & Goods Receipt (GRN) into Stock Ledger
     */
    public function test_skenario_a_pr_consolidation_and_goods_receipt_into_stock_ledger(): void
    {
        $procOfficer = User::where('role', 'PROCUREMENT_OFFICER')->first();
        $procApprover = User::where('role', 'PROCUREMENT_APPROVER')->first();
        $warehouseOfficer = User::where('role', 'WAREHOUSE_OFFICER')->first();
        $vendor = Vendor::first();
        $whCentral = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
        $item = Item::first();

        $procService = app(ProcurementService::class);
        $stockService = app(StockLedgerService::class);

        $initialBalance = $stockService->getOrCreateBalance($whCentral, $item)->on_hand;

        // 1. Create 2 Separate Approved PRs
        $prA = $procService->createPurchaseRequest(
            $whCentral->organization_id,
            'DIRECT',
            'Kebutuhan Cabang A - 50 Unit',
            [['item_id' => $item->id, 'qty' => 50, 'unit_price' => 750000]],
            $procOfficer
        );
        $procService->approvePurchaseRequest($prA, $procApprover);

        $prB = $procService->createPurchaseRequest(
            $whCentral->organization_id,
            'DIRECT',
            'Kebutuhan Cabang B - 75 Unit',
            [['item_id' => $item->id, 'qty' => 75, 'unit_price' => 750000]],
            $procOfficer
        );
        $procService->approvePurchaseRequest($prB, $procApprover);

        // 2. Consolidate into 1 PO (Total 125 units)
        $prItemA = $prA->items->first();
        $prItemB = $prB->items->first();

        $po = $procService->consolidatePRsToPO(
            [
                ['pr_item_id' => $prItemA->id, 'qty' => 50, 'unit_price' => 750000],
                ['pr_item_id' => $prItemB->id, 'qty' => 75, 'unit_price' => 750000],
            ],
            $vendor->id,
            $whCentral->id,
            date('Y-m-d', strtotime('+5 days')),
            'Konsolidasi Pengadaan PR A & B',
            $procOfficer
        );

        $this->assertEquals(2, $po->items->count());
        $expectedTotal = (float) round(125 * 750000 * 1.11, 2);
        $this->assertEquals($expectedTotal, (float) $po->total_amount);
        $this->assertEquals('FULLY_ORDERED', $prA->fresh()->status);
        $this->assertEquals('FULLY_ORDERED', $prB->fresh()->status);
        $this->assertEquals('WAITING_APPROVAL', $po->status);

        // Approve Purchase Order
        $procService->approvePurchaseOrder($po, $procApprover);
        $this->assertEquals('ISSUED', $po->fresh()->status);

        // 3. Receive Goods from Vendor (GRN)
        $poItems = $po->items;
        $grn = $procService->processGoodsReceipt(
            $po,
            [
                ['po_item_id' => $poItems[0]->id, 'qty_accepted' => 50, 'qty_rejected' => 0],
                ['po_item_id' => $poItems[1]->id, 'qty_accepted' => 75, 'qty_rejected' => 0],
            ],
            'SJ/VND/TEST-001',
            $warehouseOfficer
        );

        $this->assertEquals('COMPLETED', $po->fresh()->status);

        // 4. Verify Stock Ledger & Balance
        $newBalance = $stockService->getOrCreateBalance($whCentral, $item)->on_hand;
        $this->assertEquals($initialBalance + 125, $newBalance);

        $latestLedger = StockLedger::where('reference_number', $po->po_number)->latest()->first();
        $this->assertNotNull($latestLedger);
        $this->assertEquals('PROCUREMENT_RECEIPT', $latestLedger->transaction_type);
    }

    /**
     * Skenario B: Branch Order ➔ Fulfillment ➔ Manifest/Shipment ➔ Receiving ➔ Financial Settlement
     */
    public function test_skenario_b_order_fulfillment_shipment_and_settlement(): void
    {
        $requester = User::where('role', 'REQUESTER_CABANG')->first();
        $approver = User::where('role', 'ORDER_APPROVER')->first();
        $whOfficer = User::where('role', 'WAREHOUSE_OFFICER')->first();
        $distOfficer = User::where('role', 'DISTRIBUTION_OFFICER')->first();
        $rcvOfficer = User::where('role', 'RECEIVING_OFFICER')->first();
        $finApprover = User::where('role', 'FINANCE_APPROVER')->first();
        $courier = Courier::first();
        $item = Item::where('sku', 'ATK-KRT-001')->first();

        $orderService = app(OrderFulfillmentService::class);
        $settlementService = app(SettlementService::class);

        // 1. Create Order
        $order = $orderService->createOrder(
            $requester->organization_id,
            $requester->warehouse_id,
            'NORMAL',
            date('Y-m-d', strtotime('+3 days')),
            'Order rutin bulanan test',
            [['item_id' => $item->id, 'qty' => 10]],
            $requester
        );
        $this->assertEquals('SUBMITTED', $order->status);

        // 2. Approve & Reserve Stock
        $order = $orderService->approveOrder($order, $approver);
        $this->assertEquals('ALLOCATED', $order->fresh()->status);
        $this->assertEquals(10, $order->fresh()->items->first()->qty_allocated);

        // 3. Picking
        $picking = $orderService->generatePicking($order, $whOfficer);
        $this->assertEquals('PICKING', $order->fresh()->status);

        // 4. Packing
        $packing = $orderService->generatePacking($order, 2, 25.0, '50x40x30 cm', $whOfficer);
        $this->assertEquals('READY_TO_SHIP', $order->fresh()->status);

        // 5. Dispatch & Manifest
        $shipment = $orderService->createShipment(
            $order,
            $courier->id,
            'REGULER',
            'EXP-TEST-999',
            100000,
            date('Y-m-d', strtotime('+2 days')),
            $distOfficer
        );
        $this->assertEquals('IN_TRANSIT', $order->fresh()->status);
        $this->assertEquals('IN_TRANSIT', $shipment->status);

        // 6. Branch Receiving
        $rcv = $orderService->processReceiving(
            $shipment,
            [['order_item_id' => $order->items->first()->id, 'qty_good' => 10, 'qty_damaged' => 0, 'qty_missing' => 0]],
            'Siti Nurhaliza (KCP Gubeng)',
            'Penerimaan lengkap sesuai',
            $rcvOfficer
        );
        $this->assertEquals('RECEIVED', $order->fresh()->status);
        $this->assertEquals('DELIVERED', $shipment->fresh()->status);

        // 7. Finance Settlement
        $settlement = $settlementService->createSettlementForOrder($order, $requester);
        $this->assertEquals('WAITING_APPROVAL', $settlement->status);

        $settlement = $settlementService->approveAndPostSettlement($settlement, $finApprover);
        $this->assertEquals('POSTED', $settlement->status);
        $this->assertEquals('COMPLETED', $order->fresh()->status);
    }

    /**
     * Skenario C: Switching Stock (Alternative Branch Sourcing)
     */
    public function test_skenario_c_switching_stock_recommendation_and_approval(): void
    {
        $switchingService = app(SwitchingStockService::class);
        $inventoryOfficer = User::where('role', 'INVENTORY_OFFICER')->first();
        $approver = User::where('role', 'ORDER_APPROVER')->first();
        $item = Item::where('sku', 'IT-TNR-001')->first(); // Toner (KC Malang has excess stock in seeder)
        $order = Order::first();

        // 1. Find Alternative Sources
        $recommendations = $switchingService->findAlternativeSources($item, 40, $order->requesting_organization_id);
        $this->assertNotEmpty($recommendations);

        $bestSource = $recommendations[0];
        $this->assertGreaterThan(0, $bestSource['excess_stock']);

        // 2. Propose Switching Stock
        $switching = $switchingService->proposeSwitching(
            $order,
            $item,
            $bestSource['warehouse_id'],
            20,
            'Pemenuhan darurat dari surplus KC Malang',
            $inventoryOfficer
        );
        $this->assertEquals('PROPOSED', $switching->status);

        // 3. Approve Switching Stock
        $switching = $switchingService->approveSwitching($switching, $approver);
        $this->assertEquals('APPROVED', $switching->status);
    }

    /**
     * Skenario E: Reports & CSV Export Accessibility
     */
    public function test_skenario_e_reports_and_csv_export(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get('/reports');
        $response->assertStatus(200);

        $responseValuation = $this->actingAs($admin)->get('/reports/stock-valuation');
        $responseValuation->assertStatus(200);

        $responseCsv = $this->actingAs($admin)->get('/reports/stock-valuation/export-csv');
        $responseCsv->assertStatus(200);
        $responseCsv->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $responseCoverage = $this->actingAs($admin)->get('/reports/procurement-coverage');
        $responseCoverage->assertStatus(200);

        $responseSettlements = $this->actingAs($admin)->get('/reports/settlements');
        $responseSettlements->assertStatus(200);
    }

    /**
     * Skenario F: Stock Opname Physical Count Reconciliation
     */
    public function test_skenario_f_stock_opname_reconciliation(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $whCentral = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
        $item = Item::first();

        $stockService = app(StockLedgerService::class);
        $currentOnHand = $stockService->getOrCreateBalance($whCentral, $item)->on_hand;

        $response = $this->actingAs($admin)->post('/inventory/stock-opname', [
            'warehouse_id' => $whCentral->id,
            'opname_notes' => 'Stock opname kuartal II test audit',
            'counts' => [
                [
                    'item_id' => $item->id,
                    'system_qty' => $currentOnHand,
                    'physical_qty' => $currentOnHand + 5, // 5 surplus units found
                ],
            ],
        ]);

        $response->assertRedirect('/inventory/stock-balances?warehouse_id='.$whCentral->id);
        $this->assertEquals($currentOnHand + 5, $stockService->getOrCreateBalance($whCentral, $item)->on_hand);

        $opnameLedger = StockLedger::where('transaction_type', 'STOCK_OPNAME')->latest()->first();
        $this->assertNotNull($opnameLedger);
        $this->assertEquals(5, $opnameLedger->qty_in);
    }
}
