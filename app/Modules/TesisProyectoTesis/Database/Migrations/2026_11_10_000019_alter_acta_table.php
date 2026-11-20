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
            ALTER TABLE tesis.acta
                ALTER COLUMN id_sustentacion DROP NOT NULL,
                ALTER COLUMN nota_final DROP NOT NULL,
                ALTER COLUMN calificacion DROP NOT NULL,
                ALTER COLUMN id_archivo DROP NOT NULL,
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT 'Sustentacion',
                ADD COLUMN id_proyecto BIGINT,
                ADD COLUMN resultado VARCHAR(40),
                ADD COLUMN condicion VARCHAR(20),
                ADD COLUMN id_archivo_firmado BIGINT,
                ADD COLUMN registrado_por BIGINT,
                ADD COLUMN firmada_en TIMESTAMPTZ,
                ADD COLUMN informado_director_en TIMESTAMPTZ,
                ADD CONSTRAINT tes_acta_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_acta_tipo_ck CHECK (tipo IN ('Dictamen', 'Sustentacion')),
                ADD CONSTRAINT tes_acta_sustentacion_req_ck CHECK (tipo = 'Dictamen' OR id_sustentacion IS NOT NULL),
                ADD CONSTRAINT tes_acta_condicion_ck CHECK (condicion IS NULL OR condicion IN ('Expedito', 'Impedido')),
                ADD CONSTRAINT tes_acta_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE RESTRICT,
                ADD CONSTRAINT tes_acta_firmado_fk FOREIGN KEY (id_archivo_firmado) REFERENCES comun.archivo(id_archivo) ON DELETE RESTRICT,
                ADD CONSTRAINT tes_acta_usuario_fk FOREIGN KEY (registrado_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT;
            CREATE INDEX tes_acta_proyecto_idx ON tesis.acta(id_proyecto);
            CREATE INDEX tes_acta_firmado_idx ON tesis.acta(id_archivo_firmado);
            CREATE INDEX tes_acta_usuario_idx ON tesis.acta(registrado_por);
            CREATE UNIQUE INDEX tes_acta_dictamen_uq ON tesis.acta(id_proyecto) WHERE tipo = 'Dictamen';
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_acta_dictamen_uq;
            DROP INDEX IF EXISTS tesis.tes_acta_usuario_idx;
            DROP INDEX IF EXISTS tesis.tes_acta_firmado_idx;
            DROP INDEX IF EXISTS tesis.tes_acta_proyecto_idx;
            DELETE FROM tesis.acta WHERE id_sustentacion IS NULL OR nota_final IS NULL OR calificacion IS NULL OR id_archivo IS NULL;
            ALTER TABLE tesis.acta
                DROP CONSTRAINT IF EXISTS tes_acta_usuario_fk,
                DROP CONSTRAINT IF EXISTS tes_acta_firmado_fk,
                DROP CONSTRAINT IF EXISTS tes_acta_proyecto_fk,
                DROP CONSTRAINT IF EXISTS tes_acta_condicion_ck,
                DROP CONSTRAINT IF EXISTS tes_acta_sustentacion_req_ck,
                DROP CONSTRAINT IF EXISTS tes_acta_tipo_ck,
                DROP CONSTRAINT IF EXISTS tes_acta_uuid_uq,
                DROP COLUMN IF EXISTS informado_director_en,
                DROP COLUMN IF EXISTS firmada_en,
                DROP COLUMN IF EXISTS registrado_por,
                DROP COLUMN IF EXISTS id_archivo_firmado,
                DROP COLUMN IF EXISTS condicion,
                DROP COLUMN IF EXISTS resultado,
                DROP COLUMN IF EXISTS id_proyecto,
                DROP COLUMN IF EXISTS tipo,
                DROP COLUMN IF EXISTS uuid,
                ALTER COLUMN id_archivo SET NOT NULL,
                ALTER COLUMN calificacion SET NOT NULL,
                ALTER COLUMN nota_final SET NOT NULL,
                ALTER COLUMN id_sustentacion SET NOT NULL;
            SQL);
    }
};
