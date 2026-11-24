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
            ALTER TABLE tesis.criterio_rubrica
                ALTER COLUMN puntaje_maximo DROP NOT NULL,
                ADD COLUMN seccion VARCHAR(80) NOT NULL DEFAULT '',
                ADD COLUMN aplica_a VARCHAR(10) NOT NULL DEFAULT 'Ambas',
                ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'Activo',
                ADD COLUMN permite_no_aplica BOOLEAN NOT NULL DEFAULT false,
                ADD CONSTRAINT tes_criterio_aplica_ck CHECK (aplica_a IN ('Proyecto', 'Tesis', 'Ambas'));
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE tesis.criterio_rubrica
                DROP CONSTRAINT IF EXISTS tes_criterio_aplica_ck,
                DROP COLUMN IF EXISTS permite_no_aplica,
                DROP COLUMN IF EXISTS estado,
                DROP COLUMN IF EXISTS aplica_a,
                DROP COLUMN IF EXISTS seccion;
            UPDATE tesis.criterio_rubrica SET puntaje_maximo = 1 WHERE puntaje_maximo IS NULL;
            ALTER TABLE tesis.criterio_rubrica ALTER COLUMN puntaje_maximo SET NOT NULL;
            SQL);
    }
};
