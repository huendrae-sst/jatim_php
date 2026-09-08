<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

class MasterViewsStandardizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
    }

    public function test_master_organizations_page_renders_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.organizations'));

        $response->assertStatus(200);
        $response->assertSee('Master Organisasi');
        $response->assertDontSee('small-box', false);
        $response->assertSee('Tambah Unit Kerja Baru');
        $response->assertSee('Tambah Gudang Baru');
        $response->assertSee('openViewOrgModal');
        $response->assertSee('openEditOrgModal');
        $response->assertSee('Menampilkan');
        $response->assertSee('Baris per halaman');
    }

    public function test_master_budgets_page_renders_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.budgets'));

        $response->assertStatus(200);
        $response->assertSee('Pagu Anggaran Persediaan');
        $response->assertDontSee('small-box', false);
        $response->assertSee('Tambah Alokasi Pagu');
        $response->assertSee('openViewModal');
        $response->assertSee('Menampilkan');
        $response->assertSee('Baris per halaman');
    }

    public function test_master_vendors_couriers_page_renders_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.vendors'));

        $response->assertStatus(200);
        $response->assertDontSee('small-box', false);
        $response->assertSee('Vendor');
        $response->assertSee('Ekspedisi');
        $response->assertSee('Vendor Rekanan');
        $response->assertSee('Mitra Ekspedisi');
        $response->assertSee('Tambah Vendor Rekanan');
        $response->assertSee('Tambah Mitra Ekspedisi');
        $response->assertSee('openVendorModal');
        $response->assertSee('openCourierModal');
        $response->assertSee('Menampilkan');
        $response->assertSee('Baris per halaman');
    }

    public function test_master_users_page_renders_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->get(route('master.users'));

        $response->assertStatus(200);
        $response->assertDontSee('small-box', false);
        $response->assertSee('Manajemen User & Hak Akses');
        $response->assertSee('Tambah Pengguna Baru');
        $response->assertSee('openViewModal');
        $response->assertSee('Menampilkan');
        $response->assertSee('Baris per halaman');
    }

    public function test_can_create_budget_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();
        $org = Organization::create([
            'code' => 'CBG-TEST-001',
            'name' => 'Cabang Baru Test',
            'type' => 'SUB_BRANCH',
            'cost_center_code' => 'CC-TEST-001',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('master.budgets.store'), [
            'organization_id' => $org->id,
            'cost_center_code' => 'CC-TEST-001',
            'year' => 2026,
            'allocated_amount' => 250000000,
            'notes' => 'Alokasi awal cabang baru',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('budgets', [
            'organization_id' => $org->id,
            'cost_center_code' => 'CC-TEST-001',
            'year' => 2026,
            'allocated_amount' => 250000000,
        ]);
    }

    public function test_can_create_vendor_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->post(route('master.vendors.store'), [
            'code' => 'VND-TEST-01',
            'name' => 'PT Test Vendor Persediaan',
            'sla_days' => 5,
            'payment_terms' => 'TOP 30 Hari',
            'rating' => 4.8,
            'phone' => '031-123456',
            'email' => 'contact@testvendor.com',
            'address' => 'Jl. Pemuda No. 10 Surabaya',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('vendors', [
            'code' => 'VND-TEST-01',
            'name' => 'PT Test Vendor Persediaan',
        ]);
    }

    public function test_can_create_courier_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->post(route('master.couriers.store'), [
            'code' => 'TEST-EXP',
            'name' => 'Ekspedisi Express Logistik',
            'sla_days' => 3,
            'phone' => '0811223344',
            'service_types' => ['REGULER', 'EXPRESS'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('couriers', [
            'code' => 'TEST-EXP',
            'name' => 'Ekspedisi Express Logistik',
        ]);
    }

    public function test_can_create_user_successfully(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $response = $this->actingAs($admin)->post(route('master.users.store'), [
            'name' => 'Ahmad User Baru',
            'nip' => '199501012022011005',
            'email' => 'ahmad.baru@bankjatim.co.id',
            'role' => 'REQUESTER_CABANG',
            'phone' => '081234567899',
            'approval_limit' => 50000000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'nip' => '199501012022011005',
            'email' => 'ahmad.baru@bankjatim.co.id',
        ]);
    }

    public function test_master_pages_pagination_standardization_and_reusable_footer(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $routes = [
            'master.organizations',
            'master.items',
            'master.budgets',
            'master.vendors',
            'master.users',
        ];

        foreach ($routes as $routeName) {
            // Test default per_page is 10
            $res = $this->actingAs($admin)->get(route($routeName));
            $res->assertStatus(200);
            $this->assertEquals(10, $res->viewData('perPage'));
            $res->assertSee('table-striped');
            $res->assertSee('Baris per halaman');
            $res->assertSee('name="per_page"', false);

            // Test custom per_page selection
            $resCustom = $this->actingAs($admin)->get(route($routeName, ['per_page' => 25]));
            $resCustom->assertStatus(200);
            $this->assertEquals(25, $resCustom->viewData('perPage'));
        }
    }

    public function test_all_master_data_pages_have_print_button_next_to_add_button(): void
    {
        $admin = User::where('role', 'SUPER_ADMIN')->first();

        $routes = [
            'master.items',
            'master.organizations',
            'master.budgets',
            'master.accounting',
            'master.vendors',
            'master.users',
        ];

        foreach ($routes as $routeName) {
            $response = $this->actingAs($admin)->get(route($routeName));
            $response->assertStatus(200);
            $response->assertSee('window.print()', false);
            $response->assertSee('bi-printer', false);
            $response->assertSee('Cetak');
        }
    }
}
