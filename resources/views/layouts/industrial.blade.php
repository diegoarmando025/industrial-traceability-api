<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Traceability') · DevAlejandre</title>
    <link rel="icon" href="https://devalejandre.com/assets/brand/favicon.ico" sizes="any">
    @vite(['resources/css/industrial.css', 'resources/js/industrial.js'])
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary" data-bs-theme="light">
<div class="app-wrapper">
    <nav class="app-header navbar navbar-expand bg-body border-bottom">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item"><button class="nav-link btn" type="button" data-lte-toggle="sidebar" aria-label="Alternar menú"><i class="bi bi-list"></i></button></li>
                <li class="nav-item d-none d-sm-flex align-items-center ms-2"><span class="small text-secondary">Operación de planta</span></li>
            </ul>
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item me-3 small text-secondary">{{ auth()->user()->name }}</li>
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </nav>
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}" class="brand-link text-decoration-none">
                <span class="da-sidebar-brand"><span class="da-sidebar-logo"><img src="https://devalejandre.com/assets/brand/DevAlejandre_logo.svg" alt="DevAlejandre" width="130" height="28"></span><span class="da-sidebar-product">Traceability</span></span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-3">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Navegación principal" data-accordion="false">
                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon bi bi-grid-1x2"></i><p>Resumen</p>
                        </a>
                    </li>
                    <li class="nav-item"><a href="{{ route('portfolio.guide') }}" class="nav-link {{ request()->routeIs('portfolio.guide') ? 'active' : '' }}"><i class="nav-icon bi bi-compass"></i><p>Guía de evaluación</p></a></li>
                    <li class="nav-item"><a href="{{ route('boxes.index') }}" class="nav-link {{ request()->routeIs('boxes.*') ? 'active' : '' }}"><i class="nav-icon bi bi-box-seam"></i><p>Cajas</p></a></li>
                    <li class="nav-item"><a href="{{ route('reports.operations.index') }}" class="nav-link {{ request()->routeIs('reports.operations.*') ? 'active' : '' }}"><i class="nav-icon bi bi-bar-chart-line"></i><p>Reportes</p></a></li>
                    @if(auth()->user()->is_portfolio_owner)
                        <li class="nav-item"><a href="{{ route('team.index') }}" class="nav-link {{ request()->routeIs('team.*') ? 'active' : '' }}"><i class="nav-icon bi bi-people"></i><p>Equipo y permisos</p></a></li>
                    @endif
                    @if(auth()->user()->plants()->wherePivot('role', 'supervisor')->exists())
                        <li class="nav-item"><a href="{{ route('plants.validation.index') }}" class="nav-link {{ request()->routeIs('plants.validation.*') ? 'active' : '' }}"><i class="nav-icon bi bi-sliders"></i><p>Reglas de validación</p></a></li>
                    @endif
                </ul>
            </nav>
        </div>
    </aside>
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <h1 class="h4 mb-0">@yield('page-title', 'Producción')</h1>
            </div>
        </div>
        <div class="app-content"><div class="container-fluid">@yield('content')</div></div>
    </main>
</div>
</body>
</html>
