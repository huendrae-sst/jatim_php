<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class MasterAccountingManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_accounting_index_renders_successfully_with_tabs_and_metrics(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.accounting'));

        $response->assertStatus(200);
        $response->assertSee('Master Data Accounting');
        $response->assertSee('nav-pills card-header-pills nav-pills-scroll', false);
        $response->assertSee('Chart of Accounts (Rekening GL)');
        $response->assertSee('Master Cost Center');
        $response->assertDontSee('<div class="info-box', false);
        $response->assertSee('table-striped', false);
        $response->assertSee('openCreateCoaModal');
        $response->assertSee('openCreateCcModal');
    }

    public function test_coa_crud_operations(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        // 1. Create CoA
        $response = $this->actingAs($admin)->post(route('master.coa.store'), [
            'account_code' => '11399',
            'account_name' => 'Persediaan Khusus Cadangan Test',
            'account_type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'classification' => 'Aset Lancar',
            'description' => 'Akun khusus pengetesan',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('master.accounting', ['tab' => 'coa']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('chart_of_accounts', [
            'account_code' => '11399',
            'account_name' => 'Persediaan Khusus Cadangan Test',
            'account_type' => 'ASSET',
            'normal_balance' => 'DEBIT',
        ]);

        $coa = ChartOfAccount::where('account_code', '11399')->first();

        // 2. Update CoA
        $updateResponse = $this->actingAs($admin)->put(route('master.coa.update', $coa->id), [
            'account_code' => '11399',
            'account_name' => 'Persediaan Cadangan Diperbarui',
            'account_type' => 'ASSET',
            'normal_balance' => 'DEBIT',
            'classification' => 'Aset Lancar Logistik',
            'description' => 'Deskripsi baru',
            'is_active' => true,
        ]);

        $updateResponse->assertRedirect(route('master.accounting', ['tab' => 'coa']));
        $updateResponse->assertSessionHas('success');

        $coa->refresh();
        $this->assertEquals('Persediaan Cadangan Diperbarui', $coa->account_name);
        $this->assertEquals('Aset Lancar Logistik', $coa->classification);

        // 3. Toggle Status
        $toggleResponse = $this->actingAs($admin)->patch(route('master.coa.toggle_status', $coa->id));
        $toggleResponse->assertRedirect(route('master.accounting', ['tab' => 'coa']));
        $toggleResponse->assertSessionHas('success');

        $coa->refresh();
        $this->assertFalse($coa->is_active);

        // 4. Delete CoA
        $deleteResponse = $this->actingAs($admin)->delete(route('master.coa.destroy', $coa->id));
        $deleteResponse->assertRedirect(route('master.accounting', ['tab' => 'coa']));
        $deleteResponse->assertSessionHas('success');

        $this->assertDatabaseMissing('chart_of_accounts', [
            'id' => $coa->id,
        ]);
    }

    public function test_cost_center_crud_operations(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $org = Organization::first();

        // 1. Create Cost Center
        $response = $this->actingAs($admin)->post(route('master.cost_centers.store'), [
            'code' => 'CC-TEST-999',
            'name' => 'Cost Center Khusus Pengujian',
            'organization_id' => $org->id,
            'department' => 'Divisi Pengawasan Logistik',
            'pic_name' => 'Ahmad Testing',
            'notes' => 'Catatan unit uji',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('master.accounting', ['tab' => 'cost_centers']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cost_centers', [
            'code' => 'CC-TEST-999',
            'name' => 'Cost Center Khusus Pengujian',
            'organization_id' => $org->id,
        ]);

        $cc = CostCenter::where('code', 'CC-TEST-999')->first();

        // 2. Update Cost Center
        $updateResponse = $this->actingAs($admin)->put(route('master.cost_centers.update', $cc->id), [
            'code' => 'CC-TEST-999',
            'name' => 'Cost Center Diperbarui',
            'organization_id' => $org->id,
            'department' => 'Divisi Operasional & Akuntansi',
            'pic_name' => 'Ahmad Updated',
            'notes' => 'Catatan revisi',
            'is_active' => true,
        ]);

        $updateResponse->assertRedirect(route('master.accounting', ['tab' => 'cost_centers']));
        $updateResponse->assertSessionHas('success');

        $cc->refresh();
        $this->assertEquals('Cost Center Diperbarui', $cc->name);
        $this->assertEquals('Ahmad Updated', $cc->pic_name);

        // 3. Toggle Status
        $toggleResponse = $this->actingAs($admin)->patch(route('master.cost_centers.toggle_status', $cc->id));
        $toggleResponse->assertRedirect(route('master.accounting', ['tab' => 'cost_centers']));
        $toggleResponse->assertSessionHas('success');

        $cc->refresh();
        $this->assertFalse($cc->is_active);

        // 4. Delete Cost Center
        $deleteResponse = $this->actingAs($admin)->delete(route('master.cost_centers.destroy', $cc->id));
        $deleteResponse->assertRedirect(route('master.accounting', ['tab' => 'cost_centers']));
        $deleteResponse->assertSessionHas('success');

        $this->assertDatabaseMissing('cost_centers', [
            'id' => $cc->id,
        ]);
    }

    public function test_coa_validation_duplicate_code_fails(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        // 11301 is already seeded in DatabaseSeeder
        $response = $this->actingAs($admin)->post(route('master.coa.store'), [
            'account_code' => '11301',
            'account_name' => 'Duplicated Code',
            'account_type' => 'ASSET',
            'normal_balance' => 'DEBIT',
        ]);

        $response->assertSessionHasErrors('account_code');
    }

    public function test_cost_center_validation_duplicate_code_fails(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        // CC-KP-001 is already seeded
        $response = $this->actingAs($admin)->post(route('master.cost_centers.store'), [
            'code' => 'CC-KP-001',
            'name' => 'Duplicated Cost Center',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_search_and_filter_accounting_masters(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        // Search CoA
        $resCoaSearch = $this->actingAs($admin)->get(route('master.accounting', ['tab' => 'coa', 'coa_search' => '11301']));
        $resCoaSearch->assertStatus(200);
        $resCoaSearch->assertSee('11301');

        // Filter CoA by type
        $resCoaType = $this->actingAs($admin)->get(route('master.accounting', ['tab' => 'coa', 'coa_type' => 'EXPENSE']));
        $resCoaType->assertStatus(200);
        $resCoaType->assertSee('Beban');

        // Search Cost Center
        $resCcSearch = $this->actingAs($admin)->get(route('master.accounting', ['tab' => 'cost_centers', 'cc_search' => 'CC-KC-SBY']));
        $resCcSearch->assertStatus(200);
        $resCcSearch->assertSee('CC-KC-SBY');
    }
}
