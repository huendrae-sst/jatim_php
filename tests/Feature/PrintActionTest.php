<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Courier;
use App\Models\Discrepancy;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Receiving;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setupPrerequisites(): array
    {
        $user = User::factory()->create([
            'role' => 'SUPER_ADMIN',
        ]);

        $org = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Surabaya',
            'type' => 'MAIN_BRANCH',
            'city' => 'Surabaya',
            'cost_center_code' => 'CC-KC-SBY',
            'is_active' => true,
        ]);

        $wh = Warehouse::create([
            'organization_id' => $org->id,
            'code' => 'WH-SBY-01',
            'name' => 'Gudang Utama Surabaya',
            'type' => 'CENTRAL_LOGISTICS',
            'is_active' => true,
        ]);

        $cat = Category::create([
            'code' => 'CAT-ATK',
            'name' => 'Alat Tulis Kantor',
            'description' => 'Kebutuhan ATK',
        ]);

        $item = Item::create([
            'category_id' => $cat->id,
            'sku' => 'SKU-KRT-001',
            'name' => 'Kertas A4 80gr',
            'uom' => 'RIM',
            'estimated_unit_price' => 55000,
            'is_active' => true,
        ]);

        return compact('user', 'org', 'wh', 'cat', 'item');
    }

    public function test_orders_print_page_and_action_button(): void
    {
        $data = $this->setupPrerequisites();

        $order = Order::create([
            'order_number' => 'ORD/2026/09/0001',
            'requesting_organization_id' => $data['org']->id,
            'requesting_warehouse_id' => $data['wh']->id,
            'created_by_user_id' => $data['user']->id,
            'status' => 'SUBMITTED',
            'priority' => 'NORMAL',
            'required_date' => now()->addDays(7),
            'total_estimated_value' => 550000,
            'notes' => 'Permintaan kertas untuk operasional cabang',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $data['item']->id,
            'qty_requested' => 10,
            'unit_price_ref' => 55000,
        ]);

        // Test print page renders
        $printResponse = $this->actingAs($data['user'])
            ->get(route('orders.print', $order->id));

        $printResponse->assertStatus(200);
        $printResponse->assertSee('FORM PERMINTAAN BARANG');
        $printResponse->assertSee($order->order_number);
        $printResponse->assertSee($data['item']->name);

        // Test index page has print button for this order
        $indexResponse = $this->actingAs($data['user'])
            ->get(route('orders.index'));

        $indexResponse->assertStatus(200);
        $indexResponse->assertSee(route('orders.print', $order->id));
    }

    public function test_receiving_discrepancy_print_page_and_action_button(): void
    {
        $data = $this->setupPrerequisites();

        $courier = Courier::create([
            'code' => 'EXP-INTERNAL',
            'name' => 'Armada Internal Bank Jatim',
            'contact_number' => '08123456789',
        ]);

        $order = Order::create([
            'order_number' => 'ORD/2026/09/0002',
            'requesting_organization_id' => $data['org']->id,
            'created_by_user_id' => $data['user']->id,
            'status' => 'FULFILLED',
            'priority' => 'NORMAL',
            'total_estimated_value' => 275000,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'item_id' => $data['item']->id,
            'qty_requested' => 5,
            'unit_price_ref' => 55000,
        ]);

        $shipment = Shipment::create([
            'manifest_number' => 'MNF/2026/09/0001',
            'order_id' => $order->id,
            'origin_warehouse_id' => $data['wh']->id,
            'destination_organization_id' => $data['org']->id,
            'courier_id' => $courier->id,
            'dispatched_by_user_id' => $data['user']->id,
            'status' => 'DELIVERED',
            'shipment_date' => now(),
        ]);

        $receiving = Receiving::create([
            'receiving_number' => 'RCV/2026/09/0001',
            'shipment_id' => $shipment->id,
            'order_id' => $order->id,
            'organization_id' => $data['org']->id,
            'warehouse_id' => $data['wh']->id,
            'received_by_user_id' => $data['user']->id,
            'receipt_date' => now(),
            'notes' => 'Ditemukan barang rusak 1 rim',
        ]);

        $discrepancy = Discrepancy::create([
            'receiving_id' => $receiving->id,
            'order_item_id' => $orderItem->id,
            'item_id' => $data['item']->id,
            'discrepancy_type' => 'DAMAGED',
            'qty_expected' => 5,
            'qty_actual' => 4,
            'qty_damaged' => 1,
            'resolution_status' => 'REPORTED',
            'resolution_notes' => '1 Rim sobek dan terkena tumpahan air hujan saat pengiriman',
        ]);

        // Test discrepancy print page renders
        $printResponse = $this->actingAs($data['user'])
            ->get(route('receiving.discrepancies.print', $discrepancy->id));

        $printResponse->assertStatus(200);
        $printResponse->assertSee('BERITA ACARA DISCREPANCY');
        $printResponse->assertSee($receiving->receiving_number);
        $printResponse->assertSee($data['item']->name);
        $printResponse->assertSee('1 Rim sobek');

        // Test discrepancies index page has print button
        $indexResponse = $this->actingAs($data['user'])
            ->get(route('receiving.discrepancies'));

        $indexResponse->assertStatus(200);
        $indexResponse->assertSee(route('receiving.discrepancies.print', $discrepancy->id));
    }

    public function test_procurement_pr_print_page_and_action_button(): void
    {
        $data = $this->setupPrerequisites();

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR/2026/09/0001',
            'organization_id' => $data['org']->id,
            'created_by_user_id' => $data['user']->id,
            'status' => 'SUBMITTED',
            'procurement_method' => 'PENGADAAN_LANGSUNG',
            'budget_status' => 'VALIDATED',
            'purpose' => 'Pengadaan kertas kuartal III untuk teller dan customer service',
            'estimated_total_cost' => 1100000,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_id' => $data['item']->id,
            'qty_requested' => 20,
            'qty_approved' => 20,
            'estimated_unit_price' => 55000,
            'estimated_subtotal' => 1100000,
        ]);

        // Test PR print page renders
        $printResponse = $this->actingAs($data['user'])
            ->get(route('procurement.pr.print', $pr->id));

        $printResponse->assertStatus(200);
        $printResponse->assertSee('PURCHASE REQUEST');
        $printResponse->assertSee($pr->pr_number);
        $printResponse->assertSee($data['item']->name);
        $printResponse->assertSee('teller dan customer service');

        // Test PR index page has print button
        $indexResponse = $this->actingAs($data['user'])
            ->get(route('procurement.pr.index'));

        $indexResponse->assertStatus(200);
        $indexResponse->assertSee(route('procurement.pr.print', $pr->id));

        // Test PR show page has print button
        $showResponse = $this->actingAs($data['user'])
            ->get(route('procurement.pr.show', $pr->id));

        $showResponse->assertStatus(200);
        $showResponse->assertSee(route('procurement.pr.print', $pr->id));
    }
}
