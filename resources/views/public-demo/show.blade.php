<!doctype html>
<html lang="es" data-bs-theme="dark">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>{{ $box->code }} · PlantCore Traceability</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color-scheme:dark}
body{margin:0;background:#171c24;color:#edf1f7}
main{max-width:950px;margin:auto;padding:36px 20px 64px}
a{color:#a9caff}p,small{color:#aeb9c8}
.panel{background:#222b36;border:1px solid #3b495a;border-radius:12px;padding:22px;margin:20px 0}
h1{margin-bottom:6px;overflow-wrap:anywhere}
h2{font-size:1.15rem}
table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:13px;border-bottom:1px solid #3b495a}th{font-size:.85rem;color:#b5c0cf}.scroller{overflow-x:auto}
.ok{color:#86efac}.bad{color:#fca5a5}
</style>
</head>
<body><main>
<a href="{{ route('traceability.demo.index') }}">&larr; Volver a cajas</a>
<h1>{{ $box->code }}</h1>
<p>{{ $box->part_number }} · {{ $box->serial_number }} · {{ $box->status==='released' ? 'Liberada' : 'En validación' }}</p>
<section class="panel"><h2>Historial de escaneos</h2><div class="scroller"><table>
<thead><tr><th>Fecha</th><th>Tipo</th><th>Lectura</th><th>Resultado</th></tr></thead>
<tbody>@forelse($box->scans as $scan)
<tr><td>{{ $scan->created_at?->format('d/m/Y H:i') }}</td><td>{{ match($scan->scan_type) {'part_number'=>'Número de parte','serial_number'=>'Número de serie','package_label'=>'Etiqueta de empaque',default=>$scan->scan_type} }}</td>
<td>{{ $scan->scanned_value }}</td><td class="{{ $scan->accepted?'ok':'bad' }}">{{ $scan->accepted?'Aceptada':'Rechazada' }}</td></tr>
@empty<tr><td colspan="4">Sin escaneos registrados.</td></tr>@endforelse
</tbody></table></div></section>
<section class="panel"><h2>Bitácora de eventos</h2><div class="scroller"><table>
<thead><tr><th>Fecha</th><th>Evento</th><th>Detalle</th></tr></thead>
<tbody>@forelse($box->events as $event)
<tr><td>{{ $event->created_at?->format('d/m/Y H:i') }}</td>
<td>{{ match($event->event_type) {'created'=>'Caja registrada','release_blocked'=>'Liberación bloqueada','released'=>'Liberación autorizada',default=>$event->event_type} }}</td>
<td>
@if($event->event_type==='release_blocked')
Faltan validaciones: {{ implode(', ', $event->details['pending_scans'] ?? []) }}
@elseif($event->event_type==='released')Validaciones completas
@elseRegistro inicial de la caja@endif
</td></tr>
@empty<tr><td colspan="3">Sin eventos.</td></tr>@endforelse
</tbody></table></div></section>
<small>Datos ficticios; consulta de solo lectura. Ninguna acción de escaneo o liberación está habilitada aquí.</small>
</main></body></html>
