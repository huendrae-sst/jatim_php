<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\NotificationService;
use App\Services\OrderFulfillmentService;
use App\Services\ProcurementService;
use App\Services\SettlementService;
use App\Services\SwitchingStockService;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    /**
     * Test notification scoping, isolation, and privacy.
     */
    public function test_notification_scoping_and_privacy_isolation(): void
    {
        $branchSby = Organization::where('code', 'KC-SBY')->first();
        $branchMlg = Organization::where('code', 'KC-MLG')->first();

        $requesterSby = User::where('role', 'REQUESTER_CABANG')->where('organization_id', $branchSby->id)->first();
        $approverSby = User::where('role', 'ORDER_APPROVER')->where('organization_id', $branchSby->id)->first();

        // Ensure approver for Malang exists
        $approverMlg = User::firstOrCreate(
            ['email' => 'approver.mlg@bankjatim.co.id'],
            [
                'name' => 'Approver Malang',
                'role' => 'ORDER_APPROVER',
                'nip' => 'BJ-20002',
                'organization_id' => $branchMlg->id,
                'password' => bcrypt('password123'),
                'is_active' => true,
                'approval_limit' => 200000000,
            ]
        );

        $superAdmin = User::where('role', 'SUPER_ADMIN')->first();

        // 1. Private personal notification for requesterSby
        $personalNotif = NotificationService::sendUser(
            $requesterSby->id,
            'Privat untuk Requester SBY',
            'Pesan ini hanya untuk requester Surabaya.'
        );

        $this->assertTrue(Notification::forUser($requesterSby)->where('id', $personalNotif->id)->exists());
        $this->assertFalse(Notification::forUser($approverSby)->where('id', $personalNotif->id)->exists());
        $this->assertFalse(Notification::forUser($approverMlg)->where('id', $personalNotif->id)->exists());

        // 2. Branch-scoped Approver notification for Surabaya
        $branchNotif = NotificationService::sendActionRequired(
            'Approval Cabang Surabaya',
            'Order baru butuh approval approver Surabaya.',
            'ORDER_APPROVER',
            $branchSby->id
        );

        // Approver SBY should see it
        $this->assertTrue(Notification::forUser($approverSby)->where('id', $branchNotif->id)->exists());
        // Approver Malang must NOT see it
        $this->assertFalse(Notification::forUser($approverMlg)->where('id', $branchNotif->id)->exists());
        // Requester in same branch must NOT see it (different role)
        $this->assertFalse(Notification::forUser($requesterSby)->where('id', $branchNotif->id)->exists());
        // Super Admin can see it
        $this->assertTrue(Notification::forUser($superAdmin)->where('id', $branchNotif->id)->exists());
    }

    /**
     * Test Procurement Workflow notifications (PR Create, Approve, Reject, Consolidate, PO Approve, Reject, GRN).
     */
    public function test_procurement_workflow_notifications(): void
    {
        $procOfficer = User::where('role', 'PROCUREMENT_OFFICER')->first();
        $procApprover = User::where('role', 'PROCUREMENT_APPROVER')->first();
        $warehouseOfficer = User::where('role', 'WAREHOUSE_OFFICER')->first();
        $vendor = Vendor::first();
        $whCentral = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
        $item = Item::first();

        $procService = app(ProcurementService::class);

        // 1. Create PR -> Action Required to PROCUREMENT_APPROVER
        $initialNotifCount = Notification::count();
        $pr = $procService->createPurchaseRequest(
            $whCentral->organization_id,
            'DIRECT',
            'Pengadaan Toner Printer',
            [['item_id' => $item->id, 'qty' => 40, 'unit_price' => 500000]],
            $procOfficer
        );

        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'PROCUREMENT_APPROVER')
                ->where('reference_transaction_type', 'PR')
                ->where('reference_transaction_id', $pr->id)
                ->exists()
        );

        // 2. Approve PR -> Info to creator & Info to PROCUREMENT_OFFICER
        $procService->approvePurchaseRequest($pr, $procApprover);

        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $procOfficer->id)
                ->where('title', 'Purchase Request Disetujui')
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('target_role', 'PROCUREMENT_OFFICER')
                ->where('title', 'PR Siap Dikonsolidasi')
                ->exists()
        );

        // 3. Consolidate to PO -> Action Required to PROCUREMENT_APPROVER
        $prItem = $pr->items->first();
        $po = $procService->consolidatePRsToPO(
            [['pr_item_id' => $prItem->id, 'qty' => 40, 'unit_price' => 500000]],
            $vendor->id,
            $whCentral->id,
            date('Y-m-d', strtotime('+7 days')),
            'PO Konsolidasi Pengadaan',
            $procOfficer
        );

        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'PROCUREMENT_APPROVER')
                ->where('reference_transaction_type', 'PO')
                ->where('reference_transaction_id', $po->id)
                ->exists()
        );

        // 4. Approve PO -> Info to creator & Info to WAREHOUSE_OFFICER
        $procService->approvePurchaseOrder($po, $procApprover);

        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $procOfficer->id)
                ->where('title', 'Purchase Order Disetujui & Diterbitkan')
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('target_role', 'WAREHOUSE_OFFICER')
                ->where('title', 'PO Baru Diterbitkan - Persiapan Penerimaan')
                ->exists()
        );

        // 5. Receive Goods from Vendor with a rejected item -> Alert Discrepancy
        $poItem = $po->items->first();
        $grn = $procService->processGoodsReceipt(
            $po,
            [['po_item_id' => $poItem->id, 'qty_accepted' => 35, 'qty_rejected' => 5, 'notes' => '5 rusak kemasan']],
            'DO/VND/9991',
            $warehouseOfficer
        );

        $this->assertTrue(
            Notification::where('type', 'ALERT')
                ->where('target_role', 'PROCUREMENT_OFFICER')
                ->where('title', 'like', '%Discrepancy%')
                ->exists()
        );
    }

    /**
     * Test Branch Order and fulfillment lifecycle notifications.
     */
    public function test_branch_order_and_fulfillment_workflow_notifications(): void
    {
        $branch = Organization::where('code', 'KC-SBY')->first();
        $whBranch = Warehouse::where('organization_id', $branch->id)->first();
        $requester = User::where('role', 'REQUESTER_CABANG')->where('organization_id', $branch->id)->first();
        $approver = User::where('role', 'ORDER_APPROVER')->where('organization_id', $branch->id)->first();
        $warehouseOfficer = User::where('role', 'WAREHOUSE_OFFICER')->first();
        $distOfficer = User::where('role', 'DISTRIBUTION_OFFICER')->first();

        $rcvOfficer = User::firstOrCreate(
            ['email' => 'receiving.sby@bankjatim.co.id'],
            [
                'name' => 'Receiving Surabaya',
                'role' => 'RECEIVING_OFFICER',
                'nip' => 'BJ-10003',
                'organization_id' => $branch->id,
                'warehouse_id' => $whBranch?->id,
                'password' => bcrypt('password123'),
                'is_active' => true,
                'approval_limit' => 0,
            ]
        );

        $courier = Courier::first();
        $item = Item::first();

        $orderService = app(OrderFulfillmentService::class);

        // 1. Create Order -> ACTION_REQUIRED to ORDER_APPROVER
        $order = $orderService->createOrder(
            $branch->id,
            $whBranch?->id,
            'NORMAL',
            date('Y-m-d', strtotime('+3 days')),
            'Permintaan ATK Rutin',
            [['item_id' => $item->id, 'qty' => 10, 'notes' => 'Kebutuhan teller']],
            $requester
        );

        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'ORDER_APPROVER')
                ->where('target_organization_id', $branch->id)
                ->where('reference_transaction_id', $order->id)
                ->exists()
        );

        // 2. Approve Order -> Info to requester & ACTION_REQUIRED to WAREHOUSE_OFFICER
        $orderService->approveOrder($order, $approver);

        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $requester->id)
                ->where('title', "Order {$order->order_number} Disetujui")
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'WAREHOUSE_OFFICER')
                ->where('title', 'like', "%{$order->order_number}%Siap Diproses%")
                ->exists()
        );

        // 3. Picking -> Info to requester
        $picking = $orderService->generatePicking($order, $warehouseOfficer);
        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $requester->id)
                ->where('title', 'like', "%{$order->order_number}%Dipersiapkan%")
                ->exists()
        );

        // 4. Packing -> ACTION_REQUIRED to DISTRIBUTION_OFFICER & Info to requester
        $packing = $orderService->generatePacking($order, 2, 8.5, '40x30x20', $warehouseOfficer);
        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'DISTRIBUTION_OFFICER')
                ->where('title', "Order {$order->order_number} Siap Kirim")
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $requester->id)
                ->where('title', "Order {$order->order_number} Selesai Dipacking")
                ->exists()
        );

        // 5. Create Shipment -> Info to requester & ACTION_REQUIRED to RECEIVING_OFFICER
        $shipment = $orderService->createShipment(
            $order,
            $courier->id,
            'REGULER',
            'RESI-TEST-1234',
            50000,
            date('Y-m-d', strtotime('+1 day')),
            $distOfficer
        );

        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $requester->id)
                ->where('title', 'like', "%Pengiriman Order {$order->order_number}%")
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'RECEIVING_OFFICER')
                ->where('target_organization_id', $branch->id)
                ->where('title', 'like', '%Konfirmasi Penerimaan%')
                ->exists()
        );

        // 6. Process Receiving at Branch -> Info to requester & ACTION_REQUIRED to FINANCE_OFFICER
        $orderItem = $order->items->first();
        $receiving = $orderService->processReceiving(
            $shipment,
            [['order_item_id' => $orderItem->id, 'qty_good' => 8, 'qty_damaged' => 2, 'qty_missing' => 0]],
            'DATA:SIGNATURE_BASE64',
            'Diterima dengan 2 rusak',
            $rcvOfficer
        );

        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $requester->id)
                ->where('title', "Barang Order {$order->order_number} Telah Diterima")
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'FINANCE_OFFICER')
                ->where('title', 'like', '%Settlement Diperlukan%')
                ->exists()
        );
        // Discrepancy Alert to DISTRIBUTION_OFFICER and WAREHOUSE_OFFICER
        $this->assertTrue(
            Notification::where('type', 'ALERT')
                ->where('target_role', 'DISTRIBUTION_OFFICER')
                ->where('title', 'like', '%Discrepancy%')
                ->exists()
        );
    }

    /**
     * Test Settlement workflow notifications.
     */
    public function test_settlement_workflow_notifications(): void
    {
        $branch = Organization::where('code', 'KC-SBY')->first();
        $whBranch = Warehouse::where('organization_id', $branch->id)->first();
        $requester = User::where('role', 'REQUESTER_CABANG')->where('organization_id', $branch->id)->first();
        $financeOfficer = User::where('role', 'FINANCE_OFFICER')->first();
        $financeApprover = User::where('role', 'FINANCE_APPROVER')->first();
        $item = Item::first();

        $orderService = app(OrderFulfillmentService::class);
        $settlementService = app(SettlementService::class);

        // Create received order
        $order = $orderService->createOrder(
            $branch->id,
            $whBranch?->id,
            'NORMAL',
            date('Y-m-d'),
            'Order untuk Settlement',
            [['item_id' => $item->id, 'qty' => 5]],
            $requester
        );
        $order->status = 'RECEIVED';
        $order->save();
        $orderItem = $order->items->first();
        $orderItem->qty_received = 5;
        $orderItem->save();

        // 1. Create settlement -> ACTION_REQUIRED to FINANCE_APPROVER
        $settlement = $settlementService->createSettlementForOrder($order, $financeOfficer);

        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'FINANCE_APPROVER')
                ->where('reference_transaction_type', 'SETTLEMENT')
                ->where('reference_transaction_id', $settlement->id)
                ->exists()
        );

        // 2. Approve settlement -> Info to order requester (COMPLETED) & info to FINANCE_OFFICER
        $settlementService->approveAndPostSettlement($settlement, $financeApprover);

        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $requester->id)
                ->where('title', "Siklus Order {$order->order_number} Selesai Penuh (COMPLETED)")
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('target_role', 'FINANCE_OFFICER')
                ->where('title', 'Settlement Diposting ke Realisasi Anggaran')
                ->exists()
        );
    }

    /**
     * Test Stock Opname discrepancy notifications.
     */
    public function test_stock_opname_discrepancy_notifications(): void
    {
        $wh = Warehouse::first();
        $invOfficer = User::where('role', 'INVENTORY_OFFICER')->first();
        $item = Item::first();

        $response = $this->actingAs($invOfficer)->post(route('inventory.stock_opname.store'), [
            'warehouse_id' => $wh->id,
            'period_year' => (int) date('Y'),
            'period_month' => (int) date('n'),
            'opname_notes' => 'Uji coba selisih opname fisik',
            'counts' => [
                [
                    'item_id' => $item->id,
                    'system_qty' => 20,
                    'physical_qty' => 15, // difference = -5
                ],
            ],
        ]);

        $response->assertRedirect();

        // Alert sent to INVENTORY_OFFICER and AUDITOR
        $this->assertTrue(
            Notification::where('type', 'ALERT')
                ->where('reference_transaction_type', 'STOCK_OPNAME')
                ->where('target_role', 'INVENTORY_OFFICER')
                ->where('title', 'like', '%Selisih Stok%')
                ->exists()
        );

        $this->assertTrue(
            Notification::where('type', 'ALERT')
                ->where('reference_transaction_type', 'STOCK_OPNAME')
                ->where('target_role', 'AUDITOR')
                ->exists()
        );
    }

    /**
     * Test Switching Stock workflow notifications.
     */
    public function test_switching_stock_workflow_notifications(): void
    {
        $branchSby = Organization::where('code', 'KC-SBY')->first();
        $branchMlg = Organization::where('code', 'KC-MLG')->first();
        $whSby = Warehouse::where('organization_id', $branchSby->id)->first();
        $whMlg = Warehouse::where('organization_id', $branchMlg->id)->first();

        $switchingApprover = User::firstOrCreate(
            ['email' => 'switching.approver@bankjatim.co.id'],
            [
                'name' => 'Switching Approver SBY',
                'role' => 'SWITCHING_APPROVER',
                'nip' => 'BJ-99001',
                'organization_id' => $branchSby->id,
                'password' => bcrypt('password123'),
                'is_active' => true,
                'approval_limit' => 500000000,
            ]
        );

        $requester = User::where('role', 'REQUESTER_CABANG')->where('organization_id', $branchMlg->id)->first();
        $item = Item::first();

        $switchingService = app(SwitchingStockService::class);

        // 1. Create manual switching -> Action required to SWITCHING_APPROVER
        $switching = $switchingService->createManualSwitching([
            'source_organization_id' => $branchSby->id,
            'source_warehouse_id' => $whSby->id,
            'destination_organization_id' => $branchMlg->id,
            'destination_warehouse_id' => $whMlg->id,
            'item_id' => $item->id,
            'qty_requested' => 15,
            'recommendation_reason' => 'Stok kurang di Malang, ambil dari Surabaya',
        ], $requester);

        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'SWITCHING_APPROVER')
                ->where('reference_transaction_type', 'SWITCHING_STOCK')
                ->where('reference_transaction_id', $switching->id)
                ->exists()
        );

        // 2. Approve switching -> Info to requester/proposer, Info to INVENTORY_OFFICER, Action required to source WAREHOUSE_OFFICER
        $switchingService->approveSwitching($switching, $switchingApprover);

        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('user_id', $requester->id)
                ->where('title', 'Switching Stock Disetujui')
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'INFORMATION')
                ->where('target_role', 'INVENTORY_OFFICER')
                ->where('title', 'like', '%Switching Stock Disetujui%')
                ->exists()
        );
        $this->assertTrue(
            Notification::where('type', 'ACTION_REQUIRED')
                ->where('target_role', 'WAREHOUSE_OFFICER')
                ->where('target_organization_id', $branchSby->id)
                ->where('title', 'like', '%Persiapan Transfer%')
                ->exists()
        );
    }

    /**
     * Test Notification UI endpoints (index, mark single read, mark all read, open).
     */
    public function test_notification_ui_actions(): void
    {
        $user = User::where('role', 'REQUESTER_CABANG')->first();

        // Create 2 unread notifications for this user
        $notif1 = NotificationService::sendUser(
            $user->id,
            'Notif 1',
            'Pesan 1',
            'INFORMATION',
            'INFO',
            'ORDER',
            101,
            '/orders/101'
        );

        $notif2 = NotificationService::sendUser(
            $user->id,
            'Notif 2',
            'Pesan 2',
            'ALERT',
            'WARNING',
            'ORDER',
            102,
            '/orders/102'
        );

        // 1. Check index page with filters
        $resp = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'all']));
        $resp->assertStatus(200);
        $resp->assertSee('Pusat Notifikasi &amp; Tugas', false);
        $resp->assertSee('Notif 1');
        $resp->assertSee('Notif 2');

        // 2. Mark single notification as read
        $readResp = $this->actingAs($user)->post(route('notifications.read', $notif1->id));
        $readResp->assertRedirect();
        $this->assertTrue($notif1->fresh()->is_read);
        $this->assertFalse($notif2->fresh()->is_read);

        // 3. Open notification (marks read and redirects to action_url)
        $openResp = $this->actingAs($user)->get(route('notifications.open', $notif2->id));
        $openResp->assertRedirect('/orders/102');
        $this->assertTrue($notif2->fresh()->is_read);

        // 4. Mark all read
        $notif3 = NotificationService::sendUser($user->id, 'Notif 3', 'Pesan 3');
        $this->assertFalse($notif3->fresh()->is_read);

        $markAllResp = $this->actingAs($user)->post(route('notifications.mark_all_read'));
        $markAllResp->assertRedirect();
        $this->assertTrue($notif3->fresh()->is_read);
    }
}
