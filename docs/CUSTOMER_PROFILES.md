# Traceability — Perfiles de cliente por planta

Los perfiles permiten que una misma planta tenga configuraciones distintas para
programas o clientes **ficticios**. No convierten la aplicación en un sistema
multiempresa ni asignan permisos de usuario por cliente; la autorización continúa
basada en la planta.

## Función

- Un supervisor accede a `Reglas de validación → Perfiles de cliente` para
  registrar perfiles con código único dentro de su planta, nombre, escaneos
  obligatorios y prefijo de empaque.
- Al crear una caja, el operador selecciona **un perfil activo de su propia
  planta**, o deja el campo vacío para aplicar las reglas base de la planta.
- Las reglas quedan copiadas al campo `boxes.validation_rules` en el mismo
  momento de creación; **no se recalculan** si luego cambia el perfil.
- La referencia `boxes.customer_profile_id` permite reconocer el perfil que
  originó la caja. Una FK compuesta impide asociar un perfil de otra planta.
- Los perfiles desactivados dejan de estar disponibles para **nuevas cajas**;
  las cajas registradas anteriormente no se modifican.
- Para conservar el historial de reglas, esta versión evita editar perfiles
  existentes; se crea un nuevo código y se desactiva el antiguo.
- Operadores y viewers no pueden administrar perfiles. Los supervisores solo
  ven y administran las plantas que tienen asignadas.
- La API `POST /api/v1/boxes` admite el campo opcional
  `customer_profile_id`, sin cambiar el comportamiento por defecto.

## Alcance de reglas

Cada perfil soporta únicamente los tres tipos existentes:
`part_number`, `serial_number`, `package_label`, más un prefijo fijo de
1 a 16 caracteres `A-Z`, dígitos, guiones y guion bajo. También ofrece dos formatos de etiqueta de empaque permitidos: `colon` (`PREFIJO:CAJA:PARTE:SERIE`) y `pipe` (`PREFIJO|PARTE|SERIE|CAJA`). El formato anterior sigue siendo el predeterminado. Las cajas con `pipe` rechazan valores que contienen `|` para evitar lecturas ambiguas. No permite cargar código
arbitrario ni patrones libres. La configuración del formato se guarda en la copia inmutable de reglas de cada caja. Esta entrega no requiere migraciones nuevas ni compilación Vite.

## Seguridad y límites

Este cambio no implementa un catálogo global de clientes, acceso por cliente,
plantas compartidas entre empresas ni formatos de etiquetas completamente
parametrizables. La aplicación sigue siendo **multi-planta**, con perfiles
asociados a una planta. Los códigos internos y nombres deben ser inventados
para el portafolio; evita copiar reglas de negocio de terceros.

El formulario puede mostrar los perfiles de varias plantas autorizadas;
el backend comprueba estrictamente el par planta/perfil, así que una
selección incorrecta es rechazada.

## Publicación manual

Esta entrega incluye una nueva migración **aditiva**, cambios PHP, vistas
Blade y contrato OpenAPI. No hay cambios en CSS/JS de Vite.

Antes de instalar:
1. Exportar y verificar un respaldo de la base MariaDB exclusiva de Traceability.
2. Comprobar que el árbol Git de la aplicación esté limpio.
3. Actualizar `main` mediante `git pull --ff-only origin main`.
4. Ejecutar `php artisan migrate:status`, seguido de
   `php artisan migrate --force` con el binario de PHP 8.4 de Hostinger.
5. Ejecutar `php artisan optimize:clear`.

El respaldo es obligatorio: revertir esta migración elimina la asociación
de las cajas con perfiles y no es una recuperación inocua. No utilizar
`migrate:fresh`, `migrate:rollback` ni editar tablas a mano.

Probar con plantas y perfiles ficticios. Verificar:
- Un supervisor puede crear perfiles.
- El operador puede registrar una caja con un perfil de su planta.
- Un perfil de otra planta o desactivado no se puede utilizar.
- La caja creada conserva su configuración tras desactivar el perfil.
- Una caja sin perfil sigue usando las reglas base.
- Los visitantes sin sesión y los operadores no pueden administrar perfiles.
- La demo pública sigue siendo de solo lectura.
