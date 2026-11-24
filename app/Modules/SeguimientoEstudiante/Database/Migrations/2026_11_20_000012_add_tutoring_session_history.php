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
            ALTER TABLE seguimiento_estudiante.solicitud_estudiante
                ADD COLUMN version INTEGER NOT NULL DEFAULT 1 CHECK (version > 0);

            ALTER TABLE seguimiento_estudiante.atencion_compromiso
                ADD COLUMN origen_sesion BOOLEAN NOT NULL DEFAULT false;
            CREATE UNIQUE INDEX se_compromiso_sesion_uq ON seguimiento_estudiante.atencion_compromiso (id_atencion)
                WHERE origen_sesion;

            CREATE TABLE seguimiento_estudiante.atencion_sesion_revision (
                id_atencion BIGINT NOT NULL REFERENCES seguimiento_estudiante.atencion (id_atencion) ON DELETE RESTRICT,
                version INTEGER NOT NULL CHECK (version > 0),
                id_usuario BIGINT REFERENCES public.users (id) ON DELETE SET NULL,
                estado VARCHAR(30) NOT NULL CHECK (estado IN ('Atendida', 'Inasistencia', 'No realizada')),
                actividad TEXT,
                acuerdos TEXT,
                observaciones TEXT,
                motivo_correccion TEXT,
                registrada_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (id_atencion, version)
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TABLE seguimiento_estudiante.atencion_sesion_revision;
            DROP INDEX seguimiento_estudiante.se_compromiso_sesion_uq;
            ALTER TABLE seguimiento_estudiante.atencion_compromiso DROP COLUMN origen_sesion;
            ALTER TABLE seguimiento_estudiante.solicitud_estudiante DROP COLUMN version;
            SQL);
    }
};
