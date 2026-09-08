<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\ProcurementService;
use Tests\TestCase;

class ProcurementApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_approver_can_view_pr_approvals_page_and_kpis(): void
    {
        $approver = User::where('role', 'PROCUREMENT_APPROVER')->first();

        $response = $this->actingAs($approver)->get(route('procurement.approvals.pr'));

        $response->assertStatus(200);
        $response->assertSee('Persetujuan Purchase Request (PR)');
        $response->assertSee('Menunggu Persetujuan');
        $response->assertSee('Telah Disetujui');
        $response->assertSee('Pengajuan Ditolak');
    }

    public function test_approver_can_approve_pr_via_http(): void
    {
        $officer = User::where('role', 'PROCUREMENT_OFFICER')->first();
        $approver = User::where('role', 'PROCUREMENT_APPROVER')->first();
        $whCentral = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
        $item = Item::first();

        $procService = app(ProcurementService::class);
        $pr = $procService->createPurchaseRequest(
            $whCentral->organization_id,
            'DIRECT',
            'Pengajuan ATK Kantor Pusat',
            [['item_id' => $item->id, 'qty' => 10, 'unit_price' => 50000]],
            $officer
        );

        $this->assertEquals('SUBMITTED', $pr->status);

        $response = $this->actingAs($approver)->post(route('procurement.pr.approve', $pr->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('APPROVED', $pr->fresh()->status);
        $this->assertEquals($approver->id, $pr->fresh()->approved_by_user_id);
        $this->assertNotNull($pr->fresh()->approved_at);
        $this->assertEquals(10, $pr->fresh()->items->first()->qty_approved);
    }

    public function test_approver_can_reject_pr_with_reason(): void
    {
        $officer = User::where('role', 'PROCUREMENT_OFFICER')->first();
        $approver = User::where('role', 'PROCUREMENT_APPROVER')->first();
        $whCentral = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
        $item = Item::first();

        $procService = app(ProcurementService::class);
        $pr = $procService->createPurchaseRequest(
            $whCentral->organization_id,
            'DIRECT',
            'Pengajuan ATK Mahal',
            [['item_id' => $item->id, 'qty' => 100, 'unit_price' => 500000]],
            $officer
        );

        $response = $this->actingAs($approver)->post(route('procurement.pr.reject', $pr->id), [
            'rejection_reason' => 'Harga satuan melebihi batas kewajaran pagu anggaran cabang.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $freshPr = $pr->fresh();
        $this->assertEquals('REJECTED', $freshPr->status);
        $this->assertEquals('Harga satuan melebihi batas kewajaran pagu anggaran cabang.', $freshPr->rejection_reason);
        $this->assertEquals($approver->id, $freshPr->approved_by_user_id);
    }

    public function test_maker_cannot_approve_own_pr(): void
    {
        $officer = User::where('role', 'PROCUREMENT_OFFICER')->first();
        $whCentral = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
        $item = Item::first();

        $procService = app(ProcurementService::class);
        $pr = $procService->createPurchaseRequest(
            $whCentral->organization_id,
            'DIRECT',
            'Pengajuan Sendiri',
            [['item_id' => $item->id, 'qty' => 5, 'unit_price' => 50000]],
            $officer
        );

        // Officer tries to approve own PR
        $response = $this->actingAs($officer)->post(route('procurement.pr.approve', $pr->id));

        $response->assertSessionHas('error');
        $this->assertEquals('SUBMITTED', $pr->fresh()->status);
    }

    public function test_approver_can_view_po_approvals_page_and_kpis(): void
    {
        $approver = User::where('role', 'PROCUREMENT_APPROVER')->first();

        $response = $this->actingAs($approver)->get(route('procurement.approvals.po'));

        $response->assertStatus(200);
        $response->assertSee('Persetujuan Purchase Order (PO)');
        $response->assertSee('Menunggu Penerbitan');
        $response->assertSee('PO Diterbitkan');
        $response->assertSee('PO Selesai Diterima');
    }

    public function test_approver_can_approve_purchase_order(): void
    {
        $officer = User::where('role', 'PROCUREMENT_OFFICER')->first();
        $approver = User::where('role', 'PROCUREMENT_APPROVER')->first();
        $whCentral = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
        $vendor = Vendor::first();
        $item = Item::first();

        $procService = app(ProcurementService::class);
        $pr = $procService->createPurchaseRequest(
            $whCentral->organization_id,
            'DIRECT',
            'Pengadaan untuk PO Test',
            [['item_id' => $item->id, 'qty' => 20, 'unit_price' => 100000]],
            $officer
        );
        $procService->approvePurchaseRequest($pr, $approver);

        $po = $procService->consolidatePRsToPO(
            [['pr_item_id' => $pr->items->first()->id, 'qty' => 20, 'unit_price' => 100000]],
            $vendor->id,
            $whCentral->id,
            date('Y-m-d', strtotime('+3 days')),
            'PO Uji Coba Approval',
            $officer
        );

        $this->assertEquals('WAITING_APPROVAL', $po->status);

        $response = $this->actingAs($approver)->post(route('procurement.po.approve', $po->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $freshPo = $po->fresh();
        $this->assertEquals('ISSUED', $freshPo->status);
        $this->assertEquals($approver->id, $freshPo->approved_by_user_id);
        $this->assertNotNull($freshPo->approved_at);
    }

    public function test_approver_can_reject_purchase_order_and_rollback_pr_item_quantities(): void
    {
        $officer = User::where('role', 'PROCUREMENT_OFFICER')->first();
        $approver = User::where('role', 'PROCUREMENT_APPROVER')->first();
        $whCentral = Warehouse::where('type', 'CENTRAL_LOGISTICS')->first();
        $vendor = Vendor::first();
        $item = Item::first();

        $procService = app(ProcurementService::class);
        $pr = $procService->createPurchaseRequest(
            $whCentral->organization_id,
            'DIRECT',
            'Pengadaan untuk PO Reject Test',
            [['item_id' => $item->id, 'qty' => 30, 'unit_price' => 100000]],
            $officer
        );
        $procService->approvePurchaseRequest($pr, $approver);

        $prItem = $pr->items->first();

        $po = $procService->consolidatePRsToPO(
            [['pr_item_id' => $prItem->id, 'qty' => 30, 'unit_price' => 100000]],
            $vendor->id,
            $whCentral->id,
            date('Y-m-d', strtotime('+3 days')),
            'PO Uji Coba Tolak',
            $officer
        );

        $this->assertEquals('FULLY_ORDERED', $pr->fresh()->status);
        $this->assertEquals(30, $prItem->fresh()->qty_ordered);

        // Approver rejects PO
        $response = $this->actingAs($approver)->post(route('procurement.po.reject', $po->id), [
            'rejection_reason' => 'Spesifikasi vendor tidak memenuhi standar pengadaan bank.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $freshPo = $po->fresh();
        $this->assertEquals('REJECTED', $freshPo->status);
        $this->assertEquals('Spesifikasi vendor tidak memenuhi standar pengadaan bank.', $freshPo->rejection_reason);

        // Verify PR rollback
        $freshPrItem = $prItem->fresh();
        $this->assertEquals(0, $freshPrItem->qty_ordered);
        $this->assertEquals(30, $freshPrItem->remaining_qty_to_order);
        $this->assertEquals('APPROVED', $pr->fresh()->status);
    }
}
