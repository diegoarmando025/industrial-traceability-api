@extends('layouts.industrial')
@section('title', 'Reglas por planta')
@section('page-title', 'Reglas de validación por planta')
@section('content')
<div class="card">
    <div class="card-header"><h2 class="h6 mb-0">Plantas que puedes administrar</h2></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Planta</th><th>Lecturas obligatorias</th><th>Etiqueta</th><th class="text-end">Acción</th></tr></thead>
                <tbody>
                @foreach($plants as $plant)
                    @php($rules = \App\Support\BoxValidationRules::normalize($plant->validation_rules))
                    <tr>
                        <td><div class="fw-semibold">{{ $plant->code }}</div><div class="text-secondary small">{{ $plant->name }}</div></td>
                        <td>{{ count($rules['required_scans']) }} de 3</td>
                        <td><code>{{ $rules['package_label_prefix'] }}</code></td>
                        <td class="text-end"><div class="d-flex gap-2 justify-content-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('plants.validation.edit', $plant) }}">Reglas base</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('plants.customers.index', $plant) }}">Perfiles de cliente</a></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<p class="text-secondary small mt-3">Los cambios se aplican a las cajas que se registren después de guardar la configuración.</p>
@endsection
