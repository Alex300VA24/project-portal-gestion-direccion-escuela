# Base de datos del módulo Seguimiento al Egresado

Esquema `seguimiento_egresado`. Este documento explica qué hacen las migraciones propias del módulo, por qué existen y cómo comprobarlas.

## Por qué hay migraciones nuevas

La migración `2026_11_04_040000_create_seguimiento_egresado_tables` creó una **primera versión** del esquema (40 tablas, según `esquemas_y_origen_de_tablas.pdf`). El módulo se diseñó sobre una versión posterior y definitiva del modelo (45 tablas y 10 vistas) que añade, entre otras cosas:

- invitaciones con propósito (`CONTACTO_INICIAL` o `SEGUIMIENTO`), estado, vencimiento, canal y control de envío;
- contacto del egresado con historial de versiones;
- campañas con finalidad, horizonte y periodo; cohortes con total de ingresantes;
- los campos de la medición laboral y de la encuesta (país, idiomas, autorización para contactar a la empresa);
- brechas y fortalezas de habilidades, evaluaciones por cohorte y periodo, y los campos de mejoras y planes de acción;
- catálogos con `codigo` y `activo`, el padrón con sus importaciones, y los indicadores I1 a I6 como vistas.

La primera versión no tiene datos, y ningún otro módulo la usa (no hay claves foráneas entre esquemas de módulos). Por eso se reemplaza con migraciones **nuevas**; la anterior no se edita.

## Migraciones

Están en `app/Modules/SeguimientoEgresado/Database/Migrations/` y se aplican con `php artisan module:migrate seguimiento-egresado`.

| Migración | Qué hace |
|---|---|
| `2026_11_16_000100_replace_seguimiento_egresado_schema_with_final_model` | Elimina las 40 tablas de la primera versión y crea las 45 del modelo final, con sus restricciones, índices, 4 funciones de validación y los catálogos iniciales (249 países, estados, canales, habilidades...). |
| `2026_11_16_000200_create_seguimiento_egresado_views` | Crea las 10 vistas `vw_*` (indicadores I1 a I6, cobertura e historial de seguimiento). |

El SQL del modelo está en `Database/Sql/01_modelo_final.sql` y `02_vistas_indicadores.sql`, sin `BEGIN`/`COMMIT` porque Laravel abre la transacción.

Cada `down()` deshace su paso. El de la primera migración vuelve a crear las 40 tablas originales ejecutando la migración anterior.

### Cuidados

- Las tablas se crean sin calificar y llegan al esquema por `SET LOCAL search_path`. Los cuerpos de las funciones nombran tablas sin calificar y se resuelven al ejecutarse, por lo que cada función lleva su propio `search_path` (`ALTER FUNCTION ... SET search_path`).
- Las claves foráneas del módulo solo apuntan a su esquema, a `core` y a `public`, como pide la regla de dependencias.
- El módulo mantiene su propio personal interno (`usuario_interno`), planes (`plan_curricular`), catálogo de países y archivos de evidencia. Se enlazan con el portal por el correo: la fila de `usuario_interno` de quien inicia sesión se busca por su email y se crea la primera vez. El esquema no lleva columnas fuera del modelo final. Reemplazarlos por tablas compartidas es trabajo posterior.

## Cambios en archivos compartidos

| Archivo | Cambio |
|---|---|
| `tests/Feature/Database/ModularDatabaseStructureTest.php` | El conteo de `seguimiento_egresado` pasa de 40 a 45. |
| `tests/Database/modular_database_smoke.sql` | Igual: 40 a 45. |
| `docs/base_de_datos.md` | Conteo y descripción del esquema. |
| `config/seguimiento-egresado.php` | Configuración propia del módulo (correo). |

## Cómo comprobarlo

```bash
php artisan module:migrate seguimiento-egresado       # aplica
php artisan module:migrate seguimiento-egresado       # segunda vez: "Nothing to migrate"
php artisan migrate:rollback --path=app/Modules/SeguimientoEgresado/Database/Migrations --step=2 --force
# revierte las dos nuevas (si son las últimas aplicadas): queda con 40 tablas y 0 vistas
php artisan module:migrate seguimiento-egresado       # las vuelve a aplicar: 45 tablas y 10 vistas
```

Sin `--step`, `migrate:rollback --path=...` revierte el último lote del módulo (con `module:migrate all`, las tres migraciones) y el esquema queda vacío.

```sql
select count(*) from information_schema.tables where table_schema = 'seguimiento_egresado' and table_type = 'BASE TABLE';  -- 45
select count(*) from information_schema.views  where table_schema = 'seguimiento_egresado';                               -- 10
```

Las pruebas `ModularDatabaseStructureTest` (conteos y claves foráneas entre esquemas) y las del módulo corren sobre este esquema.
