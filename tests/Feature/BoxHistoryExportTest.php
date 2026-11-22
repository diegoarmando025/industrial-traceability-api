<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxHistoryExportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Planta ficticia']);
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => 'supervisor']);

        $box = Box::create([
            'plant_id' => $plant->id, 'code' => 'BX-EXAMPLE',
            'part_number' => 'PN-100', 'serial_number' => 'SN-200',
            'status' => 'pending',
        ]);

        $box->scans()->create([
            'user_id' => $user->id, 'scan_type' => 'part_number',
            'scanned_value' => 'PN-100', 'accepted' => true,
            'created_at' => '2026-11-19 11:00:00',
        ]);
        $box->scans()->create([
            'user_id' => $user->id, 'scan_type' => 'serial_number',
            'scanned_value' => '=HYPERLINK("http://example.invalid")',
            'accepted' => false, 'reason' => 'value_mismatch',
            'created_at' => '2026-11-20 13:00:00',
        ]);
        $box->events()->create([
            'user_id' => $user->id, 'event_type' => 'release_blocked',
            'details' => ['pending_scans' => ['serial_number']],
            'created_at' => '2026-11-20 13:00:01',
        ]);

        return [$plant, $user, $box];
    }

    public function test_guest_and_other_plant_members_cannot_read_or_export_history(): void
    {
        [$plant, $user, $box] = $this->fixture();

        foreach (['/boxes/'.$box->id.'/history', '/boxes/'.$box->id.'/history/export'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $other = Plant::create(['code' => 'MTY', 'name' => 'Otra planta']);
        $outsider = User::factory()->create();
        $outsider->plants()->attach($other->id, ['role' => 'supervisor']);

        $this->actingAs($outsider)->get('/boxes/'.$box->id.'/history')->assertNotFound();
        $this->actingAs($outsider)->get('/boxes/'.$box->id.'/history/export')->assertNotFound();
        $this->actingAs($user)->get('/boxes/'.$box->id.'/history')->assertOk();
    }

    public function test_scans_are_paginated_and_filter_by_date_result_and_type(): void
    {
        [, $user, $box] = $this->fixture();
        $this->actingAs($user)
            ->get('/boxes/'.$box->id.'/history?kind=scans&from=2026-11-20&to=2026-11-20&result=rejected&scan_type=serial_number')
            ->assertOk()
            ->assertSee('=HYPERLINK', false)
            ->assertDontSee('PN-100</td>', false)
            ->assertSee('1 registros');

        $this->actingAs($user)
            ->get('/boxes/'.$box->id.'/history?kind=events')
            ->assertOk()
            ->assertSee('release_blocked')
            ->assertSee('1 registros');

        $this->actingAs($user)
            ->get('/boxes/'.$box->id.'/history?from=2026-11-21')
            ->assertOk()
            ->assertSee('No hay escaneos que coincidan.');
    }

    public function test_csv_export_uses_filtered_rows_and_neutralizes_excel_formulas(): void
    {
        [, $user, $box] = $this->fixture();
        $response = $this->actingAs($user)
            ->get('/boxes/'.$box->id.'/history/export?kind=scans&result=rejected');
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('PN-100,', $csv);
        $this->assertStringContainsString('Rechazada', $csv);

        $events = $this->actingAs($user)
            ->get('/boxes/'.$box->id.'/history/export?kind=events');
        ob_start();
        $events->sendContent();
        $eventCsv = ob_get_clean();
        $this->assertStringContainsString('release_blocked', $eventCsv);
        $this->assertStringContainsString('serial_number', $eventCsv);
    }

    public function test_invalid_filters_are_rejected_before_any_download(): void
    {
        [, $user, $box] = $this->fixture();
        $this->actingAs($user)
            ->get('/boxes/'.$box->id.'/history/export?kind=invalid')
            ->assertSessionHasErrors('kind');
        $this->actingAs($user)
            ->get('/boxes/'.$box->id.'/history?from=2026-11-21&to=2026-11-19')
            ->assertSessionHasErrors('to');
    }
}
