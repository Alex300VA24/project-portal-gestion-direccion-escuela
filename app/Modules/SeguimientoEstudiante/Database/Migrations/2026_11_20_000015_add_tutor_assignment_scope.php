<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE seguimiento_estudiante.coordinacion_ambito (
                id_persona BIGINT NOT NULL REFERENCES core.persona (id_persona) ON DELETE RESTRICT,
                id_escuela BIGINT NOT NULL REFERENCES core.escuela_profesional (id_escuela) ON DELETE RESTRICT,
                id_periodo BIGINT NOT NULL REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
                PRIMARY KEY (id_persona, id_escuela, id_periodo)
            );
            CREATE TABLE seguimiento_estudiante.tutor_habilitado (
                id_docente BIGINT NOT NULL REFERENCES core.docente (id_docente) ON DELETE RESTRICT,
                id_escuela BIGINT NOT NULL REFERENCES core.escuela_profesional (id_escuela) ON DELETE RESTRICT,
                id_periodo BIGINT NOT NULL REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
                PRIMARY KEY (id_docente, id_escuela, id_periodo)
            );
            ALTER TABLE seguimiento_estudiante.tutor_asignacion DROP CONSTRAINT se_tutor_asignacion_uq;
            CREATE UNIQUE INDEX se_tutor_asignacion_activa_uq ON seguimiento_estudiante.tutor_asignacion
                (id_estudiante, id_docente, id_periodo, tipo) WHERE estado = 'Activa';
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE seguimiento_estudiante.tutor_asignacion ADD CONSTRAINT se_tutor_asignacion_uq
                UNIQUE (id_estudiante, id_docente, id_periodo, tipo);
            DROP INDEX seguimiento_estudiante.se_tutor_asignacion_activa_uq;
            DROP TABLE seguimiento_estudiante.tutor_habilitado;
            DROP TABLE seguimiento_estudiante.coordinacion_ambito;
            SQL);
    }
};
