<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\CostCenter;
use App\Models\Organization;
use App\Models\User;
use App\Services\EarlyWarningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetEarlyWarningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private CostCenter $costCenter;

    private User $admin;

    private EarlyWarningService $ewsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'code' => 'KC-SBY',
            'name' => 'Kantor Cabang Surabaya',
            'type' => 'MAIN_BRANCH',
        ]);

        $this->costCenter = CostCenter::create([
            'code' => 'CC-OPS-01',
            'name' => 'Operasional Cabang Surabaya',
            'organization_id' => $this->org->id,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'organization_id' => $this->org->id,
        ]);

        $this->ewsService = app(EarlyWarningService::class);
    }

    public function test_budget_status_and_risk_level_evaluation(): void
    {
        $year = (int) date('Y');

        // 1. SAFE (<80%): 50 / 100 = 50%
        $budgetSafe = Budget::create([
            'organization_id' => $this->org->id,
            'cost_center_code' => 'CC-OPS-01',
            'year' => $year,
            'allocated_amount' => 100000000,
            'committed_amount' => 0,
            'realized_amount' => 50000000,
        ]);

        $evalSafe = $this->ewsService->evaluateBudget($budgetSafe);
        $this->assertEquals('SAFE', $evalSafe['risk_level']);
        $this->assertFalse($evalSafe['is_blocked']);
        $this->assertEquals('SAFE', $budgetSafe->budget_status);

        // 2. WARNING_80 (80-89.9%): 85 / 100 = 85%
        $budgetWarning = Budget::create([
            'organization_id' => $this->org->id,
            'cost_center_code' => 'CC-OPS-01',
            'year' => $year - 1, // distinct year to avoid unique constraint if any
            'allocated_amount' => 100000000,
            'committed_amount' => 0,
            'realized_amount' => 85000000,
        ]);

        $evalWarning = $this->ewsService->evaluateBudget($budgetWarning);
        $this->assertEquals('WARNING_80', $evalWarning['risk_level']);
        $this->assertFalse($evalWarning['is_blocked']);
        $this->assertEquals('WARNING_80', $budgetWarning->budget_status);

        // 3. CRITICAL_90 (90-99.9%): 95 / 100 = 95%
        $budgetCritical = Budget::create([
            'organization_id' => $this->org->id,
            'cost_center_code' => 'CC-OPS-01',
            'year' => $year - 2,
            'allocated_amount' => 100000000,
            'committed_amount' => 0,
            'realized_amount' => 95000000,
        ]);

        $evalCritical = $this->ewsService->evaluateBudget($budgetCritical);
        $this->assertEquals('CRITICAL_90', $evalCritical['risk_level']);
        $this->assertFalse($evalCritical['is_blocked']);
        $this->assertEquals('CRITICAL_90', $budgetCritical->budget_status);

        // 4. OVERBUDGET_BLOCK (>=100%): 105 / 100 = 105%
        $budgetOver = Budget::create([
            'organization_id' => $this->org->id,
            'cost_center_code' => 'CC-OPS-01',
            'year' => $year - 3,
            'allocated_amount' => 100000000,
            'committed_amount' => 0,
            'realized_amount' => 105000000,
        ]);

        $evalOver = $this->ewsService->evaluateBudget($budgetOver);
        $this->assertEquals('OVERBUDGET_BLOCK', $evalOver['risk_level']);
        $this->assertTrue($evalOver['is_blocked']);
        $this->assertEquals('OVERBUDGET_BLOCK', $budgetOver->budget_status);
    }

    public function test_budget_early_warning_summary_aggregation(): void
    {
        $year = 2026;

        $org2 = Organization::create(['code' => 'KC-MLG', 'name' => 'KC Malang', 'type' => 'MAIN_BRANCH']);

        Budget::create([
            'organization_id' => $this->org->id,
            'cost_center_code' => 'CC-OPS-01',
            'year' => $year,
            'allocated_amount' => 100000000,
            'committed_amount' => 0,
            'realized_amount' => 92000000, // 92% -> CRITICAL_90
        ]);

        Budget::create([
            'organization_id' => $org2->id,
            'cost_center_code' => 'CC-OPS-02',
            'year' => $year,
            'allocated_amount' => 50000000,
            'committed_amount' => 0,
            'realized_amount' => 55000000, // 110% -> OVERBUDGET_BLOCK
        ]);

        $summary = $this->ewsService->getBudgetAlertSummary($year);

        $this->assertEquals(2, $summary['total_budgets']);
        $this->assertEquals(1, $summary['critical_90']);
        $this->assertEquals(1, $summary['overbudget_100']);
        $this->assertEquals(2, $summary['action_required_count']);
        $this->assertEquals(150000000, $summary['total_allocated']);
        $this->assertEquals(147000000, $summary['total_spent']);
    }

    public function test_early_warning_view_accessible(): void
    {
        $year = (int) date('Y');

        Budget::create([
            'organization_id' => $this->org->id,
            'cost_center_code' => 'CC-OPS-01',
            'year' => $year,
            'allocated_amount' => 100000000,
            'committed_amount' => 0,
            'realized_amount' => 88000000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('master.budgets.early_warning', [
            'year' => $year,
            'risk_level' => 'WARNING_80',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Kantor Cabang Surabaya');
        $response->assertSee('CC-OPS-01');
        $response->assertSee('Waspada (80-89%)');
    }

    public function test_budget_early_warning_notification_dispatch(): void
    {
        $year = (int) date('Y');

        $orderApprover = User::factory()->create([
            'role' => 'ORDER_APPROVER',
            'organization_id' => $this->org->id,
        ]);

        Budget::create([
            'organization_id' => $this->org->id,
            'cost_center_code' => 'CC-OPS-01',
            'year' => $year,
            'allocated_amount' => 100000000,
            'committed_amount' => 0,
            'realized_amount' => 95000000, // 95% -> CRITICAL
        ]);

        $response = $this->actingAs($this->admin)->post(route('master.budgets.notify_alert'), [
            'year' => $year,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('notifications', [
            'type' => 'ALERT',
            'target_role' => 'ORDER_APPROVER',
            'reference_transaction_type' => 'BUDGET_CRITICAL_90',
            'priority' => 'CRITICAL',
        ]);
    }
}
