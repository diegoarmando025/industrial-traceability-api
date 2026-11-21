@extends('layouts.industrial')
@section('title', 'Resumen')
@section('page-title', 'Resumen de producción')
@section('content')
    <p class="text-secondary mb-4">Indicadores de las plantas asignadas a tu usuario.</p>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4"><div class="small-box text-bg-primary"><div class="inner"><h3>{{ $counts['total'] }}</h3><p>Cajas registradas</p></div><div class="small-box-icon"><i class="bi bi-box-seam"></i></div></div></div>
        <div class="col-sm-6 col-lg-4"><div class="small-box text-bg-warning"><div class="inner"><h3>{{ $counts['pending'] }}</h3><p>Pendientes de liberación</p></div><div class="small-box-icon"><i class="bi bi-hourglass-split"></i></div></div></div>
        <div class="col-sm-6 col-lg-4"><div class="small-box text-bg-success"><div class="inner"><h3>{{ $counts['released'] }}</h3><p>Cajas liberadas</p></div><div class="small-box-icon"><i class="bi bi-check2-circle"></i></div></div></div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Últimas cajas registradas</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead><tr><th>Código</th><th>Planta</th><th>Número de parte</th><th>Serie</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($boxes as $box)
                            <tr>
                                <td class="fw-semibold">{{ $box->code }}</td>
                                <td>{{ $box->plant->code }}</td>
                                <td>{{ $box->part_number }}</td>
                                <td>{{ $box->serial_number }}</td>
                                <td><span class="badge {{ $box->status === 'released' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $box->status === 'released' ? 'Liberada' : 'Pendiente' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-secondary">No hay cajas registradas en tus plantas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
