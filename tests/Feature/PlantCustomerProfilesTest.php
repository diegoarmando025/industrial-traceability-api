<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\PlantCustomerProfile;
use App\Models\User;
use App\Support\BoxValidationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantCustomerProfilesTest extends TestCase
{
    use RefreshDatabase;

    private function plant(string $code): Plant
    {
        return Plant::create(['code' => $code, 'name' => 'Test '.$code]);
    }

    private function user(Plant $plant, string $role): User
    {
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => $role]);

        return $user;
    }

    private function profile(Plant $plant, string $code = 'CLIENT_A'): PlantCustomerProfile
    {
        return $plant->customerProfiles()->create([
            'code' => $code,
            'name' => 'Fictional client '.$code,
            'validation_rules' => [
                'required_scans' => ['part_number', 'package_label'],
                'package_label_prefix' => 'PACK',
            ],
        ]);
    }

    private function boxData(Plant $plant, string $code, ?int $profileId = null): array
    {
        return [
            'plant_id' => $plant->id,
            'code' => $code,
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'customer_profile_id' => $profileId,
        ];
    }

    public function test_only_plant_supervisor_can_manage_client_profiles(): void
    {
        $qro = $this->plant('QRO');
        $other = $this->plant('MTY');
        $payload = [
            'code' => 'NEW',
            'name' => 'Fictional example',
            'required_scans' => ['part_number'],
            'package_label_prefix' => 'PACK',
        ];

        $this->get("/plants/{$qro->id}/customers")->assertRedirect('/login');
        $this->post("/plants/{$qro->id}/customers", $payload)->assertRedirect('/login');

        foreach (['viewer', 'operator'] as $role) {
            $user = $this->user($qro, $role);
            $this->actingAs($user)->get("/plants/{$qro->id}/customers")->assertForbidden();
            $this->actingAs($user)->post("/plants/{$qro->id}/customers", $payload)->assertForbidden();
        }

        $supervisor = $this->user($qro, 'supervisor');
        $this->actingAs($supervisor)->get("/plants/{$other->id}/customers")->assertNotFound();
        $this->actingAs($supervisor)->post("/plants/{$other->id}/customers", $payload)->assertNotFound();

        $this->actingAs($supervisor)->post("/plants/{$qro->id}/customers", $payload)
            ->assertRedirect(route('plants.customers.index', $qro));
        $this->assertDatabaseHas('plant_customer_profiles', ['plant_id' => $qro->id, 'code' => 'NEW']);
    }

    public function test_profile_form_rejects_invalid_rules_and_duplicate_code_within_a_plant(): void
    {
        $plant = $this->plant('QRO');
        $this->profile($plant, 'CLIENT_A');
        $supervisor = $this->user($plant, 'supervisor');

        foreach ([
            ['code' => 'CLIENT_A', 'required_scans' => ['part_number']],
            ['code' => 'LOWERCASE', 'required_scans' => ['part_number'], 'package_label_prefix' => 'invalid value'],
            ['code' => 'VALID', 'required_scans' => ['part_number', 'part_number']],
            ['code' => 'VALID', 'required_scans' => []],
        ] as $invalid) {
            $this->actingAs($supervisor)->post("/plants/{$plant->id}/customers", array_merge([
                'name' => 'Client',
                'package_label_prefix' => 'PACK',
            ], $invalid))->assertSessionHasErrors();
        }

        $this->assertSame(1, PlantCustomerProfile::count());
    }

    public function test_new_box_uses_profile_snapshot_but_existing_boxes_do_not_change(): void
    {
        $plant = $this->plant('QRO');
        $profile = $this->profile($plant);
        $user = $this->user($plant, 'supervisor');
        Sanctum::actingAs($user, ['boxes:create', 'boxes:scan', 'boxes:release']);

        $created = $this->postJson('/api/v1/boxes', $this->boxData($plant, 'BOX-1', $profile->id))
            ->assertCreated();
        $boxId = $created->json('data.id');
        $this->assertSame($profile->id, $created->json('data.customer_profile_id'));
        $this->assertSame('PACK', $created->json('data.validation_rules.package_label_prefix'));

        $profile->update(['validation_rules' => BoxValidationRules::DEFAULT]);

        $this->postJson("/api/v1/boxes/{$boxId}/scans", [
            'scan_type' => 'package_label',
            'value' => 'PACK:BOX-1:PN-100:SN-200',
        ])->assertCreated();
        $this->postJson("/api/v1/boxes/{$boxId}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertCreated();

        $this->postJson("/api/v1/boxes/{$boxId}/release")
            ->assertOk()->assertJsonPath('data.released', true);

        $this->assertSame('PACK', Box::findOrFail($boxId)->validation_rules['package_label_prefix']);
    }

    public function test_cross_plant_and_inactive_profiles_are_rejected_in_api_and_web(): void
    {
        $qro = $this->plant('QRO');
        $mty = $this->plant('MTY');
        $outside = $this->profile($mty);
        $inactive = $this->profile($qro, 'INACTIVE');
        $inactive->update(['active' => false]);

        $user = $this->user($qro, 'supervisor');
        Sanctum::actingAs($user, ['boxes:create']);

        foreach ([[$outside->id, 'CROSS'], [$inactive->id, 'INACTIVE']] as [$profileId, $code]) {
            $this->postJson('/api/v1/boxes', $this->boxData($qro, $code, $profileId))
                ->assertNotFound();
            $this->actingAs($user)->post('/boxes', $this->boxData($qro, 'WEB-'.$code, $profileId))
                ->assertNotFound();
        }

        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_deactivation_is_scoped_and_does_not_alter_existing_boxes(): void
    {
        $plant = $this->plant('QRO');
        $other = $this->plant('MTY');
        $profile = $this->profile($plant);
        $otherProfile = $this->profile($other);
        $supervisor = $this->user($plant, 'supervisor');

        $box = Box::create([
            'plant_id' => $plant->id,
            'customer_profile_id' => $profile->id,
            'code' => 'EXISTING',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'validation_rules' => $profile->validation_rules,
            'status' => 'pending',
        ]);

        $this->actingAs($supervisor)
            ->post("/plants/{$plant->id}/customers/{$otherProfile->id}/deactivate")
            ->assertNotFound();
        $this->actingAs($supervisor)
            ->post("/plants/{$plant->id}/customers/{$profile->id}/deactivate")
            ->assertRedirect();

        $this->assertFalse($profile->fresh()->active);
        $this->assertSame('PACK', $box->fresh()->validation_rules['package_label_prefix']);
        $this->assertTrue($otherProfile->fresh()->active);
    }

    public function test_without_profile_legacy_behavior_is_preserved(): void
    {
        $plant = $this->plant('QRO');
        $this->profile($plant);
        $user = $this->user($plant, 'supervisor');
        Sanctum::actingAs($user, ['boxes:create', 'boxes:release']);

        $boxId = $this->postJson('/api/v1/boxes', $this->boxData($plant, 'DEFAULT-1'))
            ->assertCreated()->json('data.id');
        $this->assertNull(Box::findOrFail($boxId)->customer_profile_id);
        $this->assertSame(BoxValidationRules::DEFAULT, Box::findOrFail($boxId)->validation_rules);
        $this->postJson("/api/v1/boxes/{$boxId}/release")
            ->assertStatus(409)->assertJsonCount(3, 'data.pending_scans');
    }
}
