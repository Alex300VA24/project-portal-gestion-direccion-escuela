<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completa los esquemas compartidos según esquemas_y_origen_de_tablas.pdf:
 * core (equivalencia_curso, 22 tablas) y seguridad (10 tablas), más el
 * catálogo de los 12 módulos en seguridad.modulo_sistema.
 */
return new class extends Migration
{
    private const MODULOS = [
        ['prematricula', 'Prematrícula', 'prematricula'],
        ['bienestar', 'Bienestar', 'bienestar'],
        ['investigacion_formativa', 'Investigación formativa', 'investigacion_formativa'],
        ['seguimiento_estudiante', 'Seguimiento al estudiante', 'seguimiento_estudiante'],
        ['web_institucional', 'Web institucional', 'web_institucional'],
        ['calidad', 'Calidad', 'calidad'],
        ['gestion_horarios', 'Gestión de horarios', 'gestion_horarios'],
        ['cotecu', 'COTECU', 'cotecu'],
        ['gestion_practicas', 'Gestión de prácticas', 'gestion_practicas'],
        ['tesis', 'Tesis y proyecto de tesis', 'tesis'],
        ['gestion_matriculas', 'Gestión de matrículas', 'gestion_matriculas'],
        ['seguimiento_egresado', 'Seguimiento al egresado', 'seguimiento_egresado'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE core.equivalencia_curso (
                id_curso_plan_origen  BIGINT NOT NULL REFERENCES core.curso_plan (id_curso_plan) ON DELETE RESTRICT,
                id_curso_plan_destino BIGINT NOT NULL REFERENCES core.curso_plan (id_curso_plan) ON DELETE RESTRICT,
                CONSTRAINT core_equivalencia_curso_pk PRIMARY KEY (id_curso_plan_origen, id_curso_plan_destino),
                CONSTRAINT core_equivalencia_curso_distinto_ck CHECK (id_curso_plan_origen <> id_curso_plan_destino)
            );
            CREATE INDEX core_equivalencia_curso_destino_idx ON core.equivalencia_curso (id_curso_plan_destino);

            CREATE TABLE seguridad.rol (
                id_rol      BIGSERIAL PRIMARY KEY,
                id_modulo   BIGINT NOT NULL REFERENCES seguridad.modulo_sistema (id_modulo) ON DELETE RESTRICT,
                codigo      VARCHAR(50) NOT NULL,
                nombre      VARCHAR(120) NOT NULL,
                descripcion TEXT,
                activo      BOOLEAN NOT NULL DEFAULT TRUE,
                CONSTRAINT seg_rol_modulo_codigo_uq UNIQUE (id_modulo, codigo)
            );

            CREATE TABLE seguridad.permiso (
                id_permiso  BIGSERIAL PRIMARY KEY,
                id_modulo   BIGINT NOT NULL REFERENCES seguridad.modulo_sistema (id_modulo) ON DELETE RESTRICT,
                codigo      VARCHAR(80) NOT NULL,
                descripcion TEXT,
                CONSTRAINT seg_permiso_modulo_codigo_uq UNIQUE (id_modulo, codigo)
            );

            CREATE TABLE seguridad.rol_permiso (
                id_rol     BIGINT NOT NULL REFERENCES seguridad.rol (id_rol) ON DELETE CASCADE,
                id_permiso BIGINT NOT NULL REFERENCES seguridad.permiso (id_permiso) ON DELETE CASCADE,
                CONSTRAINT seg_rol_permiso_pk PRIMARY KEY (id_rol, id_permiso)
            );
            CREATE INDEX seg_rol_permiso_permiso_idx ON seguridad.rol_permiso (id_permiso);

            CREATE TABLE seguridad.usuario_rol (
                id_usuario_rol BIGSERIAL PRIMARY KEY,
                id_usuario     BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE CASCADE,
                id_rol         BIGINT NOT NULL REFERENCES seguridad.rol (id_rol) ON DELETE RESTRICT,
                fecha_inicio   DATE NOT NULL DEFAULT CURRENT_DATE,
                fecha_fin      DATE,
                es_principal   BOOLEAN NOT NULL DEFAULT FALSE,
                estado         VARCHAR(20) NOT NULL DEFAULT 'Activo',
                CONSTRAINT seg_usuario_rol_vigencia_uq UNIQUE (id_usuario, id_rol, fecha_inicio),
                CONSTRAINT seg_usuario_rol_fechas_ck CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
            );
            CREATE INDEX seg_usuario_rol_rol_idx ON seguridad.usuario_rol (id_rol);

            CREATE TABLE seguridad.solicitud_acceso (
                id_solicitud      BIGSERIAL PRIMARY KEY,
                id_persona        BIGINT NOT NULL REFERENCES core.persona (id_persona) ON DELETE RESTRICT,
                id_rol_solicitado BIGINT NOT NULL REFERENCES seguridad.rol (id_rol) ON DELETE RESTRICT,
                motivo            TEXT,
                estado            VARCHAR(20) NOT NULL DEFAULT 'Pendiente',
                revisado_por      BIGINT REFERENCES seguridad.usuario (id_usuario) ON DELETE SET NULL,
                solicitado_en     TIMESTAMPTZ NOT NULL DEFAULT now(),
                revisado_en       TIMESTAMPTZ
            );
            CREATE INDEX seg_solicitud_acceso_persona_idx ON seguridad.solicitud_acceso (id_persona);
            CREATE INDEX seg_solicitud_acceso_rol_idx ON seguridad.solicitud_acceso (id_rol_solicitado);
            CREATE INDEX seg_solicitud_acceso_revisor_idx ON seguridad.solicitud_acceso (revisado_por);

            CREATE TABLE seguridad.token_usuario (
                id_token   BIGSERIAL PRIMARY KEY,
                id_usuario BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE CASCADE,
                token_hash VARCHAR(255) NOT NULL,
                tipo       VARCHAR(40) NOT NULL,
                expira_en  TIMESTAMPTZ NOT NULL,
                usado_en   TIMESTAMPTZ,
                CONSTRAINT seg_token_usuario_hash_uq UNIQUE (token_hash)
            );
            CREATE INDEX seg_token_usuario_usuario_idx ON seguridad.token_usuario (id_usuario);

            CREATE TABLE seguridad.registro_acceso (
                id_registro      BIGSERIAL PRIMARY KEY,
                id_usuario       BIGINT REFERENCES seguridad.usuario (id_usuario) ON DELETE SET NULL,
                correo_intentado VARCHAR(190) NOT NULL,
                exitoso          BOOLEAN NOT NULL,
                ip               VARCHAR(45),
                user_agent       TEXT,
                registrado_en    TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX seg_registro_acceso_usuario_idx ON seguridad.registro_acceso (id_usuario);

            CREATE TABLE seguridad.auditoria (
                id_auditoria     BIGSERIAL PRIMARY KEY,
                id_usuario       BIGINT REFERENCES seguridad.usuario (id_usuario) ON DELETE SET NULL,
                id_modulo        BIGINT REFERENCES seguridad.modulo_sistema (id_modulo) ON DELETE RESTRICT,
                tabla_afectada   VARCHAR(120) NOT NULL,
                id_registro      BIGINT,
                accion           VARCHAR(20) NOT NULL,
                datos_anteriores JSONB,
                datos_nuevos     JSONB,
                ip               VARCHAR(45),
                registrado_en    TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT seg_auditoria_accion_ck CHECK (accion IN ('INSERT', 'UPDATE', 'DELETE', 'LOGIN', 'OTRA'))
            );
            CREATE INDEX seg_auditoria_usuario_idx ON seguridad.auditoria (id_usuario);
            CREATE INDEX seg_auditoria_modulo_idx ON seguridad.auditoria (id_modulo);
            CREATE INDEX seg_auditoria_tabla_idx ON seguridad.auditoria (tabla_afectada, id_registro);

            CREATE FUNCTION seguridad.auditoria_inalterable()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'seguridad.auditoria es inalterable'
                    USING ERRCODE = '23514';
            END;
            $$;

            CREATE TRIGGER seg_auditoria_inalterable_trg
            BEFORE UPDATE OR DELETE ON seguridad.auditoria
            FOR EACH ROW
            EXECUTE FUNCTION seguridad.auditoria_inalterable();
            SQL);

        foreach (self::MODULOS as [$codigo, $nombre, $esquema]) {
            DB::table('seguridad.modulo_sistema')->insertOrIgnore([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'esquema' => $esquema,
                'activo' => true,
            ]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS seg_auditoria_inalterable_trg ON seguridad.auditoria;
            DROP FUNCTION IF EXISTS seguridad.auditoria_inalterable();
            DROP TABLE IF EXISTS seguridad.auditoria;
            DROP TABLE IF EXISTS seguridad.registro_acceso;
            DROP TABLE IF EXISTS seguridad.token_usuario;
            DROP TABLE IF EXISTS seguridad.solicitud_acceso;
            DROP TABLE IF EXISTS seguridad.usuario_rol;
            DROP TABLE IF EXISTS seguridad.rol_permiso;
            DROP TABLE IF EXISTS seguridad.permiso;
            DROP TABLE IF EXISTS seguridad.rol;
            DROP TABLE IF EXISTS core.equivalencia_curso;
            SQL);
    }
};
