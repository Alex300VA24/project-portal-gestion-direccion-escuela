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
            ALTER TABLE tesis.resolucion
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN organo_emisor VARCHAR(150),
                ADD COLUMN dirigido_a VARCHAR(150),
                ADD COLUMN detalle TEXT,
                ADD COLUMN registrado_por BIGINT,
                ADD COLUMN registrado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                ADD CONSTRAINT tes_resolucion_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_resolucion_solicitud_uq UNIQUE (id_solicitud),
                ADD CONSTRAINT tes_resolucion_usuario_fk FOREIGN KEY (registrado_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT;
            CREATE INDEX tes_resolucion_usuario_idx ON tesis.resolucion(registrado_por);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_resolucion_usuario_idx;
            ALTER TABLE tesis.resolucion
                DROP CONSTRAINT IF EXISTS tes_resolucion_usuario_fk,
                DROP CONSTRAINT IF EXISTS tes_resolucion_solicitud_uq,
                DROP CONSTRAINT IF EXISTS tes_resolucion_uuid_uq,
                DROP COLUMN IF EXISTS registrado_en,
                DROP COLUMN IF EXISTS registrado_por,
                DROP COLUMN IF EXISTS detalle,
                DROP COLUMN IF EXISTS dirigido_a,
                DROP COLUMN IF EXISTS organo_emisor,
                DROP COLUMN IF EXISTS uuid;
            SQL);
    }
};
