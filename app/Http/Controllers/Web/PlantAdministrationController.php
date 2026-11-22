<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlantAdministrationController extends Controller
{
    private function owner(Request $request): void
    {
        abort_unless($request->user()?->is_portfolio_owner === true, 403);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->owner($request);
        $data = $request->validate([
            'code' => ['required','string','regex:/^[A-Z0-9_-]{2,30}$/D', Rule::unique('plants','code')],
            'name' => ['required','string','max:120'],
        ]);
        DB::transaction(function () use ($data, $request): void {
            $plant = Plant::create($data);
            DB::table('plant_user')->insert([
                'plant_id' => $plant->id,
                'user_id' => $request->user()->id,
                'role' => 'supervisor',
            ]);
        });
        return redirect()->route('team.index')->with('status', 'Planta creada y asignada al propietario.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $this->owner($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'plant_id' => ['required', 'integer', 'exists:plants,id'],
            'role' => ['required', Rule::in(['viewer', 'operator', 'supervisor'])],
        ]);
        $user = User::findOrFail($data['user_id']);
        abort_if($user->is_portfolio_demo_admin || $user->is_portfolio_owner, 403,
            'La cuenta propietaria y la cuenta de evaluación tienen accesos protegidos.');
        DB::table('plant_user')->updateOrInsert(
            ['user_id' => $user->id, 'plant_id' => $data['plant_id']],
            ['role' => $data['role']]
        );
        return redirect()->route('team.index')->with('status', 'Asignación de planta actualizada.');
    }
}
