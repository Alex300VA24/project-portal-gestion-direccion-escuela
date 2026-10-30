# Gestión de Horarios — Documentación de la base de datos

Módulo: `gestion_horarios` · Sistema Académico — Escuela Profesional de Informática, Universidad Nacional de Trujillo (UNT)
Monolito modular: **Portal Gestión Dirección** (Laravel 13, PostgreSQL 17/18).

---

## 1. Objetivo del módulo

Gestionar la construcción, validación, aprobación y publicación de los horarios académicos por período:

- Ofertar cursos y programar sus actividades (día y rango horario).
- Asignar docentes y conocer su disponibilidad declarada.
- Gestionar bloqueos de ambientes y catálogo de restricciones evaluables.
- Generar propuestas de horario por versión, validarlas técnicamente,
  registrar los conflictos detectados, revisarlas, aprobarlas y publicar
  una única versión vigente por período y ámbito.
- Registrar solicitudes de apertura de cursos de repetición con su
  trazabilidad de autorización.

Este documento describe **únicamente la capa de base de datos**. El
generador de horarios y la interfaz gráfica están pendientes (ver §10).

---

## 2. Inventario: las 15 tablas del esquema

| # | Tabla | Origen | Propósito breve |
|---|---|---|---|
| 1 | `gestion_horarios.oferta_curso` | Original (11) | Oferta de un curso en un período (regular o repetición). |
| 2 | `gestion_horarios.actividad_academica` | Original (11) | Sesión programable: tipo, día, `hora_inicio`/`hora_fin`. |
| 3 | `gestion_horarios.asignacion_docente` | Original (11) | Docente asignado a una actividad, con historial de estados. |
| 4 | `gestion_horarios.bloque_horario` | Original (11) | Bloques de la grilla horaria del período. |
| 5 | `gestion_horarios.disponibilidad_docente` | Original (11) | Bloques de tiempo disponibles de un docente. |
| 6 | `gestion_horarios.ambiente_bloqueo` | Original (11) | Bloqueos de aulas (fechas u horarios). |
| 7 | `gestion_horarios.horario` | Original (11) | Versión de horario por período y ámbito, con ciclo de vida. |
| 8 | `gestion_horarios.detalle_horario` | Original (11) | Actividades incorporadas a una versión. |
| 9 | `gestion_horarios.restriccion` | Original (11) | Catálogo de reglas evaluables (severidad DURA/BLANDA). |
| 10 | `gestion_horarios.detalle_restriccion` | Original (11) | Parámetros y ámbito de una restricción. |
| 11 | `gestion_horarios.horario_revision` | Original (11) | Revisión formal de una versión de horario. |
| 12 | `gestion_horarios.apertura_repeticion` | **Nueva (ampliación)** | Solicitud y autorización de apertura de repetición. |
| 13 | `gestion_horarios.declaracion_disponibilidad` | **Nueva (ampliación)** | Declaración formal de disponibilidad docente. |
| 14 | `gestion_horarios.validacion_horario` | **Nueva (ampliación)** | Cada ejecución de validación de una propuesta. |
| 15 | `gestion_horarios.conflicto_horario` | **Nueva (ampliación)** | Conflictos detectados en una validación. |

> **Advertencia de procedencia:** las tablas 1–11 corresponden al modelo
> modular original. El repositorio **no contiene** el archivo
> `base_datos_modular.sql`, por lo que sus definiciones se reconstruyeron
> a partir de la especificación disponible y del procedimiento Bizagi, y
> deben reconciliarse con el SQL modular definitivo cuando se incorpore
> (migración compatible, sin `DROP ... CASCADE`).
>
> Las tablas 12–15 son una **propuesta de ampliación derivada del
> análisis del proceso**, no formaban parte del modelo original de 11
> tablas. Su justificación está en §5.

### Dependencias compartidas (esquemas `core` y `seguridad`)

Tampoco existían los esquemas `core`, `seguridad` ni `comun` ni el archivo
modular, y las claves foráneas exigidas necesitan destino. Existe un
**bootstrap mínimo de uso local** (`database/shared/01_core_seguridad.sql`)
con las seis tablas compartidas mínimas. **No se publica en el repositorio
ni forma parte de este módulo**: es material provisional que sólo se aplica
a mano en bases desechables mientras el equipo define el modelo oficial
(30 de las 43 claves foráneas del módulo apuntan a `core`/`seguridad`).

