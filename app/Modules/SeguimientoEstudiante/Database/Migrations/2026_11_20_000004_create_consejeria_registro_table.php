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
            CREATE TABLE seguimiento_estudiante.consejeria_registro (
                id_ambito BIGINT NOT NULL REFERENCES seguimiento_estudiante.consejeria_ambito (id_ambito) ON DELETE RESTRICT,
                clave VARCHAR(80) NOT NULL CHECK (btrim(clave) <> ''),
                tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('case', 'alert', 'plan', 'report', 'audit', 'notification', 'meeting', 'document', 'event')),
                id_seccion BIGINT REFERENCES core.seccion (id_seccion) ON DELETE RESTRICT,
                id_estudiante BIGINT REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                datos JSONB NOT NULL CHECK (jsonb_typeof(datos) = 'object'),
                actualizado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (id_ambito, clave)
            );
            CREATE INDEX se_consejeria_registro_tipo_idx ON seguimiento_estudiante.consejeria_registro (id_ambito, tipo);
            CREATE INDEX se_consejeria_registro_estudiante_idx ON seguimiento_estudiante.consejeria_registro (id_estudiante, tipo);
            CREATE INDEX se_consejeria_registro_seccion_idx ON seguimiento_estudiante.consejeria_registro (id_seccion);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS seguimiento_estudiante.consejeria_registro');
        }
    }
};
