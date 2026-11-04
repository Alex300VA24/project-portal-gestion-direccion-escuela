<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Esquema seguimiento_estudiante (23 tablas) según
 * esquemas_y_origen_de_tablas.pdf, sección B.4. Reemplaza el borrador legacy
 * (tablas en public con enum de Laravel) que nunca se ejecutó.
 */
return new class extends Migration
{
    private const TABLES = [
        'perfil_ingreso',
        'dimension_brecha',
        'brecha_ingreso',
        'situacion_academica',
        'parametro_sat',
        'alerta_sat',
        'tutor_asignacion',
        'tipo_atencion',
        'motivo_atencion',
        'solicitud_estudiante',
        'atencion',
        'atencion_motivo',
        'atencion_compromiso',
        'atencion_derivacion',
        'atencion_evidencia',
        'programa_nivelacion',
        'nivelacion_grupo',
        'nivelacion_participante',
        'nivelacion_sesion',
        'nivelacion_asistencia',
        'nivelacion_criterio_evaluacion',
        'nivelacion_calificacion',
        'nivelacion_material',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE SCHEMA IF NOT EXISTS seguimiento_estudiante;

            CREATE TABLE seguimiento_estudiante.perfil_ingreso (
                id_perfil            BIGSERIAL PRIMARY KEY,
                id_estudiante        BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                puntaje_ingreso      NUMERIC(6,2),
                colegio_procedencia  VARCHAR(180),
                tipo_colegio         VARCHAR(40),
                id_distrito_colegio  BIGINT REFERENCES core.ubigeo_distrito (id_distrito) ON DELETE RESTRICT,
                fecha_evaluacion     DATE,
                CONSTRAINT se_perfil_ingreso_estudiante_uq UNIQUE (id_estudiante)
            );
            CREATE INDEX se_perfil_ingreso_distrito_idx ON seguimiento_estudiante.perfil_ingreso (id_distrito_colegio);

            CREATE TABLE seguimiento_estudiante.dimension_brecha (
                id_dimension BIGSERIAL PRIMARY KEY,
                codigo       VARCHAR(30) NOT NULL,
                nombre       VARCHAR(120) NOT NULL,
                CONSTRAINT se_dimension_brecha_codigo_uq UNIQUE (codigo)
            );

            CREATE TABLE seguimiento_estudiante.brecha_ingreso (
                id_perfil    BIGINT NOT NULL REFERENCES seguimiento_estudiante.perfil_ingreso (id_perfil) ON DELETE CASCADE,
                id_dimension BIGINT NOT NULL REFERENCES seguimiento_estudiante.dimension_brecha (id_dimension) ON DELETE RESTRICT,
                puntaje      NUMERIC(6,2),
                nivel        VARCHAR(30),
                CONSTRAINT se_brecha_ingreso_pk PRIMARY KEY (id_perfil, id_dimension)
            );
            CREATE INDEX se_brecha_ingreso_dimension_idx ON seguimiento_estudiante.brecha_ingreso (id_dimension);

            CREATE TABLE seguimiento_estudiante.situacion_academica (
                id_situacion         BIGSERIAL PRIMARY KEY,
                id_estudiante        BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                id_periodo           BIGINT NOT NULL REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
                promedio_ponderado   NUMERIC(5,2),
                creditos_aprobados   NUMERIC(6,2) NOT NULL DEFAULT 0,
                creditos_desaprobados NUMERIC(6,2) NOT NULL DEFAULT 0,
                condicion            VARCHAR(40),
                calculado_en         TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT se_situacion_estudiante_periodo_uq UNIQUE (id_estudiante, id_periodo),
                CONSTRAINT se_situacion_creditos_ck CHECK (creditos_aprobados >= 0 AND creditos_desaprobados >= 0)
            );
            CREATE INDEX se_situacion_periodo_idx ON seguimiento_estudiante.situacion_academica (id_periodo);

            CREATE TABLE seguimiento_estudiante.parametro_sat (
                id_parametro BIGSERIAL PRIMARY KEY,
                codigo       VARCHAR(40) NOT NULL,
                descripcion  TEXT,
                operador     VARCHAR(5) NOT NULL,
                umbral       NUMERIC(10,2) NOT NULL,
                nivel_riesgo VARCHAR(20) NOT NULL,
                activo       BOOLEAN NOT NULL DEFAULT TRUE,
                CONSTRAINT se_parametro_sat_codigo_uq UNIQUE (codigo),
                CONSTRAINT se_parametro_sat_operador_ck CHECK (operador IN ('<', '<=', '>', '>=', '=', '<>'))
            );

            CREATE TABLE seguimiento_estudiante.alerta_sat (
                id_alerta       BIGSERIAL PRIMARY KEY,
                id_estudiante   BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                id_periodo      BIGINT NOT NULL REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
                id_parametro    BIGINT NOT NULL REFERENCES seguimiento_estudiante.parametro_sat (id_parametro) ON DELETE RESTRICT,
                valor_detectado NUMERIC(10,2),
                estado          VARCHAR(30) NOT NULL DEFAULT 'Abierta',
                generada_en     TIMESTAMPTZ NOT NULL DEFAULT now(),
                cerrada_en      TIMESTAMPTZ,
                CONSTRAINT se_alerta_sat_cierre_ck CHECK (cerrada_en IS NULL OR cerrada_en >= generada_en)
            );
            CREATE INDEX se_alerta_sat_estudiante_idx ON seguimiento_estudiante.alerta_sat (id_estudiante);
            CREATE INDEX se_alerta_sat_periodo_idx ON seguimiento_estudiante.alerta_sat (id_periodo);
            CREATE INDEX se_alerta_sat_parametro_idx ON seguimiento_estudiante.alerta_sat (id_parametro);

            CREATE TABLE seguimiento_estudiante.tutor_asignacion (
                id_asignacion    BIGSERIAL PRIMARY KEY,
                id_estudiante    BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                id_docente       BIGINT NOT NULL REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
                id_periodo       BIGINT NOT NULL REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
                tipo             VARCHAR(30) NOT NULL DEFAULT 'Tutoria',
                fecha_asignacion DATE NOT NULL DEFAULT CURRENT_DATE,
                estado           VARCHAR(30) NOT NULL DEFAULT 'Activa',
                CONSTRAINT se_tutor_asignacion_uq UNIQUE (id_estudiante, id_docente, id_periodo, tipo)
            );
            CREATE INDEX se_tutor_asignacion_docente_idx ON seguimiento_estudiante.tutor_asignacion (id_docente);
            CREATE INDEX se_tutor_asignacion_periodo_idx ON seguimiento_estudiante.tutor_asignacion (id_periodo);

            CREATE TABLE seguimiento_estudiante.tipo_atencion (
                id_tipo_atencion BIGSERIAL PRIMARY KEY,
                nombre           VARCHAR(80) NOT NULL,
                CONSTRAINT se_tipo_atencion_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_estudiante.motivo_atencion (
                id_motivo BIGSERIAL PRIMARY KEY,
                nombre    VARCHAR(120) NOT NULL,
                CONSTRAINT se_motivo_atencion_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_estudiante.solicitud_estudiante (
                id_solicitud  BIGSERIAL PRIMARY KEY,
                id_estudiante BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                asunto        VARCHAR(200) NOT NULL,
                descripcion   TEXT,
                estado        VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
                solicitada_en TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX se_solicitud_estudiante_idx ON seguimiento_estudiante.solicitud_estudiante (id_estudiante);

            CREATE TABLE seguimiento_estudiante.atencion (
                id_atencion      BIGSERIAL PRIMARY KEY,
                id_asignacion    BIGINT NOT NULL REFERENCES seguimiento_estudiante.tutor_asignacion (id_asignacion) ON DELETE RESTRICT,
                id_tipo_atencion BIGINT NOT NULL REFERENCES seguimiento_estudiante.tipo_atencion (id_tipo_atencion) ON DELETE RESTRICT,
                id_alerta        BIGINT REFERENCES seguimiento_estudiante.alerta_sat (id_alerta) ON DELETE SET NULL,
                id_solicitud     BIGINT REFERENCES seguimiento_estudiante.solicitud_estudiante (id_solicitud) ON DELETE SET NULL,
                fecha_hora       TIMESTAMPTZ NOT NULL,
                modalidad        VARCHAR(30) NOT NULL DEFAULT 'Presencial',
                descripcion      TEXT,
                acuerdos         TEXT
            );
            CREATE INDEX se_atencion_asignacion_idx ON seguimiento_estudiante.atencion (id_asignacion);
            CREATE INDEX se_atencion_tipo_idx ON seguimiento_estudiante.atencion (id_tipo_atencion);
            CREATE INDEX se_atencion_alerta_idx ON seguimiento_estudiante.atencion (id_alerta);
            CREATE INDEX se_atencion_solicitud_idx ON seguimiento_estudiante.atencion (id_solicitud);

            CREATE TABLE seguimiento_estudiante.atencion_motivo (
                id_atencion BIGINT NOT NULL REFERENCES seguimiento_estudiante.atencion (id_atencion) ON DELETE CASCADE,
                id_motivo   BIGINT NOT NULL REFERENCES seguimiento_estudiante.motivo_atencion (id_motivo) ON DELETE RESTRICT,
                CONSTRAINT se_atencion_motivo_pk PRIMARY KEY (id_atencion, id_motivo)
            );
            CREATE INDEX se_atencion_motivo_motivo_idx ON seguimiento_estudiante.atencion_motivo (id_motivo);

            CREATE TABLE seguimiento_estudiante.atencion_compromiso (
                id_compromiso BIGSERIAL PRIMARY KEY,
                id_atencion   BIGINT NOT NULL REFERENCES seguimiento_estudiante.atencion (id_atencion) ON DELETE CASCADE,
                descripcion   TEXT NOT NULL,
                fecha_limite  DATE,
                cumplido      BOOLEAN NOT NULL DEFAULT FALSE
            );
            CREATE INDEX se_atencion_compromiso_atencion_idx ON seguimiento_estudiante.atencion_compromiso (id_atencion);

            -- id_caso_bienestar es referencia lógica: no hay FK entre módulos.
            CREATE TABLE seguimiento_estudiante.atencion_derivacion (
                id_derivacion     BIGSERIAL PRIMARY KEY,
                id_atencion       BIGINT NOT NULL REFERENCES seguimiento_estudiante.atencion (id_atencion) ON DELETE CASCADE,
                area_destino      VARCHAR(120) NOT NULL,
                motivo            TEXT,
                id_caso_bienestar BIGINT,
                derivado_en       TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX se_atencion_derivacion_atencion_idx ON seguimiento_estudiante.atencion_derivacion (id_atencion);
            CREATE INDEX se_atencion_derivacion_caso_idx ON seguimiento_estudiante.atencion_derivacion (id_caso_bienestar);

            CREATE TABLE seguimiento_estudiante.atencion_evidencia (
                id_atencion BIGINT NOT NULL REFERENCES seguimiento_estudiante.atencion (id_atencion) ON DELETE CASCADE,
                id_archivo  BIGINT NOT NULL REFERENCES comun.archivo (id_archivo) ON DELETE RESTRICT,
                CONSTRAINT se_atencion_evidencia_pk PRIMARY KEY (id_atencion, id_archivo)
            );
            CREATE INDEX se_atencion_evidencia_archivo_idx ON seguimiento_estudiante.atencion_evidencia (id_archivo);

            CREATE TABLE seguimiento_estudiante.programa_nivelacion (
                id_programa  BIGSERIAL PRIMARY KEY,
                id_periodo   BIGINT NOT NULL REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
                id_dimension BIGINT NOT NULL REFERENCES seguimiento_estudiante.dimension_brecha (id_dimension) ON DELETE RESTRICT,
                nombre       VARCHAR(180) NOT NULL,
                descripcion  TEXT,
                fecha_inicio DATE NOT NULL,
                fecha_fin    DATE NOT NULL,
                estado       VARCHAR(30) NOT NULL DEFAULT 'Planificado',
                CONSTRAINT se_programa_nivelacion_fechas_ck CHECK (fecha_fin >= fecha_inicio)
            );
            CREATE INDEX se_programa_nivelacion_periodo_idx ON seguimiento_estudiante.programa_nivelacion (id_periodo);
            CREATE INDEX se_programa_nivelacion_dimension_idx ON seguimiento_estudiante.programa_nivelacion (id_dimension);

            CREATE TABLE seguimiento_estudiante.nivelacion_grupo (
                id_grupo    BIGSERIAL PRIMARY KEY,
                id_programa BIGINT NOT NULL REFERENCES seguimiento_estudiante.programa_nivelacion (id_programa) ON DELETE CASCADE,
                codigo      VARCHAR(30) NOT NULL,
                id_docente  BIGINT NOT NULL REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
                capacidad   INTEGER,
                CONSTRAINT se_nivelacion_grupo_codigo_uq UNIQUE (id_programa, codigo),
                CONSTRAINT se_nivelacion_grupo_capacidad_ck CHECK (capacidad IS NULL OR capacidad > 0)
            );
            CREATE INDEX se_nivelacion_grupo_docente_idx ON seguimiento_estudiante.nivelacion_grupo (id_docente);

            CREATE TABLE seguimiento_estudiante.nivelacion_participante (
                id_participante BIGSERIAL PRIMARY KEY,
                id_grupo        BIGINT NOT NULL REFERENCES seguimiento_estudiante.nivelacion_grupo (id_grupo) ON DELETE CASCADE,
                id_estudiante   BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                estado          VARCHAR(30) NOT NULL DEFAULT 'Inscrito',
                CONSTRAINT se_nivelacion_participante_uq UNIQUE (id_grupo, id_estudiante)
            );
            CREATE INDEX se_nivelacion_participante_estudiante_idx ON seguimiento_estudiante.nivelacion_participante (id_estudiante);

            CREATE TABLE seguimiento_estudiante.nivelacion_sesion (
                id_sesion   BIGSERIAL PRIMARY KEY,
                id_grupo    BIGINT NOT NULL REFERENCES seguimiento_estudiante.nivelacion_grupo (id_grupo) ON DELETE CASCADE,
                fecha       DATE NOT NULL,
                tema        VARCHAR(200),
                id_ambiente BIGINT REFERENCES core.ambiente (id_ambiente) ON DELETE SET NULL
            );
            CREATE INDEX se_nivelacion_sesion_grupo_idx ON seguimiento_estudiante.nivelacion_sesion (id_grupo);
            CREATE INDEX se_nivelacion_sesion_ambiente_idx ON seguimiento_estudiante.nivelacion_sesion (id_ambiente);

            CREATE TABLE seguimiento_estudiante.nivelacion_asistencia (
                id_sesion       BIGINT NOT NULL REFERENCES seguimiento_estudiante.nivelacion_sesion (id_sesion) ON DELETE CASCADE,
                id_participante BIGINT NOT NULL REFERENCES seguimiento_estudiante.nivelacion_participante (id_participante) ON DELETE CASCADE,
                estado          VARCHAR(20) NOT NULL DEFAULT 'Presente',
                CONSTRAINT se_nivelacion_asistencia_pk PRIMARY KEY (id_sesion, id_participante)
            );
            CREATE INDEX se_nivelacion_asistencia_participante_idx ON seguimiento_estudiante.nivelacion_asistencia (id_participante);

            CREATE TABLE seguimiento_estudiante.nivelacion_criterio_evaluacion (
                id_criterio BIGSERIAL PRIMARY KEY,
                id_programa BIGINT NOT NULL REFERENCES seguimiento_estudiante.programa_nivelacion (id_programa) ON DELETE CASCADE,
                nombre      VARCHAR(120) NOT NULL,
                peso        NUMERIC(5,2) NOT NULL,
                CONSTRAINT se_nivelacion_criterio_programa_nombre_uq UNIQUE (id_programa, nombre),
                CONSTRAINT se_nivelacion_criterio_peso_ck CHECK (peso > 0 AND peso <= 100)
            );

            CREATE TABLE seguimiento_estudiante.nivelacion_calificacion (
                id_participante BIGINT NOT NULL REFERENCES seguimiento_estudiante.nivelacion_participante (id_participante) ON DELETE CASCADE,
                id_criterio     BIGINT NOT NULL REFERENCES seguimiento_estudiante.nivelacion_criterio_evaluacion (id_criterio) ON DELETE RESTRICT,
                nota            NUMERIC(5,2) NOT NULL,
                CONSTRAINT se_nivelacion_calificacion_pk PRIMARY KEY (id_participante, id_criterio),
                CONSTRAINT se_nivelacion_calificacion_nota_ck CHECK (nota >= 0)
            );
            CREATE INDEX se_nivelacion_calificacion_criterio_idx ON seguimiento_estudiante.nivelacion_calificacion (id_criterio);

            CREATE TABLE seguimiento_estudiante.nivelacion_material (
                id_programa BIGINT NOT NULL REFERENCES seguimiento_estudiante.programa_nivelacion (id_programa) ON DELETE CASCADE,
                id_archivo  BIGINT NOT NULL REFERENCES comun.archivo (id_archivo) ON DELETE RESTRICT,
                titulo      VARCHAR(200),
                CONSTRAINT se_nivelacion_material_pk PRIMARY KEY (id_programa, id_archivo)
            );
            CREATE INDEX se_nivelacion_material_archivo_idx ON seguimiento_estudiante.nivelacion_material (id_archivo);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TABLE IF EXISTS seguimiento_estudiante.{$table}");
        }
    }
};
