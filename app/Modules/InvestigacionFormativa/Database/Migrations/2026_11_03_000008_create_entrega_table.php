<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.entrega (
                id_entrega BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                id_entregable BIGINT NOT NULL,
                version INTEGER NOT NULL,
                id_archivo BIGINT NOT NULL,
                entregado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT entrega_proyecto_fk FOREIGN KEY (id_proyecto)
                    REFERENCES investigacion_formativa.proyecto (id_proyecto) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT entrega_entregable_fk FOREIGN KEY (id_entregable)
                    REFERENCES investigacion_formativa.entregable (id_entregable) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT entrega_proyecto_entregable_version_unique UNIQUE (id_proyecto, id_entregable, version),
                CONSTRAINT entrega_version_check CHECK (version > 0)
            );
            CREATE INDEX entrega_entregable_idx ON investigacion_formativa.entrega (id_entregable);
            CREATE INDEX entrega_archivo_idx ON investigacion_formativa.entrega (id_archivo);
            COMMENT ON COLUMN investigacion_formativa.entrega.id_archivo IS 'Referencia pendiente a comun.archivo(id_archivo), sin FK externa';
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.entrega');
    }
};
