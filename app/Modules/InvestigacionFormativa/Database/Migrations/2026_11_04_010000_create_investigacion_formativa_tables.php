<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Esquema investigacion_formativa (11 tablas) según
 * esquemas_y_origen_de_tablas.pdf, sección B.3.
 */
return new class extends Migration
{
    private const TABLES = [
        'tipo_producto',
        'grupo',
        'grupo_estudiante',
        'proyecto',
        'rubrica',
        'criterio',
        'entregable',
        'entrega',
        'evaluacion',
        'evaluacion_criterio',
        'observacion',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE SCHEMA IF NOT EXISTS investigacion_formativa;

            CREATE TABLE investigacion_formativa.tipo_producto (
                id_tipo_producto BIGSERIAL PRIMARY KEY,
                nombre           VARCHAR(100) NOT NULL,
                CONSTRAINT if_tipo_producto_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE investigacion_formativa.grupo (
                id_grupo   BIGSERIAL PRIMARY KEY,
                id_seccion BIGINT NOT NULL REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
                nombre     VARCHAR(120) NOT NULL,
                CONSTRAINT if_grupo_seccion_nombre_uq UNIQUE (id_seccion, nombre)
            );

            CREATE TABLE investigacion_formativa.grupo_estudiante (
                id_grupo       BIGINT NOT NULL REFERENCES investigacion_formativa.grupo (id_grupo) ON DELETE CASCADE,
                id_estudiante  BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                es_coordinador BOOLEAN NOT NULL DEFAULT FALSE,
                CONSTRAINT if_grupo_estudiante_pk PRIMARY KEY (id_grupo, id_estudiante)
            );
            CREATE INDEX if_grupo_estudiante_estudiante_idx ON investigacion_formativa.grupo_estudiante (id_estudiante);
            CREATE UNIQUE INDEX if_grupo_estudiante_coordinador_uq
                ON investigacion_formativa.grupo_estudiante (id_grupo) WHERE es_coordinador;

            CREATE TABLE investigacion_formativa.proyecto (
                id_proyecto      BIGSERIAL PRIMARY KEY,
                id_grupo         BIGINT NOT NULL REFERENCES investigacion_formativa.grupo (id_grupo) ON DELETE RESTRICT,
                id_linea         BIGINT NOT NULL REFERENCES core.linea_investigacion (id_linea) ON DELETE RESTRICT,
                id_tipo_producto BIGINT NOT NULL REFERENCES investigacion_formativa.tipo_producto (id_tipo_producto) ON DELETE RESTRICT,
                titulo           VARCHAR(250) NOT NULL,
                resumen          TEXT,
                estado           VARCHAR(30) NOT NULL DEFAULT 'Registrado',
                registrado_en    TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT if_proyecto_grupo_uq UNIQUE (id_grupo)
            );
            CREATE INDEX if_proyecto_linea_idx ON investigacion_formativa.proyecto (id_linea);
            CREATE INDEX if_proyecto_tipo_producto_idx ON investigacion_formativa.proyecto (id_tipo_producto);

            CREATE TABLE investigacion_formativa.rubrica (
                id_rubrica       BIGSERIAL PRIMARY KEY,
                nombre           VARCHAR(150) NOT NULL,
                descripcion      TEXT,
                id_docente_autor BIGINT NOT NULL REFERENCES core.docente (id_docente) ON DELETE RESTRICT
            );
            CREATE INDEX if_rubrica_docente_idx ON investigacion_formativa.rubrica (id_docente_autor);

            CREATE TABLE investigacion_formativa.criterio (
                id_criterio     BIGSERIAL PRIMARY KEY,
                id_rubrica      BIGINT NOT NULL REFERENCES investigacion_formativa.rubrica (id_rubrica) ON DELETE CASCADE,
                descripcion     TEXT NOT NULL,
                puntaje_maximo  NUMERIC(6,2) NOT NULL,
                orden           SMALLINT NOT NULL,
                CONSTRAINT if_criterio_rubrica_orden_uq UNIQUE (id_rubrica, orden),
                CONSTRAINT if_criterio_puntaje_ck CHECK (puntaje_maximo > 0)
            );

            CREATE TABLE investigacion_formativa.entregable (
                id_entregable BIGSERIAL PRIMARY KEY,
                id_seccion    BIGINT NOT NULL REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
                nombre        VARCHAR(150) NOT NULL,
                descripcion   TEXT,
                fecha_limite  TIMESTAMPTZ,
                orden         SMALLINT NOT NULL,
                id_rubrica    BIGINT REFERENCES investigacion_formativa.rubrica (id_rubrica) ON DELETE SET NULL,
                CONSTRAINT if_entregable_seccion_orden_uq UNIQUE (id_seccion, orden)
            );
            CREATE INDEX if_entregable_rubrica_idx ON investigacion_formativa.entregable (id_rubrica);

            CREATE TABLE investigacion_formativa.entrega (
                id_entrega    BIGSERIAL PRIMARY KEY,
                id_proyecto   BIGINT NOT NULL REFERENCES investigacion_formativa.proyecto (id_proyecto) ON DELETE CASCADE,
                id_entregable BIGINT NOT NULL REFERENCES investigacion_formativa.entregable (id_entregable) ON DELETE RESTRICT,
                version       SMALLINT NOT NULL DEFAULT 1,
                id_archivo    BIGINT NOT NULL REFERENCES comun.archivo (id_archivo) ON DELETE RESTRICT,
                entregado_en  TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT if_entrega_version_uq UNIQUE (id_proyecto, id_entregable, version),
                CONSTRAINT if_entrega_version_ck CHECK (version > 0)
            );
            CREATE INDEX if_entrega_entregable_idx ON investigacion_formativa.entrega (id_entregable);
            CREATE INDEX if_entrega_archivo_idx ON investigacion_formativa.entrega (id_archivo);

            CREATE TABLE investigacion_formativa.evaluacion (
                id_evaluacion        BIGSERIAL PRIMARY KEY,
                id_entrega           BIGINT NOT NULL REFERENCES investigacion_formativa.entrega (id_entrega) ON DELETE CASCADE,
                id_docente_evaluador BIGINT NOT NULL REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
                comentario           TEXT,
                evaluado_en          TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT if_evaluacion_entrega_uq UNIQUE (id_entrega)
            );
            CREATE INDEX if_evaluacion_docente_idx ON investigacion_formativa.evaluacion (id_docente_evaluador);

            CREATE TABLE investigacion_formativa.evaluacion_criterio (
                id_evaluacion BIGINT NOT NULL REFERENCES investigacion_formativa.evaluacion (id_evaluacion) ON DELETE CASCADE,
                id_criterio   BIGINT NOT NULL REFERENCES investigacion_formativa.criterio (id_criterio) ON DELETE RESTRICT,
                puntaje       NUMERIC(6,2) NOT NULL,
                CONSTRAINT if_evaluacion_criterio_pk PRIMARY KEY (id_evaluacion, id_criterio),
                CONSTRAINT if_evaluacion_criterio_puntaje_ck CHECK (puntaje >= 0)
            );
            CREATE INDEX if_evaluacion_criterio_criterio_idx ON investigacion_formativa.evaluacion_criterio (id_criterio);

            CREATE TABLE investigacion_formativa.observacion (
                id_observacion BIGSERIAL PRIMARY KEY,
                id_entrega     BIGINT NOT NULL REFERENCES investigacion_formativa.entrega (id_entrega) ON DELETE CASCADE,
                descripcion    TEXT NOT NULL,
                estado         VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
                registrado_por BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
                registrado_en  TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX if_observacion_entrega_idx ON investigacion_formativa.observacion (id_entrega);
            CREATE INDEX if_observacion_usuario_idx ON investigacion_formativa.observacion (registrado_por);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TABLE IF EXISTS investigacion_formativa.{$table}");
        }
    }
};
