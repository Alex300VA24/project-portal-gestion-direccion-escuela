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
            ALTER TABLE tesis.resultado_jurado
                ALTER COLUMN nota DROP NOT NULL,
                ADD COLUMN resultado VARCHAR(20),
                ADD COLUMN propone_mencion BOOLEAN NOT NULL DEFAULT false,
                ADD COLUMN registrado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                ADD CONSTRAINT tes_resultado_valor_ck CHECK (resultado IS NULL OR resultado IN ('Aprobatorio', 'Desaprobatorio'));
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE tesis.resultado_jurado
                DROP CONSTRAINT IF EXISTS tes_resultado_valor_ck,
                DROP COLUMN IF EXISTS registrado_en,
                DROP COLUMN IF EXISTS propone_mencion,
                DROP COLUMN IF EXISTS resultado;
            DELETE FROM tesis.resultado_jurado WHERE nota IS NULL;
            ALTER TABLE tesis.resultado_jurado ALTER COLUMN nota SET NOT NULL;
            SQL);
    }
};