- `core.periodo_academico`, `core.curso_plan`, `core.seccion`,
  `core.docente`, `core.ambiente`
- `seguridad.usuario` (extensión de `public.users`: su PK es FK a
  `public.users.id`, de modo que **no se duplican identidades**)

`comun` no se creó porque ninguna FK lo necesita. Este bootstrap es
provisional y debe sustituirse por el modelo modular definitivo.
No se modificó ninguna tabla de `core`, `seguridad`, `comun`, `public` ni
de los demás módulos.

---

## 3. Las 11 tablas originales — detalle

```text
oferta_curso ──< actividad_academica (vía core.seccion) ──< asignacion_docente
     │                    │
     │                    └──< detalle_horario >── horario ──< horario_revision
     │                                                  │
     └── (periodo, curso)                              └──< validacion_horario ──< conflicto_horario

bloque_horario ──< disponibilidad_docente >── declaracion_disponibilidad (nueva)
ambiente_bloqueo (ambiente × período)
restriccion ──< detalle_restriccion (ámbito GLOBAL/PERIODO/DOCENTE/AMBIENTE)
```

Puntos clave conservados del modelo original:

- `UNIQUE (id_periodo, id_curso_plan, tipo_oferta)` en `oferta_curso`.
- `UNIQUE (id_periodo, id_docente, dia_semana, id_bloque)` en
  `disponibilidad_docente`.
- `UNIQUE (id_horario, id_actividad)` en `detalle_horario`
  (**necesaria, no suficiente**, para evitar cruces: la superposición real
  se evalúa en la validación, que puede registrar propuestas inválidas).
- `UNIQUE (id_periodo, ambito, version)` en `horario`.
- `UNIQUE (id_horario, numero_revision)` en `horario_revision`.

---

## 4. Las 4 tablas nuevas — descripción

### 4.1 `apertura_repeticion`

Solicitudes de apertura de cursos de repetición: quién solicita, quién
autoriza, cuándo, con qué sección y docente propuestos y en qué estado
se encuentra (`SOLICITADA → AUTORIZADA | DENEGADA → INCORPORADA`).

- FK compuesta `(id_seccion, id_periodo, id_curso_plan)` →
  `core.seccion`: la sección vinculada **siempre** pertenece al mismo
  período y curso de la solicitud.
- El trigger `fn_transicion_apertura` impide: incorporar sin
  autorización, que una `DENEGADA` quede incorporada, reabrir una
  solicitud resuelta e incorporar sin disponibilidad presentada.

### 4.2 `declaracion_disponibilidad`

Estado formal de la disponibilidad docente por período
(`PENDIENTE | PRESENTADA | NO_PRESENTADA`) y su política para cursos
regulares (`tratamiento_regular = ESTRICTA | FLEXIBLE`).

- `UNIQUE (id_periodo, id_docente)`: una declaración vigente por docente
  y período.
- `UNIQUE (id_declaracion, id_periodo, id_docente)` habilita la FK
  compuesta desde `disponibilidad_docente`, garantizando que los bloques
  guardados pertenezcan a la declaración del **mismo docente y período**.

### 4.3 `validacion_horario`

Cada ejecución de validación de una versión: resultado
(`VALIDA | INVALIDA`), fecha, responsable, `version_reglas` y
`huella_propuesta`.

- La huella se calcula **siempre en servidor** al insertar
  (disparador `fn_validacion_horario`); no la aporta el cliente.
- Una vez registrada, la fila es inmutable salvo `resultado` y
  `observacion` (evidencia histórica).
- No puede quedar `VALIDA` si acumula conflictos `DURA`.

### 4.4 `conflicto_horario`

Evidencia de cada conflicto: `tipo` (mismos códigos que
`restriccion.tipo`), `severidad` (`DURA | BLANDA`), hasta dos detalles
afectados (opcionales), docente y ambiente afectados (opcionales) y
descripción libre.

- `id_validacion` es obligatoria: **no existen conflictos huérfanos**.
- El trigger `fn_conflicto_horario` exige que los detalles referenciados
  pertenezcan al horario de la validación que detectó el conflicto.

---

## 5. Justificación funcional de cada tabla nueva

