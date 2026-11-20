# Estado de Gestión de Horarios para Draft PR

**Rama:** `feature/gestion-horarios-integracion`
**Objeto de revisión:** consultas protegidas y preparación académica parcial dentro del portal institucional.

## 1. Estado funcional

| Área | Estado verificado | Limitación |
| --- | --- | --- |
| Navegación y consultas académicas | Implementadas con controles de rol, policy y servicios de ámbito. | Sin adscripción institucional verificable las funciones protegidas quedan cerradas. |
| Oferta y asociación a secciones | Escritura y reglas de rechazo ensayadas con proveedores ficticios y PostgreSQL aislado. | Falta proveedor oficial de permiso de gestión y escuela. |
| Periodos, secciones, asignaciones, disponibilidad y ambientes | Lecturas y diagnósticos parciales. | Identidades y recursos compartidos necesitan contratos. |
| Demanda de Prematrícula | Validación desacoplada y persistencia versionada probadas. | No existe sincronización automática institucional aprobada. |
| Preparación | Instantánea y prevalidación parcial; datos incompletos no ejecutables. | Falta mapeo y aprobación de reglas y fuentes completas. |
| CP-SAT | Integración PHP/Python y cola con datos ficticios. | Generación institucional deshabilitada; entorno productivo sin Python/OR-Tools configurados. |
| PDF/XLSX | Exportadores PHP implementados y probados internamente con datos ficticios. | Descarga institucional restringida; revisión visual densa e impresión Excel pendientes. |
| Creación, aprobación y publicación | No habilitadas. | Deben mantenerse bloqueadas hasta contar con contratos y validación institucional. |

## 2. Evidencia de pruebas

- Revisión de la etapa 28: **177 archivos PHP sin errores de sintaxis**.
- Regresión seleccionada: **116 pruebas, 752 aserciones; 115 aprobadas y una omitida** por falta del contrato de adscripción para una descarga.
- Con anterioridad se ejecutaron por separado **65 de las 66 pruebas inicialmente omitidas**, usando entornos PostgreSQL 17 y Python aislados. Estos resultados son evidencia histórica: **no se repitieron en la etapa 28**, porque no se acreditó que las bases desechables estuvieran vacías.
- `git diff --check` no reportó problemas en los archivos seguidos; **no valida los archivos nuevos sin seguimiento**.
- Las pruebas técnicas no sustituyen revisión visual ni validación con identidades reales.

### Verificaciones posteriores comunicadas (no agregadas a la regresión de etapa 28)

- Portada, Livewire y seguridad: **17 pruebas, 135 aserciones aprobadas**.
- Suite unitaria de Gestión de Horarios: **52 pruebas, 238 aserciones aprobadas**.
- Preparación institucional con PostgreSQL 17 temporal y aislado: **1 prueba, 24 aserciones aprobadas**.
- Transformador y validaciones con particiones explícitas de laboratorio: **49 pruebas, 374 aserciones aprobadas**; después se añadieron dos aserciones y se repitió la prueba específica con **14 aserciones**.

Son resultados de intervenciones distintas, comunicados previamente; **no se suman** porque pueden compartir casos y no equivalen a una nueva ejecución integral sobre el estado final del PR.

## 3. Decisiones pendientes

1. **Seguridad:** `RegistroPolicy` local autoriza por roles y `develop` usa `gestion-horarios.view`; acordar rol, permiso, escuela, vigencia y revocación antes de integrar.
2. **Core y base de datos:** cuatro migraciones nuevas definen cinco tablas; una instalación que las aplique todas tendría potencialmente 20 tablas desde la base original de 15. Las expectativas estructurales revisadas siguen siendo 17 y 15.
3. **Infraestructura:** confirmar soporte de PHP de al menos 8.4.1 para el lock actual, extensiones `gd`/`zip`, dependencias PHP y cambio del script compartido `dev`. La etiqueta `php:8.4` no fija la versión de parche.
4. **Integración:** contrato oficial de Prematrícula, ámbito de recursos compartidos, identidad docente y proceso de despliegue.
5. **Interfaz:** revisión real de escritorio, tableta y móvil, PDF denso y vista de impresión Excel. Pendiente.
6. **PR:** selección individual de archivos, pruebas contra el esquema aprobado y aprobación del propietario para cambios compartidos.

