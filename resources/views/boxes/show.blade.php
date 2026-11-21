@extends('layouts.industrial')
@section('title', 'Caja '.$box->code)
@section('page-title', 'Detalle de caja')
@section('content')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
    <div><h2 class="h5 mb-1">{{ $box->code }}</h2><div class="text-secondary small">{{ $box->plant->name }}</div></div>
    <span class="badge fs-6 {{ $box->status === 'released' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $box->status === 'released' ? 'Liberada' : 'Pendiente' }}</span>
</div>
<div class="card mb-3"><div class="card-body">
    <div class="row g-3"><div class="col-md-4"><div class="text-secondary small">Número de parte</div><div class="fw-semibold">{{ $box->part_number }}</div></div><div class="col-md-4"><div class="text-secondary small">Número de serie</div><div class="fw-semibold">{{ $box->serial_number }}</div></div><div class="col-md-4"><div class="text-secondary small">Registrada</div><div>{{ $box->created_at?->format('d/m/Y H:i') }}</div></div></div>
</div></div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Lecturas recientes</h3></div><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Tipo</th><th>Valor</th><th>Resultado</th></tr></thead><tbody>@forelse($scans as $scan)<tr><td>{{ match($scan->scan_type) { 'part_number' => 'Parte', 'serial_number' => 'Serie', default => 'Empaque' } }}</td><td class="text-break">{{ $scan->scanned_value }}</td><td><span class="badge {{ $scan->accepted ? 'text-bg-success' : 'text-bg-danger' }}">{{ $scan->accepted ? 'Válida' : 'Rechazada' }}</span></td></tr>@empty<tr><td colspan="3" class="text-secondary text-center py-3">Sin lecturas</td></tr>@endforelse</tbody></table></div></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Actividad reciente</h3></div><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Evento</th><th>Fecha</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ match($event->event_type) { 'created' => 'Registro', 'released' => 'Liberación', 'release_blocked' => 'Liberación bloqueada', default => $event->event_type } }}</td><td>{{ $event->created_at?->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="2" class="text-secondary text-center py-3">Sin actividad</td></tr>@endforelse</tbody></table></div></div></div></div>
</div>
<div class="mt-3"><a href="{{ route('boxes.index') }}" class="btn btn-outline-secondary">Volver a cajas</a></div>
@endsection
