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
            ALTER TABLE tesis.jurado_proyecto
                ADD COLUMN asignado_por BIGINT,
                ADD COLUMN fecha_asignacion DATE,
                ADD COLUMN fecha_fin DATE,
                ADD COLUMN motivo_cambio TEXT,
                ADD CONSTRAINT tes_jurado_cargo_ck CHECK (cargo IN ('Presidente', 'Secretario', 'Vocal')),
                ADD CONSTRAINT tes_jurado_estado_ck CHECK (estado IN ('Activo', 'Reemplazado')),
                ADD CONSTRAINT tes_jurado_usuario_fk FOREIGN KEY (asignado_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT;
            CREATE UNIQUE INDEX tes_jurado_cargo_activo_uq ON tesis.jurado_proyecto(id_proyecto, cargo) WHERE estado = 'Activo';
            CREATE INDEX tes_jurado_usuario_idx ON tesis.jurado_proyecto(asignado_por);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_jurado_usuario_idx;
            DROP INDEX IF EXISTS tesis.tes_jurado_cargo_activo_uq;
            ALTER TABLE tesis.jurado_proyecto
                DROP CONSTRAINT IF EXISTS tes_jurado_usuario_fk,
                DROP CONSTRAINT IF EXISTS tes_jurado_estado_ck,
                DROP CONSTRAINT IF EXISTS tes_jurado_cargo_ck,
                DROP COLUMN IF EXISTS motivo_cambio,
                DROP COLUMN IF EXISTS fecha_fin,
                DROP COLUMN IF EXISTS fecha_asignacion,
                DROP COLUMN IF EXISTS asignado_por;
            SQL);
    }
};
