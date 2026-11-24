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
            ALTER TABLE tesis.reporte_similitud
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN id_docente BIGINT,
                ADD COLUMN indicaciones TEXT,
                ADD COLUMN resultado VARCHAR(20),
                ADD CONSTRAINT tes_reporte_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_reporte_resultado_ck CHECK (resultado IS NULL OR resultado IN ('Apto', 'NoApto')),
                ADD CONSTRAINT tes_reporte_docente_fk FOREIGN KEY (id_docente) REFERENCES core.docente(id_docente) ON DELETE RESTRICT;
            CREATE INDEX tes_reporte_docente_idx ON tesis.reporte_similitud(id_docente);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_reporte_docente_idx;
            ALTER TABLE tesis.reporte_similitud
                DROP CONSTRAINT IF EXISTS tes_reporte_docente_fk,
                DROP CONSTRAINT IF EXISTS tes_reporte_resultado_ck,
                DROP CONSTRAINT IF EXISTS tes_reporte_uuid_uq,
                DROP COLUMN IF EXISTS resultado,
                DROP COLUMN IF EXISTS indicaciones,
                DROP COLUMN IF EXISTS id_docente,
                DROP COLUMN IF EXISTS uuid;
            SQL);
    }
};
