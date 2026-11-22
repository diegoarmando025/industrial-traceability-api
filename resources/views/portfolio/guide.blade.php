@extends('layouts.industrial')
@section('title', 'Guía de evaluación')
@section('page-title', 'Explorar PlantCore Traceability')
@section('content')
@if($isDemo)<div class="alert alert-info"><strong>Sesión de demostración activa.</strong> Los registros son ficticios y compartidos entre visitantes. Tus cambios permanecerán visibles para otros evaluadores. No ingreses información real.</div>@endif
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="small text-secondary">Cajas visibles en tus plantas</div><div class="display-6">{{ $totalBoxes }}</div><a href="{{ route('boxes.index') }}" class="btn btn-outline-primary mt-2">Explorar cajas</a></div></div></div>
    <div class="col-md-8"><div class="card h-100"><div class="card-body"><h2 class="h5">Prueba un flujo real de trazabilidad</h2><p class="text-secondary">Registra una caja ficticia, captura los escaneos, intenta una validación incorrecta, corrígela y libera la caja como supervisor. Consulta después su historial y exportación CSV.</p><a href="{{ route('boxes.create') }}" class="btn btn-primary">Registrar caja de prueba</a></div></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Recorrido recomendado</h2></div><div class="card-body">
    <ol class="mb-0">
        <li class="mb-2"><a href="{{ route('dashboard') }}">Dashboard</a> — revisa los indicadores del proceso.</li>
        <li class="mb-2"><a href="{{ route('boxes.create') }}">Nueva caja</a> — utiliza un código único como <code>TEST-{{ now()->format('His') }}</code>, parte <code>PN-100</code> y serie <code>SN-200</code>.</li>
        <li class="mb-2"><a href="{{ route('boxes.index') }}">Cajas</a> — abre la caja para realizar lecturas y probar el bloqueo.</li>
        <li class="mb-2">Libera la caja después de validar todas las lecturas obligatorias.</li>
        <li class="mb-2">Desde el detalle, consulta el historial completo y descarga un CSV.</li>
        <li><a href="{{ route('plants.validation.index') }}">Reglas por planta</a> — configura perfiles ficticios y formatos de etiqueta.</li>
    </ol>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Registros disponibles para explorar</h2></div><div class="card-body p-0">
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Caja</th><th>Estado</th><th></th></tr></thead><tbody>
    @forelse($sampleBoxes as $box)
    <tr><td>{{ $box->code }}</td><td>{{ $box->status === 'released' ? 'Liberada' : 'Pendiente' }}</td><td><a class="btn btn-sm btn-outline-secondary" href="{{ route('boxes.show', $box->id) }}">Abrir</a></td></tr>
    @empty<tr><td colspan="3" class="text-center text-secondary py-4">Sin cajas visibles. Registra la primera caja de prueba.</td></tr>@endforelse
    </tbody></table></div>
    </div></div></div>
</div>
<p class="text-secondary small">Este portafolio muestra funcionalidades reales de Laravel, autenticación, permisos por planta, validación de escaneos, reglas configurables y auditoría. No es un SaaS multiempresa ni procesa datos de clientes.</p>
@endsection
