# Gestión de Prácticas: propuesta de tablas para la megabase

**Presentado por:** equipo del módulo Gestión de Prácticas · **Esquema:** `gestion_practicas` (PostgreSQL) · **Script:** `gestion_practicas.sql`

## Presentación

En este documento presentamos cómo queda el esquema `gestion_practicas` dentro de la megabase. Antes de definirlo, revisamos uno por uno los procesos del módulo: los del diagrama BPMN (empresa, administrador, estudiante y supervisor) y los de las 15 historias de usuario del backlog. Con eso comprobamos si las tablas que figuran en el PDF de la megabase alcanzaban para todos.

El resultado es que el esquema necesitaba algunos ajustes. Varios campos que nuestro modelo original sí tenía se perdieron al unificar las bases, y sin ellos algunos procesos no se podían completar. Por ejemplo, no había dónde guardar el enlace de postulación de una convocatoria, las funciones de la práctica ni quién la validó.

El script que entregamos crea **solo las 11 tablas propias** de Gestión de Prácticas, con sus claves, restricciones e índices. No crea nada de `core`, `seguridad` ni `comun`, porque esas tablas las crea otro equipo. Como nuestras tablas apuntan a ellas con claves foráneas, **el script debe ejecutarse después de que existan los esquemas compartidos**. Ya lo probamos en PostgreSQL 16: crea las 11 tablas sin errores, y con datos de prueba recorrimos el proceso completo, desde la creación de una convocatoria hasta el cierre de la práctica.

El documento está organizado en cuatro partes:

1. Las tablas propias de Gestión de Prácticas.
2. Las tablas compartidas que nuestro módulo necesita.
3. Los campos que agregamos o modificamos, y por qué.
4. Los puntos que necesitamos coordinar con los responsables de las tablas compartidas.

En las tablas de columnas, la columna **Origen** indica si el campo ya estaba en el PDF de la megabase (`PDF`), si lo agregamos (`NUEVO`) o si ya estaba pero lo modificamos (`CAMBIO`).

---

## 1. Tablas propias de Gestión de Prácticas

Estas son las tablas que pertenecen únicamente a nuestro módulo. Viven en el esquema `gestion_practicas`, y ningún otro equipo las crea ni las modifica.

| # | Tabla | Para qué sirve | ¿Estaba en el PDF? |
|---|---|---|---|
| 1 | `empresa_usuario` | Indica qué cuenta de usuario representa a qué empresa | **No, la proponemos como nueva** |
| 2 | `convocatoria` | Ofertas de práctica que publican las empresas | Sí |
| 3 | `observacion_convocatoria` | Observaciones del administrador al revisar una convocatoria | Sí |
| 4 | `practica` | La práctica del estudiante: empresa, área, funciones, periodo, supervisor, validación y cierre | Sí |
| 5 | `postulacion` | Postulaciones de los estudiantes a las convocatorias y su resultado | Sí |
| 6 | `seguimiento` | Seguimientos que registra el docente supervisor | Sí |
| 7 | `documento_practica` | Documentos de la práctica: carta de aceptación, informes, constancia | Sí |
| 8 | `observacion_practica` | Observaciones a los datos de la práctica o a sus documentos | Sí |
| 9 | `criterio_evaluacion` | Catálogo de criterios de evaluación con su peso | Sí |
| 10 | `evaluacion` | Evaluación de la práctica (del supervisor y, si aplica, de la empresa) | Sí |
| 11 | `evaluacion_criterio` | Nota por cada criterio de una evaluación | Sí |

Con la tabla `empresa_usuario`, nuestro esquema pasa de **10 a 11 tablas**. A continuación explicamos cada tabla y sus columnas.

### 1.1 `empresa_usuario` (nueva)

Proponemos esta tabla para unir cada cuenta de `seguridad.usuario` con la empresa a la que representa. Nuestro modelo original tenía ese vínculo como `EMPRESA.id_usuario`, pero en la megabase desapareció. Sin él, el sistema no puede saber a qué empresa pertenece el usuario que inicia sesión.

