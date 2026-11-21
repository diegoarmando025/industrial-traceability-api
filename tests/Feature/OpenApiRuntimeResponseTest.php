<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\OpenApiResponseValidator;
use Tests\TestCase;

class OpenApiRuntimeResponseTest extends TestCase
{
    use RefreshDatabase;

    private OpenApiResponseValidator $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contract = new OpenApiResponseValidator();
    }

    private function verify(string $method, string $path, int $status, \Illuminate\Testing\TestResponse $response): void
    {
        $response->assertStatus($status);
        $this->contract->assertResponse($method, $path, $status, $response->json());
    }

    private function authenticate(Plant $plant, string $role, array $abilities): User
    {
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => $role]);
        Sanctum::actingAs($user, $abilities);
        return $user;
    }

    private function createBox(int $plantId, string $code): array
    {
        return [
            'plant_id' => $plantId,
            'code' => $code,
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
        ];
    }

    public function test_real_json_matches_contract_for_registration_and_retrieval(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Test QRO']);
        $this->authenticate($plant, 'supervisor', ['boxes:create', 'boxes:read']);

        $created = $this->postJson('/api/v1/boxes', $this->createBox($plant->id, 'QRO-100'));
        $this->verify('POST', '/api/v1/boxes', 201, $created);
        $id = $created->json('data.id');

        $this->verify('GET', '/api/v1/boxes/{box}', 200, $this->getJson("/api/v1/boxes/{$id}"));
        $this->verify('GET', '/api/v1/boxes/{box}/history', 200, $this->getJson("/api/v1/boxes/{$id}/history"));
    }

    public function test_rejected_scans_and_blocked_release_match_their_documented_422_and_409_schemas(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Test QRO']);
        $this->authenticate($plant, 'supervisor', ['boxes:create', 'boxes:scan', 'boxes:release', 'boxes:read']);

        $created = $this->postJson('/api/v1/boxes', $this->createBox($plant->id, 'QRO-200'));
        $id = $created->json('data.id');

        $badScan = $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'part_number', 'value' => 'WRONG-PN',
        ]);
        $this->verify('POST', '/api/v1/boxes/{box}/scans', 422, $badScan);
        $this->assertFalse($badScan->json('data.accepted'));

        $blocked = $this->postJson("/api/v1/boxes/{$id}/release");
        $this->verify('POST', '/api/v1/boxes/{box}/release', 409, $blocked);
        $this->assertFalse($blocked->json('data.released'));

        $history = $this->getJson("/api/v1/boxes/{$id}/history");
        $this->verify('GET', '/api/v1/boxes/{box}/history', 200, $history);
        $this->assertSame('release_blocked', $history->json('data.events.1.event_type'));
    }

    public function test_accepted_scans_and_authorized_release_match_success_schemas(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Test QRO']);
        $this->authenticate($plant, 'supervisor', ['boxes:create', 'boxes:scan', 'boxes:release', 'boxes:read']);

        $id = $this->postJson('/api/v1/boxes', $this->createBox($plant->id, 'QRO-300'))->json('data.id');

        foreach ([
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'package_label' => 'BOX:QRO-300:PN-100:SN-200',
        ] as $type => $value) {
            $response = $this->postJson("/api/v1/boxes/{$id}/scans", [
                'scan_type' => $type, 'value' => $value,
            ]);
            $this->verify('POST', '/api/v1/boxes/{box}/scans', 201, $response);
        }

        $released = $this->postJson("/api/v1/boxes/{$id}/release");
        $this->verify('POST', '/api/v1/boxes/{box}/release', 200, $released);
        $this->assertTrue($released->json('data.released'));

        // A repeat release uses the Error alternative of HTTP 409's oneOf schema.
        $this->verify('POST', '/api/v1/boxes/{box}/release', 409, $this->postJson("/api/v1/boxes/{$id}/release"));
    }

    public function test_cross_plant_access_returns_documented_404_and_never_leaks_box_contents(): void
    {
        $inside = Plant::create(['code' => 'QRO', 'name' => 'Test QRO']);
        $outside = Plant::create(['code' => 'MTY', 'name' => 'Private MTY']);

        $box = Box::create([
            'plant_id' => $outside->id,
            'code' => 'MTY-SECRET-001',
            'part_number' => 'PRIVATE-PN-876',
            'serial_number' => 'PRIVATE-SN-543',
            'status' => 'pending',
        ]);
        $this->authenticate($inside, 'supervisor', ['boxes:read', 'boxes:scan', 'boxes:release']);

        $checks = [
            ['GET', '/api/v1/boxes/{box}', $this->getJson("/api/v1/boxes/{$box->id}")],
            ['GET', '/api/v1/boxes/{box}/history', $this->getJson("/api/v1/boxes/{$box->id}/history")],
            ['POST', '/api/v1/boxes/{box}/release', $this->postJson("/api/v1/boxes/{$box->id}/release")],
            ['POST', '/api/v1/boxes/{box}/scans', $this->postJson("/api/v1/boxes/{$box->id}/scans", [
                'scan_type' => 'part_number', 'value' => 'PRIVATE-PN-876',
            ])],
        ];

        foreach ($checks as [$method, $path, $response]) {
            $this->verify($method, $path, 404, $response);
            $response->assertDontSee('PRIVATE-PN-876')->assertDontSee('MTY-SECRET-001');
        }

        $this->assertDatabaseCount('box_scans', 0);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'status' => 'pending']);
    }

    public function test_role_denied_response_matches_contract(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Test QRO']);
        $box = Box::create([
            'plant_id' => $plant->id,
            'code' => 'QRO-400',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'status' => 'pending',
        ]);
        $this->authenticate($plant, 'viewer', ['boxes:read', 'boxes:release']);

        $this->verify('POST', '/api/v1/boxes/{box}/release', 403, $this->postJson("/api/v1/boxes/{$box->id}/release"));
    }
    public function test_unauthenticated_response_matches_contract(): void
    {
        $this->verify('GET', '/api/v1/boxes/{box}', 401, $this->getJson('/api/v1/boxes/999'));
    }
}
