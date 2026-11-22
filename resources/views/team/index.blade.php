@extends('layouts.industrial')
@section('title','Equipo y permisos')
@section('page-title','Equipo y permisos')
@section('content')
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-3 mb-4">
<div class="col-lg-5"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Nuevo integrante</h2></div><div class="card-body">
<form method="POST" action="{{ route('team.store') }}">@csrf
<div class="mb-3"><label for="name" class="form-label">Nombre</label><input class="form-control" name="name" id="name" maxlength="120" value="{{ old('name') }}" required></div>
<div class="mb-3"><label for="email" class="form-label">Correo</label><input class="form-control" type="email" name="email" id="email" value="{{ old('email') }}" required></div>
<div class="mb-3"><label for="password" class="form-label">Contraseña inicial</label><input class="form-control" type="password" name="password" id="password" minlength="16" maxlength="200" autocomplete="new-password" required><div class="form-text">Mínimo 16 caracteres. Compártela directamente con el usuario por un canal privado.</div></div>
<div class="mb-3"><label for="plant_id" class="form-label">Planta</label><select class="form-select" name="plant_id" id="plant_id" required>@foreach($plants as $plant)<option value="{{ $plant->id }}">{{ $plant->name }}</option>@endforeach</select></div>
<div class="mb-3"><label for="role" class="form-label">Permiso</label><select class="form-select" name="role" id="role"><option value="viewer">Consulta</option><option value="operator">Operación</option><option value="supervisor">Supervisión</option></select></div>
<button class="btn btn-primary">Crear integrante</button></form>
</div></div></div>
<div class="col-lg-7"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Roles disponibles</h2></div><div class="card-body">
<div class="row gy-3"><div class="col-sm-4"><strong>Consulta</strong><p class="small text-secondary mb-0">Consulta historial, cajas e indicadores.</p></div><div class="col-sm-4"><strong>Operación</strong><p class="small text-secondary mb-0">Registra cajas y realiza lecturas.</p></div><div class="col-sm-4"><strong>Supervisión</strong><p class="small text-secondary mb-0">Libera cajas y configura validaciones.</p></div></div>
<hr><p class="small text-secondary mb-0">Solo el propietario puede gestionar cuentas desde este panel. La cuenta demo y la cuenta propietaria no pueden modificarse aquí.</p>
</div></div></div></div>

<div class="row g-3 mb-4">
<div class="col-lg-5"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Registrar planta</h2></div><div class="card-body"><form method="POST" action="{{ route('team.plants.store') }}">@csrf
<div class="mb-3"><label class="form-label" for="plant_code">Código</label><input id="plant_code" name="code" maxlength="30" class="form-control" pattern="[A-Z0-9_-]{2,30}" placeholder="QRO-02" required></div>
<div class="mb-3"><label class="form-label" for="plant_name">Nombre</label><input id="plant_name" name="name" maxlength="120" class="form-control" placeholder="Planta Querétaro II" required></div>
<button class="btn btn-outline-primary">Crear planta</button></form></div></div></div>
<div class="col-lg-7"><div class="card h-100"><div class="card-header"><h2 class="h5 mb-0">Asignar usuario existente</h2></div><div class="card-body"><form method="POST" action="{{ route('team.assign') }}">@csrf
<div class="mb-3"><label class="form-label" for="assign_user">Usuario</label><select class="form-select" id="assign_user" name="user_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach</select></div>
<div class="row g-2 mb-3"><div class="col-sm-7"><label class="form-label" for="assign_plant">Planta</label><select class="form-select" id="assign_plant" name="plant_id">@foreach($plants as $plant)<option value="{{ $plant->id }}">{{ $plant->name }}</option>@endforeach</select></div>
<div class="col-sm-5"><label class="form-label" for="assign_role">Rol</label><select class="form-select" name="role" id="assign_role"><option value="viewer">Consulta</option><option value="operator">Operación</option><option value="supervisor">Supervisión</option></select></div></div>
<button class="btn btn-outline-primary" @disabled($users->isEmpty())>Asignar acceso</button></form></div></div></div>
</div>
<div class="card"><div class="card-header"><h2 class="h5 mb-0">Accesos asignados por planta</h2></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead><tr><th>Usuario</th><th>Planta</th><th>Rol</th><th>Administración</th></tr></thead><tbody>
@forelse($memberships as $member)
<tr><td><strong>{{ $member->name }}</strong><div class="small text-secondary">{{ $member->email }}</div></td><td>{{ $member->plant_name }}</td><td>{{ ucfirst($member->role) }}</td><td>
@if(!$member->is_portfolio_owner && !$member->is_portfolio_demo_admin)
<div class="d-flex flex-wrap gap-2">
<form class="d-flex gap-2" method="POST" action="{{ route('team.update',$member->user_id) }}">@csrf @method('PUT')<input type="hidden" name="plant_id" value="{{ $member->plant_id }}"><select class="form-select form-select-sm" name="role"><option value="viewer" @selected($member->role==='viewer')>Consulta</option><option value="operator" @selected($member->role==='operator')>Operación</option><option value="supervisor" @selected($member->role==='supervisor')>Supervisión</option></select><button class="btn btn-sm btn-outline-primary">Guardar</button></form>
<form method="POST" action="{{ route('team.revoke',$member->user_id) }}" onsubmit="return confirm('¿Retirar el acceso a esta planta?')">@csrf @method('DELETE')<input type="hidden" name="plant_id" value="{{ $member->plant_id }}"><button class="btn btn-sm btn-outline-danger">Retirar</button></form></div>
@else<span class="small text-secondary">{{ $member->is_portfolio_owner ? 'Cuenta protegida' : 'Demo protegida' }}</span>@endif
</td></tr>
@empty<tr><td colspan="4" class="text-center text-secondary py-4">Sin asignaciones.</td></tr>@endforelse
</tbody></table></div></div></div>
@endsection