La tabla permite tres cosas: que la empresa inicie sesión, que el administrador cree y desactive usuarios de empresa (HU1), y que cada empresa vea y edite solo sus propias convocatorias. La ubicamos en nuestro esquema para no modificar `comun.empresa`, que comparten otros módulos.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_empresa` | bigint | NOT NULL | Empresa → `comun.empresa`. Parte de la PK | NUEVO |
| `id_usuario` | bigint | NOT NULL | Cuenta → `seguridad.usuario`. Parte de la PK | NUEVO |
| `es_principal` | boolean | NOT NULL | Marca al representante principal (por defecto `false`) | NUEVO |
| `estado` | varchar(10) | NOT NULL | `activo` / `inactivo`: desactivar el acceso sin tocar la empresa | NUEVO |
| `asignado_por` | bigint | NOT NULL | Administrador que creó el acceso → `seguridad.usuario` | NUEVO |
| `asignado_en` | timestamptz | NOT NULL | Fecha en que se creó el acceso | NUEVO |

Además, definimos que una cuenta activa solo puede representar a una empresa (índice único parcial `gp_eu_usu_activo_uq`).

### 1.2 `convocatoria`

Aquí se guardan las ofertas de práctica. Las crea la empresa, las revisa el administrador y, una vez publicadas, las ven los estudiantes. Agregamos los requisitos y el enlace de postulación, que el diagrama exige y el estudiante necesita para postular, además de las fechas y el responsable de cada paso de la revisión.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_convocatoria` | bigint (identity) | NOT NULL | PK | PDF |
| `id_empresa` | bigint | NOT NULL | Empresa que convoca → `comun.empresa` | PDF |
| `titulo` | varchar(200) | NOT NULL | Título de la convocatoria | PDF |
| `descripcion` | text | NOT NULL | Descripción del puesto | PDF |
| `requisitos` | text | NOT NULL | Requisitos que debe cumplir el postulante | **NUEVO** |
| `enlace_postulacion` | varchar(500) | NOT NULL | URL donde el estudiante postula (debe empezar con http/https) | **NUEVO** |
| `tipo_practica` | varchar(20) | NOT NULL | `preprofesional` / `profesional` | PDF |
| `modalidad` | varchar(15) | NOT NULL | `presencial` / `remota` / `hibrida` | PDF |
| `vacantes` | smallint | NOT NULL | Mayor que 0 | PDF |
| `fecha_publicacion` | timestamptz | NULL | Se llena al publicar | PDF |
| `fecha_cierre` | date | NOT NULL | Último día para postular; no puede ser anterior a la publicación | PDF |
| `estado` | varchar(15) | NOT NULL | `borrador`, `en_revision`, `observada`, `publicada`, `cerrada`, `anulada` | PDF |
| `registrada_por` | bigint | NOT NULL | Usuario de la empresa que la creó → `seguridad.usuario` | PDF |
| `registrada_en` | timestamptz | NOT NULL | Fecha de creación | **NUEVO** |
| `enviada_en` | timestamptz | NULL | Último envío a revisión | **NUEVO** |
| `publicada_por` | bigint | NULL | Administrador que aprobó y publicó → `seguridad.usuario` | **NUEVO** |

### 1.3 `observacion_convocatoria`

