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
            ALTER TABLE tesis.colaborador
                ALTER COLUMN id_persona DROP NOT NULL,
                ADD COLUMN nombres_completos VARCHAR(200),
                ADD CONSTRAINT tes_colaborador_identidad_ck CHECK (id_persona IS NOT NULL OR nombres_completos IS NOT NULL);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DELETE FROM tesis.colaborador WHERE id_persona IS NULL;
            ALTER TABLE tesis.colaborador
                DROP CONSTRAINT IF EXISTS tes_colaborador_identidad_ck,
                DROP COLUMN IF EXISTS nombres_completos,
                ALTER COLUMN id_persona SET NOT NULL;
            SQL);
    }
};
