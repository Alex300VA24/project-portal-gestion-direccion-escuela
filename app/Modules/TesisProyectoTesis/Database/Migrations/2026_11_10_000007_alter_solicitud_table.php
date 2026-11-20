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
            ALTER TABLE tesis.solicitud
                ALTER COLUMN id_archivo DROP NOT NULL,
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN destinatario VARCHAR(120),
                ADD COLUMN destinatario_area VARCHAR(120),
                ADD COLUMN asunto VARCHAR(200),
                ADD COLUMN detalle TEXT,
                ADD COLUMN id_docente_sugerido BIGINT,
                ADD COLUMN atendida_en TIMESTAMPTZ,
                ADD CONSTRAINT tes_solicitud_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_solicitud_estado_ck CHECK (estado IN ('Pendiente', 'Atendida', 'Rechazada')),
                ADD CONSTRAINT tes_solicitud_docente_fk FOREIGN KEY (id_docente_sugerido) REFERENCES core.docente(id_docente) ON DELETE RESTRICT;
            CREATE INDEX tes_solicitud_docente_idx ON tesis.solicitud(id_docente_sugerido);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_solicitud_docente_idx;
            ALTER TABLE tesis.solicitud
                DROP CONSTRAINT IF EXISTS tes_solicitud_docente_fk,
                DROP CONSTRAINT IF EXISTS tes_solicitud_estado_ck,
                DROP CONSTRAINT IF EXISTS tes_solicitud_uuid_uq,
                DROP COLUMN IF EXISTS atendida_en,
                DROP COLUMN IF EXISTS id_docente_sugerido,
                DROP COLUMN IF EXISTS detalle,
                DROP COLUMN IF EXISTS asunto,
                DROP COLUMN IF EXISTS destinatario_area,
                DROP COLUMN IF EXISTS destinatario,
                DROP COLUMN IF EXISTS uuid;
            DELETE FROM tesis.resolucion WHERE id_solicitud IN (SELECT id_solicitud FROM tesis.solicitud WHERE id_archivo IS NULL);
            DELETE FROM tesis.solicitud WHERE id_archivo IS NULL;
            ALTER TABLE tesis.solicitud ALTER COLUMN id_archivo SET NOT NULL;
            SQL);
    }
};
