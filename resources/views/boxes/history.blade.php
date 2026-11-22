@extends('layouts.industrial')
@section('title', 'Historial de caja')
@section('page-title', 'Historial completo de trazabilidad')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div><h2 class="h5 mb-1">{{ $box->code }}</h2><p class="text-secondary mb-0">{{ $box->plant->name }} · {{ $box->part_number }} · {{ $box->serial_number }}</p></div>
    <a href="{{ route('boxes.show', $box) }}" class="btn btn-outline-secondary">Volver a la caja</a>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('boxes.history.index', $box) }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="kind">Registro</label>
                <select class="form-select" name="kind" id="kind">
                    <option value="scans" @selected($kind === 'scans')>Escaneos</option>
                    <option value="events" @selected($kind === 'events')>Eventos de liberación</option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label" for="from">Desde</label><input class="form-control" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label" for="to">Hasta</label><input class="form-control" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label" for="result">Resultado</label>
                <select class="form-select" id="result" name="result">
                    <option value="">Todos</option>
                    <option value="accepted" @selected(($filters['result'] ?? '') === 'accepted')>Válido</option>
                    <option value="rejected" @selected(($filters['result'] ?? '') === 'rejected')>Rechazado</option>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="scan_type">Tipo de escaneo</label>
                <select class="form-select" id="scan_type" name="scan_type">
                    <option value="">Todos</option>
                    @foreach(['part_number' => 'Parte', 'serial_number' => 'Serie', 'package_label' => 'Empaque'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['scan_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                <a class="btn btn-outline-secondary" href="{{ route('boxes.history.index', $box) }}">Limpiar</a>
                <a class="btn btn-outline-success" href="{{ route('boxes.history.export', array_merge(['box' => $box->id], $filters)) }}">Exportar CSV filtrado</a>
            </div>
        </form>
        @if($errors->any())
            <div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>
        @endif
        <div class="form-text mt-2">Los filtros de resultado y tipo se aplican únicamente a escaneos. Para exportaciones superiores a 10,000 filas, reduce el intervalo de fechas.</div>
    </div>
</div>
<div class="card">
    <div class="card-header"><h3 class="h6 mb-0">{{ $kind === 'events' ? 'Eventos' : 'Escaneos' }} · {{ $records->total() }} registros</h3></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            @if($kind === 'scans')
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Valor leído</th><th>Resultado</th><th>Usuario</th></tr></thead>
            <tbody>@forelse($records as $record)
                <tr><td class="text-nowrap">{{ $record->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $record->scan_type }}</td><td class="text-break">{{ $record->scanned_value }}</td>
                    <td><span class="badge {{ $record->accepted ? 'text-bg-success' : 'text-bg-danger' }}">{{ $record->accepted ? 'Válida' : 'Rechazada' }}</span></td>
                    <td>{{ $record->user?->name ?? 'Usuario no disponible' }}</td></tr>
            @empty<tr><td colspan="5" class="text-center text-secondary py-4">No hay escaneos que coincidan.</td></tr>@endforelse</tbody>
            @else
            <thead><tr><th>Fecha</th><th>Evento</th><th>Detalle</th><th>Usuario</th></tr></thead>
            <tbody>@forelse($records as $record)
                <tr><td class="text-nowrap">{{ $record->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $record->event_type }}</td>
                    <td class="text-break">{{ json_encode($record->details ?? [], JSON_UNESCAPED_UNICODE) }}</td>
                    <td>{{ $record->user?->name ?? 'Usuario no disponible' }}</td></tr>
            @empty<tr><td colspan="4" class="text-center text-secondary py-4">No hay eventos que coincidan.</td></tr>@endforelse</tbody>
            @endif
        </table>
    </div></div>
    @if($records->hasPages())<div class="card-footer">{{ $records->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