| Tabla | Procedimiento Bizagi | Problema que resuelve |
|---|---|---|
| `apertura_repeticion` | Decisión «¿Se autorizó apertura?» en la apertura de repetición | Sin ella no queda constancia de *quién* autorizó, *cuándo* y con qué sección; sería posible incorporar una repetición no autorizada o denegada. |
| `declaracion_disponibilidad` | Registro de disponibilidad del docente antes de asignarlo a la repetición | `disponibilidad_docente` sólo guarda bloques: no distingue «no presentó» de «pendiente» ni permite exigir la disponibilidad obligatoria de una repetición. |
| `validacion_horario` | «Validar restricciones» → «¿El horario es válido?» | No quedaba registro de *qué versión* se evaluó, *cuándo* ni con *qué reglas*; imposible comprobar que una aprobación descansaba sobre una validación vigente. |
| `conflicto_horario` | Detección de conflictos → reporte → corrección/regeneración | Los conflictos eran transitorios; se necesitaba evidencia histórica (incluso tras regenerar la propuesta) para corregir y auditar. |

---

## 6. Relaciones (diagrama ER)

```mermaid
erDiagram
    periodo_academico["core.periodo_academico"] ||--o{ oferta_curso : "1-N"
    curso_plan["core.curso_plan"] ||--o{ oferta_curso : "1-N"
    periodo_academico["core.periodo_academico"] ||--o{ seccion : "1-N"
    curso_plan["core.curso_plan"] ||--o{ seccion : "1-N"
    docente["core.docente"] ||--o{ asignacion_docente : "1-N"
    ambiente["core.ambiente"] ||--o{ ambiente_bloqueo : "1-N"
    usuario["seguridad.usuario"] ||--o{ horario : "creado por"

    seccion["core.seccion"] ||--o{ actividad_academica : "1-N"
    periodo_academico["core.periodo_academico"] ||--o{ horario : "1-N"
    horario ||--o{ detalle_horario : "1-N"
    actividad_academica ||--o{ detalle_horario : "1-N"
    ambiente["core.ambiente"] ||--o{ detalle_horario : "ambiente asignado"
    actividad_academica ||--o{ asignacion_docente : "1-N"
    periodo_academico["core.periodo_academico"] ||--o{ bloque_horario : "1-N"
    bloque_horario ||--o{ disponibilidad_docente : "1-N"
    docente["core.docente"] ||--o{ disponibilidad_docente : "1-N"
    horario ||--o{ horario_revision : "1-N"
    restriccion ||--o{ detalle_restriccion : "1-N"

    periodo_academico["core.periodo_academico"] ||--o{ apertura_repeticion : "1-N"
    curso_plan["core.curso_plan"] ||--o{ apertura_repeticion : "1-N"
    seccion["core.seccion"] ||--o{ apertura_repeticion : "FK compuesta"
    docente["core.docente"] ||--o{ apertura_repeticion : "propuesto"
    usuario["seguridad.usuario"] ||--o{ apertura_repeticion : "solicita/autoriza"
    asignacion_docente ||--o{ apertura_repeticion : "trazabilidad"

    periodo_academico["core.periodo_academico"] ||--o{ declaracion_disponibilidad : "1-N"
    docente["core.docente"] ||--o{ declaracion_disponibilidad : "1-N"
    declaracion_disponibilidad ||--o{ disponibilidad_docente : "FK compuesta"
    horario ||--o{ validacion_horario : "1-N"
    validacion_horario ||--o{ conflicto_horario : "1-N"
    detalle_horario ||--o{ conflicto_horario : "detalle A/B"
    docente["core.docente"] ||--o{ conflicto_horario : "afectado"
    ambiente["core.ambiente"] ||--o{ conflicto_horario : "afectado"
```

Tablas compartidas completas (también con FK a `core.seccion`,
`core.periodo_academico`, `core.curso_plan`, `core.docente`,
`core.ambiente` y `seguridad.usuario`): `oferta_curso`,
`actividad_academica`, `bloque_horario`, `ambiente_bloqueo`,
`horario`, `restriccion`, `detalle_restriccion`,
`horario_revision`, `validacion_horario`.

**Regla de alcance:** las FK del módulo sólo apuntan a
`gestion_horarios`, `core`, `seguridad` y `public` (identidad de
autenticación). No hay FK hacia esquemas de otros módulos.

---

## 7. Relación con los pasos del procedimiento Bizagi

