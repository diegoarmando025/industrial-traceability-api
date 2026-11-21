<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebBoxesTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(string $role = 'supervisor'): array
    {
        $plant = Plant::create(['code' => 'QRO', 'name' => 'Plant Querétaro']);
        $user = User::factory()->create();
        $user->plants()->attach($plant->id, ['role' => $role]);

        return [$user, $plant];
    }

    public function test_guest_cannot_access_boxes(): void
    {
        $this->get('/boxes')->assertRedirect('/login');
        $this->get('/boxes/create')->assertRedirect('/login');
        $this->post('/boxes', [])->assertRedirect('/login');
    }

    public function test_box_list_and_detail_hide_other_plants(): void
    {
        [$user, $plant] = $this->setupUser('viewer');
        $other = Plant::create(['code' => 'MTY', 'name' => 'Monterrey']);
        $visible = Box::create(['plant_id' => $plant->id, 'code' => 'VISIBLE', 'part_number' => 'PN-1', 'serial_number' => 'SN-1']);
        $hidden = Box::create(['plant_id' => $other->id, 'code' => 'SECRET', 'part_number' => 'PN-2', 'serial_number' => 'SN-2']);

        $this->actingAs($user)->get('/boxes')->assertOk()->assertSee('VISIBLE')->assertDontSee('SECRET');
        $this->actingAs($user)->get('/boxes/'.$visible->id)->assertOk()->assertSee('VISIBLE');
        $this->actingAs($user)->get('/boxes/'.$hidden->id)->assertNotFound();
        $this->actingAs($user)->get('/boxes?plant_id='.$other->id)->assertNotFound();
    }

    public function test_viewer_cannot_create_boxes(): void
    {
        [$user, $plant] = $this->setupUser('viewer');
        $data = ['plant_id' => $plant->id, 'code' => 'NOPE', 'part_number' => 'PN-1', 'serial_number' => 'SN-1'];

        $this->actingAs($user)->get('/boxes/create')->assertForbidden();
        $this->actingAs($user)->post('/boxes', $data)->assertForbidden();
        $this->assertDatabaseMissing('boxes', ['code' => 'NOPE']);
    }

    public function test_operator_can_register_box_with_audit_event(): void
    {
        [$user, $plant] = $this->setupUser('operator');
        $this->actingAs($user)->get('/boxes/create')->assertOk();

        $this->actingAs($user)->post('/boxes', [
            'plant_id' => $plant->id,
            'code' => 'QRO-WEB-001',
            'part_number' => 'PN-WEB',
            'serial_number' => 'SN-WEB',
        ])->assertRedirect();

        $box = Box::where('code', 'QRO-WEB-001')->firstOrFail();
        $this->assertSame('pending', $box->status);
        $this->assertDatabaseHas('box_events', ['box_id' => $box->id, 'user_id' => $user->id, 'event_type' => 'created']);
        $this->actingAs($user)->get('/boxes/'.$box->id)->assertOk()->assertSee('QRO-WEB-001');
    }

    public function test_user_cannot_register_box_in_another_plant(): void
    {
        [$user] = $this->setupUser('operator');
        $other = Plant::create(['code' => 'MTY', 'name' => 'Monterrey']);

        $this->actingAs($user)->post('/boxes', [
            'plant_id' => $other->id,
            'code' => 'CROSS-PLANT',
            'part_number' => 'PN',
            'serial_number' => 'SN',
        ])->assertNotFound();
        $this->assertDatabaseMissing('boxes', ['code' => 'CROSS-PLANT']);
    }

    public function test_duplicate_box_code_is_rejected(): void
    {
        [$user, $plant] = $this->setupUser();
        Box::create(['plant_id' => $plant->id, 'code' => 'DUPLICATE', 'part_number' => 'PN-1', 'serial_number' => 'SN-1']);

        $this->actingAs($user)->post('/boxes', [
            'plant_id' => $plant->id, 'code' => 'DUPLICATE', 'part_number' => 'PN-2', 'serial_number' => 'SN-2',
        ])->assertSessionHasErrors('code');
    }

    public function test_box_list_filters_by_status(): void
    {
        [$user, $plant] = $this->setupUser('viewer');
        Box::create(['plant_id' => $plant->id, 'code' => 'PENDING-BOX', 'part_number' => 'PN', 'serial_number' => 'SN', 'status' => 'pending']);
        Box::create(['plant_id' => $plant->id, 'code' => 'RELEASED-BOX', 'part_number' => 'PN', 'serial_number' => 'SN', 'status' => 'released']);

        $this->actingAs($user)->get('/boxes?status=pending')
            ->assertOk()->assertSee('PENDING-BOX')->assertDontSee('RELEASED-BOX');
    }
}