Véanse [Decisiones administrativas](REVISION_ADMINISTRATIVA_PENDIENTES_PR.md), [Migraciones](MIGRACIONES_REVISION_PR.md) y [Manual de flujos](MANUAL_FLUJOS_GESTION_HORARIOS.md).

## 4. Declaración de alcance

Este paquete respalda un **Draft PR para recibir observaciones**, no un PR listo para fusionar ni un despliegue institucional. No debe habilitarse generación, introducir proveedores ficticios, aplicar migraciones sobre la base compartida ni interpretar casos aislados como pruebas de producción.

La modificación manual de `RegistroPolicy` requiere decisión expresa; este documento no la cambia. El commit, push y PR serán gestionados manualmente por el desarrollador una vez revisado el inventario.

## Consulta institucional: alcance actual

Se agregó una consulta autenticada de actividades publicadas por ciclo, ambiente o docente. La generación institucional sigue deshabilitada. Seguridad y Core deben confirmar identidad, adscripción y fuentes; Gestión de Matrículas debe confirmar el contrato de matrícula vigente para el horario personal.

## Fuentes verificadas en código para la integración institucional

| Fuente | Código o tabla existente | Contrato y estado |
| --- | --- | --- |
| Usuario autenticado | `config/auth.php`: guard web, `App\Models\User` por defecto; `App\Shared\Models\Seguridad\Usuario` representa `seguridad.usuario` y tiene `id_persona`. | Seguridad debe acreditar el vínculo del usuario del guard con el usuario/persona institucional; no se infiere por nombre ni correo. |
| Escuela autorizada | `core.plan_estudios.id_escuela`; `ProveedorEscuelasAutorizadas::escuelasVigentes(User): array`. | Seguridad/Core deben proveer adscripciones vigentes y revocación; sin proveedor se deniega. |
| Docente | `core.docente.id_persona`; `ProveedorIdentidadDocente::docenteId(User): ?int`; asignaciones en `gestion_horarios.asignacion_docente`. | Seguridad/Core deben acreditar el vínculo del guard con persona y docente; no hay proveedor institucional registrado. |
| Periodo, currículo y oferta | `core.periodo_academico`, `core.curso_plan`, `core.curso`, `core.seccion`, `gestion_horarios.oferta_curso` y `oferta_seccion`. | `FuentesAcademicasAutorizadas` lee parcialmente con escuela/plan verificados; no entrega entrada ejecutable. |
| Disponibilidad, ambientes y restricciones | `gestion_horarios.declaracion_disponibilidad`, `disponibilidad_docente`, `bloque_horario`, `ambiente_bloqueo`, `restriccion`, `detalle_restriccion`; `core.ambiente`. | Responsables académicos y Core deben acreditar alcance compartido, calendario y semántica/versiones de restricciones. La instantánea actual lee recursos globales. |
| Demanda y matrícula | `prematricula.solicitud_prematricula` y `detalle_solicitud`; `gestion_matriculas.matricula` y `matricula_detalle` tienen identificadores de estudiante, periodo y sección. | Prematrícula y Gestión de Matrículas deben acordar servicios autorizados, estados vigentes, versiones y acceso; solicitud preferida no equivale a matrícula. |
| Versionado y generación | `InstantaneaGeneracion` captura con REPEATABLE READ READ ONLY y SHA-256; `AdaptadorPreparacion::paraMotor` convierte un perfil de teoría, práctica y laboratorio con particiones explícitas ensayadas; `ProveedorComplementosAcademicos::obtener(string $periodo, string $huella): array`. | Faltan proveedor institucional versionado para población, particiones, sesiones, calendario y reglas; coordinación de escritores para evitar cambios entre comprobación y commit; Queue y protección existentes están limitados a pruebas. La huella no sustituye este protocolo. |

Las tablas y campos indicados se verificaron en migraciones y código, no mediante consultas a la base institucional. Su despliegue y contenidos reales requieren confirmación.