| Paso del procedimiento | Tabla(s) |
|---|---|
| Solicitar apertura de repetición | `apertura_repeticion` (estado `SOLICITADA`) |
| ¿Se autorizó apertura? | `apertura_repeticion` (`AUTORIZADA`/`DENEGADA`, `autorizado_por`, `resuelto_en`) |
| Incorporar sección autorizada al horario | `apertura_repeticion` (`INCORPORADA`) + `core.seccion` |
| Registrar disponibilidad del docente | `declaracion_disponibilidad` + `disponibilidad_docente` |
| Ofertar cursos y programar actividades | `oferta_curso`, `actividad_academica`, `asignacion_docente` |
| Bloquear ambientes | `ambiente_bloqueo` |
| Definir reglas de validación | `restriccion`, `detalle_restriccion` |
| Generar propuesta de horario | `horario` (estado `BORRADOR`→`GENERADO`), `detalle_horario` |
| Validar restricciones | `validacion_horario` |
| ¿El horario es válido? | `validacion_horario.resultado` + `conflicto_horario.severidad` |
| Corregir o regenerar ante conflictos | nuevas filas en `validacion_horario` (el historial se conserva) |
| Revisar, aprobar y publicar | `horario_revision`, `horario` (`EN_REVISION`→`APROBADO`→`PUBLICADO`) |

---

## 8. Restricciones de integridad

### Claves foráneas (43 en el módulo)

Todas apuntan a `gestion_horarios`, `core`, `seguridad` o `public`.
Destacan:

- `apertura_repeticion (id_seccion, id_periodo, id_curso_plan)` →
  `core.seccion (id_seccion, id_periodo, id_curso_plan)`.
- `disponibilidad_docente (id_bloque, id_periodo)` →
  `bloque_horario (id_bloque, id_periodo)`.
- `disponibilidad_docente (id_declaracion, id_periodo, id_docente)` →
  `declaracion_disponibilidad (...)`.
- `seguridad.usuario (id_usuario)` → `public.users (id)`.

### Índices únicos parciales (reglas de negocio)

| Índice | Regla |
|---|---|
| `uq_horario_publicado_vigente` (`id_periodo`, `ambito`) `WHERE estado='PUBLICADO'` | Una sola versión publicada vigente por período y ámbito; además **serializa la publicación concurrente** (la segunda transacción espera o recibe duplicidad). |
| `uq_asignacion_activa` (`id_actividad`) `WHERE estado IN ('PROPUESTA','CONFIRMADA')` | Un solo docente activo por actividad; conserva el histórico `RECHAZADA`/`REASIGNADA`. |
| `uq_apertura_seccion` (`id_seccion`) `WHERE id_seccion IS NOT NULL` | Una sección sólo se incorpora desde una solicitud. |

### Disparadores (integridad que una FK no puede expresar)

| Disparador | Regla |
|---|---|
| `fn_transicion_horario` | Ciclo de estados permitido; identidad (período, ámbito, versión) inmutable; exige validación `VALIDA` **vigente** para `APROBADO`/`PUBLICADO`; versión `PUBLICADO` inmutable salvo a `ARCHIVADO`. |
| `fn_validar_detalle_horario` | El período del horario debe coincidir con el de la sección de la actividad; prohíbe alterar detalles de una versión `PUBLICADO`. |
| `fn_validacion_horario` | Calcula la huella en servidor; la evidencia registrada es inmutable; impide `VALIDA` con conflictos duros. |
| `fn_conflicto_horario` | Sin huérfanos; los detalles deben pertenecer al horario evaluado; sin conflictos duros en validaciones `VALIDA`. |
| `fn_transicion_apertura` | Autorización obligatoria, denegaciones terminales, incorporación sólo con sección, docente y disponibilidad `PRESENTADA`. |

### Restricciones de datos

- `hora_inicio < hora_fin` (`actividad_academica`, `bloque_horario`,
  `ambiente_bloqueo`), `capacidad > 0` (`ambiente`, `seccion`),
  `version > 0`, `creditos`/`horas_semanales` dentro de rangos,
  estados restringidos a conjuntos cerrados, fechas coherentes
  (`fecha_inicio <= fecha_fin`, `resuelto_en >= solicitado_en`).
- Las propuestas **inválidas sí se pueden almacenar** (es necesario para
  corregirlas): no hay restricciones que impidan guardar conflictos.

### Estados de `horario`

```text
BORRADOR → GENERADO → VALIDADO → EN_REVISION → APROBADO → PUBLICADO → ARCHIVADO
                 ↑________↓         ↑________↓              │
                 (correcciones)     (observaciones)          └→ ARCHIVADO (único destino)
```

