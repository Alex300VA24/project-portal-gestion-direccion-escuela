# Arquitectura actual — Gestión de Horarios Académicos

## 1. Alcance y estado

Módulo Laravel 13 con vistas Blade, componentes Livewire, servicios y repositorios PHP; persistencia PostgreSQL. Integra **consultas y preparación académica parcial**. La generación, aprobación y publicación institucional de horarios permanecen **deshabilitadas**. La ejecución CP-SAT con Python/OR-Tools está validada únicamente para escenarios ficticios controlados.

No incorpora un catálogo académico ni una identidad institucional propia: consume entidades de Core y requiere contratos oficiales de Seguridad, recursos compartidos y Prematrícula.

## 2. Componentes

| Capa | Responsabilidad | Ejemplos de ubicación |
| --- | --- | --- |
| Rutas y controlador | Entrada pública y administrativa, bloqueo de operaciones no habilitadas. | `Routes/web.php`, `Routes/api.php`, `Controllers/HorarioController.php`. |
| Interfaz | Selección de periodo/plan, filtros, resultados, advertencias, navegación y descargas sujetas a autorización. | `Livewire/`, `resources/views/modules/gestion-horarios/`. |
| Servicios | Validación de ámbito, prevalidación, consulta, oferta, asociaciones y exportación. | `Services/`. |
| Repositorios | Consultas SQL/PgSQL y proyecciones de entidades académicas. | `Repositories/`. |
| Contratos | Límites entre módulos: escuelas, identidad docente, permisos de gestión, demanda y recursos. | `Contracts/`. |
| Demanda | Instantáneas agregadas, versiones, huellas, deduplicación y persistencia controlada. | `Demand/`. |
| Motor de prueba | Adaptación PHP, ejecución CP-SAT y validación independiente; trabajos ficticios. | `Engine/`, `Engine/python/`, `Jobs/`. |
| Migraciones | Tablas académicas propias, demanda y entidades incrementales sujetas a aprobación. | `Database/Migrations/`. |

## 3. Recorrido de una consulta administrativa

1. El usuario inicia sesión mediante el guard `web`; las rutas administrativas exigen roles del portal.
2. Los componentes Livewire comprueban la policy del módulo.
3. Los servicios determinan el ámbito de escuela/plan y las autorizaciones requeridas. **La policy por sí sola no acredita una escuela.**
4. El repositorio consulta exclusivamente las fuentes permitidas. Los recursos docentes y ambientes compartidos necesitan contratos específicos.
5. La interfaz muestra datos, un estado vacío verdadero o un error explícito; no convierte un fallo de proveedor en una lista sin registros.

Sin proveedor institucional aprobado, los servicios relevantes deben denegar el acceso por defecto. Las pruebas con proveedores ficticios no habilitan producción.

## 4. Funcionalidades y límites

| Funcionalidad | Implementación | Restricción |
| --- | --- | --- |
| Oferta académica | Consulta y escritura condicionada por permiso de gestión y escuela autorizada. | No hay proveedor institucional de gestión registrado. |
| Secciones y asociaciones | Consulta; asociación oferta–sección ensayada en PostgreSQL aislado. | Requiere identidad de curso/plan y escuela válidos; no asigna alumnos. |
| Asignación y disponibilidad | Consulta parcial y detección de inconsistencias. | Falta validar identidad docente y alcance global de recursos. |
| Periodos, ambientes y restricciones | Lecturas del modelo institucional con controles por ámbito. | No administran los catálogos centrales. |
| Preparación académica | Prevalidación de solo lectura y diagnósticos. | No implica factibilidad ni crea una solicitud ejecutable. |
| Horarios registrados | Consulta PHP de periodo, versión, ciclo, sección y sesiones simultáneas. | Descarga administrativa global denegada sin contrato de adscripción. |
| Exportación | Generación interna PDF con Dompdf y XLSX con PhpSpreadsheet, probada con datos ficticios. | Requiere permisos, `gd`/`zip` en el despliegue y revisión visual pendiente. |
| CP-SAT | Adaptador, validador y cola de prueba aislados. | Sin generación, aprobación o publicación institucional. |

