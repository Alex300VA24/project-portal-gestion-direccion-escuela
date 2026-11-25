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
            CREATE TABLE seguimiento_estudiante.atencion_participante (
                id_atencion BIGINT NOT NULL REFERENCES seguimiento_estudiante.atencion (id_atencion) ON DELETE RESTRICT,
                id_persona BIGINT NOT NULL REFERENCES core.persona (id_persona) ON DELETE RESTRICT,
                papel VARCHAR(50) NOT NULL CHECK (btrim(papel) <> ''),
                asistio BOOLEAN,
                PRIMARY KEY (id_atencion, id_persona, papel)
            );
            CREATE INDEX se_atencion_participante_persona_idx ON seguimiento_estudiante.atencion_participante (id_persona);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS seguimiento_estudiante.atencion_participante');
        }
    }
};
