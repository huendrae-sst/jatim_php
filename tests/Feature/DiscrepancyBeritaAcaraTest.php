<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Courier;
use App\Models\Discrepancy;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DiscrepancyBeritaAcaraTest extends TestCase
{
    use RefreshDatabase;

    protected User $receiverUser;

    protected Organization $branchOrg;

    protected Warehouse $originWarehouse;

    protected Item $item;

    protected Order $order;

    protected OrderItem $orderItem;

    protected Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->branchOrg = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Utama Surabaya',
            'type' => 'MAIN_BRANCH',
            'cost_center_code' => 'CC-KC-SBY',
            'is_active' => true,
        ]);

        $this->receiverUser = User::create([
            'name' => 'Branch Receiver User',
            'email' => 'receiver_branch@bankjatim.co.id',
            'password' => bcrypt('password'),
            'role' => 'BRANCH_USER',
            'organization_id' => $this->branchOrg->id,
            'is_active' => true,
        ]);

        $this->originWarehouse = Warehouse::create([
            'code' => 'GD-RKT',
            'name' => 'Gudang Logistik Pusat SIER',
            'organization_id' => $this->branchOrg->id,
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $category = Category::create([
            'code' => 'CAT-ATM',
            'name' => 'Kartu ATM',
            'description' => 'Kartu ATM dan Debet',
        ]);

        $this->item = Item::create([
            'category_id' => $category->id,
            'sku' => 'ATM-INST-001',
            'barcode' => '8991001021',
            'name' => 'Kartu ATM Instan Chip GPN Bank Jatim',
            'uom' => 'PCS',
            'estimated_unit_price' => 15000,
            'is_active' => true,
        ]);

        $courier = Courier::create([
            'code' => 'JNE',
            'name' => 'JNE Express',
            'is_active' => true,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-2026/09/0001',
            'requesting_organization_id' => $this->branchOrg->id,
            'created_by_user_id' => $this->receiverUser->id,
            'status' => 'IN_DELIVERY',
            'priority' => 'NORMAL',
            'total_estimated_value' => 1500000,
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'item_id' => $this->item->id,
            'qty_requested' => 100,
            'qty_approved' => 100,
            'qty_allocated' => 100,
            'qty_picked' => 100,
            'qty_packed' => 100,
            'qty_shipped' => 100,
            'unit_price' => 15000,
            'subtotal' => 1500000,
        ]);

        $this->shipment = Shipment::create([
            'shipment_number' => 'SHP-2026/09/0001',
            'manifest_number' => 'MNF-001',
            'tracking_number' => 'TRK-001',
            'order_id' => $this->order->id,
            'origin_warehouse_id' => $this->originWarehouse->id,
            'destination_organization_id' => $this->branchOrg->id,
            'courier_id' => $courier->id,
            'status' => 'IN_TRANSIT',
            'dispatched_by_user_id' => $this->receiverUser->id,
            'created_by_user_id' => $this->receiverUser->id,
        ]);
    }

    public function test_receiving_without_discrepancy_does_not_require_pdf(): void
    {
        $response = $this->actingAs($this->receiverUser)->post(
            route('receiving.confirm.store', $this->shipment->id),
            [
                'pod_signature' => 'Budi Santoso (Staff CS)',
                'notes' => 'Barang diterima lengkap dan utuh',
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'qty_good' => 100,
                        'qty_damaged' => 0,
                        'qty_missing' => 0,
                    ],
                ],
            ]
        );

        $response->assertRedirect(route('receiving.index'));
        $this->assertEquals(0, Discrepancy::count());
    }

    public function test_receiving_with_discrepancy_requires_pdf_file(): void
    {
        // Missing items without PDF upload must fail validation
        $response = $this->actingAs($this->receiverUser)->post(
            route('receiving.confirm.store', $this->shipment->id),
            [
                'pod_signature' => 'Budi Santoso',
                'notes' => 'Terdapat barang hilang/rusak saat unboxing',
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'qty_good' => 90,
                        'qty_damaged' => 5,
                        'qty_missing' => 5,
                    ],
                ],
            ]
        );

        $response->assertSessionHasErrors('berita_acara_pdf');
        $this->assertEquals(0, Discrepancy::count());
    }

    public function test_receiving_with_non_pdf_file_fails_validation(): void
    {
        $fakeTxtFile = UploadedFile::fake()->create('berita_acara.txt', 50, 'text/plain');

        $response = $this->actingAs($this->receiverUser)->post(
            route('receiving.confirm.store', $this->shipment->id),
            [
                'pod_signature' => 'Budi Santoso',
                'berita_acara_pdf' => $fakeTxtFile,
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'qty_good' => 95,
                        'qty_damaged' => 5,
                        'qty_missing' => 0,
                    ],
                ],
            ]
        );

        $response->assertSessionHasErrors('berita_acara_pdf');
    }

    public function test_receiving_with_valid_pdf_stores_file_and_saves_discrepancy(): void
    {
        $fakePdf = UploadedFile::fake()->create('BA_Penerimaan_Rusak.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->receiverUser)->post(
            route('receiving.confirm.store', $this->shipment->id),
            [
                'pod_signature' => 'Budi Santoso',
                'berita_acara_pdf' => $fakePdf,
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'qty_good' => 90,
                        'qty_damaged' => 10,
                        'qty_missing' => 0,
                    ],
                ],
            ]
        );

        $response->assertRedirect(route('receiving.index'));

        // Discrepancy record created with PDF path
        $discrepancy = Discrepancy::first();
        $this->assertNotNull($discrepancy);
        $this->assertEquals(10, $discrepancy->qty_damaged);
        $this->assertNotNull($discrepancy->berita_acara_path);
        $this->assertEquals('BA_Penerimaan_Rusak.pdf', $discrepancy->berita_acara_filename);

        // File must physically exist in storage
        Storage::disk('public')->assertExists($discrepancy->berita_acara_path);

        // Download route test
        $downloadResponse = $this->actingAs($this->receiverUser)->get(
            route('receiving.discrepancies.berita_acara', $discrepancy->id)
        );

        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('content-disposition');
    }
}
