# Validación y borrador de PR de Seguimiento al Estudiante

La rama de integración parte de `develop` e incorpora las correcciones preparadas para reemplazar el PR #9. Los resultados de la comprobación anterior se conservan como referencia.

## Borrador para GitHub

**Título**

feature(seguimiento-estudiante): integra los flujos iniciales de SSE-EPI

**Base**

develop

**Rama**

feature/seguimiento-estudiante-integracion

**Objetivo**

Integrar el código de SSE-EPI con la sesión y los permisos del portal. Las migraciones incorporan estructura y conservan los registros existentes. La entrega no requiere cargar cuentas, archivos ni datos históricos de SSE-EPI.

**Cambios clave**

- Identidad académica vinculada a la sesión del portal, acceso por permiso de Spatie y alcance de escuela, período y expediente.
- Tutoría con asignación y reasignación manual, solicitudes, agenda, atención, rectificaciones, compromisos, avisos y constancia personal.
- Cierre de asignaciones de Tutoría con causa vigente y reemplazo cuando corresponda. Coordinación registra el catálogo con evidencia de aprobación.
- Secretaría con prevalidación, validación de referencias, lotes versionados y confirmación para futura entrega.
- Consulta del perfil de ingreso y resultados publicados de Nivelación. Documentos PDF y materiales privados con autorización por inscripción o ámbito de gestión.
- Presentación y revisión del aporte tutorial conservando versiones y la separación del borrador privado. Registro, consulta y retiro del PDF del plan general F4 previamente aprobado.
- Consulta de alertas y situación académica existentes. Cola del portal para recordatorios de citas.

**Cómo probar**

Preparar una base PostgreSQL 17 descartable y una configuración del portal que apunte a esa base. Instalar dependencias con `composer install` y compilar los recursos con `npm ci` y `npm run build`. Exportar `DB_CONNECTION=pgsql` y las variables de la base de pruebas antes de ejecutar los siguientes comandos.

```bash
php artisan module:migrate all
php artisan module:migrate:status all
vendor/bin/phpunit tests/Unit/ModuleStructureTest.php tests/Unit/ModuleSchemaIsolationTest.php tests/Feature/ModularRouteTest.php tests/Feature/Database/ModularDatabaseStructureTest.php tests/Feature/SeguimientoEstudiante
```

Las pruebas crean sus propias cuentas y datos sintéticos. Verifican el acceso al expediente, la agenda y el cierre de Tutoría, la validación de lotes según el corte, las descargas privadas y los recordatorios.

**Verificación del 20 de noviembre en un entorno descartable**

| Comprobación | Resultado |
| --- | --- |
| PostgreSQL 17.12 con instalación modular y pruebas de aislamiento | 113 pruebas y 693 aserciones |
| Sesión, navegación y perfil del portal sobre una segunda base PostgreSQL 17.12 | 29 pruebas y 90 aserciones |
| Instalación limpia y actualización desde develop | El smoke SQL pasó. La actualización conservó una solicitud sintética preexistente. |
| Imagen del portal con PHP 8.4.28 y Apache | Sesión HTTP, expediente propio, descarga PDF, rechazo de un recurso fuera de alcance y carga del JavaScript compilado. |
| Envíos y trabajadores simultáneos | Dos validaciones del mismo lote devolvieron una revisión. Dos trabajadores dejaron un solo aviso y ningún trabajo fallido. |
| Verificación estática | Composer, Pint del módulo, compilación de Vite y git diff --check. |
| Seguimiento después de integrar F4 | 18 pruebas funcionales y 461 aserciones en PostgreSQL 18.6 aislado. Se verificaron versiones, reintentos, acceso por ámbito y retiro sin perder el PDF. |
| Seguimiento después de integrar el cierre de asignaciones | 19 pruebas funcionales y 496 aserciones en PostgreSQL 18.6 aislado. Se verificaron permisos, ámbito, aprobación de catálogo, cierre con o sin relevo y reintentos. |

Se usaron contenedores aislados de Podman sin publicar puertos y con sockets locales descartables. El paquete de ejecución excluye el almacenamiento privado y los archivos generados por pruebas. El caso de una cuenta con expedientes propios en dos escuelas verifica el perfil seleccionado en Nivelación y la constancia que corresponde a la solicitud.

**Impacto/Riesgos**

- Aplicar las 23 migraciones nuevas de Seguimiento y la migración de vistas de Matrículas con `php artisan module:migrate all`. Seguimiento queda con 39 tablas. Provisionar la habilitación académica de los tutores vigentes por escuela y período antes de abrir el acceso.
- Provisionar vínculos y permisos de Spatie por persona y sus ámbitos académicos con los responsables del portal.
- Respaldar y persistir el almacenamiento privado de Nivelación. Sus adjuntos usan el catálogo institucional. Programar el planificador del portal y un trabajador que consuma la cola seguimiento-estudiante.

### Comprobaciones de despliegue y recuperación

El índice nuevo rechaza bases con varias tutorías en estado `Activa` para el mismo estudiante y período. Esta consulta permite revisar los episodios con Coordinación antes de desplegar. El índice considera el estado almacenado, incluidos los episodios que ya vencieron y siguen figurando como `Activa`.

```sql
SELECT id_estudiante, id_periodo, array_agg(id_asignacion ORDER BY id_asignacion) AS episodios
FROM seguimiento_estudiante.tutor_asignacion
WHERE tipo = 'Tutoria' AND estado = 'Activa'
GROUP BY id_estudiante, id_periodo
HAVING count(*) > 1;
```

Si se requiere recuperar la aplicación, probar primero la versión anterior con el esquema ampliado. Para retroceder el esquema hasta antes de la migración `2026_11_20_000015`, comprobar el historial **antes de iniciar cualquier reversión**:

```sql
SELECT id_estudiante, id_docente, id_periodo, tipo, count(*) AS episodios
FROM seguimiento_estudiante.tutor_asignacion
GROUP BY id_estudiante, id_docente, id_periodo, tipo
HAVING count(*) > 1;
```

Si hay episodios repetidos, la unicidad anterior no los admite. La recuperación del esquema requiere un plan que conserve asignaciones, atenciones vinculadas y las escrituras posteriores al respaldo.

**Límites de la entrega**

- Secretaría confirma el lote dentro de Seguimiento. Queda por acordar con Gestión de Matrículas la entrega, aplicación oficial y recuperación de lotes. Las vistas de lectura y la regla de corte por retiro se presentan para revisión del equipo de Matrículas.
- Consejería y Nivelación conservan los recorridos parciales registrados en el plan. El informe final se genera a partir de un cierre publicado. La aprobación y publicación operativa del cierre pertenece al flujo académico pendiente.
- La asignación automática, los indicadores I1–I8, la continuidad y el cierre del proceso académico siguen pendientes.

Consulta [el estado de las fases 5 y 6](README.md#5-incorporar-los-procesos-por-entregas) y [los permisos y contratos](DATOS_Y_ACCESOS.md#contratos-con-otros-módulos).
