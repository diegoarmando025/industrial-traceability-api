@extends('layouts.industrial')
@section('title', 'Cajas')
@section('page-title', 'Cajas de producción')
@section('content')
<div class="d-flex justify-content-end mb-3">
    @if ($canCreate)
        <a href="{{ route('boxes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nueva caja</a>
    @endif
</div>
<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route('boxes.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-lg-5"><label class="form-label" for="search">Buscar</label><input class="form-control" id="search" name="search" maxlength="100" value="{{ $filters['search'] ?? '' }}" placeholder="Código, número de parte o serie"></div>
            <div class="col-sm-6 col-lg-3"><label for="plant_id" class="form-label">Planta</label><select name="plant_id" id="plant_id" class="form-select"><option value="">Todas</option>@foreach ($plants as $plant)<option value="{{ $plant->id }}" @selected(($filters['plant_id'] ?? null) == $plant->id)>{{ $plant->code }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-lg-2"><label for="status" class="form-label">Estado</label><select name="status" id="status" class="form-select"><option value="">Todos</option><option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pendiente</option><option value="released" @selected(($filters['status'] ?? '') === 'released')>Liberada</option></select></div>
            <div class="col-lg-2"><button class="btn btn-outline-primary w-100" type="submit">Filtrar</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Código</th><th>Planta</th><th>Parte</th><th>Serie</th><th>Estado</th><th class="text-end">Detalle</th></tr></thead>
                <tbody>
                @forelse ($boxes as $box)
                    <tr>
                        <td class="fw-semibold">{{ $box->code }}</td><td>{{ $box->plant->code }}</td><td>{{ $box->part_number }}</td><td>{{ $box->serial_number }}</td>
                        <td><span class="badge {{ $box->status === 'released' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $box->status === 'released' ? 'Liberada' : 'Pendiente' }}</span></td>
                        <td class="text-end"><a href="{{ route('boxes.show', $box) }}" class="btn btn-sm btn-outline-secondary">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">No se encontraron cajas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($boxes->hasPages())<div class="card-footer">{{ $boxes->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