Mapeo/documentación: el módulo no tenía estados previos, se adoptó el
conjunto completo recomendado. `PUBLICADO` exige haber pasado por
`APROBADO` con validación vigente; sólo puede archivarse después.

### Huella de la propuesta (`huella_propuesta`)

```text
huella = md5( concat_ordenado )
concat_ordenado = filas ordenadas por id_detalle, unidas con E'\n'
fila = id_detalle | id_actividad | dia_semana | hora_inicio | hora_fin |
       tipo_actividad | id_docente_activo (o SIN_DOCENTE)
```

Se calcula con `gestion_horarios.fn_huella_horario(id_horario)`; el
`id_docente_activo` es la asignación con estado `PROPUESTA`/`CONFIRMADA`
de mayor `id_asignacion` de la actividad. Un horario sin detalles tiene
huella `md5('')`. Al cambiar el contenido, la huella vigente deja de
coincidir y la validación anterior deja de ser apta para aprobar o
publicar (pruebas N5 y N6).

---

## 9. Instrucciones de ejecución y prueba

### Opción A — Con Laravel (recomendada en el proyecto)

```bash
# El servidor PostgreSQL debe estar disponible (por ejemplo, el de Docker:
# docker compose up -d postgresql -> localhost:5433)
php artisan migrate            # aplica las migraciones nuevas
php artisan migrate:status     # debe mostrar en estado "Ran"
```

Las 16 migraciones del módulo (una por tabla más la de restricciones),
todas en `app/Modules/GestionHorarios/Database/Migrations/` y cargadas
automáticamente por `ModulesServiceProvider`, en este orden:

| # | Migración | Crea |
|---|---|---|
| 1 | `2026_10_29_000001_create_oferta_curso_table` | `oferta_curso` |
| 2 | `2026_10_29_000002_create_actividad_academica_table` | `actividad_academica` |
| 3 | `2026_10_29_000003_create_asignacion_docente_table` | `asignacion_docente` |
| 4 | `2026_10_29_000004_create_bloque_horario_table` | `bloque_horario` |
| 5 | `2026_10_29_000005_create_disponibilidad_docente_table` | `disponibilidad_docente` |
| 6 | `2026_10_29_000006_create_ambiente_bloqueo_table` | `ambiente_bloqueo` |
| 7 | `2026_10_29_000007_create_horario_table` | `horario` |
| 8 | `2026_10_29_000008_create_detalle_horario_table` | `detalle_horario` |
| 9 | `2026_10_29_000009_create_restriccion_table` | `restriccion` |
| 10 | `2026_10_29_000010_create_detalle_restriccion_table` | `detalle_restriccion` |
| 11 | `2026_10_29_000011_create_horario_revision_table` | `horario_revision` |
| 12 | `2026_10_29_000012_create_apertura_repeticion_table` | `apertura_repeticion` |
| 13 | `2026_10_29_000013_create_declaracion_disponibilidad_table` | `declaracion_disponibilidad` |
| 14 | `2026_10_29_000014_create_validacion_horario_table` | `validacion_horario` |
| 15 | `2026_10_29_000015_create_conflicto_horario_table` | `conflicto_horario` |
| 16 | `2026_10_29_000016_add_restricciones_horarios` | 2 FK, 37 índices, 6 funciones, 5 disparadores y los comentarios de `03`/`04` |

Notas de integración:

- Cada migración ejecuta SQL crudo con nombres **calificados**
  (`gestion_horarios.<tabla>`) y crea su esquema si no existe; el orden
  inverso de `down()` es válido porque ninguna FK apunta a una tabla creada
  después (revisado una a una).
- `000016` carga `database/gestion_horarios/03_restricciones_indices.sql`
  y `04_comentarios.sql`; su `down()` elimina los 5 disparadores, las 2 FK
  añadidas, los 37 índices (detectados por regex sobre el propio SQL) y las
  6 funciones, en ese orden.
- **Requiere `core` y `seguridad` ya provisionales**: sin ellos
  `000001` falla con `schema "core" does not exist` (dependencia del
  equipo, ver §10).

### Opción B — Con psql (sin Laravel)

