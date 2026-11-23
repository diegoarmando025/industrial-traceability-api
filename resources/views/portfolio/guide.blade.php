@extends('layouts.industrial')
@section('title','Centro de evaluación')
@section('page-title','Centro de evaluación')
@section('content')
<section class="portfolio-hero mb-4">
 <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center">
  <div><div class="portfolio-eyebrow mb-2"><i class="bi bi-upc-scan me-1"></i> DEVALEJANDRE / TRAZABILIDAD INDUSTRIAL</div>
   <h2 class="h3 fw-semibold mb-2">Valida cada lectura antes de liberar una caja.</h2>
   <p class="mb-0 portfolio-subtitle">Registra cajas, prueba las reglas de validación, detecta errores y consulta la trazabilidad completa desde el mismo sistema.</p>
  </div>
  <div class="d-flex flex-wrap gap-2 flex-shrink-0"><a href="{{ route('boxes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Nueva caja</a><a href="{{ route('reports.operations.index') }}" class="btn btn-outline-secondary">Ver reportes</a></div>
 </div>
</section>
@if($isDemo)<div class="portfolio-demo-note mb-4"><i class="bi bi-info-circle me-2"></i><strong>Modo evaluación:</strong> puedes capturar y liberar cajas ficticias. Los datos se comparten entre visitantes; no introduzcas información real.</div>@endif
<div class="row g-3 mb-4">
 <div class="col-md-4"><div class="card portfolio-stat h-100"><div class="card-body"><div class="small text-secondary">Cajas disponibles</div><div class="portfolio-stat-value">{{ number_format($totalBoxes) }}</div><div class="small text-secondary">Registros consultados de tus plantas autorizadas</div></div></div></div>
 <div class="col-md-4"><div class="card portfolio-stat h-100"><div class="card-body"><div class="small text-secondary">Validaciones</div><div class="h5 mt-3 mb-2">Escaneos y reglas</div><div class="small text-secondary">Parte, serie y etiqueta de empaque</div></div></div></div>
 <div class="col-md-4"><div class="card portfolio-stat h-100"><div class="card-body"><div class="small text-secondary">Trazabilidad</div><div class="h5 mt-3 mb-2">Historial auditable</div><div class="small text-secondary">Rechazos, liberaciones, eventos y CSV</div></div></div></div>
</div>
<h2 class="h5 mb-1">Prueba el flujo de trazabilidad</h2><p class="small text-secondary mb-3">Cada módulo es funcional y utiliza la misma base de datos de demostración.</p>
<div class="row g-3 mb-4">
 @foreach([
 ['01','Registrar caja','Crea una caja ficticia con pieza y número de serie.','boxes.create','Registrar caja','bi-box-seam'],
 ['02','Validar lecturas','Abre una caja pendiente; prueba valores válidos e inválidos.','boxes.index','Explorar cajas','bi-upc-scan'],
 ['03','Configurar reglas','Revisa reglas por planta y formatos de etiqueta.','plants.validation.index','Ver reglas','bi-sliders'],
 ['04','Consultar resultados','Analiza rechazos, liberaciones y tendencias diarias.','reports.operations.index','Ver reportes','bi-graph-up-arrow']
 ] as [$number,$title,$description,$route,$button,$icon])
 <div class="col-md-6 col-xl-3"><div class="card portfolio-step h-100"><div class="card-body d-flex flex-column"><div class="d-flex justify-content-between align-items-center mb-3"><span class="portfolio-step-number">{{ $number }}</span><i class="bi {{ $icon }} fs-4 text-secondary"></i></div><h3 class="h6 fw-semibold">{{ $title }}</h3><p class="small text-secondary flex-grow-1">{{ $description }}</p><a class="btn btn-sm btn-outline-primary align-self-start" href="{{ route($route) }}">{{ $button }} <i class="bi bi-arrow-right ms-1"></i></a></div></div></div>
 @endforeach
</div>
<div class="card mb-4"><div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Registros para explorar</h2><a href="{{ route('boxes.index') }}" class="small">Ver todas las cajas</a></div>
<div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Código</th><th>Estado</th><th class="text-end">Acción</th></tr></thead><tbody>
@forelse($sampleBoxes as $box)
<tr><td class="fw-semibold">{{ $box->code }}</td><td><span class="badge {{ $box->status === 'released' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $box->status === 'released' ? 'Liberada' : 'Pendiente' }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('boxes.show', $box->id) }}">Abrir</a></td></tr>
@empty<tr><td colspan="3" class="text-center text-secondary py-4">No hay cajas visibles. Registra una para iniciar el flujo.</td></tr>@endforelse
</tbody></table></div></div></div>
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h2 class="h6 mb-0">Casos de evaluación</h2></div>
            <div class="card-body">
                <p class="small text-secondary">Escenarios ficticios que permiten comprobar distintos resultados del proceso. Si no aparecen todos, el propietario puede completar el escenario desde Equipo y permisos.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Caja</th><th>Qué demuestra</th></tr></thead>
                        <tbody>
                            <tr><td><code>004</code></td><td>Lectura rechazada, corregida y pendiente de empaque</td></tr>
                            <tr><td><code>005</code></td><td>Validaciones completas y liberación pendiente de autorización</td></tr>
                            <tr><td><code>006</code></td><td>Etiqueta incorrecta y liberación bloqueada</td></tr>
                            <tr><td><code>007</code></td><td>Rechazo, corrección y liberación con historial</td></tr>
                            <tr><td><code>008</code></td><td>Caja sin lecturas para ejecutar el flujo desde cero</td></tr>
                        </tbody>
                    </table>
                </div>
                <a href="{{ route('boxes.index') }}" class="btn btn-sm btn-outline-primary">Explorar cajas</a>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h2 class="h6 mb-0">API REST y código</h2></div>
            <div class="card-body d-flex flex-column">
                <p class="small text-secondary">La aplicación expone operaciones versionadas para registro de cajas, lecturas, liberaciones e historial. El repositorio incluye contrato OpenAPI y colección Postman.</p>
                <code class="d-block p-2 bg-body-tertiary rounded mb-3">/api/v1/boxes</code>
                <div class="d-flex flex-wrap gap-2 mt-auto">
                    <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer" href="https://github.com/diegoarmando025/industrial-traceability-api/blob/main/docs/openapi.json">Ver contrato OpenAPI</a>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer" href="https://github.com/diegoarmando025/industrial-traceability-api/tree/main/docs/postman">Colección Postman</a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="portfolio-next-step d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4"><div><strong>¿Buscas trazabilidad para una operación específica?</strong><p class="small text-secondary mb-0">Explora validación de etiquetas, autorizaciones por planta, historial auditable y API REST. Las integraciones y ampliaciones pueden definirse según los procesos del proyecto.</p></div><a href="https://devalejandre.com" class="btn btn-outline-primary" rel="noopener">Conocer DevAlejandre</a></div>
@endsection
