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
            ALTER TABLE tesis.entrega
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'Avance',
                ADD COLUMN numero_semana SMALLINT,
                ADD COLUMN comentario TEXT,
                ADD COLUMN entregado_por BIGINT,
                ADD COLUMN estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
                ADD CONSTRAINT tes_entrega_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_entrega_tipo_ck CHECK (tipo IN ('Avance', 'Final')),
                ADD CONSTRAINT tes_entrega_semana_ck CHECK (numero_semana IS NULL OR numero_semana > 0),
                ADD CONSTRAINT tes_entrega_estado_ck CHECK (estado IN ('Pendiente', 'Observada', 'SinObservaciones', 'Culminada')),
                ADD CONSTRAINT tes_entrega_estudiante_fk FOREIGN KEY (entregado_por) REFERENCES core.estudiante(id_estudiante) ON DELETE RESTRICT;
            CREATE INDEX tes_entrega_estudiante_idx ON tesis.entrega(entregado_por);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_entrega_estudiante_idx;
            ALTER TABLE tesis.entrega
                DROP CONSTRAINT IF EXISTS tes_entrega_estudiante_fk,
                DROP CONSTRAINT IF EXISTS tes_entrega_estado_ck,
                DROP CONSTRAINT IF EXISTS tes_entrega_semana_ck,
                DROP CONSTRAINT IF EXISTS tes_entrega_tipo_ck,
                DROP CONSTRAINT IF EXISTS tes_entrega_uuid_uq,
                DROP COLUMN IF EXISTS estado,
                DROP COLUMN IF EXISTS entregado_por,
                DROP COLUMN IF EXISTS comentario,
                DROP COLUMN IF EXISTS numero_semana,
                DROP COLUMN IF EXISTS tipo,
                DROP COLUMN IF EXISTS uuid;
            SQL);
    }
};
