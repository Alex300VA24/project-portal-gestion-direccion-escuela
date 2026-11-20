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
            ALTER TABLE tesis.revision
                ALTER COLUMN id_rubrica DROP NOT NULL,
                DROP CONSTRAINT tes_revision_entrega_docente_uq,
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'Avance',
                ADD COLUMN rol_revisor VARCHAR(20) NOT NULL DEFAULT 'Asesor',
                ADD COLUMN cumple_todo BOOLEAN,
                ADD COLUMN conforme BOOLEAN NOT NULL DEFAULT false,
                ADD COLUMN referencial BOOLEAN NOT NULL DEFAULT false,
                ADD CONSTRAINT tes_revision_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_revision_entrega_docente_tipo_uq UNIQUE (id_entrega, id_docente, tipo),
                ADD CONSTRAINT tes_revision_tipo_ck CHECK (tipo IN ('Avance', 'Verificacion', 'Intermedia')),
                ADD CONSTRAINT tes_revision_rol_ck CHECK (rol_revisor IN ('Asesor', 'Jurado')),
                ADD CONSTRAINT tes_revision_conforme_ck CHECK (NOT conforme OR cumple_todo IS TRUE);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DELETE FROM tesis.revision r USING tesis.revision o
                WHERE r.id_entrega = o.id_entrega AND r.id_docente = o.id_docente AND r.id_revision > o.id_revision;
            UPDATE tesis.revision SET id_rubrica = (SELECT MIN(id_rubrica) FROM tesis.rubrica) WHERE id_rubrica IS NULL;
            DELETE FROM tesis.revision WHERE id_rubrica IS NULL;
            ALTER TABLE tesis.revision
                DROP CONSTRAINT IF EXISTS tes_revision_conforme_ck,
                DROP CONSTRAINT IF EXISTS tes_revision_rol_ck,
                DROP CONSTRAINT IF EXISTS tes_revision_tipo_ck,
                DROP CONSTRAINT IF EXISTS tes_revision_entrega_docente_tipo_uq,
                DROP CONSTRAINT IF EXISTS tes_revision_uuid_uq,
                DROP COLUMN IF EXISTS referencial,
                DROP COLUMN IF EXISTS conforme,
                DROP COLUMN IF EXISTS cumple_todo,
                DROP COLUMN IF EXISTS rol_revisor,
                DROP COLUMN IF EXISTS tipo,
                DROP COLUMN IF EXISTS uuid,
                ADD CONSTRAINT tes_revision_entrega_docente_uq UNIQUE (id_entrega, id_docente),
                ALTER COLUMN id_rubrica SET NOT NULL;
            SQL);
    }
};