Cuando el administrador revisa una convocatoria y encuentra algo que corregir, registra aquí la observación. La empresa corrige y la vuelve a enviar. Agregamos la fecha de subsanación para saber cuándo se atendió cada observación.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_observacion` | bigint (identity) | NOT NULL | PK | PDF |
| `id_convocatoria` | bigint | NOT NULL | → `convocatoria` | PDF |
| `descripcion` | text | NOT NULL | Texto de la observación | PDF |
| `estado` | varchar(10) | NOT NULL | `pendiente` / `subsanada` | PDF |
| `registrado_por` | bigint | NOT NULL | Administrador → `seguridad.usuario` | PDF |
| `registrado_en` | timestamptz | NOT NULL | Fecha de la observación | PDF |
| `subsanada_en` | timestamptz | NULL | Fecha en que la empresa la corrigió (obligatoria si `estado = subsanada`) | **NUEVO** |

### 1.4 `practica`

Es la tabla central del módulo. La registra el estudiante cuando lo seleccionan; luego el administrador la valida y le asigna un supervisor, el supervisor la acompaña y, al final, se cierra con una nota. Es la tabla que más cambios necesitó, porque en el PDF no había dónde registrar las funciones, la validación ni el cierre.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_practica` | bigint (identity) | NOT NULL | PK | PDF |
| `id_estudiante` | bigint | NOT NULL | → `core.estudiante` | PDF |
| `id_empresa` | bigint | NOT NULL | → `comun.empresa` | PDF |
| `id_docente_supervisor` | bigint | **NULL** | → `core.docente`. Vacío hasta que el administrador asigna supervisor | **CAMBIO** |
| `id_contacto_jefe` | bigint | NULL | Jefe inmediato → `comun.contacto_empresa` | PDF |
| `id_periodo` | bigint | NULL | Semestre → `core.periodo_academico` | **NUEVO** |
| `area` | varchar(150) | NOT NULL | Área donde practica | PDF |
| `funciones` | text | NOT NULL | Funciones que realizará el estudiante | **NUEVO** |
| `tipo_practica` | varchar(20) | NOT NULL | `preprofesional` / `profesional` | PDF |
| `fecha_inicio` | date | NOT NULL | Inicio | PDF |
| `fecha_fin` | date | NOT NULL | Fin (posterior al inicio) | PDF |
| `horas_totales` | smallint | NOT NULL | Horas planificadas (mayor que 0) | PDF |
| `estado` | varchar(15) | NOT NULL | `registrada`, `en_revision`, `observada`, `validada`, `en_curso`, `finalizada`, `anulada` | PDF |
| `registrada_en` | timestamptz | NOT NULL | Fecha en que el estudiante la registró | **NUEVO** |
| `enviada_en` | timestamptz | NULL | Último envío a revisión | **NUEVO** |
| `validado_por` | bigint | NULL | Administrador que validó → `seguridad.usuario` | **NUEVO** |
| `validada_en` | timestamptz | NULL | Fecha de validación | **NUEVO** |
| `supervisor_asignado_por` | bigint | NULL | Administrador que asignó al supervisor → `seguridad.usuario` | **NUEVO** |
| `supervisor_asignado_en` | timestamptz | NULL | Fecha de asignación | **NUEVO** |
| `nota_final` | numeric(4,2) | NULL | Nota final ponderada (0 a 20) | **NUEVO** |
| `resultado` | varchar(12) | NULL | `aprobado` / `desaprobado` | **NUEVO** |
| `cerrada_por` | bigint | NULL | Quién cerró la práctica → `seguridad.usuario` | **NUEVO** |
| `cerrada_en` | timestamptz | NULL | Fecha de cierre | **NUEVO** |

Para que los datos sean coherentes con cada etapa, definimos estas restricciones según el estado:

- Desde `validada`, deben estar registrados quién validó (`validado_por`) y cuándo (`validada_en`).
- No se puede pasar a `en_curso` sin un supervisor asignado.
- `finalizada` exige nota, resultado, quién cerró y cuándo.

### 1.5 `postulacion`

Registra la postulación del estudiante a una convocatoria y el resultado de la selección. Si lo seleccionan, la postulación queda enlazada con la práctica que se registra después. Recuperamos dos cosas de nuestro modelo original: la regla de que nadie postule dos veces a la misma convocatoria y la fecha del resultado.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_postulacion` | bigint (identity) | NOT NULL | PK | PDF |
| `id_convocatoria` | bigint | NOT NULL | → `convocatoria` | PDF |
| `id_estudiante` | bigint | NOT NULL | → `core.estudiante` | PDF |
| `id_archivo_cv` | bigint | NULL | CV del postulante → `comun.archivo` | PDF |
| `estado` | varchar(15) | NOT NULL | `postulado`, `seleccionado`, `no_seleccionado`, `desistido` | PDF |
| `id_practica` | bigint | NULL | Práctica que nace de esta postulación → `practica` (única) | PDF |
| `postulado_en` | timestamptz | NOT NULL | Fecha de postulación | PDF |
| `resultado_en` | timestamptz | NULL | Fecha del resultado (seleccionado o no) | **NUEVO** |
| `resultado_registrado_por` | bigint | NULL | Quién registró el resultado → `seguridad.usuario` | **NUEVO** |

También agregamos dos restricciones:

- `UNIQUE (id_convocatoria, id_estudiante)`: un estudiante no puede postular dos veces a la misma convocatoria.
- Solo una postulación con estado `seleccionado` puede tener `id_practica`.

### 1.6 `seguimiento`

Aquí el docente supervisor registra el acompañamiento de la práctica: reuniones, visitas o revisiones de avance. Agregamos un campo de observaciones, que pide la HU10 y que estaba en nuestro modelo original, y la fecha de registro en el sistema.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_seguimiento` | bigint (identity) | NOT NULL | PK | PDF |
| `id_practica` | bigint | NOT NULL | → `practica` | PDF |
| `fecha` | date | NOT NULL | Fecha del seguimiento | PDF |
| `tipo` | varchar(20) | NOT NULL | `reunion`, `visita`, `llamada`, `revision_avance`, `otro` | PDF |
| `descripcion` | text | NOT NULL | Qué se revisó | PDF |
| `observaciones` | text | NULL | Observaciones del supervisor | **NUEVO** |
| `registrado_por` | bigint | NOT NULL | Docente supervisor → `core.docente` | PDF |
| `registrado_en` | timestamptz | NOT NULL | Fecha en que se registró | **NUEVO** |

