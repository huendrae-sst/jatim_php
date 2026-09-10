<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Tests\TestCase;

class ProcurementPrManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_pr_index_renders_with_kpi_cards_and_modal_actions(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('procurement.pr.index'));

        $response->assertStatus(200);
        $response->assertSee('Purchase Requests (PR)');
        $response->assertDontSee('<div class="info-box', false);
        $response->assertDontSee('Daftar Purchase Request (PR)');
        $response->assertDontSee('Total Pengajuan PR');
        $response->assertDontSee('small-box', false);
        $response->assertSee('table-striped', false);
        $response->assertSee('openViewModal');
        $response->assertSee('openEditModal');
        $response->assertSee('openDeleteModal');
        $response->assertSee('openCreateModal');
        $response->assertSee('Tambah Barang EWS');
        $response->assertSee('generateEwsItems');
        $response->assertSee('Menampilkan');
        $response->assertSee('Baris per halaman');
    }

    public function test_can_create_pr_via_modal_store(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $org = Organization::first();
        $item = Item::first();

        $response = $this->actingAs($admin)->post(route('procurement.pr.store'), [
            'organization_id' => $org->id,
            'procurement_method' => 'E_PURCHASING',
            'purpose' => 'Pengadaan ATK Rutin Kantor Test',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 15,
                    'unit_price' => 50000,
                    'specs' => 'Kertas A4 80gr',
                ],
            ],
        ]);

        $response->assertRedirect(route('procurement.pr.index'));
        $this->assertDatabaseHas('purchase_requests', [
            'organization_id' => $org->id,
            'purpose' => 'Pengadaan ATK Rutin Kantor Test',
            'procurement_method' => 'E_PURCHASING',
        ]);
        $this->assertDatabaseHas('purchase_request_items', [
            'item_id' => $item->id,
            'qty_requested' => 15,
        ]);
    }

    public function test_can_update_pr_via_put_route(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $org = Organization::first();
        $item = Item::first();

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-TEST-UPDATE-01',
            'organization_id' => $org->id,
            'created_by_user_id' => $admin->id,
            'procurement_method' => 'DIRECT_PURCHASE',
            'purpose' => 'Pengadaan Awal',
            'status' => 'SUBMITTED',
            'estimated_total_cost' => 100000,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_id' => $item->id,
            'qty_requested' => 5,
            'estimated_unit_price' => 20000,
            'estimated_subtotal' => 100000,
            'notes' => 'Awal',
        ]);

        $response = $this->actingAs($admin)->put(route('procurement.pr.update', $pr->id), [
            'organization_id' => $org->id,
            'procurement_method' => 'E_PURCHASING',
            'purpose' => 'Pengadaan ATK Diperbarui',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 10,
                    'unit_price' => 25000,
                    'notes' => 'Diperbarui',
                ],
            ],
        ]);

        $response->assertRedirect(route('procurement.pr.index'));
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $pr->id,
            'purpose' => 'Pengadaan ATK Diperbarui',
            'procurement_method' => 'E_PURCHASING',
            'estimated_total_cost' => 250000,
        ]);
        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $pr->id,
            'qty_requested' => 10,
            'estimated_subtotal' => 250000,
        ]);
    }

    public function test_pr_cannot_be_edited_when_status_is_approved_fully_ordered_or_partially_ordered(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $org = Organization::first();
        $item = Item::first();

        foreach (['APPROVED', 'FULLY_ORDERED', 'PARTIALLY_ORDERED'] as $status) {
            $pr = PurchaseRequest::create([
                'pr_number' => "PR-TEST-LOCK-{$status}",
                'organization_id' => $org->id,
                'created_by_user_id' => $admin->id,
                'procurement_method' => 'DIRECT_PURCHASE',
                'purpose' => "Pengadaan {$status}",
                'status' => $status,
                'estimated_total_cost' => 100000,
            ]);

            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
                'item_id' => $item->id,
                'qty_requested' => 2,
                'estimated_unit_price' => 50000,
                'estimated_subtotal' => 100000,
            ]);

            $response = $this->actingAs($admin)->put(route('procurement.pr.update', $pr->id), [
                'organization_id' => $org->id,
                'procurement_method' => 'DIRECT_PURCHASE',
                'purpose' => "Pengadaan Update {$status}",
                'items' => [
                    [
                        'item_id' => $item->id,
                        'qty' => 5,
                        'unit_price' => 50000,
                    ],
                ],
            ]);

            $response->assertSessionHas('error');
            $this->assertDatabaseMissing('purchase_requests', [
                'id' => $pr->id,
                'purpose' => "Pengadaan Update {$status}",
            ]);
        }

        // Verify index view renders disabled edit button
        $responseView = $this->actingAs($admin)->get(route('procurement.pr.index'));
        $responseView->assertStatus(200);
        $responseView->assertSee('opacity-25', false);
        $responseView->assertSee('sudah tidak dapat diedit', false);
    }

    public function test_can_destroy_unapproved_pr(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $org = Organization::first();
        $item = Item::first();

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-TEST-DESTROY-01',
            'organization_id' => $org->id,
            'created_by_user_id' => $admin->id,
            'procurement_method' => 'DIRECT_PURCHASE',
            'purpose' => 'Pengadaan Untuk Dihapus',
            'status' => 'SUBMITTED',
            'estimated_total_cost' => 50000,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_id' => $item->id,
            'qty_requested' => 1,
            'estimated_unit_price' => 50000,
            'estimated_subtotal' => 50000,
        ]);

        $response = $this->actingAs($admin)->delete(route('procurement.pr.destroy', $pr->id));

        $response->assertRedirect(route('procurement.pr.index'));
        $this->assertDatabaseMissing('purchase_requests', [
            'id' => $pr->id,
        ]);
        $this->assertDatabaseMissing('purchase_request_items', [
            'purchase_request_id' => $pr->id,
        ]);
    }

    public function test_pr_cannot_be_deleted_when_status_is_approved_fully_ordered_or_partially_ordered(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $org = Organization::first();
        $item = Item::first();

        foreach (['APPROVED', 'FULLY_ORDERED', 'PARTIALLY_ORDERED'] as $status) {
            $pr = PurchaseRequest::create([
                'pr_number' => "PR-TEST-DEL-LOCK-{$status}",
                'organization_id' => $org->id,
                'created_by_user_id' => $admin->id,
                'procurement_method' => 'DIRECT_PURCHASE',
                'purpose' => "Pengadaan Hapus {$status}",
                'status' => $status,
                'estimated_total_cost' => 100000,
            ]);

            PurchaseRequestItem::create([
                'purchase_request_id' => $pr->id,
                'item_id' => $item->id,
                'qty_requested' => 2,
                'estimated_unit_price' => 50000,
                'estimated_subtotal' => 100000,
            ]);

            $response = $this->actingAs($admin)->delete(route('procurement.pr.destroy', $pr->id));

            $response->assertSessionHas('error');
            $this->assertDatabaseHas('purchase_requests', [
                'id' => $pr->id,
            ]);
        }

        // Verify index view renders disabled delete button
        $responseView = $this->actingAs($admin)->get(route('procurement.pr.index'));
        $responseView->assertStatus(200);
        $responseView->assertSee('sudah tidak dapat dihapus', false);
    }

    public function test_branch_requester_can_access_and_create_pr_scoped_to_own_branch(): void
    {
        $branchUser = User::where('email', 'requester.sby@bankjatim.co.id')->firstOrFail();
        $item = Item::firstOrFail();

        // 1. Can view PR Index
        $responseIndex = $this->actingAs($branchUser)->get(route('procurement.pr.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Purchase Requests (PR)');
        $responseIndex->assertSee('Buat PR Baru');

        // 2. Can create PR
        $responseStore = $this->actingAs($branchUser)->post(route('procurement.pr.store'), [
            'organization_id' => $branchUser->organization_id,
            'procurement_method' => 'E_PURCHASING',
            'purpose' => 'Pengadaan Kertas Form Cabang Surabaya',
            'items' => [
                [
                    'item_id' => $item->id,
                    'qty' => 10,
                    'unit_price' => 75000,
                ],
            ],
        ]);

        $responseStore->assertRedirect(route('procurement.pr.index'));
        $this->assertDatabaseHas('purchase_requests', [
            'organization_id' => $branchUser->organization_id,
            'created_by_user_id' => $branchUser->id,
            'purpose' => 'Pengadaan Kertas Form Cabang Surabaya',
        ]);
    }
}
