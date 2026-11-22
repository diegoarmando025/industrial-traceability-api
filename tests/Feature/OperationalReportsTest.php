<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): array
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Planta visible']);
        $other = Plant::create(['code' => 'MTY', 'name' => 'Planta restringida']);
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => 'viewer']);

        $box = Box::create([
            'plant_id' => $plant->id, 'code' => 'BX-100', 'part_number' => 'PN-100',
            'serial_number' => 'SN-100', 'status' => 'released',
            'released_at' => '2026-11-20 12:00:00',
        ]);
        $box->forceFill(['created_at' => '2026-11-19 10:00:00'])->save();
        $box->scans()->create([
            'user_id' => $user->id, 'scan_type' => 'part_number',
            'scanned_value' => 'PN-100', 'accepted' => true,
            'created_at' => '2026-11-20 11:00:00',
        ]);
        $box->scans()->create([
            'user_id' => $user->id, 'scan_type' => 'serial_number',
            'scanned_value' => 'SN-WRONG', 'accepted' => false,
            'created_at' => '2026-11-20 11:01:00',
        ]);
        $box->events()->create([
            'user_id' => $user->id, 'event_type' => 'release_blocked',
            'details' => ['pending_scans' => ['serial_number']],
            'created_at' => '2026-11-20 11:02:00',
        ]);

        $privateBox = Box::create([
            'plant_id' => $other->id, 'code' => 'TOP-SECRET', 'part_number' => 'PN-PRIVATE',
            'serial_number' => 'SN-PRIVATE', 'status' => 'released',
            'released_at' => '2026-11-20 14:00:00',
        ]);
        $privateBox->forceFill(['created_at' => '2026-11-20 09:00:00'])->save();
        $privateBox->scans()->create([
            'user_id' => $user->id, 'scan_type' => 'part_number',
            'scanned_value' => 'PN-PRIVATE', 'accepted' => false,
            'created_at' => '2026-11-20 09:01:00',
        ]);

        return [$user, $plant, $other];
    }

    public function test_report_requires_login_and_does_not_leak_other_plant_counts(): void
    {
        [$user, $plant, $other] = $this->seedData();

        $this->get('/reports/operations')->assertRedirect('/login');
        $this->get('/reports/operations/export')->assertRedirect('/login');

        $this->actingAs($user)->get('/reports/operations?from=2026-11-19&to=2026-11-20')
            ->assertOk()
            ->assertSee('Planta visible')
            ->assertDontSee('Planta restringida')
            ->assertSee('50.0%');

        $this->actingAs($user)->get('/reports/operations?plant_id='.$other->id)
            ->assertNotFound();
        $this->actingAs($user)->get('/reports/operations/export?plant_id='.$other->id)
            ->assertNotFound();
    }

    public function test_csv_has_same_scoped_daily_aggregates_and_excludes_private_data(): void
    {
        [$user, $plant] = $this->seedData();
        $response = $this->actingAs($user)->get(
            '/reports/operations/export?from=2026-11-19&to=2026-11-20&plant_id='.$plant->id
        );
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('2026-11-19,1,0,0,0', $csv);
        $this->assertStringContainsString('2026-11-20,0,1,1,1', $csv);
        $this->assertStringNotContainsString('TOP-SECRET', $csv);
    }

    public function test_report_rejects_invalid_dates_and_overlarge_windows(): void
    {
        [$user] = $this->seedData();

        $this->actingAs($user)
            ->get('/reports/operations?from=2026-11-22&to=2026-11-19')
            ->assertSessionHasErrors('to');
        $this->actingAs($user)
            ->get('/reports/operations?from=2025-01-01&to=2026-11-20')
            ->assertSessionHasErrors('from');
        $this->actingAs($user)
            ->get('/reports/operations/export?from=2025-01-01&to=2026-11-20')
            ->assertSessionHasErrors('from');
    }

    public function test_empty_visible_plants_have_zero_counts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/reports/operations')
            ->assertOk()->assertSee('Sin plantas asignadas');
    }
}
