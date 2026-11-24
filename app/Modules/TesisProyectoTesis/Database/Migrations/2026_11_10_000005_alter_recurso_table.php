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
            ALTER TABLE tesis.recurso
                ADD COLUMN cantidad NUMERIC(12,2),
                ADD CONSTRAINT tes_recurso_tipo_ck CHECK (tipo IN ('Personal', 'Bienes', 'Servicios', 'Local')),
                ADD CONSTRAINT tes_recurso_cantidad_ck CHECK (cantidad IS NULL OR cantidad > 0);
            CREATE INDEX tes_recurso_proyecto_idx ON tesis.recurso(id_proyecto);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_recurso_proyecto_idx;
            ALTER TABLE tesis.recurso
                DROP CONSTRAINT IF EXISTS tes_recurso_cantidad_ck,
                DROP CONSTRAINT IF EXISTS tes_recurso_tipo_ck,
                DROP COLUMN IF EXISTS cantidad;
            SQL);
    }
};
