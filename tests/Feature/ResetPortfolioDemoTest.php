<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\TraceabilityPublicDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetPortfolioDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_preserves_boxes(): void
    {
        (new TraceabilityPublicDemoSeeder())->run();
        $original = Box::count();
        $this->artisan('portfolio:reset-demo --dry-run')->assertExitCode(0);
        $this->assertSame($original, Box::count());
    }

    public function test_reset_restores_demo_boxes_without_deleting_other_plants_or_users(): void
    {
        (new TraceabilityPublicDemoSeeder())->run();
        $demo = Plant::where('code', 'TRACE-PUBLIC')->firstOrFail();
        $other = Plant::create(['code' => 'PRIVATE-PLANT', 'name' => 'Otra planta']);
        $user = User::factory()->create();
        $real = Box::create(['plant_id' => $other->id, 'code' => 'PRIVATE-BOX-X01', 'part_number' => 'PN-R', 'serial_number' => 'SN-R', 'status' => 'pending']);
        $visitor = Box::create(['plant_id' => $demo->id, 'code' => 'DEMO-VISITOR-X01', 'part_number' => 'PN-V', 'serial_number' => 'SN-V', 'status' => 'pending']);
        $users = User::count();

        $this->artisan('portfolio:reset-demo')
            ->expectsConfirmation('¿Confirmas que verificaste un respaldo recuperable de la base de datos?', 'yes')
            ->expectsQuestion('Escribe RESTABLECER TRACE-PUBLIC para continuar', 'RESTABLECER TRACE-PUBLIC')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('boxes', ['code' => $visitor->code]);
        $this->assertDatabaseHas('boxes', ['code' => $real->code, 'plant_id' => $other->id]);
        $this->assertSame(3, Box::where('plant_id', $demo->id)->count());
        $this->assertSame($users, User::count());
    }

    public function test_refuses_to_reset_when_demo_has_custom_validation_rules(): void
    {
        (new TraceabilityPublicDemoSeeder())->run();
        $demo = Plant::where('code', 'TRACE-PUBLIC')->firstOrFail();
        $demo->validation_rules = ['required_scans' => ['part_number']];
        $demo->save();
        $this->artisan('portfolio:reset-demo --dry-run')->assertExitCode(1);
        $this->assertSame(3, Box::where('plant_id', $demo->id)->count());
    }
}
