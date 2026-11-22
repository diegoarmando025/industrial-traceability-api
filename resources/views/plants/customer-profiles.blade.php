@extends('layouts.industrial')
@section('title', 'Perfiles por cliente')
@section('page-title', 'Perfiles de validación por cliente')
@section('content')
<div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
    <div><h2 class="h5 mb-0">{{ $plant->code }} · {{ $plant->name }}</h2><p class="text-secondary small mb-0">Perfiles exclusivos de esta planta. Las cajas existentes conservan sus reglas.</p></div>
    <a href="{{ route('plants.validation.index') }}" class="btn btn-outline-secondary">Volver a plantas</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())
<div class="alert alert-danger" role="alert"><strong>Revisa los datos del perfil.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="row g-3">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><h3 class="h6 mb-0">Nuevo perfil</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('plants.customers.store', $plant) }}">
                    @csrf
                    <div class="mb-3"><label class="form-label" for="code">Código interno</label><input class="form-control" name="code" id="code" maxlength="30" pattern="[A-Z0-9_-]{1,30}" value="{{ old('code') }}" required><div class="form-text">Único dentro de la planta; utiliza letras mayúsculas, números y guiones.</div></div>
                    <div class="mb-3"><label class="form-label" for="name">Cliente / programa (ficticio)</label><input class="form-control" name="name" id="name" maxlength="120" value="{{ old('name') }}" required></div>
                    <fieldset class="mb-3"><legend class="h6">Escaneos obligatorios</legend>
                        @php($selected = old('required_scans', ['part_number', 'serial_number', 'package_label']))
                        @foreach(['part_number' => 'Número de parte', 'serial_number' => 'Número de serie', 'package_label' => 'Etiqueta de empaque'] as $type => $label)
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="scan_{{ $type }}" name="required_scans[]" value="{{ $type }}" @checked(in_array($type, is_array($selected) ? $selected : [], true))><label for="scan_{{ $type }}" class="form-check-label">{{ $label }}</label></div>
                        @endforeach
                    </fieldset>
                    <div class="mb-3"><label class="form-label" for="package_label_prefix">Prefijo de etiqueta</label><input class="form-control" name="package_label_prefix" id="package_label_prefix" maxlength="16" pattern="[A-Z0-9_-]{1,16}" value="{{ old('package_label_prefix', 'BOX') }}" required></div>
                    <div class="mb-3"><label for="package_label_format" class="form-label">Formato de etiqueta de empaque</label>
                        <select id="package_label_format" class="form-select" name="package_label_format" required>
                            <option value="colon" @selected(old('package_label_format', 'colon') === 'colon')>Estándar: PREFIJO:CAJA:PARTE:SERIE</option>
                            <option value="pipe" @selected(old('package_label_format') === 'pipe')>Alternativo: PREFIJO|PARTE|SERIE|CAJA</option>
                        </select>
                        <div class="form-text">No se permiten expresiones libres ni formatos personalizados.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Crear perfil</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header"><h3 class="h6 mb-0">Perfiles registrados</h3></div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
                <thead><tr><th>Cliente / código</th><th>Requisitos</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                @forelse($profiles as $profile)
                <tr>
                    <td><strong>{{ $profile->name }}</strong><div class="small text-secondary">{{ $profile->code }}</div></td>
                    <td>{{ implode(', ', $profile->validation_rules['required_scans']) }}<div class="small text-secondary">Prefijo: {{ $profile->validation_rules['package_label_prefix'] }} · Formato: {{ ($profile->validation_rules['package_label_format'] ?? 'colon') === 'pipe' ? 'Alternativo' : 'Estándar' }}</div></td>
                    <td><span class="badge {{ $profile->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $profile->active ? 'Activo' : 'Inactivo' }}</span></td>
                    <td class="text-end">
                        @if($profile->active)
                        <form action="{{ route('plants.customers.deactivate', [$plant, $profile]) }}" method="POST">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger" type="submit">Desactivar</button>
                        </form>
                        @else <span class="text-secondary small">No disponible</span> @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-secondary py-4">Todavía no se registran perfiles.</td></tr>
                @endforelse
                </tbody>
            </table></div></div>
            @if($profiles->hasPages())<div class="card-footer">{{ $profiles->links('pagination::bootstrap-5') }}</div>@endif
        </div>
    </div>
</div>
<p class="text-secondary small mt-3">Los perfiles creados no se editan directamente: desactiva el anterior y crea otro código para preservar la trazabilidad. La desactivación no cambia las cajas existentes.</p>
@endsection
