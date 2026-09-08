<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\ProcurementService;
use Tests\TestCase;

class ProcurementConsolidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_consolidation_pool_renders_successfully_with_redesigned_ui(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('procurement.consolidation.index'));

        $response->assertStatus(200);
        $response->assertSee('Approved PR Pool & Konsolidasi PO');
        $response->assertSee('Parameter Purchase Order (PO)');
        $response->assertSee('Daftar Approved PR Items');
        $response->assertSee('card card-outline card-danger', false);
        $response->assertSee('openConfirmModal');
        $response->assertSee('form="consolidationForm"', false);
        $response->assertSee('formatRupiah');
        $response->assertSee('vendorsList');
        $response->assertSee('warehousesList');
        $response->assertDontSee('Ketentuan Konsolidasi Purchase Order');
        $response->assertDontSee('Item PR Siap PO');

        // Old prototype Tailwind and FontAwesome elements should be removed
        $response->assertDontSee('bg-indigo-50/60', false);
        $response->assertDontSee('rounded-2xl', false);
        $response->assertDontSee('fa-solid fa-circle-info', false);
        $response->assertDontSee('fa-solid fa-file-invoice', false);
    }

    public function test_can_consolidate_approved_prs_into_po_with_redesigned_workflow(): void
    {
        $officer = User::where('role', 'PROCUREMENT_OFFICER')->first();
        $org = Organization::first();
        $item = Item::first();
        $vendor = Vendor::where('is_active', true)->first();
        $warehouse = Warehouse::where('is_active', true)->first();

        $procApprover = User::where('role', 'PROCUREMENT_APPROVER')->first();
        $procService = app(ProcurementService::class);

        // Create an Approved PR with remaining quantity using ProcurementService
        $pr = $procService->createPurchaseRequest(
            $org->id,
            'PENGADAAN_LANGSUNG',
            'Testing Consolidation PR',
            [['item_id' => $item->id, 'qty' => 10, 'unit_price' => 500000]],
            $officer
        );
        $procService->approvePurchaseRequest($pr, $procApprover);

        $prItem = $pr->items->first();

        // Access consolidation page
        $res = $this->actingAs($officer)->get(route('procurement.consolidation.index'));
        $res->assertStatus(200);
        $res->assertSee($pr->pr_number);
        $res->assertSee($item->name);

        // Submit consolidation form
        $postData = [
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'notes' => 'Batch konsolidasi resmi',
            'selections' => [
                0 => [
                    'pr_item_id' => $prItem->id,
                    'qty' => 5,
                    'unit_price' => 500000,
                ],
            ],
        ];

        $postRes = $this->actingAs($officer)->post(route('procurement.consolidation.store'), $postData);

        $postRes->assertSessionHasNoErrors();
        $postRes->assertRedirect(route('procurement.consolidation.index'));

        // Verify PR item qty_ordered updated
        $prItem->refresh();
        $this->assertEquals(5, $prItem->qty_ordered);

        // Verify PR status updated to PARTIALLY_ORDERED
        $pr->refresh();
        $this->assertEquals('PARTIALLY_ORDERED', $pr->status);
    }

    public function test_consolidation_requires_valid_data(): void
    {
        $officer = User::where('role', 'PROCUREMENT_OFFICER')->first();

        $response = $this->actingAs($officer)->post(route('procurement.consolidation.store'), []);

        $response->assertSessionHasErrors(['vendor_id', 'warehouse_id', 'expected_delivery_date', 'selections']);
    }
}