```powershell
$psql = "C:\Program Files\PostgreSQL\18\bin\psql.exe"   # o el psql en PATH
$db = "portal_gestion_direccion_pruebas"

& $psql -X -v ON_ERROR_STOP=1 -d $db -f database\shared\01_core_seguridad.sql
& $psql -X -v ON_ERROR_STOP=1 -d $db -f database\gestion_horarios\01_tablas_base.sql
& $psql -X -v ON_ERROR_STOP=1 -d $db -f database\gestion_horarios\02_tablas_adicionales.sql
& $psql -X -v ON_ERROR_STOP=1 -d $db -f database\gestion_horarios\03_restricciones_indices.sql
& $psql -X -v ON_ERROR_STOP=1 -d $db -f database\gestion_horarios\04_comentarios.sql

# Pruebas de integridad (45 aserciones; termina con ROLLBACK)
& $psql -X -v ON_ERROR_STOP=1 -d $db -f database\gestion_horarios\05_pruebas_integridad.sql
```

Añade `-h 127.0.0.1 -p <puerto> -U <usuario>` según el entorno. Cualquier
error detiene la ejecución con código de salida distinto de cero; la
salida esperada es una línea `OK: ...` por prueba y ninguna `FALLO:`.

> **`01_tablas_base.sql` y `02_tablas_adicionales.sql` son scripts
> independientes de psql**: reproducen las mismas 15 tablas que las 16
> migraciones Laravel. **No deben ejecutarse encima de una base ya
> migrada** (`CREATE TABLE` sin `IF NOT EXISTS` → error de relación ya
> existente) ni mezclarse con `php artisan migrate`. Úsalos sólo sobre una
> base desechable vacía, en el orden 01→02→03→04 de la Opción B. En
> cambio, `03` y `04` **sí** son la fuente de `000016`, de modo que en la
> Opción A no hay que ejecutarlos a mano.

El script de pruebas ejecuta todo dentro de una transacción terminada en
`ROLLBACK`: **no deja datos de prueba**.

### Consultar los comentarios en el catálogo (pgAdmin/psql)

```sql
SELECT obj_description('gestion_horarios.apertura_repeticion'::regclass);

SELECT c.relname AS tabla, a.attname AS columna,
       col_description(c.oid, a.attnum) AS comentario
FROM pg_attribute a
JOIN pg_class c ON c.oid = a.attrelid
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'gestion_horarios' AND a.attnum > 0 AND NOT a.attisdropped
  AND col_description(c.oid, a.attnum) IS NOT NULL
ORDER BY c.relname, a.attnum;
```

### Pruebas verificadas (2026-10-29)

Ciclo completo sobre una base desechable aislada (PostgreSQL 18.6,
contenedor propio; no se tocó ninguna base compartida):

| Paso | Resultado |
|---|---|
| `php artisan migrate` (16 migraciones) | **PASÓ** |
| 15 tablas en `gestion_horarios` + 62 índices + 5 disparadores + 6 funciones + 30 FK a `core`/`seguridad` | **PASÓ** |
| `php artisan migrate:rollback --path=app/Modules/GestionHorarios/Database/Migrations` (16 `down()` en orden inverso) | **PASÓ** — 0 tablas y 0 registros de migración del módulo |
| `php artisan migrate` (segunda pasada) | **PASÓ** — 15 tablas, 62 índices, 5 disparadores restaurados |

### Pruebas PHPUnit

```bash
php artisan test                                  # suite completa (SQLite por defecto)
php artisan test --filter PostgreSqlSchemaTest    # requiere DB_CONNECTION=pgsql migrada
```

---

## 10. Pendientes conocidos

- **Reconciliación con `base_datos_modular.sql`**: cuando el SQL modular
  definitivo se incorpore al repositorio, comparar columna a columna las
  11 tablas originales con estas definiciones y migrar diferencias de
  forma compatible (sin perder datos).
- **Bootstrap compartido**: sustituir `core`/`seguridad` de
  `database/shared/01_core_seguridad.sql` por las tablas oficiales del
  modelo modular.
- **Servicios e interfaces**: el módulo sólo tiene scaffolds
  (`HorarioController`, `HorarioService`, modelo `Registro` apuntando a
  `gestion_horarios.registros`, tabla que nunca existió). No hay lógica
  de negocio, políticas aplicadas ni vistas operativas.
- **Motor de validación**: `conflicto_horario` está listo para recibir
  los resultados, pero la detección de cruces/superposiciones/capacidad
  aún no está implementada en PHP.
- **Sincronización `public.users` ↔ `seguridad.usuario`**: al crear un
  usuario de Laravel que actúe como identidad del modelo modular hay que
  insertar la fila correspondiente en `seguridad.usuario` (pendiente de
  un servicio o evento).
