<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Esquema bienestar (18 tablas) según esquemas_y_origen_de_tablas.pdf,
 * sección B.2. Reemplaza el borrador legacy que usaba tablas de public.
 * Contiene datos sensibles: el acceso se restringe por permisos del esquema.
 */
return new class extends Migration
{
    private const TABLES = [
        'especialidad',
        'especialista',
        'tipo_caso',
        'tipo_condicion',
        'deporte',
        'beneficio',
        'perfil_socioeconomico',
        'estudiante_deporte',
        'estudiante_condicion',
        'solicitud_apoyo',
        'solicitud_documento',
        'caso_bienestar',
        'historial_estado_caso',
        'horario_disponible',
        'cita',
        'derivacion',
        'seguimiento_derivacion',
        'reporte',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE SCHEMA IF NOT EXISTS bienestar;

            CREATE TABLE bienestar.especialidad (
                id_especialidad BIGSERIAL PRIMARY KEY,
                nombre          VARCHAR(120) NOT NULL,
                CONSTRAINT bie_especialidad_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE bienestar.especialista (
                id_especialista    BIGSERIAL PRIMARY KEY,
                id_usuario         BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
                id_especialidad    BIGINT NOT NULL REFERENCES bienestar.especialidad (id_especialidad) ON DELETE RESTRICT,
                registro_profesional VARCHAR(60),
                activo             BOOLEAN NOT NULL DEFAULT TRUE,
                CONSTRAINT bie_especialista_usuario_uq UNIQUE (id_usuario)
            );
            CREATE INDEX bie_especialista_especialidad_idx ON bienestar.especialista (id_especialidad);

            CREATE TABLE bienestar.tipo_caso (
                id_tipo_caso BIGSERIAL PRIMARY KEY,
                nombre       VARCHAR(120) NOT NULL,
                descripcion  TEXT,
                CONSTRAINT bie_tipo_caso_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE bienestar.tipo_condicion (
                id_tipo_condicion BIGSERIAL PRIMARY KEY,
                nombre            VARCHAR(120) NOT NULL,
                descripcion       TEXT,
                CONSTRAINT bie_tipo_condicion_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE bienestar.deporte (
                id_deporte BIGSERIAL PRIMARY KEY,
                nombre     VARCHAR(100) NOT NULL,
                CONSTRAINT bie_deporte_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE bienestar.beneficio (
                id_beneficio BIGSERIAL PRIMARY KEY,
                nombre       VARCHAR(120) NOT NULL,
                descripcion  TEXT,
                activo       BOOLEAN NOT NULL DEFAULT TRUE,
                CONSTRAINT bie_beneficio_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE bienestar.perfil_socioeconomico (
                id_perfil              BIGSERIAL PRIMARY KEY,
                id_estudiante          BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                fecha_evaluacion       DATE NOT NULL DEFAULT CURRENT_DATE,
                integrantes_hogar      SMALLINT,
                tipo_vivienda          VARCHAR(60),
                rango_ingreso_familiar VARCHAR(60),
                clasificacion          VARCHAR(60),
                evaluado_por           BIGINT REFERENCES bienestar.especialista (id_especialista) ON DELETE SET NULL,
                CONSTRAINT bie_perfil_integrantes_ck CHECK (integrantes_hogar IS NULL OR integrantes_hogar > 0)
            );
            CREATE INDEX bie_perfil_estudiante_idx ON bienestar.perfil_socioeconomico (id_estudiante);
            CREATE INDEX bie_perfil_evaluador_idx ON bienestar.perfil_socioeconomico (evaluado_por);

            CREATE TABLE bienestar.estudiante_deporte (
                id_estudiante BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE CASCADE,
                id_deporte    BIGINT NOT NULL REFERENCES bienestar.deporte (id_deporte) ON DELETE RESTRICT,
                CONSTRAINT bie_estudiante_deporte_pk PRIMARY KEY (id_estudiante, id_deporte)
            );
            CREATE INDEX bie_estudiante_deporte_deporte_idx ON bienestar.estudiante_deporte (id_deporte);

            CREATE TABLE bienestar.estudiante_condicion (
                id_estudiante     BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE CASCADE,
                id_tipo_condicion BIGINT NOT NULL REFERENCES bienestar.tipo_condicion (id_tipo_condicion) ON DELETE RESTRICT,
                fecha_registro    DATE NOT NULL DEFAULT CURRENT_DATE,
                observacion       TEXT,
                CONSTRAINT bie_estudiante_condicion_pk PRIMARY KEY (id_estudiante, id_tipo_condicion)
            );
            CREATE INDEX bie_estudiante_condicion_tipo_idx ON bienestar.estudiante_condicion (id_tipo_condicion);

            CREATE TABLE bienestar.solicitud_apoyo (
                id_solicitud  BIGSERIAL PRIMARY KEY,
                id_estudiante BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                id_beneficio  BIGINT NOT NULL REFERENCES bienestar.beneficio (id_beneficio) ON DELETE RESTRICT,
                motivo        TEXT NOT NULL,
                estado        VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
                evaluado_por  BIGINT REFERENCES bienestar.especialista (id_especialista) ON DELETE SET NULL,
                solicitada_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                resuelta_en   TIMESTAMPTZ,
                CONSTRAINT bie_solicitud_apoyo_resolucion_ck CHECK (resuelta_en IS NULL OR resuelta_en >= solicitada_en)
            );
            CREATE INDEX bie_solicitud_apoyo_estudiante_idx ON bienestar.solicitud_apoyo (id_estudiante);
            CREATE INDEX bie_solicitud_apoyo_beneficio_idx ON bienestar.solicitud_apoyo (id_beneficio);
            CREATE INDEX bie_solicitud_apoyo_evaluador_idx ON bienestar.solicitud_apoyo (evaluado_por);

            CREATE TABLE bienestar.solicitud_documento (
                id_solicitud   BIGINT NOT NULL REFERENCES bienestar.solicitud_apoyo (id_solicitud) ON DELETE CASCADE,
                id_archivo     BIGINT NOT NULL REFERENCES comun.archivo (id_archivo) ON DELETE RESTRICT,
                tipo_documento VARCHAR(80),
                CONSTRAINT bie_solicitud_documento_pk PRIMARY KEY (id_solicitud, id_archivo)
            );
            CREATE INDEX bie_solicitud_documento_archivo_idx ON bienestar.solicitud_documento (id_archivo);

            CREATE TABLE bienestar.caso_bienestar (
                id_caso                    BIGSERIAL PRIMARY KEY,
                id_estudiante              BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                id_tipo_caso               BIGINT NOT NULL REFERENCES bienestar.tipo_caso (id_tipo_caso) ON DELETE RESTRICT,
                id_especialista_responsable BIGINT REFERENCES bienestar.especialista (id_especialista) ON DELETE SET NULL,
                id_solicitud_origen        BIGINT REFERENCES bienestar.solicitud_apoyo (id_solicitud) ON DELETE SET NULL,
                descripcion                TEXT NOT NULL,
                estado                     VARCHAR(30) NOT NULL DEFAULT 'Abierto',
                abierto_en                 TIMESTAMPTZ NOT NULL DEFAULT now(),
                cerrado_en                 TIMESTAMPTZ,
                CONSTRAINT bie_caso_cierre_ck CHECK (cerrado_en IS NULL OR cerrado_en >= abierto_en)
            );
            CREATE INDEX bie_caso_estudiante_idx ON bienestar.caso_bienestar (id_estudiante);
            CREATE INDEX bie_caso_tipo_idx ON bienestar.caso_bienestar (id_tipo_caso);
            CREATE INDEX bie_caso_responsable_idx ON bienestar.caso_bienestar (id_especialista_responsable);
            CREATE INDEX bie_caso_solicitud_idx ON bienestar.caso_bienestar (id_solicitud_origen);

            CREATE TABLE bienestar.historial_estado_caso (
                id_historial    BIGSERIAL PRIMARY KEY,
                id_caso         BIGINT NOT NULL REFERENCES bienestar.caso_bienestar (id_caso) ON DELETE CASCADE,
                estado_anterior VARCHAR(30),
                estado_nuevo    VARCHAR(30) NOT NULL,
                cambiado_por    BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
                cambiado_en     TIMESTAMPTZ NOT NULL DEFAULT now(),
                observacion     TEXT
            );
            CREATE INDEX bie_historial_caso_idx ON bienestar.historial_estado_caso (id_caso);
            CREATE INDEX bie_historial_usuario_idx ON bienestar.historial_estado_caso (cambiado_por);

            CREATE TABLE bienestar.horario_disponible (
                id_horario      BIGSERIAL PRIMARY KEY,
                id_especialista BIGINT NOT NULL REFERENCES bienestar.especialista (id_especialista) ON DELETE CASCADE,
                dia_semana      SMALLINT NOT NULL,
                hora_inicio     TIME NOT NULL,
                hora_fin        TIME NOT NULL,
                vigente_desde   DATE NOT NULL DEFAULT CURRENT_DATE,
                vigente_hasta   DATE,
                CONSTRAINT bie_horario_dia_ck CHECK (dia_semana BETWEEN 1 AND 7),
                CONSTRAINT bie_horario_horas_ck CHECK (hora_fin > hora_inicio),
                CONSTRAINT bie_horario_vigencia_ck CHECK (vigente_hasta IS NULL OR vigente_hasta >= vigente_desde)
            );
            CREATE INDEX bie_horario_especialista_idx ON bienestar.horario_disponible (id_especialista);

            CREATE TABLE bienestar.cita (
                id_cita         BIGSERIAL PRIMARY KEY,
                id_caso         BIGINT NOT NULL REFERENCES bienestar.caso_bienestar (id_caso) ON DELETE CASCADE,
                id_especialista BIGINT NOT NULL REFERENCES bienestar.especialista (id_especialista) ON DELETE RESTRICT,
                inicio          TIMESTAMPTZ NOT NULL,
                fin             TIMESTAMPTZ NOT NULL,
                modalidad       VARCHAR(30) NOT NULL DEFAULT 'Presencial',
                estado          VARCHAR(30) NOT NULL DEFAULT 'Programada',
                notas_atencion  TEXT,
                CONSTRAINT bie_cita_horas_ck CHECK (fin > inicio)
            );
            CREATE INDEX bie_cita_caso_idx ON bienestar.cita (id_caso);
            CREATE INDEX bie_cita_especialista_inicio_idx ON bienestar.cita (id_especialista, inicio);

            CREATE TABLE bienestar.derivacion (
                id_derivacion          BIGSERIAL PRIMARY KEY,
                id_caso                BIGINT NOT NULL REFERENCES bienestar.caso_bienestar (id_caso) ON DELETE CASCADE,
                id_especialista_destino BIGINT REFERENCES bienestar.especialista (id_especialista) ON DELETE SET NULL,
                entidad_externa        VARCHAR(180),
                motivo                 TEXT NOT NULL,
                estado                 VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
                derivado_en            TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT bie_derivacion_destino_ck CHECK (id_especialista_destino IS NOT NULL OR entidad_externa IS NOT NULL)
            );
            CREATE INDEX bie_derivacion_caso_idx ON bienestar.derivacion (id_caso);
            CREATE INDEX bie_derivacion_destino_idx ON bienestar.derivacion (id_especialista_destino);

            CREATE TABLE bienestar.seguimiento_derivacion (
                id_seguimiento BIGSERIAL PRIMARY KEY,
                id_derivacion  BIGINT NOT NULL REFERENCES bienestar.derivacion (id_derivacion) ON DELETE CASCADE,
                fecha          DATE NOT NULL DEFAULT CURRENT_DATE,
                descripcion    TEXT NOT NULL,
                registrado_por BIGINT NOT NULL REFERENCES bienestar.especialista (id_especialista) ON DELETE RESTRICT
            );
            CREATE INDEX bie_seguimiento_derivacion_idx ON bienestar.seguimiento_derivacion (id_derivacion);
            CREATE INDEX bie_seguimiento_registrador_idx ON bienestar.seguimiento_derivacion (registrado_por);

            CREATE TABLE bienestar.reporte (
                id_reporte   BIGSERIAL PRIMARY KEY,
                tipo         VARCHAR(80) NOT NULL,
                parametros   JSONB,
                id_archivo   BIGINT REFERENCES comun.archivo (id_archivo) ON DELETE SET NULL,
                generado_por BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
                generado_en  TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX bie_reporte_archivo_idx ON bienestar.reporte (id_archivo);
            CREATE INDEX bie_reporte_usuario_idx ON bienestar.reporte (generado_por);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TABLE IF EXISTS bienestar.{$table}");
        }
    }
};