### 1.7 `documento_practica`

Guarda los documentos de cada práctica, como la carta de aceptación y los informes. El archivo físico vive en `comun.archivo`, y aquí registramos de qué documento se trata y en qué estado está. Agregamos quién lo revisó y cuándo, porque el supervisor revisa el informe del estudiante y puede aprobarlo u observarlo.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_documento` | bigint (identity) | NOT NULL | PK | PDF |
| `id_practica` | bigint | NOT NULL | → `practica` | PDF |
| `id_archivo` | bigint | NOT NULL | Archivo físico → `comun.archivo` | PDF |
| `tipo_documento` | varchar(20) | NOT NULL | `carta_aceptacion`, `plan_practica`, `informe_parcial`, `informe_final`, `constancia`, `otro` | PDF |
| `estado` | varchar(12) | NOT NULL | `pendiente`, `aprobado`, `observado`, `reemplazado` | PDF |
| `subido_en` | timestamptz | NOT NULL | Fecha de carga | PDF |
| `revisado_por` | bigint | NULL | Quién lo revisó → `seguridad.usuario` | **NUEVO** |
| `revisado_en` | timestamptz | NULL | Fecha de revisión | **NUEVO** |

### 1.8 `observacion_practica`

Registra las observaciones sobre una práctica. En el PDF, esta tabla solo podía apuntar a un documento, pero el administrador también observa datos que no son documentos, como el área, las funciones o las fechas. Por eso la observación ahora pertenece siempre a una práctica, y el documento queda como dato opcional.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_observacion` | bigint (identity) | NOT NULL | PK | PDF |
| `id_practica` | bigint | NOT NULL | Práctica observada → `practica` | **NUEVO** |
| `id_documento` | bigint | **NULL** | Documento observado, si la observación es sobre un documento | **CAMBIO** |
| `descripcion` | text | NOT NULL | Texto de la observación | PDF |
| `estado` | varchar(10) | NOT NULL | `pendiente` / `subsanada` | PDF |
| `registrado_por` | bigint | NOT NULL | Administrador o supervisor → `seguridad.usuario` | PDF |
| `registrado_en` | timestamptz | NOT NULL | Fecha | PDF |
| `subsanada_en` | timestamptz | NULL | Fecha de corrección | **NUEVO** |

Usamos una clave foránea compuesta `(id_documento, id_practica)`: si la observación se refiere a un documento, este debe pertenecer a la misma práctica.

### 1.9 `criterio_evaluacion`

