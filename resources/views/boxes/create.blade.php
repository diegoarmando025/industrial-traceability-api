@extends('layouts.industrial')
@section('title', 'Nueva caja')
@section('page-title', 'Registrar caja')
@section('content')
<div class="row"><div class="col-xl-7">
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('boxes.store') }}">
            @csrf
            <div class="mb-3"><label class="form-label" for="plant_id">Planta</label><select class="form-select @error('plant_id') is-invalid @enderror" id="plant_id" name="plant_id" required><option value="">Selecciona una planta</option>@foreach($plants as $plant)<option value="{{ $plant->id }}" @selected(old('plant_id') == $plant->id)>{{ $plant->code }} — {{ $plant->name }}</option>@endforeach</select>@error('plant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><label class="form-label" for="customer_profile_id">Perfil por cliente (opcional)</label>
                <select class="form-select @error('customer_profile_id') is-invalid @enderror" id="customer_profile_id" name="customer_profile_id">
                    <option value="">Reglas base de la planta</option>
                    @foreach($profiles as $profile)
                        <option value="{{ $profile->id }}" data-plant="{{ $profile->plant_id }}" @selected(old('customer_profile_id') == $profile->id)>{{ $profile->code }} — {{ $profile->name }} ({{ $profile->plant->code }})</option>
                    @endforeach
                </select>
                <div class="form-text">Solo perfiles activos de la planta elegida; las reglas se guardan en cada caja.</div>
                @error('customer_profile_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3"><label class="form-label" for="code">Código de caja</label><input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" maxlength="60" required>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><label class="form-label" for="part_number">Número de parte</label><input class="form-control @error('part_number') is-invalid @enderror" id="part_number" name="part_number" value="{{ old('part_number') }}" maxlength="80" required>@error('part_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-4"><label class="form-label" for="serial_number">Número de serie</label><input class="form-control @error('serial_number') is-invalid @enderror" id="serial_number" name="serial_number" value="{{ old('serial_number') }}" maxlength="80" required>@error('serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Registrar caja</button><a class="btn btn-outline-secondary" href="{{ route('boxes.index') }}">Cancelar</a></div>
        </form>
    </div></div>
</div></div>
@endsection
