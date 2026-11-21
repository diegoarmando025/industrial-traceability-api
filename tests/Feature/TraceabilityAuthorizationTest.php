<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TraceabilityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private ?int $plantId = null;

    private function actingWithAbilities(array $abilities): void
    {
        if ($this->plantId === null) {
            $this->plantId = Plant::create(['code' => 'QRO', 'name' => 'Demo Plant'])->id;
        }
        $user = User::factory()->create();
        $user->plants()->attach($this->plantId, ['role' => 'supervisor']);
        Sanctum::actingAs($user, $abilities);
    }

    private function createBoxAsAuthorizedUser(): int
    {
        $this->actingWithAbilities(['boxes:create']);

        return $this->postJson('/api/v1/boxes', [
            'plant_id' => $this->plantId,
            'code' => 'BX-AUTH-001',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
        ])->assertCreated()->json('data.id');
    }

    public function test_token_without_create_ability_cannot_create_boxes(): void
    {
        $this->actingWithAbilities(['boxes:read']);

        $this->postJson('/api/v1/boxes', [
            'plant_id' => $this->plantId,
            'code' => 'BX-DENIED',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
        ])->assertForbidden();

        $this->assertDatabaseMissing('boxes', ['code' => 'BX-DENIED']);
    }

    public function test_read_only_token_can_view_but_cannot_scan_or_release(): void
    {
        $id = $this->createBoxAsAuthorizedUser();

        $this->actingWithAbilities(['boxes:read']);

        $this->getJson("/api/v1/boxes/{$id}")->assertOk();
        $this->getJson("/api/v1/boxes/{$id}/history")->assertOk();

        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'part_number',
            'value' => 'PN-100',
        ])->assertForbidden();

        $this->postJson("/api/v1/boxes/{$id}/release")->assertForbidden();
        $this->assertDatabaseCount('box_scans', 0);
        $this->assertDatabaseHas('boxes', ['id' => $id, 'status' => 'pending']);
    }

    public function test_scan_only_token_can_scan_but_cannot_view_or_release(): void
    {
        $id = $this->createBoxAsAuthorizedUser();

        $this->actingWithAbilities(['boxes:scan']);

        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'part_number',
            'value' => 'PN-100',
        ])->assertCreated()->assertJsonPath('data.accepted', true);

        $this->getJson("/api/v1/boxes/{$id}")->assertForbidden();
        $this->getJson("/api/v1/boxes/{$id}/history")->assertForbidden();
        $this->postJson("/api/v1/boxes/{$id}/release")->assertForbidden();
    }

    public function test_release_only_token_can_attempt_release_without_read_or_scan(): void
    {
        $id = $this->createBoxAsAuthorizedUser();

        $this->actingWithAbilities(['boxes:release']);

        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertStatus(409)
            ->assertJsonPath('data.released', false);

        $this->getJson("/api/v1/boxes/{$id}")->assertForbidden();
        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'part_number',
            'value' => 'PN-100',
        ])->assertForbidden();
    }

    public function test_token_with_no_abilities_is_denied(): void
    {
        $id = $this->createBoxAsAuthorizedUser();

        $this->actingWithAbilities([]);

        $this->getJson("/api/v1/boxes/{$id}")->assertForbidden();
        $this->postJson("/api/v1/boxes/{$id}/release")->assertForbidden();
    }
}