Es el catálogo de criterios con los que se evalúa la práctica, cada uno con su peso y con el tipo de evaluador que lo califica. Agregamos el campo `activo`, para poder retirar un criterio en el futuro sin alterar el significado de las notas ya registradas.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_criterio` | bigint (identity) | NOT NULL | PK | PDF |
| `nombre` | varchar(120) | NOT NULL | Nombre del criterio (único) | PDF |
| `evaluador` | varchar(12) | NOT NULL | Quién califica este criterio: `supervisor` / `empresa` | PDF |
| `peso` | numeric(5,2) | NOT NULL | Porcentaje (mayor que 0 y hasta 100) | PDF |
| `activo` | boolean | NOT NULL | Permite retirar un criterio sin alterar notas pasadas | **NUEVO** |

### 1.10 `evaluacion`

Registra la evaluación de la práctica y la observación final. En el PDF, el campo `evaluador` solo indica el tipo de evaluador y no la persona, así que no quedaba registrado quién evaluó. Nuestro modelo original sí lo tenía (`id_supervisor`), por eso agregamos `evaluado_por`.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_evaluacion` | bigint (identity) | NOT NULL | PK | PDF |
| `id_practica` | bigint | NOT NULL | → `practica` | PDF |
| `evaluador` | varchar(12) | NOT NULL | Tipo de evaluador: `supervisor` / `empresa` | PDF |
| `evaluado_por` | bigint | NOT NULL | Usuario que evaluó → `seguridad.usuario` | **NUEVO** |
| `comentario` | text | NULL | Observación final | PDF |
| `evaluado_en` | timestamptz | NOT NULL | Fecha | PDF |

También agregamos la restricción `UNIQUE (id_practica, evaluador)`: cada práctica tiene como máximo una evaluación del supervisor y una de la empresa.

### 1.11 `evaluacion_criterio`

Guarda la nota de cada criterio dentro de una evaluación. Con estas notas y los pesos de los criterios se calcula la nota final ponderada. No necesitó campos nuevos; solo agregamos una restricción para que la nota esté entre 0 y 20.

| Columna | Tipo | Nulo | Descripción | Origen |
|---|---|---|---|---|
| `id_evaluacion` | bigint | NOT NULL | → `evaluacion`. Parte de la PK | PDF |
| `id_criterio` | bigint | NOT NULL | → `criterio_evaluacion`. Parte de la PK | PDF |
| `nota` | numeric(4,2) | NOT NULL | Nota del criterio, **0 a 20** | PDF (+ CHECK nuevo) |

---

## 2. Tablas compartidas que necesita Gestión de Prácticas

Nuestro módulo no trabaja aislado: necesita información que vive en los esquemas compartidos `core`, `seguridad` y `comun`. **Estas tablas no las creamos nosotros**, pero las listamos para que quede claro de cuáles dependemos. Algunas se conectan con nuestras tablas mediante claves foráneas, y por eso deben existir antes de ejecutar nuestro script.

### 2.1 Tablas que referenciamos con clave foránea

| Tabla compartida | Qué tomamos de ella | Columnas nuestras que la referencian |
|---|---|---|
| `core.estudiante` | El estudiante que postula y realiza la práctica | `postulacion.id_estudiante`, `practica.id_estudiante` |
| `core.docente` | El docente supervisor | `practica.id_docente_supervisor`, `seguimiento.registrado_por` |
| `core.periodo_academico` | El semestre de la práctica, para reportes | `practica.id_periodo` |
| `seguridad.usuario` | Las cuentas que inician sesión y quién hace cada acción | `empresa_usuario.id_usuario`, `empresa_usuario.asignado_por`, `convocatoria.registrada_por`, `convocatoria.publicada_por`, `observacion_convocatoria.registrado_por`, `observacion_practica.registrado_por`, `postulacion.resultado_registrado_por`, `practica.validado_por`, `practica.supervisor_asignado_por`, `practica.cerrada_por`, `documento_practica.revisado_por`, `evaluacion.evaluado_por` |
| `comun.empresa` | La empresa que convoca y en la que se realiza la práctica | `empresa_usuario.id_empresa`, `convocatoria.id_empresa`, `practica.id_empresa` |
| `comun.contacto_empresa` | El jefe inmediato del estudiante | `practica.id_contacto_jefe` |
| `comun.archivo` | Los archivos: CV, carta de aceptación, informes | `postulacion.id_archivo_cv`, `documento_practica.id_archivo` |

### 2.2 Tablas que usamos sin clave foránea

Estas tablas no se conectan directamente con las nuestras, pero el sistema las consulta o escribe en ellas desde la aplicación:

