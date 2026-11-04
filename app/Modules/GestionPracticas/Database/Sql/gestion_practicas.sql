-- =====================================================================
-- gestion_practicas.sql
-- Megabase de la Escuela de Ingeniería Informática (PostgreSQL 16+)
-- Esquema PROPIO del proyecto Gestión de Prácticas: 11 tablas.
--
-- Este script SOLO crea las tablas de gestion_practicas. NO crea tablas
-- de core, seguridad ni comun (las crea otro equipo). Debe ejecutarse
-- DESPUÉS de que existan estas tablas compartidas, porque las FK las
-- referencian:
--   core.estudiante, core.docente, core.periodo_academico
--   seguridad.usuario
--   comun.empresa, comun.contacto_empresa, comun.archivo
--
-- Marcas en los comentarios:
--   [NUEVO]   campo, tabla o restricción que no estaba en el PDF de la
--             megabase y se agrega porque un proceso lo necesita
--   [CAMBIO]  campo que ya estaba pero cambia (por ejemplo, pasa a NULL)
--   sin marca = tal como figura en el PDF de la megabase
-- La explicación de cada cambio está en gestion_practicas_tablas.md.
--
-- Los valores de los CHECK (estados, tipos) son una propuesta: confirmar
-- en el diccionario de datos antes de pasarlos a migraciones de Laravel.
-- =====================================================================

CREATE SCHEMA IF NOT EXISTS gestion_practicas;

-- ---------------------------------------------------------------------
-- 1. empresa_usuario  [NUEVO]  (tabla nueva: el esquema pasa de 10 a 11 tablas)
--    Vincula una cuenta de seguridad.usuario con la empresa que
--    representa. El modelo original tenía EMPRESA.id_usuario; en la
--    megabase ese vínculo desapareció. Se resuelve con tabla puente del
--    módulo, como indica la descripción del esquema comun, sin tocar
--    comun.empresa ni comun.contacto_empresa.
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.empresa_usuario (
    id_empresa    bigint      NOT NULL,
    id_usuario    bigint      NOT NULL,
    es_principal  boolean     NOT NULL DEFAULT false,
    estado        varchar(10) NOT NULL DEFAULT 'activo',
    asignado_por  bigint      NOT NULL,           -- administrador que creó el acceso
    asignado_en   timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT gp_eu_pk PRIMARY KEY (id_empresa, id_usuario),
    CONSTRAINT gp_eu_emp_fk FOREIGN KEY (id_empresa)  REFERENCES comun.empresa (id_empresa),
    CONSTRAINT gp_eu_usu_fk FOREIGN KEY (id_usuario)  REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_eu_asg_fk FOREIGN KEY (asignado_por) REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_eu_estado_ck CHECK (estado IN ('activo', 'inactivo'))
);
-- Una cuenta representa a una sola empresa a la vez.
CREATE UNIQUE INDEX gp_eu_usu_activo_uq
    ON gestion_practicas.empresa_usuario (id_usuario) WHERE estado = 'activo';

-- ---------------------------------------------------------------------
-- 2. convocatoria
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.convocatoria (
    id_convocatoria    bigint GENERATED ALWAYS AS IDENTITY,
    id_empresa         bigint       NOT NULL,
    titulo             varchar(200) NOT NULL,
    descripcion        text         NOT NULL,
    requisitos         text         NOT NULL,                 -- [NUEVO]
    enlace_postulacion varchar(500) NOT NULL,                 -- [NUEVO]
    tipo_practica      varchar(20)  NOT NULL,
    modalidad          varchar(15)  NOT NULL,
    vacantes           smallint     NOT NULL,
    fecha_publicacion  timestamptz,
    fecha_cierre       date         NOT NULL,
    estado             varchar(15)  NOT NULL DEFAULT 'borrador',
    registrada_por     bigint       NOT NULL,
    registrada_en      timestamptz  NOT NULL DEFAULT now(),   -- [NUEVO]
    enviada_en         timestamptz,                           -- [NUEVO] último envío a revisión
    publicada_por      bigint,                                -- [NUEVO] administrador que aprobó
    CONSTRAINT gp_conv_pk PRIMARY KEY (id_convocatoria),
    CONSTRAINT gp_conv_emp_fk FOREIGN KEY (id_empresa)     REFERENCES comun.empresa (id_empresa),
    CONSTRAINT gp_conv_reg_fk FOREIGN KEY (registrada_por) REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_conv_pub_fk FOREIGN KEY (publicada_por)  REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_conv_tipo_ck   CHECK (tipo_practica IN ('preprofesional', 'profesional')),
    CONSTRAINT gp_conv_modal_ck  CHECK (modalidad IN ('presencial', 'remota', 'hibrida')),
    CONSTRAINT gp_conv_vac_ck    CHECK (vacantes > 0),
    CONSTRAINT gp_conv_enlace_ck CHECK (enlace_postulacion ~* '^https?://'),
    CONSTRAINT gp_conv_estado_ck CHECK (estado IN
        ('borrador', 'en_revision', 'observada', 'publicada', 'cerrada', 'anulada')),        -- [NUEVO]
    CONSTRAINT gp_conv_pub_ck CHECK (
        estado NOT IN ('publicada', 'cerrada')
        OR (fecha_publicacion IS NOT NULL AND publicada_por IS NOT NULL)),
    CONSTRAINT gp_conv_fechas_ck CHECK (                                                   -- [NUEVO]
        fecha_publicacion IS NULL
        OR fecha_cierre >= (fecha_publicacion AT TIME ZONE 'America/Lima')::date)
);

