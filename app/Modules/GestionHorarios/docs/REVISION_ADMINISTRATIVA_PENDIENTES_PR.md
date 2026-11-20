# Decisiones pendientes de revisión institucional — Gestión de Horarios

**Rama de trabajo:** `feature/gestion-horarios-integracion`
**Estado:** documento para evaluación de los responsables de Seguridad, Core e infraestructura. **No constituye una aprobación para desplegar ni para habilitar generación institucional.**

## 1. Decisión pendiente: `RegistroPolicy.php`

**Archivo:** `app/Modules/GestionHorarios/Policies/RegistroPolicy.php`.

En la versión de referencia de `develop`, `RegistroPolicy` hereda el método `viewAny()` de `App\Shared\Policies\ModulePolicy` y declara el permiso `gestion-horarios.view`. La clase base comprueba `$user->can($this->permission)`.

En la copia local, una modificación manual sustituye ese criterio por:

```php
public function viewAny(User $user): bool
{
    return $user->hasAnyRole([
        'director_escuela',
        'secretaria',
    ]);
}
```

También define `gestionar(User $user)` delegando a `viewAny()`. La importación de `EscuelaAutorizada` está presente, pero no se utiliza en la política.

**Diferencia e impacto:** con la implementación local, una persona con uno de esos roles puede superar `viewAny()` aun sin el permiso Spatie `gestion-horarios.view`. La política original exigía el permiso. Los controles adicionales de ámbito por escuela y los contratos de gestión del módulo pueden denegar operaciones posteriormente; **esto no debe interpretarse como una garantía de que el cambio de política sea equivalente o seguro en todas las rutas**.

### Se solicita al equipo administrador / Seguridad

1. Definir oficialmente si la consulta administrativa requiere **rol**, **permiso `gestion-horarios.view`**, o **ambos**.
2. Especificar qué servicio institucional acredita la adscripción a escuelas y cómo se comprueban su revocación, errores y falta de respuesta (denegación por defecto).
3. Determinar un permiso de **gestión/escritura** distinto del permiso de consulta, si corresponde, e indicar quién lo asigna y revoca.
4. Aprobar el comportamiento esperado para un usuario con rol pero sin permiso, y para otro con permiso pero sin adscripción vigente. Añadir pruebas de regresión para ambos casos.
5. Decidir si la modificación manual de `RegistroPolicy.php` debe formar parte del PR. **No incorporarla por inercia ni cambiarla sin la revisión del propietario.**

**Medida vigente:** creación y guardado de horarios institucionales permanecen bloqueados; los contratos externos no disponibles deben continuar denegando acceso. No registrar proveedores ficticios para producción.

## 2. Controlador, rutas públicas y solicitud de escritura

- `HorarioController::create()` y `::store()` responden `403`: la creación institucional todavía no está habilitada.
- `HorarioRequest::authorize()` devuelve `false`, por lo que también se rechaza la solicitud de guardado antes de alcanzar el controlador.
- `HorarioController::publicados()` expone una respuesta JSON pública limitada a `id_horario`, `id_periodo`, `ambito`, `version` y `publicado_en`, filtrando `estado = PUBLICADO` y `publicado_en` no nulo.

**Revisión solicitada:** confirmar con Seguridad y el propietario del portal que esos metadatos publicados son realmente públicos, que la ruta y el contrato de respuesta son los previstos, y que ninguna operación administrativa utiliza el endpoint público para consultar información restringida. No se afirma aquí que exista una fuga de datos; se pide validar su contrato.

## 3. Migraciones y prueba estructural: discrepancia por resolver

La prueba `tests/Feature/GestionHorariosDatabaseTest.php` pasó de exigir **15 a 17 tablas**, incorporando únicamente `demanda_instantanea` y `demanda_curso`.

Sin embargo, las migraciones nuevas del módulo definen:

| Migración | Tabla(s) nuevas |
| --- | --- |
| `2026_11_16_000001_create_demanda_academica_tables.php` | `demanda_instantanea`, `demanda_curso` |
| `2026_11_16_000002_create_revision_oferta_table.php` | `revision_oferta_pendiente` |
| `2026_11_17_000003_create_oferta_seccion_table.php` | `oferta_seccion` |
| `2026_11_17_000004_create_ejecucion_cpsat_prueba.php` | `ejecucion_cpsat_prueba` |

Si se aplican las cuatro migraciones sobre el esquema original de 15 tablas, el total esperado sería **20**, no 17. La configuración modular disponible marca `gestion-horarios` como migrable, por lo que es necesario verificar qué migraciones se ejecutan realmente en el flujo institucional.

**Decisiones solicitadas:**

- Confirmar el inventario final de tablas y actualizar la prueba estructural conforme al esquema aprobado, sin alterar los resultados esperados simplemente para forzar un `PASS`.
- Evaluar si `ejecucion_cpsat_prueba` (orientada a escenarios ficticios) debe desplegarse como migración institucional o mantenerse exclusivamente en entornos de prueba.
- Coordinar la aplicación de las demás migraciones con el responsable de Core y el procedimiento de despliegue. **No aplicarlas a la base institucional para resolver esta discrepancia.**

## 4. Cambios compartidos en infraestructura

