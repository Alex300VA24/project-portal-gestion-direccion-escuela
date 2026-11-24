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
            ALTER TABLE tesis.sustentacion
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN hora_fin TIME,
                ADD COLUMN numero_intento SMALLINT NOT NULL DEFAULT 1,
                ADD COLUMN total_aprobatorios SMALLINT,
                ADD COLUMN resultado VARCHAR(20),
                ADD COLUMN modo_resultado VARCHAR(20),
                ADD COLUMN mencion_honrosa BOOLEAN NOT NULL DEFAULT false,
                ADD COLUMN programada_por BIGINT,
                ADD CONSTRAINT tes_sustentacion_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_sustentacion_proyecto_intento_uq UNIQUE (id_proyecto, numero_intento),
                ADD CONSTRAINT tes_sustentacion_intento_ck CHECK (numero_intento > 0),
                ADD CONSTRAINT tes_sustentacion_aprob_ck CHECK (total_aprobatorios IS NULL OR total_aprobatorios BETWEEN 0 AND 3),
                ADD CONSTRAINT tes_sustentacion_resultado_ck CHECK (resultado IS NULL OR resultado IN ('Aprobado', 'Desaprobado')),
                ADD CONSTRAINT tes_sustentacion_modo_ck CHECK (modo_resultado IS NULL OR modo_resultado IN ('Unanimidad', 'Mayoria')),
                ADD CONSTRAINT tes_sustentacion_mencion_ck CHECK (NOT mencion_honrosa OR modo_resultado = 'Unanimidad'),
                ADD CONSTRAINT tes_sustentacion_usuario_fk FOREIGN KEY (programada_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT;
            CREATE INDEX tes_sustentacion_usuario_idx ON tesis.sustentacion(programada_por);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_sustentacion_usuario_idx;
            ALTER TABLE tesis.sustentacion
                DROP CONSTRAINT IF EXISTS tes_sustentacion_usuario_fk,
                DROP CONSTRAINT IF EXISTS tes_sustentacion_mencion_ck,
                DROP CONSTRAINT IF EXISTS tes_sustentacion_modo_ck,
                DROP CONSTRAINT IF EXISTS tes_sustentacion_resultado_ck,
                DROP CONSTRAINT IF EXISTS tes_sustentacion_aprob_ck,
                DROP CONSTRAINT IF EXISTS tes_sustentacion_intento_ck,
                DROP CONSTRAINT IF EXISTS tes_sustentacion_proyecto_intento_uq,
                DROP CONSTRAINT IF EXISTS tes_sustentacion_uuid_uq,
                DROP COLUMN IF EXISTS programada_por,
                DROP COLUMN IF EXISTS mencion_honrosa,
                DROP COLUMN IF EXISTS modo_resultado,
                DROP COLUMN IF EXISTS resultado,
                DROP COLUMN IF EXISTS total_aprobatorios,
                DROP COLUMN IF EXISTS numero_intento,
                DROP COLUMN IF EXISTS hora_fin,
                DROP COLUMN IF EXISTS uuid;
            SQL);
    }
};
