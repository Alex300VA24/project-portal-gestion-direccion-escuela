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
            ALTER TABLE seguimiento_estudiante.tutor_asignacion
                ADD COLUMN id_usuario_asignador BIGINT REFERENCES public.users (id) ON DELETE SET NULL,
                ADD COLUMN fecha_inicio DATE,
                ADD COLUMN fecha_fin DATE,
                ADD COLUMN motivo TEXT,
                ADD COLUMN causa_fin TEXT,
                ADD COLUMN observacion TEXT,
                ADD CONSTRAINT se_tutor_asignacion_vigencia_ck CHECK (fecha_fin >= fecha_inicio);

            CREATE INDEX se_tutor_asignacion_usuario_idx
                ON seguimiento_estudiante.tutor_asignacion (id_usuario_asignador);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE seguimiento_estudiante.tutor_asignacion
                DROP CONSTRAINT se_tutor_asignacion_vigencia_ck,
                DROP COLUMN id_usuario_asignador,
                DROP COLUMN fecha_inicio,
                DROP COLUMN fecha_fin,
                DROP COLUMN motivo,
                DROP COLUMN causa_fin,
                DROP COLUMN observacion;
            SQL);
    }
};
