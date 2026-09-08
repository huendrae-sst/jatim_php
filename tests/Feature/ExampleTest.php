<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Login');
    }

    public function test_register_page_returns_successful_response(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('Register a new membership');
    }

    public function test_forgot_password_page_returns_successful_response(): void
    {
        $response = $this->get('/forgot-password');
        $response->assertStatus(200);
        $response->assertSee('retrieve a new password');
    }

    public function test_user_can_register_new_membership(): void
    {
        $response = $this->post('/register', [
            'name' => 'Calon Pegawai Jatim',
            'email' => 'calon.pegawai@bankjatim.co.id',
            'nip' => '99887766',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'agree' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'calon.pegawai@bankjatim.co.id',
            'nip' => '99887766',
        ]);
    }

    public function test_forgot_password_request(): void
    {
        $user = User::factory()->create([
            'email' => 'pegawai.reset@bankjatim.co.id',
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'pegawai.reset@bankjatim.co.id',
        ]);

        $response->assertSessionHas('status');
    }

    public function test_user_can_login_with_password123(): void
    {
        $user = User::factory()->create([
            'email' => 'teller.test@bankjatim.co.id',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'teller.test@bankjatim.co.id',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_demo_password_fallback(): void
    {
        $user = User::factory()->create([
            'email' => 'branch.test@bankjatim.co.id',
            'password' => Hash::make('password123'),
        ]);

        // When user types 'password', fallback allows login
        $response = $this->post('/login', [
            'email' => 'branch.test@bankjatim.co.id',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_quick_switch_from_login_page_succeeds(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User Quick',
            'email' => 'quick.test@bankjatim.co.id',
        ]);

        $response = $this->from(route('login'))->post(route('quick.switch'), [
            'user_id' => $user->id,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_switching_stocks_page_returns_successful_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/inventory/switching-stocks');

        $response->assertStatus(200);
    }

    public function test_settlements_page_returns_successful_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/finance/settlements');

        $response->assertStatus(200);
        $response->assertSee('info-box');
        $response->assertSee('Perlu Settlement');
        $response->assertSee('Menunggu Approval');
        $response->assertSee('Telah Diposting');
        $response->assertSee('Total Nilai Settlement');
    }
}
