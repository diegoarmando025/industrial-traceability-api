<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TraceabilityApiTest extends TestCase
{
    use RefreshDatabase;

    private int $plantId;

    private function authenticate(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Demo Plant']);
        $this->plantId = $plant->id;
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => 'supervisor']);
        Sanctum::actingAs($user, [
            'boxes:create',
            'boxes:read',
            'boxes:scan',
            'boxes:release',
        ]);
    }

    private function box(): int
    {
        return $this->postJson('/api/v1/boxes', [
            'plant_id' => $this->plantId, 'code' => 'BX-0001', 'part_number' => 'PN-100', 'serial_number' => 'SN-200',
        ])->assertCreated()->json('data.id');
    }

    public function test_authentication_is_required(): void
    {
        $this->postJson('/api/v1/boxes', [])->assertUnauthorized();
    }

    public function test_release_is_blocked_when_scans_are_missing(): void
    {
        $this->authenticate();
        $id = $this->box();
        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertStatus(409)->assertJsonPath('data.released', false)
            ->assertJsonCount(3, 'data.pending_scans');
    }

    public function test_invalid_scan_is_recorded_and_does_not_release_box(): void
    {
        $this->authenticate();
        $id = $this->box();
        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'part_number', 'value' => 'WRONG',
        ])->assertStatus(422)->assertJsonPath('data.accepted', false);
        $this->assertDatabaseHas('box_scans', ['box_id' => $id, 'accepted' => false]);
        $this->postJson("/api/v1/boxes/{$id}/release")->assertStatus(409);
    }

    public function test_valid_scans_allow_release_only_once(): void
    {
        $this->authenticate();
        $id = $this->box();
        foreach ([
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'package_label' => 'BOX:BX-0001:PN-100:SN-200',
        ] as $type => $value) {
            $this->postJson("/api/v1/boxes/{$id}/scans", [
                'scan_type' => $type, 'value' => $value,
            ])->assertCreated();
        }
        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertOk()->assertJsonPath('data.released', true);
        $this->postJson("/api/v1/boxes/{$id}/release")->assertStatus(409);
        $this->assertDatabaseHas('boxes', ['id' => $id, 'status' => 'released']);
    }

    public function test_a_later_invalid_scan_invalidates_earlier_valid_scan(): void
    {
        $this->authenticate();
        $id = $this->box();
        foreach ([
            ['part_number', 'PN-100'], ['serial_number', 'SN-200'],
            ['package_label', 'BOX:BX-0001:PN-100:SN-200'],
            ['part_number', 'WRONG'],
        ] as [$type, $value]) {
            $this->postJson("/api/v1/boxes/{$id}/scans", [
                'scan_type' => $type, 'value' => $value,
            ]);
        }
        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertStatus(409)->assertJsonPath('data.pending_scans.0', 'part_number');
    }
}
