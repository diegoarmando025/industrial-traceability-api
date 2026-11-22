<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    private function owner(Request $request): void
    {
        abort_unless($request->user()?->is_portfolio_owner === true, 403);
    }

    public function index(Request $request): View
    {
        $this->owner($request);
        $plants = Plant::query()->orderBy('name')->get();
        $memberships = DB::table('plant_user')
            ->join('users', 'users.id', '=', 'plant_user.user_id')
            ->join('plants', 'plants.id', '=', 'plant_user.plant_id')
            ->select('users.id as user_id', 'users.name', 'users.email', 'users.is_portfolio_owner',
                'users.is_portfolio_demo_admin', 'plants.id as plant_id', 'plants.name as plant_name',
                'plant_user.role')
            ->orderBy('users.name')->orderBy('plants.name')->get();
        $users = User::query()->where('is_portfolio_owner', false)->where('is_portfolio_demo_admin', false)->orderBy('name')->get(['id', 'name', 'email']);
        return view('team.index', compact('plants', 'memberships', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->owner($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'plant_id' => ['required', 'integer', 'exists:plants,id'],
            'role' => ['required', Rule::in(['viewer', 'operator', 'supervisor'])],
            'password' => ['required', 'string', 'min:16', 'max:200'],
        ]);
        $email = strtolower(trim($data['email']));
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            abort_if($existing->is_portfolio_demo_admin, 403, 'La cuenta de evaluación se configura únicamente desde el servidor.');
            abort_if($existing->is_portfolio_owner, 403, 'La cuenta propietaria no se puede modificar.');
            return back()->withErrors(['email' => 'El correo ya existe. Usa la sección de asignaciones para gestionar accesos.'])->withInput();
        }
        DB::transaction(function () use ($data, $email): void {
            $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => $data['password']]);
            DB::table('plant_user')->insert([
                'plant_id' => $data['plant_id'], 'user_id' => $user->id, 'role' => $data['role'],
            ]);
        });
        return back()->with('status', 'Usuario creado. Entrégale su contraseña inicial por un canal privado.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->owner($request);
        abort_if($user->is_portfolio_owner || $user->is_portfolio_demo_admin, 403);
        $data = $request->validate([
            'plant_id' => ['required', 'integer', 'exists:plants,id'],
            'role' => ['required', Rule::in(['viewer', 'operator', 'supervisor'])],
        ]);
        $updated = DB::table('plant_user')->where('plant_id', $data['plant_id'])
            ->where('user_id', $user->id)->update(['role' => $data['role']]);
        abort_if($updated === 0, 404);
        return back()->with('status', 'Rol actualizado.');
    }

    public function revoke(Request $request, User $user): RedirectResponse
    {
        $this->owner($request);
        abort_if($user->is_portfolio_owner || $user->is_portfolio_demo_admin, 403);
        $data = $request->validate(['plant_id' => ['required', 'integer', 'exists:plants,id']]);
        DB::table('plant_user')->where('user_id', $user->id)->where('plant_id', $data['plant_id'])->delete();
        // Existing bearer tokens could still be valid for other plants; token
        // abilities are independently checked on each API request.
        return back()->with('status', 'Acceso a la planta retirado.');
    }
}
