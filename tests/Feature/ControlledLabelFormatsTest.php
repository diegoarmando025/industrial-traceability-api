<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use App\Support\BoxValidationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ControlledLabelFormatsTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(Plant $plant): User
    {
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => 'supervisor']);
        Sanctum::actingAs($user, ['boxes:create', 'boxes:scan', 'boxes:release', 'boxes:read']);

        return $user;
    }

    private function box(Plant $plant, string $code, ?int $profileId = null): int
    {
        return $this->postJson('/api/v1/boxes', [
            'plant_id' => $plant->id,
            'code' => $code,
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'customer_profile_id' => $profileId,
        ])->assertCreated()->json('data.id');
    }

    public function test_existing_default_and_legacy_snapshots_still_expect_colons(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Demo']);
        $this->supervisor($plant);
        $newId = $this->box($plant, 'DEFAULT');
        $legacy = Box::create([
            'plant_id' => $plant->id,
            'code' => 'LEGACY',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'status' => 'pending',
            'validation_rules' => null,
        ]);

        foreach ([$newId => 'DEFAULT', $legacy->id => 'LEGACY'] as $id => $code) {
            $this->postJson("/api/v1/boxes/{$id}/scans", [
                'scan_type' => 'package_label',
                'value' => 'BOX:'.$code.':PN-100:SN-200',
            ])->assertCreated()->assertJsonPath('data.accepted', true);
        }
    }

    public function test_supervisor_can_create_pipe_profile_and_box_release_uses_exact_alternative_layout(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Demo']);
        $user = $this->supervisor($plant);

        $this->actingAs($user)->post("/plants/{$plant->id}/customers", [
            'code' => 'CLIENT_PIPE',
            'name' => 'Programa ficticio',
            'required_scans' => ['part_number', 'package_label'],
            'package_label_prefix' => 'PACK',
            'package_label_format' => 'pipe',
        ])->assertRedirect();

        $profile = $plant->customerProfiles()->sole();
        $this->assertSame('pipe', $profile->validation_rules['package_label_format']);
        $id = $this->box($plant, 'BX-901', $profile->id);

        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'package_label',
            'value' => 'PACK:BX-901:PN-100:SN-200',
        ])->assertStatus(422)->assertJsonPath('data.accepted', false);

        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'package_label',
            'value' => 'PACK|PN-100|SN-200|BX-901',
        ])->assertCreated();
        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertStatus(409)->assertJsonPath('data.pending_scans.0', 'part_number');
        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertCreated();
        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertOk()->assertJsonPath('data.released', true);
    }

    public function test_old_boxes_keep_pipe_snapshot_after_plant_rules_change(): void
    {
        $plant = Plant::create([
            'code' => 'QRO',
            'name' => 'Demo',
            'validation_rules' => [
                'required_scans' => ['package_label'],
                'package_label_prefix' => 'PACK',
                'package_label_format' => 'pipe',
            ],
        ]);
        $this->supervisor($plant);
        $old = $this->box($plant, 'OLD-1');
        $plant->update(['validation_rules' => BoxValidationRules::DEFAULT]);
        $new = $this->box($plant, 'NEW-1');

        $this->assertSame('pipe', Box::findOrFail($old)->validation_rules['package_label_format']);
        $this->assertArrayNotHasKey('package_label_format', Box::findOrFail($new)->validation_rules);
        $this->postJson("/api/v1/boxes/{$old}/scans", [
            'scan_type' => 'package_label', 'value' => 'PACK|PN-100|SN-200|OLD-1',
        ])->assertCreated();
        $this->postJson("/api/v1/boxes/{$old}/release")
            ->assertOk()->assertJsonPath('data.released', true);
        $this->postJson("/api/v1/boxes/{$new}/release")
            ->assertStatus(409)->assertJsonCount(3, 'data.pending_scans');
    }

    public function test_pipe_format_rejects_separator_in_record_fields_before_creation(): void
    {
        $plant = Plant::create([
            'code' => 'QRO',
            'name' => 'Demo',
            'validation_rules' => [
                'required_scans' => ['package_label'],
                'package_label_prefix' => 'PACK',
                'package_label_format' => 'pipe',
            ],
        ]);
        $this->supervisor($plant);
        $this->postJson('/api/v1/boxes', [
            'plant_id' => $plant->id,
            'code' => 'BX-ILLEGAL',
            'part_number' => 'PN|100',
            'serial_number' => 'SN-200',
        ])->assertUnprocessable()->assertJsonValidationErrors('part_number');
        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_invalid_format_is_rejected_in_configuration_forms(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Demo']);
        $user = $this->supervisor($plant);

        $this->actingAs($user)->post("/plants/{$plant->id}/customers", [
            'code' => 'BAD',
            'name' => 'Test',
            'required_scans' => ['part_number'],
            'package_label_prefix' => 'PACK',
            'package_label_format' => 'custom_php',
        ])->assertSessionHasErrors('package_label_format');

        $this->actingAs($user)->put("/plants/{$plant->id}/validation", [
            'version' => 0,
            'required_scans' => ['part_number'],
            'package_label_prefix' => 'PACK',
            'package_label_format' => 'custom_php',
        ])->assertSessionHasErrors('package_label_format');

        $this->assertDatabaseCount('plant_customer_profiles', 0);
        $this->assertNull($plant->fresh()->validation_rules);
    }

    public function test_supervisor_can_select_alternative_layout_in_plant_settings(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Demo']);
        $user = $this->supervisor($plant);

        $this->actingAs($user)->put("/plants/{$plant->id}/validation", [
            'version' => 0,
            'required_scans' => ['package_label'],
            'package_label_prefix' => 'PACK',
            'package_label_format' => 'pipe',
        ])->assertRedirect();

        $this->assertSame('pipe', $plant->fresh()->validation_rules['package_label_format']);
        $id = $this->box($plant, 'FROM-PLANT');
        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'package_label',
            'value' => 'PACK|PN-100|SN-200|FROM-PLANT',
        ])->assertCreated();
    }
}
