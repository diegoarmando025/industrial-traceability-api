<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\PlantValidationRuleChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantValidationAdminTest extends TestCase
{
    use RefreshDatabase;

    private function plant(string $code = 'QRO'): Plant
    {
        return Plant::query()->create(['code' => $code, 'name' => 'Planta '.$code]);
    }

    private function user(Plant $plant, string $role): User
    {
        $user = User::factory()->create();
        $user->plants()->attach($plant, ['role' => $role]);

        return $user;
    }

    private function payload(Plant $plant, array $scans = ['part_number', 'package_label'], string $prefix = 'PACK'): array
    {
        return [
            'version' => $plant->fresh()->validation_rules_version,
            'required_scans' => $scans,
            'package_label_prefix' => $prefix,
        ];
    }

    public function test_guests_and_read_only_roles_cannot_view_or_change_rules(): void
    {
        $plant = $this->plant();
        $this->get('/plants/validation')->assertRedirect('/login');
        $this->get("/plants/{$plant->id}/validation")->assertRedirect('/login');
        $this->put("/plants/{$plant->id}/validation", $this->payload($plant))->assertRedirect('/login');

        foreach (['viewer', 'operator'] as $role) {
            $user = $this->user($plant, $role);
            $this->actingAs($user)->get('/plants/validation')->assertForbidden();
            $this->actingAs($user)->get("/plants/{$plant->id}/validation")->assertForbidden();
            $this->actingAs($user)->put("/plants/{$plant->id}/validation", $this->payload($plant))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('plant_validation_rule_changes', 0);
        $this->assertNull($plant->fresh()->validation_rules);
    }

    public function test_supervisor_can_configure_own_plant_and_records_previous_and_new_values(): void
    {
        $plant = $this->plant();
        $supervisor = $this->user($plant, 'supervisor');

        $this->actingAs($supervisor)->get('/plants/validation')->assertOk()
            ->assertSee('QRO');
        $this->actingAs($supervisor)->get("/plants/{$plant->id}/validation")->assertOk()
            ->assertSee('Guardar reglas')
            ->assertSee('Historial de cambios');

        $this->actingAs($supervisor)
            ->put("/plants/{$plant->id}/validation", $this->payload($plant))
            ->assertRedirect(route('plants.validation.edit', $plant));

        $plant->refresh();
        $this->assertSame(1, $plant->validation_rules_version);
        $this->assertSame(['part_number', 'package_label'], $plant->validation_rules['required_scans']);
        $this->assertSame('PACK', $plant->validation_rules['package_label_prefix']);

        $change = PlantValidationRuleChange::query()->sole();
        $this->assertSame($supervisor->id, $change->user_id);
        $this->assertSame(1, $change->version);
        $this->assertSame('BOX', $change->previous_rules['package_label_prefix']);
        $this->assertSame('PACK', $change->new_rules['package_label_prefix']);

        $this->actingAs($supervisor)->get("/plants/{$plant->id}/validation")
            ->assertOk()->assertSee($supervisor->name)->assertSee('Versión 1');
    }

    public function test_cross_plant_access_does_not_reveal_or_modify_settings(): void
    {
        $plant = $this->plant();
        $other = $this->plant('MTY');
        $user = $this->user($plant, 'supervisor');

        $this->actingAs($user)->get('/plants/validation')->assertOk()
            ->assertSee('QRO')->assertDontSee('MTY');
        $this->actingAs($user)->get("/plants/{$other->id}/validation")->assertNotFound();
        $this->actingAs($user)->put("/plants/{$other->id}/validation", $this->payload($other))
            ->assertNotFound();

        $this->assertNull($other->fresh()->validation_rules);
        $this->assertDatabaseCount('plant_validation_rule_changes', 0);
    }

    public function test_form_rejects_empty_unknown_duplicate_scans_and_invalid_prefix(): void
    {
        $plant = $this->plant();
        $user = $this->user($plant, 'supervisor');

        foreach ([
            ['required_scans' => [], 'package_label_prefix' => 'PACK'],
            ['required_scans' => ['part_number', 'other'], 'package_label_prefix' => 'PACK'],
            ['required_scans' => ['part_number', 'part_number'], 'package_label_prefix' => 'PACK'],
            ['required_scans' => ['part_number'], 'package_label_prefix' => 'bad prefix'],
        ] as $invalid) {
            $this->actingAs($user)->from("/plants/{$plant->id}/validation")
                ->put("/plants/{$plant->id}/validation", array_merge(
                    ['version' => 0], $invalid
                ))->assertSessionHasErrors();
        }

        $this->assertNull($plant->fresh()->validation_rules);
        $this->assertDatabaseCount('plant_validation_rule_changes', 0);
    }

    public function test_stale_version_is_rejected_and_identical_changes_do_not_create_history(): void
    {
        $plant = $this->plant();
        $supervisor = $this->user($plant, 'supervisor');
        $initial = $this->payload($plant);

        $this->actingAs($supervisor)->put("/plants/{$plant->id}/validation", $initial)
            ->assertRedirect();

        $this->actingAs($supervisor)->from("/plants/{$plant->id}/validation")
            ->put("/plants/{$plant->id}/validation", $initial)
            ->assertSessionHasErrors('version');

        $this->actingAs($supervisor)->put("/plants/{$plant->id}/validation", $this->payload($plant))
            ->assertRedirect();

        $this->assertSame(1, $plant->fresh()->validation_rules_version);
        $this->assertDatabaseCount('plant_validation_rule_changes', 1);
    }

    public function test_existing_box_snapshots_remain_unchanged_after_configuration_edit(): void
    {
        $plant = $this->plant();
        $user = $this->user($plant, 'supervisor');
        $box = Box::create([
            'plant_id' => $plant->id,
            'code' => 'BX-OLD',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'status' => 'pending',
            'validation_rules' => [
                'required_scans' => ['part_number', 'serial_number', 'package_label'],
                'package_label_prefix' => 'BOX',
            ],
        ]);

        $this->actingAs($user)->put("/plants/{$plant->id}/validation", $this->payload($plant))
            ->assertRedirect();

        $this->assertSame('BOX', $box->fresh()->validation_rules['package_label_prefix']);
        $this->assertSame('PACK', $plant->fresh()->validation_rules['package_label_prefix']);
    }
}