-- ---------------------------------------------------------------------
-- 3. observacion_convocatoria
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.observacion_convocatoria (
    id_observacion  bigint GENERATED ALWAYS AS IDENTITY,
    id_convocatoria bigint      NOT NULL,
    descripcion     text        NOT NULL,
    estado          varchar(10) NOT NULL DEFAULT 'pendiente',
    registrado_por  bigint      NOT NULL,
    registrado_en   timestamptz NOT NULL DEFAULT now(),
    subsanada_en    timestamptz,                                   -- [NUEVO]
    CONSTRAINT gp_obsc_pk PRIMARY KEY (id_observacion),
    CONSTRAINT gp_obsc_conv_fk FOREIGN KEY (id_convocatoria)
        REFERENCES gestion_practicas.convocatoria (id_convocatoria) ON DELETE CASCADE,
    CONSTRAINT gp_obsc_reg_fk FOREIGN KEY (registrado_por) REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_obsc_estado_ck CHECK (estado IN ('pendiente', 'subsanada')),
    CONSTRAINT gp_obsc_subs_ck CHECK ((estado = 'subsanada') = (subsanada_en IS NOT NULL))
);

-- ---------------------------------------------------------------------
-- 4. practica
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.practica (
    id_practica             bigint GENERATED ALWAYS AS IDENTITY,
    id_estudiante           bigint       NOT NULL,
    id_empresa              bigint       NOT NULL,
    id_docente_supervisor   bigint,                   -- [CAMBIO] acepta NULL hasta que el admin asigne
    id_contacto_jefe        bigint,
    id_periodo              bigint,                   -- [NUEVO] para reportes por semestre
    area                    varchar(150) NOT NULL,
    funciones               text         NOT NULL,    -- [NUEVO]
    tipo_practica           varchar(20)  NOT NULL,
    fecha_inicio            date         NOT NULL,
    fecha_fin               date         NOT NULL,
    horas_totales           smallint     NOT NULL,    -- horas planificadas (ver R5)
    estado                  varchar(15)  NOT NULL DEFAULT 'registrada',
    registrada_en           timestamptz  NOT NULL DEFAULT now(),  -- [NUEVO]
    enviada_en              timestamptz,                          -- [NUEVO] último envío a revisión
    validado_por            bigint,                               -- [NUEVO]
    validada_en             timestamptz,                          -- [NUEVO]
    supervisor_asignado_por bigint,                               -- [NUEVO]
    supervisor_asignado_en  timestamptz,                          -- [NUEVO]
    nota_final              numeric(4,2),                         -- [NUEVO]
    resultado               varchar(12),                          -- [NUEVO]
    cerrada_por             bigint,                               -- [NUEVO]
    cerrada_en              timestamptz,                          -- [NUEVO]
    CONSTRAINT gp_prac_pk PRIMARY KEY (id_practica),
    CONSTRAINT gp_prac_est_fk FOREIGN KEY (id_estudiante)         REFERENCES core.estudiante (id_estudiante),
    CONSTRAINT gp_prac_emp_fk FOREIGN KEY (id_empresa)            REFERENCES comun.empresa (id_empresa),
    CONSTRAINT gp_prac_doc_fk FOREIGN KEY (id_docente_supervisor) REFERENCES core.docente (id_docente),
    CONSTRAINT gp_prac_jefe_fk FOREIGN KEY (id_contacto_jefe)     REFERENCES comun.contacto_empresa (id_contacto),
    CONSTRAINT gp_prac_per_fk FOREIGN KEY (id_periodo)            REFERENCES core.periodo_academico (id_periodo),
    CONSTRAINT gp_prac_val_fk FOREIGN KEY (validado_por)          REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_prac_asg_fk FOREIGN KEY (supervisor_asignado_por) REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_prac_cer_fk FOREIGN KEY (cerrada_por)           REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_prac_tipo_ck   CHECK (tipo_practica IN ('preprofesional', 'profesional')),
    CONSTRAINT gp_prac_fechas_ck CHECK (fecha_fin > fecha_inicio),
    CONSTRAINT gp_prac_horas_ck  CHECK (horas_totales > 0),
    CONSTRAINT gp_prac_estado_ck CHECK (estado IN
        ('registrada', 'en_revision', 'observada', 'validada', 'en_curso', 'finalizada', 'anulada')),  -- [NUEVO]
    CONSTRAINT gp_prac_nota_ck   CHECK (nota_final BETWEEN 0 AND 20),
    CONSTRAINT gp_prac_res_ck    CHECK (resultado IN ('aprobado', 'desaprobado')),
    -- Trazabilidad obligatoria según el estado (la guía pide "trazabilidad
    -- de la aprobación antes de permitir el cierre").
    CONSTRAINT gp_prac_valida_ck CHECK (
        estado NOT IN ('validada', 'en_curso', 'finalizada')
        OR (validado_por IS NOT NULL AND validada_en IS NOT NULL)),
    CONSTRAINT gp_prac_sup_ck CHECK (
        (id_docente_supervisor IS NULL)
        = (supervisor_asignado_por IS NULL AND supervisor_asignado_en IS NULL)),
    CONSTRAINT gp_prac_curso_ck CHECK (
        estado NOT IN ('en_curso', 'finalizada') OR id_docente_supervisor IS NOT NULL),
    CONSTRAINT gp_prac_cierre_ck CHECK (
        (estado = 'finalizada')
        = (cerrada_por IS NOT NULL AND cerrada_en IS NOT NULL
           AND nota_final IS NOT NULL AND resultado IS NOT NULL))
);

-- ---------------------------------------------------------------------
-- 5. postulacion
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.postulacion (
    id_postulacion           bigint GENERATED ALWAYS AS IDENTITY,
    id_convocatoria          bigint      NOT NULL,
    id_estudiante            bigint      NOT NULL,
    id_archivo_cv            bigint,
    estado                   varchar(15) NOT NULL DEFAULT 'postulado',
    id_practica              bigint,
    postulado_en             timestamptz NOT NULL DEFAULT now(),
    resultado_en             timestamptz,           -- [NUEVO] antes fecha_seleccion
    resultado_registrado_por bigint,                -- [NUEVO]
    CONSTRAINT gp_post_pk PRIMARY KEY (id_postulacion),
    CONSTRAINT gp_post_conv_fk FOREIGN KEY (id_convocatoria)
        REFERENCES gestion_practicas.convocatoria (id_convocatoria),
    CONSTRAINT gp_post_est_fk FOREIGN KEY (id_estudiante) REFERENCES core.estudiante (id_estudiante),
    CONSTRAINT gp_post_cv_fk  FOREIGN KEY (id_archivo_cv) REFERENCES comun.archivo (id_archivo),
    CONSTRAINT gp_post_prac_fk FOREIGN KEY (id_practica)
        REFERENCES gestion_practicas.practica (id_practica),
    CONSTRAINT gp_post_res_fk FOREIGN KEY (resultado_registrado_por)
        REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_post_prac_uq UNIQUE (id_practica),
    CONSTRAINT gp_post_conv_est_uq UNIQUE (id_convocatoria, id_estudiante),             -- [NUEVO]
    CONSTRAINT gp_post_estado_ck CHECK (estado IN
        ('postulado', 'seleccionado', 'no_seleccionado', 'desistido')),                 -- [NUEVO]
    CONSTRAINT gp_post_res_ck CHECK (
        estado NOT IN ('seleccionado', 'no_seleccionado')
        OR (resultado_en IS NOT NULL AND resultado_registrado_por IS NOT NULL)),
    CONSTRAINT gp_post_prac_ck CHECK (id_practica IS NULL OR estado = 'seleccionado')
);

-- ---------------------------------------------------------------------
-- 6. seguimiento
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.seguimiento (
    id_seguimiento bigint GENERATED ALWAYS AS IDENTITY,
    id_practica    bigint      NOT NULL,
    fecha          date        NOT NULL,
    tipo           varchar(20) NOT NULL,
    descripcion    text        NOT NULL,
    observaciones  text,                                   -- [NUEVO] estaba en el modelo original
    registrado_por bigint      NOT NULL,                   -- core.docente, como en el PDF
    registrado_en  timestamptz NOT NULL DEFAULT now(),     -- [NUEVO]
    CONSTRAINT gp_seg_pk PRIMARY KEY (id_seguimiento),
    CONSTRAINT gp_seg_prac_fk FOREIGN KEY (id_practica)
        REFERENCES gestion_practicas.practica (id_practica) ON DELETE CASCADE,
    CONSTRAINT gp_seg_doc_fk FOREIGN KEY (registrado_por) REFERENCES core.docente (id_docente),
    CONSTRAINT gp_seg_tipo_ck CHECK (tipo IN ('reunion', 'visita', 'llamada', 'revision_avance', 'otro'))
);

-- ---------------------------------------------------------------------
-- 7. documento_practica
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.documento_practica (
    id_documento   bigint GENERATED ALWAYS AS IDENTITY,
    id_practica    bigint      NOT NULL,
    id_archivo     bigint      NOT NULL,
    tipo_documento varchar(20) NOT NULL,
    estado         varchar(12) NOT NULL DEFAULT 'pendiente',
    subido_en      timestamptz NOT NULL DEFAULT now(),
    revisado_por   bigint,                                 -- [NUEVO]
    revisado_en    timestamptz,                            -- [NUEVO]
    CONSTRAINT gp_docp_pk PRIMARY KEY (id_documento),
    CONSTRAINT gp_docp_doc_prac_uq UNIQUE (id_documento, id_practica),   -- soporte para FK compuesta [NUEVO]
    CONSTRAINT gp_docp_prac_fk FOREIGN KEY (id_practica)
        REFERENCES gestion_practicas.practica (id_practica) ON DELETE CASCADE,
    CONSTRAINT gp_docp_arch_fk FOREIGN KEY (id_archivo)   REFERENCES comun.archivo (id_archivo),
    CONSTRAINT gp_docp_rev_fk  FOREIGN KEY (revisado_por) REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_docp_tipo_ck CHECK (tipo_documento IN                                  -- [NUEVO]
        ('carta_aceptacion', 'plan_practica', 'informe_parcial', 'informe_final', 'constancia', 'otro')),
    CONSTRAINT gp_docp_estado_ck CHECK (estado IN ('pendiente', 'aprobado', 'observado', 'reemplazado')),
    CONSTRAINT gp_docp_rev_ck CHECK (
        estado NOT IN ('aprobado', 'observado')
        OR (revisado_por IS NOT NULL AND revisado_en IS NOT NULL))
);

-- ---------------------------------------------------------------------
-- 8. observacion_practica
--    [NUEVO] Cuelga de la práctica (obligatorio) y opcionalmente de un
--    documento. En la megabase solo tenía id_documento, por lo que el
--    administrador no podía observar área, funciones o fechas.
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.observacion_practica (
    id_observacion bigint GENERATED ALWAYS AS IDENTITY,
    id_practica    bigint      NOT NULL,                   -- [NUEVO]
    id_documento   bigint,                                 -- [CAMBIO] ahora opcional
    descripcion    text        NOT NULL,
    estado         varchar(10) NOT NULL DEFAULT 'pendiente',
    registrado_por bigint      NOT NULL,
    registrado_en  timestamptz NOT NULL DEFAULT now(),
    subsanada_en   timestamptz,                            -- [NUEVO]
    CONSTRAINT gp_obsp_pk PRIMARY KEY (id_observacion),
    CONSTRAINT gp_obsp_prac_fk FOREIGN KEY (id_practica)
        REFERENCES gestion_practicas.practica (id_practica) ON DELETE CASCADE,
    -- FK compuesta: si hay documento, debe ser de la MISMA práctica.
    CONSTRAINT gp_obsp_doc_fk FOREIGN KEY (id_documento, id_practica)
        REFERENCES gestion_practicas.documento_practica (id_documento, id_practica),
    CONSTRAINT gp_obsp_reg_fk FOREIGN KEY (registrado_por) REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_obsp_estado_ck CHECK (estado IN ('pendiente', 'subsanada')),
    CONSTRAINT gp_obsp_subs_ck CHECK ((estado = 'subsanada') = (subsanada_en IS NOT NULL))
);

-- ---------------------------------------------------------------------
-- 9. criterio_evaluacion
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.criterio_evaluacion (
    id_criterio bigint GENERATED ALWAYS AS IDENTITY,
    nombre      varchar(120) NOT NULL,
    evaluador   varchar(12)  NOT NULL,
    peso        numeric(5,2) NOT NULL,                  -- porcentaje
    activo      boolean      NOT NULL DEFAULT true,     -- [NUEVO] retirar criterios sin borrar historia
    CONSTRAINT gp_crit_pk PRIMARY KEY (id_criterio),
    CONSTRAINT gp_crit_nombre_uq UNIQUE (nombre),
    CONSTRAINT gp_crit_eval_ck CHECK (evaluador IN ('supervisor', 'empresa')),
    CONSTRAINT gp_crit_peso_ck CHECK (peso > 0 AND peso <= 100)
);

-- ---------------------------------------------------------------------
-- 10. evaluacion
--    [NUEVO] evaluado_por: en la megabase "evaluador" es solo un texto
--    (tipo de evaluador); se había perdido QUIÉN evaluó (el modelo
--    original tenía id_supervisor).
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.evaluacion (
    id_evaluacion bigint GENERATED ALWAYS AS IDENTITY,
    id_practica   bigint      NOT NULL,
    evaluador     varchar(12) NOT NULL,
    evaluado_por  bigint      NOT NULL,                    -- [NUEVO]
    comentario    text,                                    -- observación final
    evaluado_en   timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT gp_eval_pk PRIMARY KEY (id_evaluacion),
    CONSTRAINT gp_eval_prac_fk FOREIGN KEY (id_practica)
        REFERENCES gestion_practicas.practica (id_practica) ON DELETE CASCADE,
    CONSTRAINT gp_eval_usu_fk FOREIGN KEY (evaluado_por) REFERENCES seguridad.usuario (id_usuario),
    CONSTRAINT gp_eval_eval_ck CHECK (evaluador IN ('supervisor', 'empresa')),
    CONSTRAINT gp_eval_prac_eval_uq UNIQUE (id_practica, evaluador)                      -- [NUEVO]
);

-- ---------------------------------------------------------------------
-- 11. evaluacion_criterio
-- ---------------------------------------------------------------------
CREATE TABLE gestion_practicas.evaluacion_criterio (
    id_evaluacion bigint       NOT NULL,
    id_criterio   bigint       NOT NULL,
    nota          numeric(4,2) NOT NULL,
    CONSTRAINT gp_evcr_pk PRIMARY KEY (id_evaluacion, id_criterio),
    CONSTRAINT gp_evcr_eval_fk FOREIGN KEY (id_evaluacion)
        REFERENCES gestion_practicas.evaluacion (id_evaluacion) ON DELETE CASCADE,
    CONSTRAINT gp_evcr_crit_fk FOREIGN KEY (id_criterio)
        REFERENCES gestion_practicas.criterio_evaluacion (id_criterio),
    CONSTRAINT gp_evcr_nota_ck CHECK (nota BETWEEN 0 AND 20)                              -- [NUEVO]
);

-- =====================================================================
-- ÍNDICES  [NUEVO]  (PostgreSQL no crea índice en la columna hija de una FK)
-- =====================================================================
CREATE INDEX gp_conv_emp_ix     ON gestion_practicas.convocatoria (id_empresa);
CREATE INDEX gp_conv_pub_ix     ON gestion_practicas.convocatoria (fecha_cierre)
                                   WHERE estado = 'publicada';          -- búsqueda de convocatorias
CREATE INDEX gp_conv_estado_ix  ON gestion_practicas.convocatoria (estado);
CREATE INDEX gp_obsc_conv_ix    ON gestion_practicas.observacion_convocatoria (id_convocatoria);
CREATE INDEX gp_post_est_ix     ON gestion_practicas.postulacion (id_estudiante);
CREATE INDEX gp_prac_est_ix     ON gestion_practicas.practica (id_estudiante);
CREATE INDEX gp_prac_emp_ix     ON gestion_practicas.practica (id_empresa);
CREATE INDEX gp_prac_sup_ix     ON gestion_practicas.practica (id_docente_supervisor);   -- supervisor ve sus prácticas
CREATE INDEX gp_prac_per_ix     ON gestion_practicas.practica (id_periodo);
CREATE INDEX gp_prac_estado_ix  ON gestion_practicas.practica (estado);
CREATE INDEX gp_seg_prac_ix     ON gestion_practicas.seguimiento (id_practica);
CREATE INDEX gp_docp_prac_ix    ON gestion_practicas.documento_practica (id_practica);
CREATE INDEX gp_obsp_prac_ix    ON gestion_practicas.observacion_practica (id_practica);
CREATE INDEX gp_obsp_doc_ix     ON gestion_practicas.observacion_practica (id_documento);
CREATE INDEX gp_evcr_crit_ix    ON gestion_practicas.evaluacion_criterio (id_criterio);
