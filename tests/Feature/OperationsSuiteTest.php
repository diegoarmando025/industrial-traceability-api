<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function setupUser(string $role, bool $owner = false): array
    {
        $plant = Plant::create(['code' => 'QRO'.strtoupper(substr($role,0,3)).($owner?'1':'0'), 'name' => 'Planta']);
        $user = User::factory()->create();
        if ($owner) {
            $user->is_portfolio_owner = true;
            $user->save();
        }
        DB::table('plant_user')->insert(['plant_id' => $plant->id, 'user_id' => $user->id, 'role' => $role]);
        return [$plant, $user];
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('boxes.csv', $content);
    }

    public function test_operator_can_import_multiple_boxes_atomically(): void
    {
        [$plant,$user] = $this->setupUser('operator');
        $this->actingAs($user)->post('/boxes/import', [
            'plant_id' => $plant->id,
            'file' => $this->csv("code,part_number,serial_number\nLOT-001,PN-1,SN-1\nLOT-002,PN-2,SN-2\n"),
        ])->assertRedirect('/boxes?plant_id='.$plant->id);
        $this->assertSame(2, Box::where('plant_id', $plant->id)->count());
        $this->assertDatabaseHas('boxes',['code'=>'LOT-001','status'=>'pending']);
    }

    public function test_duplicate_in_upload_rejects_all_rows(): void
    {
        [$plant,$user] = $this->setupUser('operator');
        $this->actingAs($user)->post('/boxes/import', [
            'plant_id'=>$plant->id,
            'file'=>$this->csv("code,part_number,serial_number\nLOT-001,PN-1,SN-1\nLOT-001,PN-2,SN-2\n"),
        ])->assertSessionHasErrors('file');
        $this->assertSame(0,Box::count());
    }

    public function test_viewer_and_foreign_plant_cannot_import(): void
    {
        [$plant,$viewer] = $this->setupUser('viewer');
        $this->actingAs($viewer)->post('/boxes/import', [
            'plant_id'=>$plant->id,
            'file'=>$this->csv("code,part_number,serial_number\nLOT-003,PN-1,SN-1\n"),
        ])->assertForbidden();
        [$other,$operator] = $this->setupUser('operator');
        $this->actingAs($operator)->post('/boxes/import', [
            'plant_id'=>$plant->id,
            'file'=>$this->csv("code,part_number,serial_number\nLOT-004,PN-1,SN-1\n"),
        ])->assertNotFound();
        $this->assertSame(0,Box::count());
    }

    public function test_only_owner_manages_team_and_demo_account_is_untouchable(): void
    {
        [$plant,$owner] = $this->setupUser('supervisor',true);
        $other = User::factory()->create();
        $other->is_portfolio_demo_admin = true; $other->save();
        DB::table('plant_user')->insert(['plant_id'=>$plant->id,'user_id'=>$other->id,'role'=>'supervisor']);
        $supervisor = User::factory()->create();
        DB::table('plant_user')->insert(['plant_id'=>$plant->id,'user_id'=>$supervisor->id,'role'=>'supervisor']);

        $this->actingAs($supervisor)->get('/team')->assertForbidden();
        $this->actingAs($supervisor)->post('/team',[
            'name'=>'Nuevo','email'=>'nuevo@example.test','password'=>str_repeat('a',16),
            'plant_id'=>$plant->id,'role'=>'operator',
        ])->assertForbidden();

        $this->actingAs($owner)->get('/team')->assertOk();
        $this->actingAs($owner)->put('/team/'.$other->id,['plant_id'=>$plant->id,'role'=>'viewer'])->assertForbidden();
        $this->actingAs($owner)->delete('/team/'.$owner->id,['plant_id'=>$plant->id])->assertForbidden();
    }

    public function test_owner_can_create_member_and_change_revoke_role(): void
    {
        [$plant,$owner] = $this->setupUser('supervisor',true);
        $this->actingAs($owner)->post('/team',[
            'name'=>'Operador Uno', 'email'=>'operador@example.test','password'=>str_repeat('Z',18),
            'plant_id'=>$plant->id,'role'=>'operator',
        ])->assertRedirect();
        $user = User::where('email','operador@example.test')->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(str_repeat('Z',18),$user->password));
        $this->actingAs($owner)->put('/team/'.$user->id,['plant_id'=>$plant->id,'role'=>'viewer'])->assertRedirect();
        $this->assertDatabaseHas('plant_user',['user_id'=>$user->id,'plant_id'=>$plant->id,'role'=>'viewer']);
        $this->actingAs($owner)->delete('/team/'.$user->id,['plant_id'=>$plant->id])->assertRedirect();
        $this->assertDatabaseMissing('plant_user',['user_id'=>$user->id,'plant_id'=>$plant->id]);
    }
}
