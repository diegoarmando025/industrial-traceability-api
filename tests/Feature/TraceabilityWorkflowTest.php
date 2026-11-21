<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TraceabilityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_box_goes_from_registration_to_supervisor_release_with_full_audit(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Demo QRO']);
        $otherPlant = Plant::create(['code' => 'MTY', 'name' => 'Demo MTY']);

        $operator = User::factory()->create();
        $operator->plants()->attach($plant->id, ['role' => 'operator']);

        $supervisor = User::factory()->create();
        $supervisor->plants()->attach($plant->id, ['role' => 'supervisor']);

        $outsider = User::factory()->create();
        $outsider->plants()->attach($otherPlant->id, ['role' => 'supervisor']);

        Sanctum::actingAs($operator, ['boxes:create', 'boxes:scan', 'boxes:read']);

        $boxId = $this->postJson('/api/v1/boxes', [
            'plant_id' => $plant->id,
            'code' => 'QRO-0001',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.plant_id', $plant->id)
            ->json('data.id');

        // A box with missing scans cannot be released, even by a supervisor.
        Sanctum::actingAs($supervisor, ['boxes:release', 'boxes:read']);
        $this->postJson("/api/v1/boxes/{$boxId}/release")
            ->assertStatus(409)
            ->assertJsonPath('data.released', false)
            ->assertJsonCount(3, 'data.pending_scans');

        // A scanner can submit an invalid value, which is persisted for audit.
        Sanctum::actingAs($operator, ['boxes:scan', 'boxes:read']);
        $this->postJson("/api/v1/boxes/{$boxId}/scans", [
            'scan_type' => 'part_number', 'value' => 'WRONG',
        ])->assertStatus(422)->assertJsonPath('data.accepted', false);

        foreach ([
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'package_label' => 'BOX:QRO-0001:PN-100:SN-200',
        ] as $type => $value) {
            $this->postJson("/api/v1/boxes/{$boxId}/scans", [
                'scan_type' => $type, 'value' => $value,
            ])->assertCreated()->assertJsonPath('data.accepted', true);
        }

        // The operator still cannot release a fully validated box.
        $this->postJson("/api/v1/boxes/{$boxId}/release")->assertForbidden();

        // An authorized token from another plant must not access the box.
        Sanctum::actingAs($outsider, ['boxes:read', 'boxes:release']);
        $this->getJson("/api/v1/boxes/{$boxId}")->assertNotFound();
        $this->postJson("/api/v1/boxes/{$boxId}/release")->assertNotFound();

        Sanctum::actingAs($supervisor, ['boxes:release', 'boxes:read']);
        $this->postJson("/api/v1/boxes/{$boxId}/release")
            ->assertOk()
            ->assertJsonPath('data.released', true)
            ->assertJsonPath('data.pending_scans', []);

        $this->getJson("/api/v1/boxes/{$boxId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'released');

        $this->getJson("/api/v1/boxes/{$boxId}/history")
            ->assertOk()
            ->assertJsonCount(4, 'data.scans')
            ->assertJsonCount(3, 'data.events')
            ->assertJsonPath('data.events.0.event_type', 'created')
            ->assertJsonPath('data.events.1.event_type', 'release_blocked')
            ->assertJsonPath('data.events.2.event_type', 'released');

        $this->assertDatabaseHas('box_events', [
            'box_id' => $boxId,
            'user_id' => $supervisor->id,
            'event_type' => 'released',
        ]);

        $this->assertDatabaseHas('box_scans', [
            'box_id' => $boxId,
            'user_id' => $operator->id,
            'scanned_value' => 'WRONG',
            'accepted' => false,
        ]);

        // A second release attempt must not create another release event.
        $this->postJson("/api/v1/boxes/{$boxId}/release")->assertStatus(409);
        $this->assertDatabaseCount('box_events', 3);
    }
}
