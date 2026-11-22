<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_requires_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => 'safe-test-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->post('/login', ['email' => $user->email, 'password' => 'safe-test-password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_dashboard_only_counts_boxes_in_assigned_plants(): void
    {
        $visiblePlant = Plant::create(['code' => 'PLANT-A', 'name' => 'Plant A']);
        $otherPlant = Plant::create(['code' => 'PLANT-B', 'name' => 'Plant B']);
        $user = User::factory()->create();
        $user->plants()->attach($visiblePlant->id, ['role' => 'viewer']);

        Box::create(['plant_id' => $visiblePlant->id, 'code' => 'VISIBLE-001', 'part_number' => 'PN-1', 'serial_number' => 'SN-1', 'status' => 'pending']);
        Box::create(['plant_id' => $otherPlant->id, 'code' => 'HIDDEN-001', 'part_number' => 'PN-2', 'serial_number' => 'SN-2', 'status' => 'released']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('VISIBLE-001')
            ->assertDontSee('HIDDEN-001')
            ->assertSee('Cajas registradas')
            ->assertDontSee('Registrar caja');
    }

    public function test_logout_ends_web_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
