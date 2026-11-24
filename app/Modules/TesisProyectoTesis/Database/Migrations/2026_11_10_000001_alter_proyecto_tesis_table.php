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
            ALTER TABLE tesis.proyecto_tesis
                ADD COLUMN uuid UUID NOT NULL DEFAULT gen_random_uuid(),
                ADD COLUMN enfoque VARCHAR(30),
                ADD COLUMN tipo_investigacion VARCHAR(60),
                ADD COLUMN diseno VARCHAR(120),
                ADD COLUMN lugar_ejecucion VARCHAR(200),
                ADD COLUMN institucion VARCHAR(200),
                ADD COLUMN duracion_meses SMALLINT,
                ADD COLUMN financiamiento VARCHAR(150),
                ADD COLUMN fecha_aprobacion_proyecto DATE,
                ADD COLUMN fecha_aprobacion_tesis DATE,
                ADD COLUMN id_periodo_tesis BIGINT,
                ADD COLUMN id_archivo_documento BIGINT,
                ADD CONSTRAINT tes_proyecto_uuid_uq UNIQUE (uuid),
                ADD CONSTRAINT tes_proyecto_enfoque_ck CHECK (enfoque IS NULL OR enfoque IN ('Cuantitativo', 'Cualitativo', 'Mixto')),
                ADD CONSTRAINT tes_proyecto_duracion_ck CHECK (duracion_meses IS NULL OR duracion_meses > 0),
                ADD CONSTRAINT tes_proyecto_periodo_tesis_fk FOREIGN KEY (id_periodo_tesis) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT,
                ADD CONSTRAINT tes_proyecto_archivo_fk FOREIGN KEY (id_archivo_documento) REFERENCES comun.archivo(id_archivo) ON DELETE RESTRICT;
            CREATE INDEX tes_proyecto_estado_idx ON tesis.proyecto_tesis(estado);
            CREATE INDEX tes_proyecto_periodo_tesis_idx ON tesis.proyecto_tesis(id_periodo_tesis);
            CREATE INDEX tes_proyecto_archivo_idx ON tesis.proyecto_tesis(id_archivo_documento);
            COMMENT ON COLUMN tesis.proyecto_tesis.estado IS 'Registrado, ConAsesor, EnDesarrollo, VerificadoAsesor, EnRevisionJurado, Aprobado, EnTesis, ListoSustentar, Programado, Sustentado, Desaprobado, TesisAprobada';
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP INDEX IF EXISTS tesis.tes_proyecto_archivo_idx;
            DROP INDEX IF EXISTS tesis.tes_proyecto_periodo_tesis_idx;
            DROP INDEX IF EXISTS tesis.tes_proyecto_estado_idx;
            COMMENT ON COLUMN tesis.proyecto_tesis.estado IS NULL;
            ALTER TABLE tesis.proyecto_tesis
                DROP CONSTRAINT IF EXISTS tes_proyecto_archivo_fk,
                DROP CONSTRAINT IF EXISTS tes_proyecto_periodo_tesis_fk,
                DROP CONSTRAINT IF EXISTS tes_proyecto_duracion_ck,
                DROP CONSTRAINT IF EXISTS tes_proyecto_enfoque_ck,
                DROP CONSTRAINT IF EXISTS tes_proyecto_uuid_uq,
                DROP COLUMN IF EXISTS id_archivo_documento,
                DROP COLUMN IF EXISTS id_periodo_tesis,
                DROP COLUMN IF EXISTS fecha_aprobacion_tesis,
                DROP COLUMN IF EXISTS fecha_aprobacion_proyecto,
                DROP COLUMN IF EXISTS financiamiento,
                DROP COLUMN IF EXISTS duracion_meses,
                DROP COLUMN IF EXISTS institucion,
                DROP COLUMN IF EXISTS lugar_ejecucion,
                DROP COLUMN IF EXISTS diseno,
                DROP COLUMN IF EXISTS tipo_investigacion,
                DROP COLUMN IF EXISTS enfoque,
                DROP COLUMN IF EXISTS uuid;
            SQL);
    }
};
