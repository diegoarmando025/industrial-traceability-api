<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantAccessTest extends TestCase
{
    use RefreshDatabase;

    private function plant(string $code): Plant
    {
        return Plant::create(['code' => $code, 'name' => "Plant {$code}"]);
    }

    private function userFor(Plant $plant, string $role, array $abilities = ['*']): User
    {
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => $role]);
        Sanctum::actingAs($user, $abilities);

        return $user;
    }

    private function boxIn(Plant $plant, string $code): Box
    {
        return Box::create([
            'plant_id' => $plant->id,
            'code' => $code,
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_read_scan_release_or_view_history_in_another_plant(): void
    {
        $qro = $this->plant('QRO');
        $mty = $this->plant('MTY');
        $box = $this->boxIn($mty, 'MTY-001');
        $this->userFor($qro, 'supervisor');

        $this->getJson("/api/v1/boxes/{$box->id}")->assertNotFound();
        $this->getJson("/api/v1/boxes/{$box->id}/history")->assertNotFound();
        $this->postJson("/api/v1/boxes/{$box->id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertNotFound();
        $this->postJson("/api/v1/boxes/{$box->id}/release")->assertNotFound();

        $this->assertDatabaseCount('box_scans', 0);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'status' => 'pending']);
    }

    public function test_unassigned_user_cannot_create_box_for_any_plant(): void
    {
        $plant = $this->plant('QRO');
        Sanctum::actingAs(User::factory()->create(), ['boxes:create']);

        $this->postJson('/api/v1/boxes', [
            'plant_id' => $plant->id,
            'code' => 'DENIED-001',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
        ])->assertNotFound();

        $this->assertDatabaseMissing('boxes', ['code' => 'DENIED-001']);
    }

    public function test_viewer_can_read_but_cannot_create_scan_or_release(): void
    {
        $plant = $this->plant('QRO');
        $box = $this->boxIn($plant, 'QRO-001');
        $this->userFor($plant, 'viewer');

        $this->getJson("/api/v1/boxes/{$box->id}")->assertOk();
        $this->getJson("/api/v1/boxes/{$box->id}/history")->assertOk();

        $this->postJson('/api/v1/boxes', [
            'plant_id' => $plant->id, 'code' => 'DENIED-002',
            'part_number' => 'PN-100', 'serial_number' => 'SN-200',
        ])->assertForbidden();

        $this->postJson("/api/v1/boxes/{$box->id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertForbidden();

        $this->postJson("/api/v1/boxes/{$box->id}/release")->assertForbidden();
    }

    public function test_operator_can_create_and_scan_but_cannot_release(): void
    {
        $plant = $this->plant('QRO');
        $this->userFor($plant, 'operator');

        $id = $this->postJson('/api/v1/boxes', [
            'plant_id' => $plant->id, 'code' => 'QRO-002',
            'part_number' => 'PN-100', 'serial_number' => 'SN-200',
        ])->assertCreated()->assertJsonPath('data.plant_id', $plant->id)->json('data.id');

        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertCreated();

        $this->postJson("/api/v1/boxes/{$id}/release")->assertForbidden();
    }

    public function test_supervisor_can_release_after_all_valid_scans(): void
    {
        $plant = $this->plant('QRO');
        $user = $this->userFor($plant, 'supervisor');
        $box = $this->boxIn($plant, 'QRO-003');

        foreach ([
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'package_label' => 'BOX:QRO-003:PN-100:SN-200',
        ] as $type => $value) {
            $this->postJson("/api/v1/boxes/{$box->id}/scans", [
                'scan_type' => $type,
                'value' => $value,
            ])->assertCreated();
        }

        $this->postJson("/api/v1/boxes/{$box->id}/release")
            ->assertOk()->assertJsonPath('data.released', true);

        $this->assertDatabaseHas('box_events', [
            'box_id' => $box->id,
            'user_id' => $user->id,
            'event_type' => 'released',
        ]);
    }

    public function test_unassigned_legacy_box_is_not_accessible(): void
    {
        $plant = $this->plant('QRO');
        $box = Box::create([
            'code' => 'LEGACY-001',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'status' => 'pending',
        ]);
        $this->userFor($plant, 'supervisor');

        $this->getJson("/api/v1/boxes/{$box->id}")->assertNotFound();
        $this->postJson("/api/v1/boxes/{$box->id}/release")->assertNotFound();
    }

    public function test_tokens_still_restrict_actions_inside_authorized_plant(): void
    {
        $plant = $this->plant('QRO');
        $box = $this->boxIn($plant, 'QRO-004');
        $this->userFor($plant, 'supervisor', ['boxes:read']);

        $this->getJson("/api/v1/boxes/{$box->id}")->assertOk();
        $this->postJson("/api/v1/boxes/{$box->id}/release")->assertForbidden();
    }
}
