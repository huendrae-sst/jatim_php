<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Settlement;
use App\Models\User;
use Tests\TestCase;

class SettlementApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_user_can_view_settlements_index_with_standardized_ui(): void
    {
        $user = User::where('role', 'SUPER_ADMIN')->firstOrFail();

        $response = $this->actingAs($user)->get(route('finance.settlements.index'));

        $response->assertStatus(200);
        $response->assertSee('Settlement Antar-Unit');
        $response->assertSee('Menunggu Approval');
        $response->assertSee('Telah Diposting');
        $response->assertSee('Perlu Settlement');
        $response->assertSee('Nilai Menunggu Approval');
        $response->assertSee('Semua');
        $response->assertSee('Unit Debit (Pembebanan)');
        $response->assertSee('Unit Kredit (Penyedia)');
    }

    public function test_settlement_tab_and_search_filtering(): void
    {
        $user = User::where('role', 'FINANCE_APPROVER')->firstOrFail();

        // Test tab: waiting_approval
        $resWaiting = $this->actingAs($user)->get(route('finance.settlements.index', ['tab' => 'waiting_approval']));
        $resWaiting->assertStatus(200);

        // Test tab: posted
        $resPosted = $this->actingAs($user)->get(route('finance.settlements.index', ['tab' => 'posted']));
        $resPosted->assertStatus(200);

        // Test tab: all
        $resAll = $this->actingAs($user)->get(route('finance.settlements.index', ['tab' => 'all']));
        $resAll->assertStatus(200);

        // Test search
        $firstSettlement = Settlement::first();
        if ($firstSettlement) {
            $resSearch = $this->actingAs($user)->get(route('finance.settlements.index', [
                'tab' => 'all',
                'search' => $firstSettlement->settlement_number,
            ]));
            $resSearch->assertStatus(200);
            $resSearch->assertSee($firstSettlement->settlement_number);
        }
    }

    public function test_finance_approver_can_approve_and_post_settlement(): void
    {
        $approver = User::where('role', 'FINANCE_APPROVER')->firstOrFail();

        // Create or get a settlement with WAITING_APPROVAL
        $order = Order::where('status', 'RECEIVED')->first();
        if (! $order) {
            $order = Order::first();
        }

        $settlement = Settlement::where('status', 'WAITING_APPROVAL')->first();
        if (! $settlement) {
            $settlement = Settlement::create([
                'settlement_number' => 'STL/TEST/0001',
                'order_id' => $order->id,
                'debit_organization_id' => $order->requesting_organization_id,
                'credit_organization_id' => 1,
                'debit_cost_center' => 'CC-TEST-DEBIT',
                'credit_cost_center' => 'CC-TEST-CREDIT',
                'item_amount' => 500000,
                'shipping_amount' => 50000,
                'total_amount' => 550000,
                'status' => 'WAITING_APPROVAL',
                'created_by_user_id' => $approver->id,
            ]);
        }

        $response = $this->actingAs($approver)->post(route('finance.settlements.approve', $settlement->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals('POSTED', $settlement->fresh()->status);
        $this->assertEquals($approver->id, $settlement->fresh()->approved_by_user_id);
        $this->assertNotNull($settlement->fresh()->posted_at);
    }
}
