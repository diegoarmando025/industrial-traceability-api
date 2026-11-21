@extends('layouts.industrial')
@section('title', 'Resumen')
@section('page-title', 'Resumen de producción')
@section('content')
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4"><div class="small-box text-bg-primary"><div class="inner"><h3>{{ $counts['total'] }}</h3><p>Cajas registradas</p></div><div class="small-box-icon"><i class="bi bi-box-seam"></i></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="small-box text-bg-warning"><div class="inner"><h3>{{ $counts['pending'] }}</h3><p>Pendientes de liberación</p></div><div class="small-box-icon"><i class="bi bi-hourglass-split"></i></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="small-box text-bg-success"><div class="inner"><h3>{{ $counts['released'] }}</h3><p>Cajas liberadas</p></div><div class="small-box-icon"><i class="bi bi-check2-circle"></i></div></div></div>
    </div>
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between"><h2 class="card-title mb-0">Últimas cajas registradas</h2><a href="{{ route('boxes.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">Ver todas</a></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead><tr><th>Código</th><th>Planta</th><th>Número de parte</th><th>Serie</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($boxes as $box)
                            <tr>
                                <td class="fw-semibold">{{ $box->code }}</td>
                                <td>{{ $box->plant->code }}</td>
                                <td>{{ $box->part_number }}</td>
                                <td>{{ $box->serial_number }}</td>
                                <td><span class="badge {{ $box->status === 'released' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $box->status === 'released' ? 'Liberada' : 'Pendiente' }}</span></td><td class="text-end"><a href="{{ route('boxes.show', $box) }}" class="btn btn-sm btn-outline-secondary">Ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-secondary">No hay cajas registradas en tus plantas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
