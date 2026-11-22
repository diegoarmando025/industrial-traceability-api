@extends('layouts.industrial')
@section('title', 'Reportes operativos')
@section('page-title', 'Reportes operativos')
@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('reports.operations.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3"><label for="from" class="form-label">Desde</label><input id="from" class="form-control" type="date" name="from" value="{{ $filters['from'] }}" required></div>
            <div class="col-md-3"><label for="to" class="form-label">Hasta</label><input id="to" class="form-control" type="date" name="to" value="{{ $filters['to'] }}" required></div>
            <div class="col-md-3"><label for="plant_id" class="form-label">Planta</label>
                <select id="plant_id" name="plant_id" class="form-select">
                    <option value="">Todas mis plantas</option>
                    @foreach($plants as $plant)<option value="{{ $plant->id }}" @selected((string) $filters['plant_id'] === (string) $plant->id)>{{ $plant->code }} · {{ $plant->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Consultar</button><a class="btn btn-outline-success" href="{{ route('reports.operations.export', array_filter($filters, fn($value) => $value !== null && $value !== '')) }}">CSV</a></div>
        </form>
        <div class="form-text mt-2">Intervalo máximo de 90 días. Los indicadores por fecha corresponden a la fecha real de cada evento; los totales por planta son acumulados.</div>
    </div>
</div>
<div class="row g-3 mb-3">
    @foreach([
        ['Cajas registradas', $kpis['registered'], 'primary'],
        ['Cajas liberadas', $kpis['released'], 'success'],
        ['Lecturas realizadas', $kpis['scans'], 'secondary'],
        ['Lecturas rechazadas', $kpis['rejected'], 'danger'],
        ['Tasa de rechazo', $kpis['rejection_pct'] === null ? '—' : number_format($kpis['rejection_pct'], 1).'%', 'warning'],
        ['Intentos de liberación bloqueados', $kpis['blocked'], 'dark']
    ] as [$label, $value, $color])
    <div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body"><div class="small text-secondary">{{ $label }}</div><div class="display-6 fw-semibold text-{{ $color }}">{{ $value }}</div></div></div></div>
    @endforeach
</div>
@if($kpis['scans'] > 0)
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div><div class="fw-semibold">Resultados de lectura</div><div class="small text-secondary">Distribución de lecturas válidas y rechazadas en el periodo seleccionado</div></div>
            <span class="small fw-semibold">{{ $kpis['scans'] }} lecturas</span>
        </div>
        @php($acceptedPct=round(100 * ($kpis['scans'] - $kpis['rejected']) / $kpis['scans'],1))
        <div class="progress" role="progressbar" aria-label="Lecturas válidas" aria-valuenow="{{ $acceptedPct }}" aria-valuemin="0" aria-valuemax="100" style="height:12px">
            <div class="progress-bar bg-success" style="width:{{ $acceptedPct }}%"></div>
            <div class="progress-bar bg-danger" style="width:{{ 100 - $acceptedPct }}%"></div>
        </div>
        <div class="d-flex flex-wrap gap-3 small text-secondary mt-2"><span><i class="bi bi-circle-fill text-success me-1"></i> Válidas: {{ $kpis['scans'] - $kpis['rejected'] }}</span><span><i class="bi bi-circle-fill text-danger me-1"></i> Rechazadas: {{ $kpis['rejected'] }}</span></div>
    </div>
</div>
@endif
<div class="row g-3">
    <div class="col-xl-8"><div class="card h-100">
        <div class="card-header"><h2 class="h6 mb-0">Días con actividad</h2></div>
        <div class="card-body px-3 pt-2 pb-0"><p class="text-secondary small mb-2">Solo se muestran fechas con operaciones. La exportación CSV conserva todos los días del periodo.</p></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover table-sm align-middle mb-0">
            <thead><tr><th>Fecha</th><th class="text-end">Registradas</th><th class="text-end">Liberadas</th><th class="text-end">Lecturas válidas</th><th class="text-end">Rechazadas</th></tr></thead>
            <tbody>
            @forelse(array_reverse($activityDays) as $day)
                <tr><td>{{ \Carbon\CarbonImmutable::parse($day['date'])->format('d/m/Y') }}</td><td class="text-end">{{ $day['registered'] }}</td><td class="text-end">{{ $day['released'] }}</td><td class="text-end">{{ $day['accepted'] }}</td><td class="text-end">{{ $day['rejected'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-secondary py-4">Sin operaciones en el periodo seleccionado. Prueba otro intervalo o registra una caja ficticia.</td></tr>
            @endforelse
            </tbody></table></div></div>
    </div></div>
    <div class="col-xl-4"><div class="card">
        <div class="card-header"><h2 class="h6 mb-0">Estado acumulado por planta</h2></div>
        <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Planta</th><th class="text-end">Cajas</th><th class="text-end">Liberadas</th></tr></thead>
            <tbody>
            @forelse($plantSummary as $plant)
                <tr><td><strong>{{ $plant['code'] }}</strong><div class="small text-secondary">{{ $plant['name'] }}</div></td><td class="text-end">{{ $plant['total'] }}</td><td class="text-end">{{ $plant['released'] }}</td></tr>
            @empty<tr><td colspan="3" class="text-center text-secondary py-3">Sin plantas asignadas</td></tr>@endforelse
            </tbody>
        </table></div></div>
    </div></div></div>
<p class="text-secondary small mt-3">La tasa de rechazo se calcula sobre todas las lecturas realizadas, incluidas las repetidas. No representa porcentaje de cajas defectuosas. Una caja liberada puede haberse registrado en otra fecha.</p>
@endsection