| Tabla compartida | Para qué la usamos |
|---|---|
| `core.persona` | Nombres, documento, correo y teléfono del estudiante, del docente, del representante de la empresa y del jefe inmediato. Se llega a ella a través de `estudiante`, `docente`, `usuario` o `contacto_empresa`. Aquí se completa el perfil del estudiante |
| `seguridad.modulo_sistema` | El registro de nuestro módulo: su `id_modulo` se usa en roles, archivos y notificaciones |
| `seguridad.rol`, `seguridad.usuario_rol`, `seguridad.permiso`, `seguridad.rol_permiso` | Los roles del módulo: administrador, empresa, estudiante y supervisor |
| `seguridad.auditoria` | El historial de cambios: reenvíos, reasignaciones de supervisor y cambios de estado |
| `comun.notificacion` | Los avisos de cada cambio de estado (HU14), con `referencia_tabla` y `referencia_id` apuntando a nuestras tablas |
| `comun.sector_economico`, `core.ubigeo_distrito` | Los filtros de búsqueda de convocatorias por sector y ubicación, a través de `comun.empresa` |

---

## 3. Campos que agregamos o modificamos, y por qué

Aquí resumimos todos los cambios a nuestras tablas respecto del PDF de la megabase: **28 campos nuevos, 2 campos que ahora pueden quedar vacíos (NULL) y 1 tabla nueva**. Cada cambio responde a un proceso del módulo que no se podía completar o que perdía información. Muchos de estos campos ya estaban en nuestro modelo original y se perdieron al unificar; lo indicamos en cada caso.

| Tabla | Campo / restricción | Cambio | Por qué lo necesitamos |
|---|---|---|---|
| `empresa_usuario` | Tabla completa | Tabla nueva | Para el inicio de sesión de la empresa y la gestión de sus usuarios (HU1). Nuestro modelo original tenía `EMPRESA.id_usuario`, que en la megabase desapareció |
| `convocatoria` | `requisitos` | Nuevo | El diagrama pide "Crear convocatoria" con requisitos, y el estudiante los consulta en "Ver detalles". Estaba en nuestro modelo original |
| `convocatoria` | `enlace_postulacion` | Nuevo | El estudiante debe "Acceder al enlace" para postular; sin este campo ese paso no existe. Estaba en nuestro modelo original |
| `convocatoria` | `registrada_en` | Nuevo | Para conocer la fecha de creación (`fecha_creacion` en nuestro modelo original) |
| `convocatoria` | `enviada_en` | Nuevo | Para saber cuándo la empresa envió la convocatoria a revisión |
| `convocatoria` | `publicada_por` | Nuevo | Para saber qué administrador aprobó y publicó la convocatoria |
| `observacion_convocatoria` | `subsanada_en` | Nuevo | Para saber cuándo se corrigió cada observación |
| `practica` | `funciones` | Nuevo | El estudiante registra "Empresa, Área, **Funciones**, Periodo" y el supervisor las visualiza. Estaba en nuestro modelo original |
| `practica` | `id_docente_supervisor` | Pasa a NULL | El estudiante registra la práctica antes de que el administrador le asigne supervisor |
| `practica` | `supervisor_asignado_por`, `supervisor_asignado_en` | Nuevos | Para el proceso "Asignar supervisor" (HU9): quién lo asignó y cuándo |
| `practica` | `validado_por`, `validada_en` | Nuevos | Para el paso "Validar práctica": quién validó y cuándo. Estaban en nuestro modelo original (`validado_por`, `fecha_validacion`) |
| `practica` | `registrada_en`, `enviada_en` | Nuevos | Para conocer la fecha de registro (estaba en nuestro modelo original) y la de envío a revisión |
| `practica` | `nota_final`, `resultado`, `cerrada_por`, `cerrada_en` | Nuevos | Para "Registrar nota" y "Cerrar práctica / consultar resultado" (HU12 y HU13) |
| `practica` | `id_periodo` | Nuevo | Para "Consultar reportes de prácticas" (HU15) por semestre |
| `postulacion` | `UNIQUE (id_convocatoria, id_estudiante)` | Restricción nueva | Para que nadie postule dos veces a la misma convocatoria. Estaba en nuestro modelo original |
| `postulacion` | `resultado_en`, `resultado_registrado_por` | Nuevos | Para el paso "¿Fue seleccionado?": cuándo y quién registró el resultado (`fecha_seleccion` en nuestro modelo original) |
| `seguimiento` | `observaciones` | Nuevo | La HU10 pide "Registrar seguimiento / Observaciones". Estaba en nuestro modelo original |
| `seguimiento` | `registrado_en` | Nuevo | Para saber cuándo se registró en el sistema, que puede ser distinto de la fecha del seguimiento |
| `documento_practica` | `revisado_por`, `revisado_en` | Nuevos | Para "Revisar el informe del estudiante": quién lo revisó y cuándo |
| `observacion_practica` | `id_practica` | Nuevo (obligatorio) | El administrador observa datos de la práctica (área, funciones, fechas) que no son un documento. Estaba en nuestro modelo original |
| `observacion_practica` | `id_documento` | Pasa a NULL | Solo se llena cuando la observación es sobre un documento |
| `observacion_practica` | `subsanada_en` | Nuevo | Para saber cuándo se corrigió la observación |
| `criterio_evaluacion` | `activo` | Nuevo | Para retirar un criterio sin cambiar el significado de las notas anteriores |
| `evaluacion` | `evaluado_por` | Nuevo | Para saber quién evaluó. En la megabase, `evaluador` solo indica el tipo; nuestro modelo original tenía `id_supervisor` |
| `evaluacion` | `UNIQUE (id_practica, evaluador)` | Restricción nueva | Para que haya una sola evaluación por tipo de evaluador en cada práctica |
| `evaluacion_criterio` | `CHECK (nota BETWEEN 0 AND 20)` | Restricción nueva | Para respetar la escala vigesimal |
| Todas las tablas con `estado` | `CHECK` de valores | Restricción nueva | Para evitar estados inválidos (los valores son una propuesta que podemos ajustar en el diccionario de datos) |
| Todas | 15 índices | Nuevos | PostgreSQL no crea índices por sí solo en las columnas de las claves foráneas; sin ellos, las búsquedas y los listados serían lentos |

---

## 4. Puntos a coordinar sobre las tablas compartidas

Queremos dejar claro que **no necesitamos que se agregue ningún campo a las tablas compartidas**. Todo lo que faltaba para cumplir nuestros procesos lo resolvimos dentro de nuestro propio esquema. Por ejemplo, el vínculo entre el usuario y la empresa lo pusimos en `empresa_usuario` y no en `comun.empresa`, para no afectar a otros módulos.

Aun así, hay seis puntos que dependen de cómo se definan las tablas compartidas, y necesitamos coordinarlos con sus responsables:

| Tabla compartida | Qué necesitamos acordar | Por qué nos afecta |
|---|---|---|
| `seguridad.usuario.correo_institucional` | Los usuarios de empresa no tienen correo institucional. Proponemos que en ese campo se guarde su correo corporativo, o que se renombre a algo como `correo_acceso` | Es el correo con el que se inicia sesión y debe ser único; sin este acuerdo, la empresa no podría tener cuenta |
| `core.persona` | Que no sean obligatorios más campos que nombres y apellidos, o que se pueda registrar a una persona sin DNI | El representante de la empresa y el jefe inmediato se registran como `persona`, y es posible que el estudiante no conozca el DNI de su jefe |
| `comun.empresa.estado` | Que ese campo no se use para quitar el acceso de una empresa en Prácticas | La tabla la comparte Seguimiento al egresado; para quitar el acceso nosotros usamos `empresa_usuario.estado` |
| `seguridad.modulo_sistema` y `seguridad.rol` | Que se registre el módulo `gestion_practicas` y sus 4 roles con prefijo: `PRA_ADMIN`, `PRA_EMPRESA`, `PRA_ESTUDIANTE`, `PRA_SUPERVISOR` | `rol.codigo` es único en toda la base; sin prefijo podría chocar con los roles de otros módulos |
| `comun.notificacion.tipo_evento` | Reservar los valores que usaremos: `convocatoria_enviada`, `convocatoria_observada`, `convocatoria_publicada`, `postulacion_resultado`, `practica_enviada`, `practica_observada`, `practica_validada`, `supervisor_asignado`, `informe_observado`, `practica_cerrada` | Para que no se repitan ni choquen con los de otros módulos |
| Inventario de la megabase | Actualizar el conteo: `gestion_practicas` pasa de 10 a 11 tablas y el total, de 220 a 221 | Para que el PDF de esquemas y el conteo de verificación de la guía coincidan con lo que se crea |

Quedamos atentos a cualquier observación sobre esta propuesta, sobre todo a los seis puntos anteriores, para poder cerrar el diseño y pasarlo a las migraciones.
