<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\TraceabilityPublicDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PortfolioScenarioMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_scenarios_without_modifying_existing_scans_or_releases(): void
    {
        $this->seed(TraceabilityPublicDemoSeeder::class);
        $plant = Plant::query()->where('code', 'TRACE-PUBLIC')->firstOrFail();
        $owner = User::factory()->create();
        $owner->forceFill(['is_portfolio_owner' => true])->save();

        $original = Box::query()->where('code', 'TRACE-PUBLIC-001')->firstOrFail();
        $originalScans = $original->scans()->count();
        $originalEvents = $original->events()->count();

        foreach (range(4, 8) as $number) {
            $example = Box::query()->where('code', 'TRACE-PUBLIC-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT))->firstOrFail();
            $example->scans()->delete();
            $example->events()->delete();
            $example->delete();
        }
        $this->assertSame(3, Box::query()->where('plant_id', $plant->id)->count());

        $this->actingAs($owner)->get('/team')->assertOk()->assertSee('Completar escenario');
        $this->actingAs($owner)->post('/team/demo-scenario/complete', [
            'confirmation' => 'COMPLETAR DEMO',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(8, Box::query()->where('plant_id', $plant->id)->count());
        $this->assertSame('released', $original->fresh()->status);
        $this->assertSame($originalScans, $original->scans()->count());
        $this->assertSame($originalEvents, $original->events()->count());

        $this->actingAs($owner)->post('/team/demo-scenario/complete', [
            'confirmation' => 'COMPLETAR DEMO',
        ])->assertRedirect();
        $this->assertSame(8, Box::query()->where('plant_id', $plant->id)->count());
    }

    public function test_public_demo_and_other_users_cannot_manage_shared_scenarios(): void
    {
        $this->seed(TraceabilityPublicDemoSeeder::class);
        $reviewer = User::factory()->create();
        $reviewer->forceFill(['is_portfolio_demo_admin' => true])->save();

        $this->actingAs($reviewer)->post('/team/demo-scenario/complete', [
            'confirmation' => 'COMPLETAR DEMO',
        ])->assertForbidden();

        $user = User::factory()->create();
        $this->actingAs($user)->post('/team/demo-scenario/complete', [
            'confirmation' => 'COMPLETAR DEMO',
        ])->assertForbidden();

        $owner = User::factory()->create();
        $owner->forceFill(['is_portfolio_owner' => true])->save();
        $this->actingAs($owner)->post('/team/demo-scenario/complete', [
            'confirmation' => 'WRONG',
        ])->assertSessionHasErrors('confirmation');
        $this->assertSame(8, Box::query()->where('code', 'like', 'TRACE-PUBLIC-%')->count());
    }
}
