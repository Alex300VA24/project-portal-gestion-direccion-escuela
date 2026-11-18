<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registro append-only de decisiones docentes sobre el trabajo final.
 *
 * Cada fila conserva al actor, la fecha y la instantánea de versiones de
 * entrega/evaluación usadas. Una decisión posterior no borra la anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.decision_trabajo (
                id_decision BIGSERIAL PRIMARY KEY,
                id_seccion  BIGINT NOT NULL REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
                tipo        VARCHAR(20) NOT NULL,
                accion      VARCHAR(20) NOT NULL,
                id_proyecto BIGINT NOT NULL REFERENCES investigacion_formativa.proyecto (id_proyecto) ON DELETE RESTRICT,
                version     INTEGER NOT NULL,
                user_id     BIGINT NOT NULL REFERENCES public.users (id) ON DELETE RESTRICT,
                id_docente  BIGINT NOT NULL REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
                decidido_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                evidencia   JSONB NOT NULL DEFAULT '{}'::jsonb,
                motivo      TEXT,
                CONSTRAINT if_decision_trabajo_tipo_ck
                    CHECK (tipo IN ('aprobacion', 'seleccion')),
                CONSTRAINT if_decision_trabajo_accion_ck
                    CHECK (accion IN ('registrar', 'retirar')),
                CONSTRAINT if_decision_trabajo_version_ck
                    CHECK (version > 0),
                CONSTRAINT if_decision_trabajo_version_unica
                    UNIQUE (id_seccion, tipo, id_proyecto, version)
            );
            CREATE INDEX if_decision_trabajo_seccion_tipo_idx
                ON investigacion_formativa.decision_trabajo (id_seccion, tipo, id_proyecto, version DESC);
            CREATE INDEX if_decision_trabajo_proyecto_idx
                ON investigacion_formativa.decision_trabajo (id_proyecto);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.decision_trabajo');
    }
};
