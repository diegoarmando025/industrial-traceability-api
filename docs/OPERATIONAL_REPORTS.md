# Traceability — reportes operativos

Nueva pantalla autenticada `/reports/operations` con datos reales por planta autorizada:
cajas registradas, cajas liberadas, lecturas totales, lecturas rechazadas,
porcentaje de rechazo e intentos de liberación bloqueados. La tendencia
diaria muestra los mismos eventos agrupados por fecha. La exportación
`/reports/operations/export` entrega un CSV con los filtros aplicados.

- El usuario solo consulta sus plantas (sin membresía no ve datos).
- Un `plant_id` ajeno devuelve 404 y no exporta información.
- El rango está limitado a 90 días para evitar consultas excesivas en
  hosting compartido. Si no se indica, se usan los últimos 30 días.
- **Cajas registradas** cuentan por `boxes.created_at`.
- **Cajas liberadas** cuentan por `boxes.released_at` (pueden haberse creado antes).
- **Lecturas rechazadas** cuentan intentos, no cajas. La tasa de rechazo
  es lecturas rechazadas / lecturas totales, sin confundirla con scrap.
- **Liberaciones bloqueadas** cuentan los eventos `release_blocked`.
- La tabla de planta presenta totales acumulados del estado actual, no
  cifras limitadas al filtro de fechas; se indica expresamente en la vista.
- El CSV usa únicamente fechas y números agregados; incluye BOM UTF-8.

## Instalación con ZIP

No añade migraciones, dependencias ni recursos frontend. Sube únicamente
los archivos del ZIP incremental a la carpeta Laravel privada
`apps/traceability`, tras respaldar los archivos originales. No subas
nada a `public_html`. Ejecuta:

```bash
cd /home/u641948971/domains/devalejandre.com/apps/traceability
/opt/alt/php84/usr/bin/php artisan optimize:clear
```

Prueba `/reports/operations` y descarga un CSV desde una cuenta
demo; comprueba con tu cuenta propietaria que los datos están limitados
a las plantas asignadas. No ejecutes `git pull` tras extraer el ZIP
incremental porque deja cambios locales.
