<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TraceabilityDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_fictional_reproducible_data_without_duplicates(): void
    {
        Artisan::call('db:seed', ['--class' => 'TraceabilityDemoSeeder']);
        Artisan::call('db:seed', ['--class' => 'TraceabilityDemoSeeder']);

        $plant = Plant::query()->where('code', 'DEMO-QRO')->firstOrFail();
        $operator = User::query()->where('email', 'operator@traceability.example.invalid')->firstOrFail();
        $supervisor = User::query()->where('email', 'supervisor@traceability.example.invalid')->firstOrFail();

        $this->assertDatabaseHas('plant_user', [
            'plant_id' => $plant->id, 'user_id' => $operator->id, 'role' => 'operator',
        ]);
        $this->assertDatabaseHas('plant_user', [
            'plant_id' => $plant->id, 'user_id' => $supervisor->id, 'role' => 'supervisor',
        ]);

        $this->assertSame(2, Box::query()->where('plant_id', $plant->id)->count());

        $complete = Box::query()->where('code', 'DEMO-QRO-001')->firstOrFail();
        $this->assertSame('released', $complete->status);
        $this->assertSame(3, $complete->scans()->count());
        $this->assertSame(2, $complete->events()->count());

        $pending = Box::query()->where('code', 'DEMO-QRO-002')->firstOrFail();
        $this->assertSame('pending', $pending->status);
        $this->assertSame(1, $pending->scans()->count());
        $this->assertFalse((bool) $pending->scans()->firstOrFail()->accepted);
        $this->assertSame(1, $pending->events()->count());
    }
}
