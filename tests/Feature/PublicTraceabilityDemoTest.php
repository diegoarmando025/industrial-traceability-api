<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\TraceabilityPublicDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTraceabilityDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_not_available_until_explicitly_enabled(): void
    {
        $this->get('/demo')->assertNotFound();
        $this->get('/demo/boxes/1')->assertNotFound();
    }

    public function test_public_view_exposes_only_fictional_records_and_cannot_write(): void
    {
        $this->seed(TraceabilityPublicDemoSeeder::class);
        config(['traceability_demo.enabled' => true]);

        $plant = Plant::query()->where('code', 'TRACE-PUBLIC')->firstOrFail();
        $released = Box::query()->where('plant_id', $plant->id)->where('status', 'released')->firstOrFail();

        $internalPlant = Plant::create(['code' => 'INTERNAL-QRO', 'name' => 'Confidential plant']);
        $privateBox = Box::create([
            'plant_id' => $internalPlant->id,
            'code' => 'PRIVATE-BOX-004',
            'part_number' => 'SECRET-PN',
            'serial_number' => 'SECRET-SN',
            'status' => 'pending',
        ]);

        $this->get('/demo')
            ->assertOk()
            ->assertSee('TRACE-PUBLIC-001')
            ->assertDontSee('PRIVATE-BOX-004')
            ->assertDontSee('SECRET-PN')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/demo?status=released')
            ->assertOk()
            ->assertSee('TRACE-PUBLIC-001')
            ->assertDontSee('TRACE-PUBLIC-002');

        $this->get('/demo/boxes/'.$released->id)
            ->assertOk()
            ->assertSee('Historial de escaneos')
            ->assertSee('Liberación autorizada');

        $this->get('/demo/boxes/'.$privateBox->id)->assertNotFound();

        $this->post('/boxes', [])->assertRedirect('/login');
        $this->post('/boxes/'.$released->id.'/release', [])->assertRedirect('/login');
        $this->post('/boxes/'.$released->id.'/scans', [])->assertRedirect('/login');
    }

    public function test_demo_data_is_repeatable_without_duplicate_scans_or_events(): void
    {
        $this->seed(TraceabilityPublicDemoSeeder::class);
        $this->seed(TraceabilityPublicDemoSeeder::class);

        $plant = Plant::query()->where('code', 'TRACE-PUBLIC')->firstOrFail();
        $this->assertSame(8, Box::query()->where('plant_id', $plant->id)->count());

        $released = Box::query()->where('code', 'TRACE-PUBLIC-001')->firstOrFail();
        $this->assertSame('released', $released->status);
        $this->assertSame(3, $released->scans()->count());
        $this->assertSame(2, $released->events()->count());

        $blocked = Box::query()->where('code', 'TRACE-PUBLIC-002')->firstOrFail();
        $this->assertSame('pending', $blocked->status);
        $this->assertSame(1, $blocked->scans()->count());
        $this->assertSame(2, $blocked->events()->count());


        $corrected = Box::query()->where('code', 'TRACE-PUBLIC-004')->firstOrFail();
        $this->assertSame('pending', $corrected->status);
        $this->assertSame(3, $corrected->scans()->count());
        $this->assertSame(1, $corrected->scans()->where('accepted', false)->count());

        $ready = Box::query()->where('code', 'TRACE-PUBLIC-005')->firstOrFail();
        $this->assertSame('pending', $ready->status);
        $this->assertSame(3, $ready->scans()->where('accepted', true)->count());

        $blocked = Box::query()->where('code', 'TRACE-PUBLIC-006')->firstOrFail();
        $this->assertSame('pending', $blocked->status);
        $this->assertSame(1, $blocked->events()->where('event_type', 'release_blocked')->count());

        $recovered = Box::query()->where('code', 'TRACE-PUBLIC-007')->firstOrFail();
        $this->assertSame('released', $recovered->status);
        $this->assertSame(1, $recovered->scans()->where('accepted', false)->count());
        $this->assertSame(1, $recovered->events()->where('event_type', 'released')->count());

        $new = Box::query()->where('code', 'TRACE-PUBLIC-008')->firstOrFail();
        $this->assertSame('pending', $new->status);
        $this->assertSame(0, $new->scans()->count());

        $this->assertSame(0, User::query()->where('email', 'public-operator@traceability.example.invalid')
            ->firstOrFail()->plants()->count());
    }
}
