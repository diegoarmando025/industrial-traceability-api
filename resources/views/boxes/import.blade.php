@extends('layouts.industrial')
@section('title','Importación masiva')
@section('page-title','Importar cajas')
@section('content')
<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="card-header"><h2 class="h5 mb-0">Registro masivo de producción</h2></div>
<div class="card-body">
<p class="text-secondary">Carga hasta 200 cajas en un CSV. Se validará el archivo completo antes de guardar; si alguna caja es inválida, no se registrará ninguna.</p>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" enctype="multipart/form-data" action="{{ route('boxes.import.store') }}">
@csrf
<div class="mb-3"><label for="plant_id" class="form-label">Planta de destino</label><select id="plant_id" name="plant_id" class="form-select" required>@foreach($plants as $plant)<option value="{{ $plant->id }}" @selected(old('plant_id') == $plant->id)>{{ $plant->code }} — {{ $plant->name }}</option>@endforeach</select></div>
<div class="mb-3"><label for="file" class="form-label">Archivo CSV (máximo 1 MB)</label><input type="file" accept=".csv,.txt,text/csv" id="file" name="file" class="form-control" required></div>
<div class="d-flex gap-2"><button class="btn btn-primary">Validar e importar</button><a class="btn btn-outline-secondary" href="{{ route('boxes.index') }}">Volver a cajas</a></div>
</form></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header"><h2 class="h6 mb-0">Formato esperado</h2></div><div class="card-body">
<p class="small text-secondary">Tres columnas obligatorias, separadas por comas. Los códigos de caja deben ser únicos.</p>
<pre class="bg-body-tertiary p-3 rounded small overflow-auto">code,part_number,serial_number
QRO-0001,PN-100,SN-100
QRO-0002,PN-101,SN-101</pre>
<p class="small text-secondary mb-0">Las cajas importadas quedan pendientes de escaneo y liberación. Puedes probarlas desde el listado de cajas.</p>
</div></div></div>
</div>
@endsection
