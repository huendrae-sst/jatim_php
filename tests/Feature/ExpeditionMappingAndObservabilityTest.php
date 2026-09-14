<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\ExpeditionMapping;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpeditionMappingAndObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $branch;

    private Courier $courierJne;

    private Courier $courierInternal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Organization::create([
            'code' => 'KC-MLG',
            'name' => 'Kantor Cabang Malang',
            'type' => 'MAIN_BRANCH',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'SUPER_ADMIN',
            'organization_id' => $this->branch->id,
        ]);

        $this->courierJne = Courier::create([
            'code' => 'JNE',
            'name' => 'JNE Express',
            'contact_person' => 'Customer Care',
            'phone' => '021-29278888',
            'sla_days' => 2,
            'is_active' => true,
        ]);

        $this->courierInternal = Courier::create([
            'code' => 'INTERNAL',
            'name' => 'Armada Logistik Bank',
            'contact_person' => 'Divisi Umum',
            'phone' => '031-567890',
            'sla_days' => 1,
            'is_active' => true,
        ]);
    }

    public function test_expedition_mapping_crud(): void
    {
        // Store
        $response = $this->actingAs($this->admin)->post(route('master.expedition_mappings.store'), [
            'organization_id' => $this->branch->id,
            'courier_id' => $this->courierJne->id,
            'default_service_type' => 'EXPRESS',
            'notes' => 'Rute prioritas antar cabang',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expedition_mappings', [
            'organization_id' => $this->branch->id,
            'courier_id' => $this->courierJne->id,
            'default_service_type' => 'EXPRESS',
        ]);

        // Default relationship check
        $this->assertEquals($this->courierJne->id, $this->branch->fresh()->defaultExpeditionMapping->courier_id);

        // Index page check
        $indexResponse = $this->actingAs($this->admin)->get(route('master.expedition_mappings'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Kantor Cabang Malang');
        $indexResponse->assertSee('JNE Express');
        $indexResponse->assertSee('EXPRESS');

        // Destroy
        $mapping = ExpeditionMapping::first();
        $destroyResponse = $this->actingAs($this->admin)->delete(route('master.expedition_mappings.destroy', $mapping->id));
        $destroyResponse->assertRedirect();
        $this->assertDatabaseMissing('expedition_mappings', ['id' => $mapping->id]);
    }

    public function test_correlation_id_middleware_injects_response_header(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Correlation-ID');
        $correlationId = $response->headers->get('X-Correlation-ID');
        $this->assertTrue(Str::isUuid($correlationId));
    }

    public function test_correlation_id_middleware_preserves_incoming_header(): void
    {
        $customId = 'test-trace-id-12345';
        $response = $this->withHeaders([
            'X-Correlation-ID' => $customId,
        ])->get('/login');

        $response->assertHeader('X-Correlation-ID', $customId);
    }
}
