# Restauración del escenario ficticio de Traceability

Comando nuevo: `portfolio:reset-demo`. No crea otra instalación ni un tenant.

Solo reconstruye cajas, escaneos y eventos de la planta TRACE-PUBLIC. Conserva usuarios, credenciales, membresías, perfiles por cliente y registros de auditoría de reglas. Si hay reglas personalizadas en esa planta, se niega a operar para evitar reconstruir ejemplos incompatibles.

1. Verifica un respaldo recuperable de MariaDB y asegúrate de que no haya visitantes usando el demo.
2. Desde la carpeta Laravel privada de Traceability, ejecuta `php artisan portfolio:reset-demo --dry-run`.
3. Revisa los registros afectados.
4. Ejecuta `php artisan portfolio:reset-demo` en SSH interactivo.
5. Confirma el respaldo y escribe exactamente `RESTABLECER TRACE-PUBLIC`.
6. Revisa /guia, las cajas y los reportes.

El proceso es transaccional; no se ejecuta automáticamente y no existe una ruta web. Los perfiles de cliente y los cambios de reglas no se restauran con este comando. Una actividad simultánea de visitantes puede interferir: usar una ventana sin visitantes. Este mecanismo está diseñado para una demo de portafolio, no para datos reales ni para ofrecer aislamiento multiempresa.