- `composer.json` incorpora `dompdf/dompdf` y `phpoffice/phpspreadsheet`; el `composer.lock` debe corresponder exactamente a esas dependencias.
- `Dockerfile` incorpora `gd` y `zip` en las etapas de dependencias y ejecución para las exportaciones PDF/XLSX.
- El script compartido `composer run dev` deja de ejecutar Laravel Pail por su dependencia de `pcntl` en Windows. El responsable del portal debe decidir si este cambio global es adecuado o si conviene separar un script de desarrollo para Windows.

Las pruebas locales de exportación pasaron, pero el cambio de imagen y dependencias del portal requiere revisión y despliegue coordinado; una prueba satisfactoria en Windows no demuestra por sí sola el funcionamiento de la imagen institucional desplegada.

## 5. Evidencia y límites de las pruebas

- `PersistenciaPostgresTest`: 5 pruebas, 23 aserciones, aprobadas en PostgreSQL 17 aislado.
- `InstantaneaPostgresTest`: 3 pruebas, 12 aserciones, aprobadas en PostgreSQL 17 aislado.
- Se han ejecutado satisfactoriamente 65 de las 66 pruebas que se omitían en la primera suite por requerir entornos o contratos específicos.
- La prueba de descarga en `HorariosRegistradosTest` sigue omitida por falta de un contrato institucional de adscripción. **No debe desbloquearse artificialmente.**
- Las pruebas aisladas no sustituyen la verificación de permisos y perfiles reales, integración con Prematrícula y Core, revisión visual, ni una regresión final sobre el conjunto exacto de archivos candidato al PR.

## 6. Decisiones para cerrar antes del PR

| Responsable sugerido | Decisión requerida | Estado |
| --- | --- | --- |
| Seguridad / administrador de roles | Contrato definitivo de roles, permiso y escuela para `RegistroPolicy` | Pendiente |
| Core / responsable de base de datos | Esquema final, migraciones y conteo de tablas | Pendiente |
| Infraestructura / coordinador del portal | Aprobar dependencias Composer, extensiones Docker y script de desarrollo | Pendiente |
| Equipo de Gestión de Horarios | Validar respuesta pública, pruebas y funcionamiento visual | Pendiente |

**Alcance de este documento:** registrar decisiones técnicas pendientes, no modificar la política local, no conceder nuevos permisos ni ejecutar operaciones sobre bases institucionales. El commit, push y PR quedan a cargo del desarrollador después de la revisión correspondiente.

## 7. Comprobaciones de la revisión final

`ModuleMigrateCommand` solicita a `ModularMigrationRegistry` los grupos de infraestructura, shared y el directorio completo del módulo configurado en `config/modules.php`. Gestión de Horarios está habilitado para migraciones; por tanto, si las cuatro migraciones nuevas permanecen en ese directorio y se ejecuta el flujo modular correspondiente, el migrador las contempla, incluida `ejecucion_cpsat_prueba`. No se ejecutó ese flujo sobre una base institucional. Las 15 tablas originales más las cinco creadas por esas cuatro migraciones dan 20 en una instalación limpia que las aplique todas; la prueba específica espera 17 y la estructural compartida espera 15. El esquema definitivo y el destino de la migración ficticia siguen pendientes de aprobación.

`composer validate --no-check-publish` y `composer check-platform-reqs --no-dev` pasaron en el PHP local 8.5.7. Sin embargo, el manifiesto declara `php: ^8.3` y el lock contiene paquetes Symfony que requieren **PHP >= 8.4.1**; `composer prohibits php 8.3.0 --locked` y `composer prohibits php 8.4.0 --locked` los identifican. La imagen usa `php:8.4`, pero no se reconstruyó aquí ni se verificó su versión de parche. Infraestructura debe coordinar el mínimo admitido, el lock, la imagen y el script de desarrollo que dejó de iniciar Pail. La imagen de aplicación inspeccionada tampoco instala Python u OR-Tools; la generación institucional continúa deshabilitada y su entorno futuro requiere diseño y aprobación.

El contrato `ProveedorPermisoGestionAcademica` no tiene implementación institucional registrada. Las escrituras de oferta exigen autorización de consulta, ese contrato y escuela autorizada; el permiso o la escuela ausentes deniegan. La doble comprobación de revocación no acredita atomicidad hasta el commit. El endpoint de publicados sigue limitado a metadatos y requiere validación institucional de su carácter público. La revisión visual y la selección individual definitiva de archivos para el PR siguen pendientes.

En esta intervención se comprobó la sintaxis de 177 archivos PHP sin errores y una regresión seleccionada de 116 pruebas, 752 aserciones y una omitida (`HorariosRegistradosTest`, por el contrato de adscripción). Las pruebas incluidas del motor utilizaron Python 3.14.1 y OR-Tools 9.15.6755. Los ensayos PostgreSQL de 65 pruebas comunicados anteriormente son evidencia histórica: no se repitieron aquí porque los puertos de prueba estaban ocupados por contenedores preexistentes y no se acreditaron bases vacías. `git diff --check` pasó; no cubre archivos sin seguimiento. Pint del módulo señaló formato en las 16 migraciones antiguas y la policy manual; no se modificaron esos archivos.
