<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\User;
use App\Services\TraceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TransactionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoked_plant_membership_prevents_scanning_and_release_at_service_boundary(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Fictional Plant']);
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => 'supervisor']);
        $service = app(TraceabilityService::class);
        $box = $service->create([
            'plant_id' => $plant->id,
            'code' => 'TX-AUTH-001',
            'part_number' => 'PN-001',
            'serial_number' => 'SN-001',
        ], $user->id);

        $user->plants()->detach($plant->id);

        try {
            $service->scan($box, 'part_number', 'PN-001', $user->id);
            $this->fail('A revoked account must not record scans.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        try {
            $service->release($box, $user->id);
            $this->fail('A revoked account must not release a box.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('box_scans', 0);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('box_events', ['box_id' => $box->id, 'event_type' => 'release_blocked']);
    }

    public function test_http_create_requires_plant_membership(): void
    {
        $plant = Plant::create(['code' => 'MTY', 'name' => 'Fictional Plant']);
        $user = User::factory()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user, ['boxes:create']);

        $this->postJson('/api/v1/boxes', [
            'plant_id' => $plant->id,
            'code' => 'TX-AUTH-002',
            'part_number' => 'PN-002',
            'serial_number' => 'SN-002',
        ])->assertNotFound();

        $this->assertDatabaseCount('boxes', 0);
    }
}
