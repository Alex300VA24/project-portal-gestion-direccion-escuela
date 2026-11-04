-- ============================================================
-- GESTIÓN DE HORARIOS — 02 TABLAS ADICIONALES (4 nuevas)
-- ============================================================
-- ORIGEN:
-- Propuesta de ampliación al modelo modular original (11 tablas).
-- Derivada del análisis de los diagramas Bizagi de Gestión de
-- Horarios, que contienen procedimientos no representados
-- completamente por las tablas originales.
--
-- ESTAS CUATRO TABLAS NO FORMABAN PARTE DEL MODELO ORIGINAL.
-- Se documentan aquí y en docs/gestion_horarios/README.md para el
-- trabajo colaborativo del equipo.
-- ============================================================

-- ============================================================
-- TABLA: gestion_horarios.apertura_repeticion
-- ============================================================
-- ORIGEN:
-- Propuesta adicional al modelo modular original.
-- Derivada del proceso Bizagi de Gestión de Horarios.
--
-- PROPÓSITO:
-- Registrar las solicitudes y autorizaciones de apertura
-- de cursos de repetición.
--
-- PROCEDIMIENTO:
-- En el diagrama Bizagi existe la decisión "¿Se autorizó
-- apertura?". Si la solicitud se DENIEGA, el curso de repetición
-- no debe incorporarse al horario. Si se AUTORIZA, puede
-- asociarse una sección, asignarse un docente, registrar su
-- disponibilidad obligatoria e incorporarse la sección al
-- horario.
--
-- RELACIONES:
-- core.periodo_academico
-- core.curso_plan
-- core.seccion
-- core.docente
-- seguridad.usuario
--
-- IMPORTANTE:
-- No reemplaza ni duplica core.seccion. La clave foránea
-- compuesta (id_seccion, id_periodo, id_curso_plan) garantiza que
-- la sección vinculada pertenezca al mismo período y curso de la
-- solicitud. Las reglas de transición (denegada no puede quedar
-- incorporada; sin autorización no se incorpora) se imponen con
-- el disparador fn_transicion_apertura (script 03).
-- ============================================================
CREATE TABLE gestion_horarios.apertura_repeticion (
    id_apertura BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    id_curso_plan BIGINT NOT NULL,
    id_seccion BIGINT NULL,
    id_docente_propuesto BIGINT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'SOLICITADA',
    solicitado_por BIGINT NOT NULL,
    autorizado_por BIGINT NULL,
    solicitado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    resuelto_en TIMESTAMPTZ NULL,
    observacion TEXT NULL,
    CONSTRAINT pk_apertura_repeticion PRIMARY KEY (id_apertura),
    CONSTRAINT fk_apertura_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_curso_plan FOREIGN KEY (id_curso_plan)
        REFERENCES core.curso_plan (id_curso_plan) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_seccion_periodo_curso FOREIGN KEY (id_seccion, id_periodo, id_curso_plan)
        REFERENCES core.seccion (id_seccion, id_periodo, id_curso_plan) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_docente_propuesto FOREIGN KEY (id_docente_propuesto)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_solicitado_por FOREIGN KEY (solicitado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_apertura_autorizado_por FOREIGN KEY (autorizado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT ck_apertura_estado CHECK (estado IN (
        'SOLICITADA', 'AUTORIZADA', 'DENEGADA', 'INCORPORADA'
    )),
    CONSTRAINT ck_apertura_solicitada CHECK (
        estado <> 'SOLICITADA'
        OR (autorizado_por IS NULL AND resuelto_en IS NULL)
    ),
    CONSTRAINT ck_apertura_resuelta CHECK (
        estado NOT IN ('AUTORIZADA', 'DENEGADA', 'INCORPORADA')
        OR (autorizado_por IS NOT NULL AND resuelto_en IS NOT NULL)
    ),
    CONSTRAINT ck_apertura_incorporada CHECK (
        estado <> 'INCORPORADA'
        OR (id_seccion IS NOT NULL AND id_docente_propuesto IS NOT NULL)
    ),
    CONSTRAINT ck_apertura_fechas CHECK (resuelto_en IS NULL OR resuelto_en >= solicitado_en)
);

-- ============================================================
-- TABLA: gestion_horarios.declaracion_disponibilidad
-- ============================================================
-- ORIGEN:
-- Propuesta adicional al modelo modular original.
-- Derivada del proceso Bizagi de Gestión de Horarios.
--
-- PROPÓSITO:
-- Registrar si un docente presentó formalmente su disponibilidad
-- para un periodo académico. Complementa a
-- gestion_horarios.disponibilidad_docente, que almacena los
-- bloques de tiempo disponibles.
--
-- PROBLEMA QUE RESUELVE:
-- disponibilidad_docente por sí sola no distingue entre un
-- docente que presentó disponibilidad, uno que no la presentó,
-- uno pendiente de registrarla y uno al que se aplica política
-- flexible. La declaración formal separa el estado de "presentó o
-- no" del detalle de bloques disponibles.
--
-- PROCEDIMIENTO:
-- La apertura de una repetición exige comprobar que el docente
-- asignado tenga estado PRESENTADA en el período correspondiente
-- antes de quedar INCORPORADA.
--
-- RELACIONES:
-- core.periodo_academico
-- core.docente
-- seguridad.usuario
-- gestion_horarios.disponibilidad_docente (id_declaracion)
--
-- IMPORTANTE:
-- La disponibilidad obligatoria de una repetición se comprueba
-- sobre la actividad o sección correspondiente; esta tabla no
-- convierte esa exigencia en una condición global para todos los
-- cursos del docente.
-- ============================================================
CREATE TABLE gestion_horarios.declaracion_disponibilidad (
    id_declaracion BIGINT GENERATED ALWAYS AS IDENTITY,
    id_periodo BIGINT NOT NULL,
    id_docente BIGINT NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    tratamiento_regular VARCHAR(20) NOT NULL DEFAULT 'ESTRICTA',
    presentado_en TIMESTAMPTZ NULL,
    id_registrado_por BIGINT NULL,
    observacion TEXT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_declaracion_disponibilidad PRIMARY KEY (id_declaracion),
    CONSTRAINT fk_declaracion_periodo FOREIGN KEY (id_periodo)
        REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
    CONSTRAINT fk_declaracion_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_declaracion_registrado_por FOREIGN KEY (id_registrado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT uq_declaracion_periodo_docente UNIQUE (id_periodo, id_docente),
    -- Habilita la clave foránea compuesta desde disponibilidad_docente
    -- para exigir que los bloques correspondan a la declaración del
    -- MISMO docente y período.
    CONSTRAINT uq_declaracion_compuesta UNIQUE (id_declaracion, id_periodo, id_docente),
    CONSTRAINT ck_declaracion_estado
        CHECK (estado IN ('PENDIENTE', 'PRESENTADA', 'NO_PRESENTADA')),
    CONSTRAINT ck_declaracion_tratamiento
        CHECK (tratamiento_regular IN ('ESTRICTA', 'FLEXIBLE')),
    CONSTRAINT ck_declaracion_presentada CHECK (
        (estado = 'PRESENTADA' AND presentado_en IS NOT NULL)
        OR (estado <> 'PRESENTADA')
    )
);

-- ============================================================
-- TABLA: gestion_horarios.validacion_horario
-- ============================================================
-- ORIGEN:
-- Propuesta adicional al modelo modular original.
-- Derivada del proceso Bizagi de Gestión de Horarios.
--
-- PROPÓSITO:
-- Registrar cada ejecución del proceso de validación de una
-- propuesta de horario: qué versión se evaluó, cuándo, con qué
-- reglas y con qué resultado. Las validaciones son evidencia
-- histórica y no se borran cuando se genera una versión corregida.
--
-- PROCEDIMIENTO BIZAGI:
-- "Generar propuesta de horario" -> "Validar restricciones" ->
-- "¿El horario es válido?" -> corregir o regenerar si hay
-- conflictos.
--
-- HUELLA DE LA PROPUESTA:
-- md5 del contenido del horario en el momento de la validación,
-- calculado de forma determinista por
-- gestion_horarios.fn_huella_horario(id_horario) a partir de:
--   id_detalle | id_actividad | dia_semana | hora_inicio | hora_fin
--   | tipo_actividad | id_docente activo (o SIN_DOCENTE)
-- ordenados por id_detalle y unidos con salto de línea, y luego
-- hasheados con md5. El disparador fn_validacion_horario IMPONE
-- ese valor al insertar: la huella no la aporta el cliente, por lo
-- que acredita exactamente el contenido evaluado. Si el contenido
-- cambia después, la huella vigente deja de coincidir y la
-- validación deja de ser apta para aprobar/publicar.
--
-- RELACIONES:
-- gestion_horarios.horario
-- seguridad.usuario
-- gestion_horarios.conflicto_horario (id_validacion)
--
-- REGLAS:
-- Una propuesta con conflictos duros no puede quedar VALIDA
-- (disparador sobre conflicto_horario). Una versión no puede
-- aprobarse ni publicarse sin una validación vigente y válida
-- (disparador sobre horario).
-- ============================================================
CREATE TABLE gestion_horarios.validacion_horario (
    id_validacion BIGINT GENERATED ALWAYS AS IDENTITY,
    id_horario BIGINT NOT NULL,
    resultado VARCHAR(10) NOT NULL,
    validado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    id_validado_por BIGINT NULL,
    version_reglas VARCHAR(40) NOT NULL,
    huella_propuesta VARCHAR(64) NOT NULL,
    observacion TEXT NULL,
    CONSTRAINT pk_validacion_horario PRIMARY KEY (id_validacion),
    CONSTRAINT fk_validacion_horario FOREIGN KEY (id_horario)
        REFERENCES gestion_horarios.horario (id_horario) ON DELETE CASCADE,
    CONSTRAINT fk_validacion_validado_por FOREIGN KEY (id_validado_por)
        REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT ck_validacion_resultado CHECK (resultado IN ('VALIDA', 'INVALIDA')),
    CONSTRAINT ck_validacion_huella CHECK (huella_propuesta ~ '^[0-9a-f]{32}$'),
    CONSTRAINT ck_validacion_version_reglas CHECK (length(trim(version_reglas)) > 0)
);

-- ============================================================
-- TABLA: gestion_horarios.conflicto_horario
-- ============================================================
-- ORIGEN:
-- Propuesta adicional al modelo modular original.
-- Derivada del proceso Bizagi de Gestión de Horarios.
--
-- PROPÓSITO:
-- Registrar los conflictos detectados durante la validación de una
-- propuesta de horario y conservar su evidencia histórica, incluso
-- cuando se genere una versión corregida.
--
-- PROCEDIMIENTO BIZAGI:
-- Detección de conflictos -> reporte -> corrección o regeneración
-- del horario.
--
-- RELACIONES:
-- gestion_horarios.validacion_horario (obligatoria: no existen
--   conflictos huérfanos)
-- gestion_horarios.detalle_horario (opcional: uno o dos detalles
--   afectados; un conflicto también puede afectar a un docente o a
--   un ambiente sin necesitar dos actividades)
-- core.docente
-- core.ambiente
--
-- REGLAS:
-- El disparador fn_conflicto_horario exige que los detalles
-- referenciados pertenezcan al horario de la validación que detectó
-- el conflicto y prohíbe un conflicto DURA sobre una validación
-- declarada VALIDA.
-- ============================================================
CREATE TABLE gestion_horarios.conflicto_horario (
    id_conflicto BIGINT GENERATED ALWAYS AS IDENTITY,
    id_validacion BIGINT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    severidad VARCHAR(10) NOT NULL,
    id_detalle_a BIGINT NULL,
    id_detalle_b BIGINT NULL,
    id_docente BIGINT NULL,
    id_ambiente BIGINT NULL,
    descripcion TEXT NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT pk_conflicto_horario PRIMARY KEY (id_conflicto),
    CONSTRAINT fk_conflicto_validacion FOREIGN KEY (id_validacion)
        REFERENCES gestion_horarios.validacion_horario (id_validacion) ON DELETE RESTRICT,
    CONSTRAINT fk_conflicto_detalle_a FOREIGN KEY (id_detalle_a)
        REFERENCES gestion_horarios.detalle_horario (id_detalle) ON DELETE RESTRICT,
    CONSTRAINT fk_conflicto_detalle_b FOREIGN KEY (id_detalle_b)
        REFERENCES gestion_horarios.detalle_horario (id_detalle) ON DELETE RESTRICT,
    CONSTRAINT fk_conflicto_docente FOREIGN KEY (id_docente)
        REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
    CONSTRAINT fk_conflicto_ambiente FOREIGN KEY (id_ambiente)
        REFERENCES core.ambiente (id_ambiente) ON DELETE RESTRICT,
    CONSTRAINT ck_conflicto_tipo CHECK (tipo IN (
        'CRUCE_DOCENTE', 'CRUCE_AMBIENTE', 'CAPACIDAD_INSUFICIENTE',
        'AMBIENTE_INCOMPATIBLE', 'FUERA_DISPONIBILIDAD', 'AMBIENTE_BLOQUEADO',
        'HORAS_INCOMPLETAS', 'PERIODO_INCOMPATIBLE', 'APERTURA_NO_AUTORIZADA', 'OTRO'
    )),
    CONSTRAINT ck_conflicto_severidad CHECK (severidad IN ('DURA', 'BLANDA')),
    CONSTRAINT ck_conflicto_descripcion CHECK (length(trim(descripcion)) > 0),
    CONSTRAINT ck_conflicto_detalles_distintos CHECK (
        id_detalle_a IS NULL OR id_detalle_b IS NULL OR id_detalle_a <> id_detalle_b
    )
);
