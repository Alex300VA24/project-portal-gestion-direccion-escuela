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
            ALTER TABLE tesis.partida_presupuesto
                ALTER COLUMN id_recurso DROP NOT NULL,
                ADD COLUMN id_proyecto BIGINT,
                ADD COLUMN codigo_clasificador VARCHAR(20),
                ADD COLUMN descripcion VARCHAR(200),
                ADD CONSTRAINT tes_partida_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE,
                ADD CONSTRAINT tes_partida_origen_ck CHECK (id_recurso IS NOT NULL OR id_proyecto IS NOT NULL);
            CREATE INDEX tes_partida_proyecto_idx ON tesis.partida_presupuesto(id_proyecto);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_partida_proyecto_idx;
            DELETE FROM tesis.partida_presupuesto WHERE id_recurso IS NULL;
            ALTER TABLE tesis.partida_presupuesto
                DROP CONSTRAINT IF EXISTS tes_partida_origen_ck,
                DROP CONSTRAINT IF EXISTS tes_partida_proyecto_fk,
                DROP COLUMN IF EXISTS descripcion,
                DROP COLUMN IF EXISTS codigo_clasificador,
                DROP COLUMN IF EXISTS id_proyecto,
                ALTER COLUMN id_recurso SET NOT NULL;
            SQL);
    }
};