El diagnóstico de complementos comprueba presencia y vinculación de periodo y huella. No valida el contenido, la procedencia ni la consistencia transaccional. `paraMotor()` admite múltiples secciones y actividades de teoría, práctica y laboratorio con particiones explícitas en una escuela y un plan, sin demanda, horarios existentes, bloqueos, sesiones compartidas ni restricciones adicionales. La autorización y la vigencia institucionales siguen pendientes.

## Preparación institucional parcial

`PreparacionInstitucional::preparar(User, escuela, periodo, plan)` exige el permiso de gestión y una adscripción verificada; utiliza `FuentesAcademicasAutorizadas` y, cuando existe alcance de recursos, `ConsultaRecursosAcademicosAcotados`. `CapturaAcademicaAcotadaRepository` lee la proyección académica acotada en una única transacción PostgreSQL `REPEATABLE READ READ ONLY`; su huella cubre esa proyección y sus diagnósticos. Las versiones de las fuentes figuran como desconocidas cuando no existe una versión acreditada. Vuelve a comprobar permiso y alcance antes de devolver datos. La preparación siempre devuelve `ejecutable: false` y no entrega entrada al motor: la lectura de recursos es separada y la autorización de identidad no acredita visibilidad de disponibilidad u ocupación. La doble comprobación no garantiza que una autorización no haya cambiado entre comprobaciones ni protege el commit de una futura propuesta.

| Contrato requerido | Punto de integración | Responsable y bloqueo |
| --- | --- | --- |
| Permiso de gestión | `ProveedorPermisoGestionAcademica::puedeGestionar(User): bool` | Seguridad debe confirmar permiso, vigencia y revocación. |
| Adscripción por escuela | `ProveedorEscuelasAutorizadas::escuelasVigentes(User): array` | Seguridad/Core deben vincular el usuario del guard con las escuelas vigentes; el rol por sí solo no lo acredita. |
| Identidad de recursos compartidos | `ProveedorAlcanceRecursosAcademicos::recursosVisibles(User, int, int): array` | Seguridad/Core/Recursos Académicos deben acreditar docentes y ambientes visibles. No concede acceso a ocupación ni disponibilidad. |
| Captura completa acotada | Pendiente: captura en la misma conexión PostgreSQL y ámbito escuela/plan/periodo, incluyendo disponibilidad, ocupación y restricciones autorizadas. | Core/Seguridad/Recursos Académicos deben aprobar alcance, versiones y protocolo de lectura; `InstantaneaGeneracionRepository::leer()` consulta recursos globales y no se usa en este servicio. |
| Complementos y población | `ProveedorComplementosAcademicos::obtener(string, string): array` y `ProveedorComplementosTransaccional::conexion()` | Responsables académicos y Matrículas deben acreditar padrones, grupos, calendario, patrones, sesiones y versiones; Prematrícula debe confirmar el contrato de demanda. |
| Vigencia y escritura | Pendiente: protocolo cooperativo de versión/bloqueo coordinado con todos los escritores. | Core, Seguridad y módulos propietarios deben aprobar protección entre captura, validación y commit. SHA-256 no evita por sí solo cambios concurrentes. |

La preparación parcial se probó en PostgreSQL 17 desechable con datos y proveedores de prueba; una segunda conexión confirmó un cambio durante la lectura y la siguiente captura produjo otra huella. No se verificaron versiones de fuentes externas ni existe un proveedor institucional aprobado o un recorrido institucional ejecutable conectado a esta clase.

## Navegación temporal por rol sin adscripción

La portada diferencia las cuentas `director_escuela`, `secretaria`, `profesor` y `alumno`. Dirección y secretaría pueden confirmar el ingreso administrativo cuando la sesión y el rol son válidos; el permiso `gestion-horarios.view` se comprobó en el entorno local, pero la falta de `ProveedorEscuelasAutorizadas` sigue impidiendo las consultas privadas por escuela. El profesor puede confirmar el acceso a su espacio con rol `profesor`, pero, sin `ProveedorIdentidadDocente`, no obtiene datos personales de asignaciones o disponibilidad. El alumno no accede a la ruta docente. No se habilitaron escrituras, generación ni publicaciones. Véase [Acceso por rol sin adscripción](ACCESO_POR_ROL_SIN_ADSCRIPCION.md).
