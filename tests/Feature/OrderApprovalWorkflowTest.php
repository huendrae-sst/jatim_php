<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Order;
use App\Models\User;
use Tests\TestCase;

class OrderApprovalWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_approvals_page_can_be_rendered(): void
    {
        $approver = User::where('role', 'ORDER_APPROVER')->first() ?? User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($approver)->get(route('orders.approvals'));

        $response->assertStatus(200);
        $response->assertSee('Persetujuan Order');
        $response->assertSee('Status Alur Transaksi');
    }

    public function test_order_workflow_timeline_contains_status_date_actor_and_description(): void
    {
        $order = Order::first();
        $this->assertNotNull($order);

        $timeline = $order->getWorkflowTimeline();

        $this->assertIsArray($timeline);
        $this->assertNotEmpty($timeline);

        foreach ($timeline as $step) {
            $this->assertArrayHasKey('label', $step); // Status
            $this->assertArrayHasKey('date_formatted', $step); // Tanggal
            $this->assertArrayHasKey('actor_name', $step); // Siapa yang memproses
            $this->assertArrayHasKey('description', $step); // Keterangan
        }
    }

    public function test_order_can_be_rejected_with_reason(): void
    {
        $approver = User::where('role', 'SUPER_ADMIN')->first();
        $order = Order::whereIn('status', ['SUBMITTED', 'WAITING_APPROVAL'])->first();

        if (! $order) {
            $order = Order::first();
            $order->update(['status' => 'SUBMITTED']);
        }

        $response = $this->actingAs($approver)->post(route('orders.reject', $order->id), [
            'reason' => 'Melebihi alokasi anggaran triwulan cabang.',
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('REJECTED', $order->status);

        $timeline = $order->getWorkflowTimeline();
        $rejectedStep = collect($timeline)->firstWhere('code', 'REJECTED');
        $this->assertNotNull($rejectedStep);
        $this->assertStringContainsString('Ditolak', $rejectedStep['label']);
        $this->assertStringContainsString('Melebihi alokasi anggaran', $rejectedStep['description']);
    }

    public function test_orders_index_renders_edit_order_action(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('title="Edit Order"', false);
        $response->assertSee('openEditModal', false);
        $response->assertSee('Edit Order:', false);
        $response->assertDontSee('Lihat Detail Lengkap Transaksi');
    }

    public function test_order_can_be_updated_via_modal_endpoint_and_redirects_to_index(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $order = Order::whereIn('status', ['DRAFT', 'SUBMITTED', 'WAITING_APPROVAL'])->first();

        if (! $order) {
            $order = Order::first();
            $order->update(['status' => 'SUBMITTED']);
        }

        $items = Item::take(2)->get();
        $newDate = now()->addDays(5)->format('Y-m-d');
        $response = $this->actingAs($user)->put(route('orders.update', $order->id), [
            'priority' => 'URGENT',
            'required_date' => $newDate,
            'notes' => 'Dipercepat karena kebutuhan audit cabang mendesak.',
            'items' => [
                ['item_id' => $items[0]->id, 'qty' => 10],
                ['item_id' => $items[1]->id, 'qty' => 5],
            ],
        ]);

        $response->assertRedirect(route('orders.index'));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('URGENT', $order->priority);
        $this->assertEquals($newDate, $order->required_date?->format('Y-m-d'));
        $this->assertEquals('Dipercepat karena kebutuhan audit cabang mendesak.', $order->notes);
        $this->assertEquals(15, $order->total_items);
        $this->assertCount(2, $order->items);
    }

    public function test_order_show_redirects_to_orders_index(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $order = Order::first();

        $response = $this->actingAs($user)->get(route('orders.show', $order->id));

        $response->assertRedirect(route('orders.index'));
    }

    public function test_orders_index_can_be_rendered_with_modal_components(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('Daftar Order Permintaan Barang');
        $response->assertSee('Buat Order Baru');
        $response->assertDontSee('selectAll');
    }

    public function test_order_can_be_deleted(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $order = Order::whereIn('status', ['DRAFT', 'SUBMITTED', 'WAITING_APPROVAL', 'CANCELLED', 'REJECTED'])->first();

        if (! $order) {
            $order = Order::first();
            $order->update(['status' => 'SUBMITTED']);
        }

        $orderId = $order->id;
        $orderNumber = $order->order_number;

        $response = $this->actingAs($user)->delete(route('orders.destroy', $orderId));

        $response->assertRedirect(route('orders.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
    }

    public function test_completed_or_in_transit_order_cannot_be_deleted(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();
        $order = Order::first();
        $order->update(['status' => 'COMPLETED']);

        $response = $this->actingAs($user)->delete(route('orders.destroy', $order->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_orders_page_contains_searchable_item_dropdowns(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('orders.index'));

        $response->assertStatus(200);
        $response->assertSee('filteredItems');
        $response->assertSee('sCreateInput');
        $response->assertSee('searchable-item-option');

        $createResponse = $this->actingAs($user)->get(route('orders.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee('filteredItems');
        $createResponse->assertSee('sInput');
    }

    public function test_orders_page_contains_filter_toolbar_matching_master_items(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($user)->get(route('orders.index', [
            'organization_id' => 'ALL',
            'status' => 'ALL',
            'search' => 'ORD',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Semua Unit Kerja');
        $response->assertSee('Semua Status');
        $response->assertSee('name="organization_id"', false);
        $response->assertSee('name="status"', false);
        $response->assertSee('name="sort_by"', false);
        $response->assertSee('name="search"', false);
        $response->assertSee('Reset');
    }
}
