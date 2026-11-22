@extends('layouts.industrial')
@section('title', 'Configurar validaciones')
@section('page-title', 'Configuración de validación')
@section('content')
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header">
                <h2 class="h6 mb-1">{{ $plant->code }} — {{ $plant->name }}</h2>
                <div class="small text-secondary">Reglas aplicables a nuevas cajas. Las cajas anteriores conservan su configuración.</div>
            </div>
            <div class="card-body">
                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <div class="fw-semibold mb-1">Revisa la configuración antes de guardar.</div>
                        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('plants.validation.update', $plant) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="version" value="{{ $plant->validation_rules_version }}">
                    <fieldset class="mb-4">
                        <legend class="h6">Lecturas obligatorias</legend>
                        @php($selected = old('required_scans', $rules['required_scans']))
                        <div class="form-text mb-2">Selecciona al menos una lectura. Solo las seleccionadas serán necesarias para liberar una caja nueva.</div>
                        @foreach([
                            'part_number' => 'Número de parte',
                            'serial_number' => 'Número de serie',
                            'package_label' => 'Etiqueta de empaque'
                        ] as $type => $label)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="scan_{{ $type }}" name="required_scans[]" value="{{ $type }}" @checked(in_array($type, is_array($selected) ? $selected : [], true))>
                                <label class="form-check-label" for="scan_{{ $type }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </fieldset>
                    <div class="mb-4">
                        <label for="package_label_prefix" class="form-label">Prefijo de etiqueta</label>
                        <input id="package_label_prefix" name="package_label_prefix" class="form-control @error('package_label_prefix') is-invalid @enderror" value="{{ old('package_label_prefix', $rules['package_label_prefix']) }}" pattern="[A-Z0-9_-]{1,16}" maxlength="16" required autocomplete="off">
                        <div class="form-text">Ejemplo: <code>PACK:CAJA-001:PN-100:SN-200</code>. Usa de 1 a 16 letras mayúsculas, números, guiones o guion bajo.</div>
                        @error('package_label_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4"><label class="form-label" for="package_label_format">Formato de etiqueta de empaque</label>
                        <select class="form-select" id="package_label_format" name="package_label_format">
                            <option value="colon" @selected(old('package_label_format', $rules['package_label_format'] ?? 'colon') === 'colon')>Estándar: PREFIJO:CAJA:PARTE:SERIE</option>
                            <option value="pipe" @selected(old('package_label_format', $rules['package_label_format'] ?? 'colon') === 'pipe')>Alternativo: PREFIJO|PARTE|SERIE|CAJA</option>
                        </select>
                        <div class="form-text">Se aplica únicamente a cajas nuevas; las anteriores conservan su formato.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit">Guardar reglas</button>
                        <a class="btn btn-outline-secondary" href="{{ route('plants.validation.index') }}">Volver a plantas</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header"><h2 class="h6 mb-0">Historial de cambios</h2></div>
            <div class="card-body">
                @forelse($changes as $change)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>Versión {{ $change->version }}</strong>
                            <small class="text-secondary">{{ $change->created_at?->format('d/m/Y H:i') }}</small>
                        </div>
                        <div class="small mb-2">{{ $change->user?->name ?? 'Usuario no disponible' }}</div>
                        <div class="small text-secondary mb-1">Antes: {{ implode(', ', $change->previous_rules['required_scans']) }} · {{ $change->previous_rules['package_label_prefix'] }}</div>
                        <div class="small">Después: {{ implode(', ', $change->new_rules['required_scans']) }} · {{ $change->new_rules['package_label_prefix'] }}</div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Todavía no hay cambios registrados.</p>
                @endforelse
                {{ $changes->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection
