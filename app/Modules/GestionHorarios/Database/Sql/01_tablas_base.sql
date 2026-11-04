-- ============================================================
-- GESTIÓN DE HORARIOS — 01 TABLAS BASE (11 tablas originales)
-- ============================================================
-- ORIGEN:
-- Modelo de base de datos modular del Sistema Académico UNT.
-- IMPORTANTE: el repositorio no contiene `base_datos_modular.sql`;
-- estas definiciones se reconstruyen a partir de la especificación
-- del modelo y del procedimiento Bizagi de Gestión de Horarios.
-- Deben reconciliarse con el SQL modular definitivo cuando esté
-- disponible (ver docs/gestion_horarios/README.md, sección
-- "Compatibilidad con el modelo modular").
--
-- ORDEN DE EJECUCIÓN:
--   1) database/shared/01_core_seguridad.sql
--   2) database/gestion_horarios/01_tablas_base.sql       (este)
--   3) database/gestion_horarios/02_tablas_adicionales.sql
--   4) database/gestion_horarios/03_restricciones_indices.sql
--   5) database/gestion_horarios/04_comentarios.sql
--   6) database/gestion_horarios/05_pruebas_integridad.sql (pruebas)
-- ============================================================

CREATE SCHEMA IF NOT EXISTS gestion_horarios;

-- ------------------------------------------------------------
-- 1. gestion_horarios.oferta_curso
-- ------------------------------------------------------------
-- Oferta de un curso en un período (regular o de repetición).
-- Agrupa las secciones y las asignaciones que se programarán.
CREATE TABLE gestion_horarios.oferta_curso (
    id_oferta BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    id_curso_plan BIGINT NOT NULL,
    tipo_oferta VARCHAR(20) NOT NULL DEFAULT 'REGULAR',
    docentes_minimos SMALLINT NOT NULL DEFAULT 1,
    estado VARCHAR(20) NOT NULL DEFAULT 'PLANIFICADA',
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_oferta_curso PRIMARY KEY (id_oferta),
    CONSTRAINT fk_oferta_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_oferta_curso_plan FOREIGN KEY (id_curso_plan)
        REFERENCES core.curso_plan (id_curso_plan) ON DELETE RESTRICT,
    CONSTRAINT uq_oferta_curso UNIQUE (id_periodo, id_curso_plan, tipo_oferta),
    CONSTRAINT ck_oferta_tipo CHECK (tipo_oferta IN ('REGULAR', 'REPETICION')),
    CONSTRAINT ck_oferta_estado CHECK (estado IN ('PLANIFICADA', 'OFERTADA', 'CERRADA')),
    CONSTRAINT ck_oferta_docentes_minimos CHECK (docentes_minimos > 0)
);

-- ------------------------------------------------------------
-- 2. gestion_horarios.actividad_academica
-- ------------------------------------------------------------
-- Unidad mínima programable: un tipo de sesión (teoría, práctica,
-- laboratorio) de una sección en un día y rango horario.
CREATE TABLE gestion_horarios.actividad_academica (
    id_actividad BIGINT GENERATED ALWAYS AS IDENTITY,
    id_seccion BIGINT NOT NULL,
    tipo_actividad VARCHAR(20) NOT NULL DEFAULT 'TEORIA',
    dia_semana VARCHAR(10) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    minutos_sesion SMALLINT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_actividad_academica PRIMARY KEY (id_actividad),
    CONSTRAINT fk_actividad_seccion FOREIGN KEY (id_seccion)
        REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
    CONSTRAINT ck_actividad_tipo
        CHECK (tipo_actividad IN ('TEORIA', 'PRACTICA', 'LABORATORIO', 'TALLER')),
    CONSTRAINT ck_actividad_dia
        CHECK (dia_semana IN ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO')),
    CONSTRAINT ck_actividad_horas CHECK (hora_inicio < hora_fin),
    CONSTRAINT ck_actividad_minutos CHECK (minutos_sesion IS NULL OR minutos_sesion > 0)
);

-- ------------------------------------------------------------
-- 3. gestion_horarios.asignacion_docente
-- ------------------------------------------------------------
-- Docente asignado a una actividad. Permite historia de cambios
-- (PROPUESTA/CONFIRMADA/RECHAZADA/REASIGNADA); sólo puede haber
-- UNA asignación activa por actividad (índice único parcial en 03).
-- id_apertura enlaza la asignación con la autorización de apertura
-- cuando la actividad proviene de una repetición (FK en 03).
CREATE TABLE gestion_horarios.asignacion_docente (
    id_asignacion BIGINT GENERATED ALWAYS AS IDENTITY,
    id_actividad BIGINT NOT NULL,
    id_docente BIGINT NOT NULL,
    id_apertura BIGINT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'PROPUESTA',
    observacion TEXT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_asignacion_docente PRIMARY KEY (id_asignacion),
    CONSTRAINT fk_asignacion_actividad FOREIGN KEY (id_actividad)
        REFERENCES gestion_horarios.actividad_academica (id_actividad) ON DELETE RESTRICT,
    CONSTRAINT fk_asignacion_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT ck_asignacion_estado
        CHECK (estado IN ('PROPUESTA', 'CONFIRMADA', 'RECHAZADA', 'REASIGNADA'))
);

-- ------------------------------------------------------------
-- 4. gestion_horarios.bloque_horario
-- ------------------------------------------------------------
-- Bloque de la grilla horaria de un período (por ejemplo
-- 07:00-07:50). Sirve de referencia para construir propuestas y
-- para declarar disponibilidades.
CREATE TABLE gestion_horarios.bloque_horario (
    id_bloque BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    orden SMALLINT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_bloque_horario PRIMARY KEY (id_bloque),
    CONSTRAINT fk_bloque_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT uq_bloque_periodo_inicio UNIQUE (id_periodo, hora_inicio),
    CONSTRAINT uq_bloque_periodo_compuesto UNIQUE (id_bloque, id_periodo),
    CONSTRAINT ck_bloque_horas CHECK (hora_inicio < hora_fin),
    CONSTRAINT ck_bloque_orden CHECK (orden IS NULL OR orden > 0)
);

-- ------------------------------------------------------------
-- 5. gestion_horarios.disponibilidad_docente
-- ------------------------------------------------------------
-- Bloques de tiempo en que un docente está disponible en un
-- período. La declaración FORMAL (presentó o no su disponibilidad)
-- vive en declaracion_disponibilidad; aquí sólo se guardan bloques.
-- La columna id_declaracion (FK compuesta en 03) garantiza que los
-- bloques pertenezcan a la declaración del MISMO docente y período.
CREATE TABLE gestion_horarios.disponibilidad_docente (
    id_disponibilidad BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    id_docente BIGINT NOT NULL,
    id_declaracion BIGINT NULL,
    dia_semana VARCHAR(10) NOT NULL,
    id_bloque BIGINT NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_disponibilidad_docente PRIMARY KEY (id_disponibilidad),
    CONSTRAINT fk_disponibilidad_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_disponibilidad_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_disponibilidad_bloque FOREIGN KEY (id_bloque)
        REFERENCES gestion_horarios.bloque_horario (id_bloque) ON DELETE RESTRICT,
    CONSTRAINT fk_disponibilidad_bloque_periodo FOREIGN KEY (id_bloque, id_periodo)
        REFERENCES gestion_horarios.bloque_horario (id_bloque, id_periodo) ON DELETE RESTRICT,
    CONSTRAINT uq_disponibilidad UNIQUE (id_periodo, id_docente, dia_semana, id_bloque),
    CONSTRAINT ck_disponibilidad_dia
        CHECK (dia_semana IN ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'))
);

-- ------------------------------------------------------------
-- 6. gestion_horarios.ambiente_bloqueo
-- ------------------------------------------------------------
-- Bloqueos de aulas (mantenimiento, reserva, examen). La
-- validación AMBIENTE_BLOQUEADO los contrasta con la propuesta.
CREATE TABLE gestion_horarios.ambiente_bloqueo (
    id_bloqueo BIGINT GENERATED ALWAYS AS IDENTITY,
    id_ambiente BIGINT NOT NULL,
    id_periodo BIGINT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    dia_semana VARCHAR(10) NULL,
    hora_inicio TIME NULL,
    hora_fin TIME NULL,
    motivo TEXT NOT NULL,
    id_registrado_por BIGINT NULL,
    registrado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_ambiente_bloqueo PRIMARY KEY (id_bloqueo),
    CONSTRAINT fk_bloqueo_ambiente FOREIGN KEY (id_ambiente)
        REFERENCES core.ambiente (id_ambiente) ON DELETE RESTRICT,
    CONSTRAINT fk_bloqueo_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_bloqueo_usuario FOREIGN KEY (id_registrado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT ck_bloqueo_fechas CHECK (fecha_inicio <= fecha_fin),
    CONSTRAINT ck_bloqueo_horas CHECK (
        (hora_inicio IS NULL AND hora_fin IS NULL)
        OR (hora_inicio IS NOT NULL AND hora_fin IS NOT NULL AND hora_inicio < hora_fin)
    ),
    CONSTRAINT ck_bloqueo_dia
        CHECK (dia_semana IS NULL
            OR dia_semana IN ('LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO')),
    CONSTRAINT ck_bloqueo_motivo CHECK (length(trim(motivo)) > 0)
);

-- ------------------------------------------------------------
-- 7. gestion_horarios.horario
-- ------------------------------------------------------------
-- Versión de un horario para un período y ámbito. Controla
-- versionado (version) y ciclo de vida:
--   BORRADOR -> GENERADO -> VALIDADO -> EN_REVISION -> APROBADO
--             -> PUBLICADO -> ARCHIVADO
-- Los estados, la aprobación condicionada a una validación vigente
-- y la inmutabilidad de la versión publicada se imponen mediante
-- el disparador fn_transicion_horario (script 03).
CREATE TABLE gestion_horarios.horario (
    id_horario BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    ambito VARCHAR(40) NOT NULL DEFAULT 'GENERAL',
    version SMALLINT NOT NULL DEFAULT 1,
    estado VARCHAR(20) NOT NULL DEFAULT 'BORRADOR',
    id_creado_por BIGINT NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    id_aprobado_por BIGINT NULL,
    aprobado_en TIMESTAMPTZ NULL,
    publicado_en TIMESTAMPTZ NULL,
    CONSTRAINT pk_horario PRIMARY KEY (id_horario),
    CONSTRAINT fk_horario_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_horario_creado_por FOREIGN KEY (id_creado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_horario_aprobado_por FOREIGN KEY (id_aprobado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT uq_horario_version UNIQUE (id_periodo, ambito, version),
    CONSTRAINT ck_horario_version CHECK (version > 0),
    CONSTRAINT ck_horario_estado CHECK (estado IN (
        'BORRADOR', 'GENERADO', 'VALIDADO', 'EN_REVISION',
        'APROBADO', 'PUBLICADO', 'ARCHIVADO'
    )),
    CONSTRAINT ck_horario_aprobacion CHECK (
        (estado IN ('APROBADO', 'PUBLICADO')
            AND id_aprobado_por IS NOT NULL AND aprobado_en IS NOT NULL)
        OR (estado NOT IN ('APROBADO', 'PUBLICADO'))
    ),
    CONSTRAINT ck_horario_publicacion CHECK (
        publicado_en IS NULL OR estado IN ('PUBLICADO', 'ARCHIVADO')
    )
);

-- ------------------------------------------------------------
-- 8. gestion_horarios.detalle_horario
-- ------------------------------------------------------------
-- Incorporación de una actividad a una versión de horario.
-- Se conserva la restricción UNIQUE original (id_horario,
-- id_actividad); es NECESARIA pero NO SUFICIENTE para evitar
-- cruces: la superposición real, los cruces de docente/ambiente,
-- los bloqueos y la capacidad se evalúan en la validación
-- (validacion_horario + conflicto_horario).
CREATE TABLE gestion_horarios.detalle_horario (
    id_detalle BIGINT GENERATED ALWAYS AS IDENTITY,
    id_horario BIGINT NOT NULL,
    id_actividad BIGINT NOT NULL,
    id_ambiente BIGINT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_detalle_horario PRIMARY KEY (id_detalle),
    CONSTRAINT fk_detalle_horario FOREIGN KEY (id_horario)
        REFERENCES gestion_horarios.horario (id_horario) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_actividad FOREIGN KEY (id_actividad)
        REFERENCES gestion_horarios.actividad_academica (id_actividad) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_ambiente FOREIGN KEY (id_ambiente)
        REFERENCES core.ambiente (id_ambiente) ON DELETE RESTRICT,
    CONSTRAINT uq_detalle_horario_actividad UNIQUE (id_horario, id_actividad)
);

-- ------------------------------------------------------------
-- 9. gestion_horarios.restriccion
-- ------------------------------------------------------------
-- Catálogo de reglas evaluables. `tipo` usa los mismos códigos que
-- los conflictos y `severidad` distingue DURA (bloquea la
-- aprobación) de BLANDA (sólo observa).
CREATE TABLE gestion_horarios.restriccion (
    id_restriccion BIGINT GENERATED ALWAYS AS IDENTITY,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    severidad VARCHAR(10) NOT NULL,
    activa BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_restriccion PRIMARY KEY (id_restriccion),
    CONSTRAINT uq_restriccion_codigo UNIQUE (codigo),
    CONSTRAINT ck_restriccion_tipo CHECK (tipo IN (
        'CRUCE_DOCENTE', 'CRUCE_AMBIENTE', 'CAPACIDAD_INSUFICIENTE',
        'AMBIENTE_INCOMPATIBLE', 'FUERA_DISPONIBILIDAD', 'AMBIENTE_BLOQUEADO',
        'HORAS_INCOMPLETAS', 'PERIODO_INCOMPATIBLE', 'APERTURA_NO_AUTORIZADA', 'OTRO'
    )),
    CONSTRAINT ck_restriccion_severidad CHECK (severidad IN ('DURA', 'BLANDA')),
    CONSTRAINT ck_restriccion_descripcion CHECK (length(trim(descripcion)) > 0)
);

-- ------------------------------------------------------------
-- 10. gestion_horarios.detalle_restriccion
-- ------------------------------------------------------------
-- Parámetros y ámbito de aplicación de una restricción. El CHECK
-- impone coherencia entre `ambito` y las referencias opcionales.
CREATE TABLE gestion_horarios.detalle_restriccion (
    id_detalle_restriccion BIGINT GENERATED ALWAYS AS IDENTITY,
    id_restriccion BIGINT NOT NULL,
    ambito VARCHAR(20) NOT NULL DEFAULT 'GLOBAL',
    id_periodo BIGINT NULL,
    id_docente BIGINT NULL,
    id_ambiente BIGINT NULL,
    parametros JSONB NOT NULL DEFAULT '{}'::jsonb,
    prioridad SMALLINT NOT NULL DEFAULT 100,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_detalle_restriccion PRIMARY KEY (id_detalle_restriccion),
    CONSTRAINT fk_detalle_restriccion_restriccion FOREIGN KEY (id_restriccion)
        REFERENCES gestion_horarios.restriccion (id_restriccion) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_restriccion_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_restriccion_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_detalle_restriccion_ambiente FOREIGN KEY (id_ambiente)
        REFERENCES core.ambiente (id_ambiente) ON DELETE RESTRICT,
    CONSTRAINT ck_detalle_restriccion_ambito CHECK (
        (ambito = 'GLOBAL'
            AND id_periodo IS NULL AND id_docente IS NULL AND id_ambiente IS NULL)
        OR (ambito = 'PERIODO'
            AND id_periodo IS NOT NULL AND id_docente IS NULL AND id_ambiente IS NULL)
        OR (ambito = 'DOCENTE'
            AND id_docente IS NOT NULL AND id_periodo IS NULL AND id_ambiente IS NULL)
        OR (ambito = 'AMBIENTE'
            AND id_ambiente IS NOT NULL AND id_periodo IS NULL AND id_docente IS NULL)
    ),
    CONSTRAINT ck_detalle_restriccion_prioridad CHECK (prioridad > 0)
);

-- ------------------------------------------------------------
-- 11. gestion_horarios.horario_revision
-- ------------------------------------------------------------
-- Revisiones formales de una versión de horario (revisión humana
-- posterior a la validación técnica). Conserva evidencia histórica.
CREATE TABLE gestion_horarios.horario_revision (
    id_revision BIGINT GENERATED ALWAYS AS IDENTITY,
    id_horario BIGINT NOT NULL,
    numero_revision SMALLINT NOT NULL DEFAULT 1,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    id_revisor BIGINT NULL,
    revisado_en TIMESTAMPTZ NULL,
    observacion TEXT NULL,
    CONSTRAINT pk_horario_revision PRIMARY KEY (id_revision),
    CONSTRAINT fk_horario_revision_horario FOREIGN KEY (id_horario)
        REFERENCES gestion_horarios.horario (id_horario) ON DELETE CASCADE,
    CONSTRAINT fk_horario_revision_revisor FOREIGN KEY (id_revisor)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT uq_horario_revision_numero UNIQUE (id_horario, numero_revision),
    CONSTRAINT ck_horario_revision_numero CHECK (numero_revision > 0),
    CONSTRAINT ck_horario_revision_estado
        CHECK (estado IN ('PENDIENTE', 'APROBADA', 'OBSERVADA', 'RECHAZADA')),
    CONSTRAINT ck_horario_revision_fechas CHECK (
        (estado = 'PENDIENTE' AND revisado_en IS NULL)
        OR (estado <> 'PENDIENTE' AND revisado_en IS NOT NULL)
    )
);
