@extends('layouts.industrial')
@section('title', 'Caja '.$box->code)
@section('page-title', 'Detalle de caja')
@section('content')
@if (session('status'))<div class="alert alert-success py-2">{{ session('status') }}</div>@endif
@if (session('warning'))<div class="alert alert-warning py-2">{{ session('warning') }}</div>@endif
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
    <div><h2 class="h5 mb-1">{{ $box->code }}</h2><div class="text-secondary small">{{ $box->plant->name }} @if($box->customerProfile) · Perfil: {{ $box->customerProfile->code }} @endif</div></div>
    <span class="badge fs-6 {{ $box->status === 'released' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $box->status === 'released' ? 'Liberada' : 'Pendiente' }}</span>
</div>
<div class="card mb-3"><div class="card-body py-3">
    <div class="row g-3"><div class="col-md-4"><div class="text-secondary small">Número de parte</div><div class="fw-semibold">{{ $box->part_number }}</div></div><div class="col-md-4"><div class="text-secondary small">Número de serie</div><div class="fw-semibold">{{ $box->serial_number }}</div></div><div class="col-md-4"><div class="text-secondary small">Registrada</div><div>{{ $box->created_at?->format('d/m/Y H:i') }}</div></div></div>
</div></div>
@if ($box->status !== 'released')
<div class="row g-3 mb-3">
    @if ($canScan)
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Registrar lectura</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('boxes.scans.store', $box) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="scan_type" class="form-label">Tipo de etiqueta</label>
                        <select class="form-select @error('scan_type') is-invalid @enderror" name="scan_type" id="scan_type" required>
                            <option value="part_number" @selected(old('scan_type') === 'part_number')>Número de parte</option>
                            <option value="serial_number" @selected(old('scan_type') === 'serial_number')>Número de serie</option>
                            <option value="package_label" @selected(old('scan_type') === 'package_label')>Etiqueta de empaque</option>
                        </select>
                        @error('scan_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="value" class="form-label">Lectura</label>
                        <input type="text" class="form-control @error('value') is-invalid @enderror" id="value" name="value" maxlength="255" value="{{ old('value') }}" autocomplete="off" required autofocus>
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upc-scan me-1"></i>Validar lectura</button>
                </form>
            </div>
        </div>
    </div>
    @endif
    <div class="{{ $canScan ? 'col-lg-5' : 'col-12' }}">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Validaciones</h3></div>
            <div class="card-body">
                @foreach (['part_number' => 'Número de parte', 'serial_number' => 'Número de serie', 'package_label' => 'Etiqueta de empaque'] as $type => $label)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>{{ $label }}</span>
                        <span class="badge {{ in_array($type, $pendingScans, true) ? 'text-bg-warning' : 'text-bg-success' }}">{{ in_array($type, $pendingScans, true) ? 'Pendiente' : 'Validada' }}</span>
                    </div>
                @endforeach
                @if ($canRelease)
                    <form method="POST" action="{{ route('boxes.release', $box) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-success w-100" @disabled($pendingScans !== [])>Liberar caja</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
<div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Lecturas recientes</h3></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0 scan-history-table">
            <thead><tr><th class="scan-type-cell">Tipo</th><th>Valor leído</th><th>Resultado</th><th>Usuario</th><th>Fecha</th></tr></thead>
            <tbody>
            @forelse ($scans as $scan)
                <tr>
                    <td class="scan-type-cell">{{ match($scan->scan_type) { 'part_number' => 'Parte', 'serial_number' => 'Serie', default => 'Empaque' } }}</td>
                    <td class="scan-value-cell">{{ $scan->scanned_value }}</td>
                    <td><span class="badge {{ $scan->accepted ? 'text-bg-success' : 'text-bg-danger' }}">{{ $scan->accepted ? 'Válida' : 'Rechazada' }}</span></td>
                    <td>{{ $scan->user?->name ?? 'Usuario no disponible' }}</td>
                    <td class="text-nowrap">{{ $scan->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-secondary text-center py-3">Sin lecturas</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</div>
<div class="card">
    <div class="card-header"><h3 class="card-title">Historial de operaciones</h3></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Evento</th><th>Usuario</th><th>Fecha</th><th>Detalle</th></tr></thead>
            <tbody>
            @forelse ($events as $event)
                <tr>
                    <td>{{ match($event->event_type) { 'created' => 'Registro', 'released' => 'Liberación', 'release_blocked' => 'Liberación bloqueada', default => $event->event_type } }}</td>
                    <td>{{ $event->user?->name ?? 'Usuario no disponible' }}</td>
                    <td class="text-nowrap">{{ $event->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        @if ($event->event_type === 'release_blocked')
                            Faltantes: {{ implode(', ', $event->details['pending_scans'] ?? []) }}
                        @elseif ($event->event_type === 'released')
                            Caja liberada
                        @elseif ($event->event_type === 'created')
                            Caja registrada
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-secondary text-center py-3">Sin actividad</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</div>
<div class="mt-3"><a href="{{ route('boxes.index') }}" class="btn btn-outline-secondary">Volver a cajas</a></div>
@endsection
