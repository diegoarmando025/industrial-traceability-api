<!doctype html>
<html lang="es" data-bs-theme="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>PlantCore Traceability · Demo</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color-scheme:dark}
body{margin:0;background:#171c24;color:#edf1f7}
main{max-width:1100px;margin:auto;padding:36px 20px 64px}
header{display:flex;justify-content:space-between;gap:20px;align-items:center;border-bottom:1px solid #3b4657;padding-bottom:24px}
h1{font-size:clamp(1.7rem,4vw,2.35rem);letter-spacing:-.04em;margin:8px 0}
h2{font-size:1.15rem}
p,small{color:#aeb9c8}
aside{border:1px solid #55708e;padding:8px 14px;border-radius:30px;font-size:.8rem;white-space:nowrap}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:30px 0}
.stat,.panel{background:#222b36;border:1px solid #3b495a;border-radius:12px;padding:20px}
.stat strong{display:block;font-size:2rem}
a{color:#a9caff}
table{border-collapse:collapse;width:100%}
th,td{text-align:left;padding:14px;border-bottom:1px solid #3b495a}
th{color:#b5c0cf;font-size:.83rem}
.scroller{overflow-x:auto}
label{margin-right:10px}
select,button{padding:9px 12px;border-radius:7px;border:1px solid #64758a;background:#1c2633;color:white}
button{cursor:pointer}
.tag{font-size:.8rem;border-radius:20px;padding:5px 10px;background:#344a5e}
footer{margin-top:26px}
@media(max-width:640px){header{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:1fr 1fr}.stat:last-child{grid-column:span 2}th,td{padding:10px}}
</style>
</head>
<body>
<main>
<header><div><small>PLANTCORE SYSTEMS / TRAZABILIDAD</small><h1>Validación y liberación de cajas</h1><p>{{ $plant->name }} · Escenario de demostración</p></div><aside>Solo lectura · Datos ficticios</aside></header>
<section class="stats" aria-label="Indicadores">
<div class="stat"><strong>{{ $counts['total'] }}</strong><small>Cajas registradas</small></div>
<div class="stat"><strong>{{ $counts['released'] }}</strong><small>Liberadas</small></div>
<div class="stat"><strong>{{ $counts['pending'] }}</strong><small>En validación</small></div>
</section>
<section class="panel">
<h2>Seguimiento de cajas</h2>
<form action="{{ route('traceability.demo.index') }}" method="GET" style="margin:18px 0">
<label for="status">Estado</label>
<select id="status" name="status"><option value="">Todos</option><option value="pending" @selected(($filters['status']??'')==='pending')>En validación</option><option value="released" @selected(($filters['status']??'')==='released')>Liberada</option></select>
<button type="submit">Filtrar</button>
<a href="{{ route('traceability.demo.index') }}" style="margin-left:12px">Restablecer</a>
</form>
<div class="scroller"><table>
<thead><tr><th>Caja</th><th>Número de parte</th><th>Serie</th><th>Estado</th><th>Detalle</th></tr></thead>
<tbody>
@forelse($boxes as $box)
<tr><td>{{ $box->code }}</td><td>{{ $box->part_number }}</td><td>{{ $box->serial_number }}</td><td><span class="tag">{{ $box->status==='released' ? 'Liberada' : 'En validación' }}</span></td><td><a href="{{ route('traceability.demo.show',$box) }}">Ver trazabilidad</a></td></tr>
@empty
<tr><td colspan="5">No hay cajas con este filtro.</td></tr>
@endforelse
</tbody></table></div>
@if($boxes->hasPages())<div style="margin-top:18px">{{ $boxes->links() }}</div>@endif
</section>
<footer><small>Esta vista muestra datos ficticios y no ejecuta escaneos ni liberaciones. Las operaciones reales requieren autenticación.</small></footer>
</main></body></html>
