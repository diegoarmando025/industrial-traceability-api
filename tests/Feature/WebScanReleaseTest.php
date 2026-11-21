<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebScanReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function setupBox(string $role = 'supervisor'): array
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Querétaro']);
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => $role]);
        $box = Box::create([
            'plant_id' => $plant->id,
            'code' => 'BX-1234',
            'part_number' => 'PN-100',
            'serial_number' => 'SN-200',
            'status' => 'pending',
        ]);

        return [$user, $box];
    }

    public function test_operator_can_scan_but_not_release(): void
    {
        [$user, $box] = $this->setupBox('operator');

        $this->actingAs($user)->get("/boxes/{$box->id}")
            ->assertOk()->assertSee('Registrar lectura')
            ->assertDontSee('Liberar caja');

        $this->actingAs($user)->post("/boxes/{$box->id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertRedirect("/boxes/{$box->id}");

        $this->assertDatabaseHas('box_scans', ['box_id' => $box->id, 'accepted' => 1]);
        $this->actingAs($user)->post("/boxes/{$box->id}/release")->assertForbidden();
    }

    public function test_invalid_scan_is_recorded_and_prevents_release(): void
    {
        [$user, $box] = $this->setupBox();

        $this->actingAs($user)->post("/boxes/{$box->id}/scans", [
            'scan_type' => 'part_number', 'value' => 'WRONG',
        ])->assertRedirect("/boxes/{$box->id}")->assertSessionHas('warning');

        $this->assertDatabaseHas('box_scans', ['box_id' => $box->id, 'accepted' => 0]);
        $this->actingAs($user)->post("/boxes/{$box->id}/release")
            ->assertRedirect("/boxes/{$box->id}")->assertSessionHas('warning');

        $this->assertDatabaseHas('box_events', ['box_id' => $box->id, 'event_type' => 'release_blocked']);
        $this->assertSame('pending', $box->fresh()->status);
    }

    public function test_supervisor_can_release_only_after_all_valid_scans(): void
    {
        [$user, $box] = $this->setupBox();

        foreach ([
            ['part_number', 'PN-100'],
            ['serial_number', 'SN-200'],
            ['package_label', 'BOX:BX-1234:PN-100:SN-200'],
        ] as [$type, $value]) {
            $this->actingAs($user)->post("/boxes/{$box->id}/scans", [
                'scan_type' => $type, 'value' => $value,
            ])->assertRedirect("/boxes/{$box->id}")->assertSessionHas('status');
        }

        $this->actingAs($user)->get("/boxes/{$box->id}")
            ->assertOk()->assertSee('Liberar caja');

        $this->actingAs($user)->post("/boxes/{$box->id}/release")
            ->assertRedirect("/boxes/{$box->id}")->assertSessionHas('status');

        $this->assertSame('released', $box->fresh()->status);
        $this->assertDatabaseHas('box_events', ['box_id' => $box->id, 'event_type' => 'released']);
        $this->actingAs($user)->post("/boxes/{$box->id}/release")->assertStatus(409);
        $this->actingAs($user)->post("/boxes/{$box->id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertStatus(409);
    }

    public function test_box_history_shows_actor_for_scans_and_release(): void
    {
        [$user, $box] = $this->setupBox();

        foreach ([
            ['part_number', 'PN-100'],
            ['serial_number', 'SN-200'],
            ['package_label', 'BOX:BX-1234:PN-100:SN-200'],
        ] as [$type, $value]) {
            $this->actingAs($user)->post("/boxes/{$box->id}/scans", [
                'scan_type' => $type,
                'value' => $value,
            ])->assertRedirect();
        }

        $this->actingAs($user)->post("/boxes/{$box->id}/release")->assertRedirect();

        $this->actingAs($user)->get("/boxes/{$box->id}")
            ->assertOk()
            ->assertSee('Historial de operaciones')
            ->assertSee($user->name)
            ->assertSee('Caja liberada')
            ->assertSee('BOX:BX-1234:PN-100:SN-200');
    }

    public function test_viewer_cannot_submit_scans_or_release(): void
    {
        [$user, $box] = $this->setupBox('viewer');

        $this->actingAs($user)->get("/boxes/{$box->id}")
            ->assertOk()->assertDontSee('Registrar lectura')->assertDontSee('Liberar caja');

        $this->actingAs($user)->post("/boxes/{$box->id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertForbidden();
        $this->actingAs($user)->post("/boxes/{$box->id}/release")->assertForbidden();
    }

    public function test_cross_plant_cannot_scan_or_release(): void
    {
        [$user, $box] = $this->setupBox();
        $other = Plant::create(['code' => 'MTY', 'name' => 'Monterrey']);
        $box->update(['plant_id' => $other->id]);

        $this->actingAs($user)->post("/boxes/{$box->id}/scans", [
            'scan_type' => 'part_number', 'value' => 'PN-100',
        ])->assertNotFound();
        $this->actingAs($user)->post("/boxes/{$box->id}/release")->assertNotFound();
    }

    public function test_later_invalid_scan_blocks_release_even_after_prior_valid_scan(): void
    {
        [$user, $box] = $this->setupBox();

        foreach ([
            ['part_number', 'PN-100'],
            ['serial_number', 'SN-200'],
            ['package_label', 'BOX:BX-1234:PN-100:SN-200'],
            ['part_number', 'WRONG'],
        ] as [$type, $value]) {
            $this->actingAs($user)->post("/boxes/{$box->id}/scans", [
                'scan_type' => $type, 'value' => $value,
            ])->assertRedirect("/boxes/{$box->id}");
        }

        $this->actingAs($user)->get("/boxes/{$box->id}")
            ->assertOk()->assertSee('Pendiente');

        $this->actingAs($user)->post("/boxes/{$box->id}/release")
            ->assertRedirect("/boxes/{$box->id}")->assertSessionHas('warning');

        $this->assertSame('pending', $box->fresh()->status);
    }
}
