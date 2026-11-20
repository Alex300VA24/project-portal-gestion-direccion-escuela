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
            ALTER TABLE tesis.observacion
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN id_criterio BIGINT,
                ADD COLUMN seccion_documento VARCHAR(120),
                ADD COLUMN respuesta_estudiante TEXT,
                ADD COLUMN id_entrega_correccion BIGINT,
                ADD COLUMN respondida_en TIMESTAMPTZ,
                ADD CONSTRAINT tes_observacion_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_observacion_estado_ck CHECK (estado IN ('Pendiente', 'Respondida', 'Levantada')),
                ADD CONSTRAINT tes_observacion_criterio_fk FOREIGN KEY (id_criterio) REFERENCES tesis.criterio_rubrica(id_criterio) ON DELETE RESTRICT,
                ADD CONSTRAINT tes_observacion_entrega_fk FOREIGN KEY (id_entrega_correccion) REFERENCES tesis.entrega(id_entrega) ON DELETE SET NULL;
            CREATE INDEX tes_observacion_revision_idx ON tesis.observacion(id_revision);
            CREATE INDEX tes_observacion_criterio_idx ON tesis.observacion(id_criterio);
            CREATE INDEX tes_observacion_entrega_idx ON tesis.observacion(id_entrega_correccion);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_observacion_entrega_idx;
            DROP INDEX IF EXISTS tesis.tes_observacion_criterio_idx;
            DROP INDEX IF EXISTS tesis.tes_observacion_revision_idx;
            ALTER TABLE tesis.observacion
                DROP CONSTRAINT IF EXISTS tes_observacion_entrega_fk,
                DROP CONSTRAINT IF EXISTS tes_observacion_criterio_fk,
                DROP CONSTRAINT IF EXISTS tes_observacion_estado_ck,
                DROP CONSTRAINT IF EXISTS tes_observacion_uuid_uq,
                DROP COLUMN IF EXISTS respondida_en,
                DROP COLUMN IF EXISTS id_entrega_correccion,
                DROP COLUMN IF EXISTS respuesta_estudiante,
                DROP COLUMN IF EXISTS seccion_documento,
                DROP COLUMN IF EXISTS id_criterio,
                DROP COLUMN IF EXISTS uuid;
            SQL);
    }
};
