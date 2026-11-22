@extends('layouts.industrial')
@section('title', 'Resumen')
@section('page-title', 'Resumen de producción')
@section('content')
<section class="ops-intro mb-4">
    <div>
        <div class="ops-eyebrow">CONTROL DE LIBERACIÓN · PRODUCCIÓN</div>
        <h2 class="h3 fw-semibold mb-2">Trazabilidad de cajas</h2>
        <p class="text-secondary mb-0">Del registro a la liberación: identifica cajas pendientes, valida sus lecturas y consulta cada movimiento.</p>
    </div>
    <div class="ops-intro-actions">
        <a href="{{ route('boxes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Registrar caja</a>
        <a href="{{ route('reports.operations.index') }}" class="btn btn-outline-secondary"><i class="bi bi-graph-up me-1"></i> Consultar reportes</a>
    </div>
</section>
<div class="row g-3 mb-4 ops-kpis">
    <div class="col-sm-6 col-xl-4"><div class="card h-100 ops-kpi"><div class="card-body"><div class="ops-kpi-label"><i class="bi bi-box-seam me-2"></i> Cajas registradas</div><div class="ops-kpi-number">{{ number_format($counts['total']) }}</div><div class="ops-kpi-foot">Registro de plantas autorizadas</div></div></div></div>
    <div class="col-sm-6 col-xl-4"><div class="card h-100 ops-kpi ops-kpi-attention"><div class="card-body"><div class="ops-kpi-label"><i class="bi bi-hourglass-split me-2"></i> Pendientes de liberación</div><div class="ops-kpi-number">{{ number_format($counts['pending']) }}</div><div class="ops-kpi-foot">Requieren completar sus validaciones</div></div></div></div>
    <div class="col-sm-6 col-xl-4"><div class="card h-100 ops-kpi ops-kpi-success"><div class="card-body"><div class="ops-kpi-label"><i class="bi bi-check2-circle me-2"></i> Cajas liberadas</div><div class="ops-kpi-number">{{ number_format($counts['released']) }}</div><div class="ops-kpi-foot">Liberación registrada en el historial</div></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between"><h2 class="card-title mb-0">Últimas cajas registradas</h2><a href="{{ route('boxes.index') }}" class="btn btn-sm btn-outline-primary ms-auto">Ver todas</a></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Código</th><th>Planta</th><th>Número de parte</th><th>Serie</th><th>Estado</th><th class="text-end">Detalle</th></tr></thead>
                        <tbody>
                        @forelse ($boxes as $box)
                            <tr>
                                <td class="fw-semibold"><a class="ops-table-link" href="{{ route('boxes.show', $box) }}">{{ $box->code }}</a></td>
                                <td>{{ $box->plant->code }}</td>
                                <td>{{ $box->part_number }}</td>
                                <td>{{ $box->serial_number }}</td>
                                <td><span class="badge {{ $box->status === 'released' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $box->status === 'released' ? 'Liberada' : 'Pendiente' }}</span></td>
                                <td class="text-end"><a href="{{ route('boxes.show', $box) }}" class="btn btn-sm btn-outline-secondary">Abrir</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-secondary">Aún no hay cajas registradas. Registra la primera caja para comenzar.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100 ops-flow">
            <div class="card-header"><h2 class="card-title mb-0">Flujo de liberación</h2></div>
            <div class="card-body">
                <div class="ops-flow-step"><span class="ops-step-count">01</span><div><strong>Registrar</strong><div class="small text-secondary">Identifica la caja, pieza y serie.</div></div></div>
                <div class="ops-flow-step"><span class="ops-step-count">02</span><div><strong>Validar</strong><div class="small text-secondary">Comprueba parte, serie y empaque.</div></div></div>
                <div class="ops-flow-step"><span class="ops-step-count">03</span><div><strong>Liberar</strong><div class="small text-secondary">Registra la autorización y evidencia.</div></div></div>
                <a href="{{ route('portfolio.guide') }}" class="btn btn-outline-primary w-100 mt-2">Ver recorrido del sistema</a>
            </div>
        </div>
    </div>
</div>
@endsection
