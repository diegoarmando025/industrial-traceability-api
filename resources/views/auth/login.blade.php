<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso · PlantCore Traceability</title>
    @vite(['resources/css/industrial.css', 'resources/js/industrial.js'])
</head>
<body class="login-page bg-body-tertiary">
    <main class="login-box">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header text-center py-3"><strong>PlantCore Traceability</strong></div>
            <div class="card-body">
                <h1 class="h5 mb-2">Acceso a producción</h1>
                <p class="small text-secondary mb-4">Ingresa con un usuario autorizado de planta.</p>
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="username" required autofocus>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Iniciar sesión</button>
                </form>
            </div>
        </div>
        <p class="text-center text-secondary small mt-3">Entorno de demostración · Sin registro público</p>
    </main>
</body>
</html>
