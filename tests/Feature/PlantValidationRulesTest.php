<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use App\Support\BoxValidationRules;
use App\Services\TraceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(Plant $plant): void
    {
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => 'supervisor']);
        Sanctum::actingAs($user, ['boxes:create', 'boxes:scan', 'boxes:release', 'boxes:read']);
    }

    private function createBox(Plant $plant, string $code): int
    {
        return $this->postJson('/api/v1/boxes', [
            'plant_id' => $plant->id, 'code' => $code,
            'part_number' => 'PN-100', 'serial_number' => 'SN-200',
        ])->assertCreated()->json('data.id');
    }

    public function test_default_validation_remains_unchanged_for_new_boxes(): void
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Original process']);
        $this->supervisor($plant);
        $id = $this->createBox($plant, 'BX-DEFAULT');
        $box = Box::findOrFail($id);

        $this->assertSame(BoxValidationRules::DEFAULT, $box->validation_rules);
        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertStatus(409)->assertJsonCount(3, 'data.pending_scans');
        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'package_label', 'value' => 'BOX:BX-DEFAULT:PN-100:SN-200',
        ])->assertCreated();
    }

    public function test_plant_rules_change_expected_label_and_required_scans(): void
    {
        $plant = Plant::create([
            'code' => 'ZIB', 'name' => 'Custom example',
            'validation_rules' => [
                'required_scans' => ['part_number', 'package_label'],
                'package_label_prefix' => 'PACK',
            ],
        ]);
        $this->supervisor($plant);
        $id = $this->createBox($plant, 'BX-CUSTOM');

        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'package_label', 'value' => 'BOX:BX-CUSTOM:PN-100:SN-200',
        ])->assertStatus(422)->assertJsonPath('data.accepted', false);
        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'package_label', 'value' => 'PACK:BX-CUSTOM:PN-100:SN-200',
        ])->assertCreated();
        $this->postJson("/api/v1/boxes/{$id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertCreated();

        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertOk()->assertJsonPath('data.released', true);
        $this->assertDatabaseHas('boxes', ['id' => $id, 'status' => 'released']);
    }

    public function test_rule_snapshot_survives_later_plant_reconfiguration(): void
    {
        $plant = Plant::create([
            'code' => 'QRO', 'name' => 'Plant',
            'validation_rules' => [
                'required_scans' => ['serial_number'],
                'package_label_prefix' => 'PACK',
            ],
        ]);
        $this->supervisor($plant);
        $oldId = $this->createBox($plant, 'BX-OLD');

        $plant->update(['validation_rules' => [
            'required_scans' => ['part_number', 'package_label'],
            'package_label_prefix' => 'TOTE',
        ]]);

        $newId = $this->createBox($plant, 'BX-NEW');
        $this->assertSame(['serial_number'], Box::findOrFail($oldId)->validation_rules['required_scans']);
        $this->assertSame('TOTE', Box::findOrFail($newId)->validation_rules['package_label_prefix']);

        $this->postJson("/api/v1/boxes/{$oldId}/scans", [
            'scan_type' => 'serial_number', 'value' => 'SN-200',
        ])->assertCreated();
        $this->postJson("/api/v1/boxes/{$oldId}/release")
            ->assertOk()->assertJsonPath('data.released', true);
        $this->postJson("/api/v1/boxes/{$newId}/release")
            ->assertStatus(409)->assertJsonCount(2, 'data.pending_scans');
    }

    public function test_boxes_created_before_migration_keep_original_validation_semantics(): void
    {
        $plant = Plant::create(['code' => 'LEG', 'name' => 'Legacy']);
        $this->supervisor($plant);
        $legacy = Box::create([
            'plant_id' => $plant->id,
            'code' => 'BX-LEGACY', 'part_number' => 'PN-100',
            'serial_number' => 'SN-200', 'status' => 'pending',
        ]);
        $this->assertNull($legacy->validation_rules);

        $this->postJson("/api/v1/boxes/{$legacy->id}/release")
            ->assertStatus(409)->assertJsonCount(3, 'data.pending_scans');
        $this->postJson("/api/v1/boxes/{$legacy->id}/scans", [
            'scan_type' => 'package_label',
            'value' => 'BOX:BX-LEGACY:PN-100:SN-200',
        ])->assertCreated();
    }

    public function test_invalid_plant_rules_are_rejected_without_creating_box(): void
    {
        $plant = Plant::create([
            'code' => 'BAD', 'name' => 'Invalid settings',
            'validation_rules' => [
                'required_scans' => ['part_number', 'part_number'],
                'package_label_prefix' => 'PACK',
            ],
        ]);
        $this->supervisor($plant);

        try {
            app(TraceabilityService::class)->create([
                'plant_id' => $plant->id, 'code' => 'BAD-BOX',
                'part_number' => 'PN-100', 'serial_number' => 'SN-200',
            ], User::factory()->create()->id);
            $this->fail('An invalid profile must fail closed.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid required scan types.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('boxes', ['code' => 'BAD-BOX']);
    }

    public function test_other_plant_cannot_influence_validation_rules(): void
    {
        $qro = Plant::create(['code' => 'QRO', 'name' => 'QRO']);
        Plant::create([
            'code' => 'MTY', 'name' => 'MTY',
            'validation_rules' => [
                'required_scans' => ['part_number'],
                'package_label_prefix' => 'ALT',
            ],
        ]);
        $this->supervisor($qro);
        $id = $this->createBox($qro, 'BX-QRO');

        $this->assertSame(BoxValidationRules::DEFAULT, Box::findOrFail($id)->validation_rules);
        $this->postJson("/api/v1/boxes/{$id}/release")
            ->assertStatus(409)->assertJsonCount(3, 'data.pending_scans');
    }
}