La ruta pública de horarios publicados devuelve **solo metadatos** de registros con estado publicado. El propietario institucional debe confirmar que esta exposición es correcta; no existe una cuadrícula pública detallada habilitada. **Sí existe una consulta semanal autenticada** de actividades publicadas por ciclo/sección, ambiente o docente, con filtros de publicación y periodo aplicados en el servidor; no debe confundirse con la ruta pública.

## 5. Datos y consistencia

- **Core** gestiona periodos, planes, cursos, secciones, docentes y ambientes. Gestión de Horarios no debe inventar equivalencias de identificadores.
- **Seguridad** debe resolver usuario, rol vigente, permisos y escuelas autorizadas. `RegistroPolicy` tiene una modificación local no aprobada que difiere de `develop`; véase [Roles y permisos](ROLES_PERMISOS_MIDDLEWARE.md).
- **Prematrícula** aún no transmite automáticamente una instantánea completa institucional. Se ha preparado un contrato desacoplado y persistencia versionada; los totales son demanda agregada, no matrícula ni población por sección.
- **Persistencia** protege versiones y referencias de periodo mediante restricciones y bloqueos comprobados en pruebas. La protección completa hasta un commit institucional simultáneo con otros módulos requiere protocolo acordado.
- **Instantánea de preparación** utiliza lectura consistente en PostgreSQL cuando sus fuentes comparten conexión; no garantiza coherencia atómica con servicios externos.

## 6. Generación: límite de la integración

El flujo propuesto sería: datos institucionales y autorización → instantánea verificada → mapeo completo → reglas HARD/SOFT acordadas → ejecución CP-SAT → validación independiente → propuesta persistida bajo bloqueo y versión → revisión/aprobación/publicación autorizadas.

**Ese recorrido extremo a extremo no está habilitado.** La entrada institucional queda marcada como parcial o no ejecutable; los trabajos existentes aceptan escenarios de prueba y no modifican horarios oficiales. El Dockerfile inspeccionado no instala Python ni OR-Tools para una eventual ejecución institucional. Véase [Contrato CP-SAT](CONTRATO_GENERACION_CPSAT.md).

## 7. Migraciones y despliegue

El módulo está habilitado en el registro modular de migraciones. El esquema original contabiliza 15 tablas; cuatro migraciones nuevas definen cinco tablas más, por lo que **20 sería el total potencial** si todas se ejecutasen. Las pruebas estructurales revisadas esperan 17 y 15 en distintos puntos: discrepancia pendiente de aprobación. La migración `ejecucion_cpsat_prueba` merece una decisión explícita por su uso ficticio.

No aplicar migraciones nuevas a la base institucional ni reiniciar un contenedor compartido como paso de verificación. Véase [Migraciones para revisión](MIGRACIONES_REVISION_PR.md).

## 8. Calidad y documentación

Las pruebas unitarias, de funciones Livewire y de integración en PostgreSQL 17 aislado cubren contratos parciales, aislamiento, concurrencia, validación, exportación y motor ficticio. **No** acreditan disponibilidad institucional ni revisión visual. Consulte [Estado de revisión](ESTADO_REVISION_PR.md) y [Flujos de prueba](MANUAL_FLUJOS_GESTION_HORARIOS.md).

## Consulta institucional: alcance actual

La página autenticada de versiones publicadas reutiliza la consulta de metadatos del endpoint público. HorariosPublicadosRepository obtiene actividades por ciclo, ambiente o docente con filtro SQL de estado PUBLICADO, fecha de publicación y periodo de sección; no utiliza ConsultaHorarios, que también permite consultar estados no publicados. La visibilidad se limita a datos de las clases publicadas.
