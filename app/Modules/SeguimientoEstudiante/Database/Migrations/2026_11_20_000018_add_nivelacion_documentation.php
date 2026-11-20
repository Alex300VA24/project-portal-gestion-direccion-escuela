<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.programa_nivelacion
                    ADD COLUMN id_escuela BIGINT REFERENCES core.escuela_profesional (id_escuela) ON DELETE RESTRICT,
                    ADD COLUMN documento_cierre JSONB CHECK (jsonb_typeof(documento_cierre) = 'object'),
                    ADD COLUMN cierre_publicado_en TIMESTAMPTZ,
                    ADD CONSTRAINT se_nivelacion_cierre_publicado_ck
                        CHECK (cierre_publicado_en IS NULL OR (documento_cierre IS NOT NULL AND publicado_en IS NOT NULL
                            AND cierre_publicado_en >= publicado_en));
                CREATE INDEX se_programa_nivelacion_escuela_idx ON seguimiento_estudiante.programa_nivelacion (id_escuela, id_periodo);
                ALTER TABLE seguimiento_estudiante.nivelacion_material ADD COLUMN visible_desde TIMESTAMPTZ;
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.nivelacion_material DROP COLUMN visible_desde;
                DROP INDEX seguimiento_estudiante.se_programa_nivelacion_escuela_idx;
                ALTER TABLE seguimiento_estudiante.programa_nivelacion DROP CONSTRAINT se_nivelacion_cierre_publicado_ck,
                    DROP COLUMN cierre_publicado_en, DROP COLUMN documento_cierre, DROP COLUMN id_escuela;
                SQL);
        }
    }
};
