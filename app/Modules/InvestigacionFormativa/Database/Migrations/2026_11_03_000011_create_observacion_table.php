<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.observacion (
                id_observacion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_entrega BIGINT NOT NULL,
                descripcion TEXT NOT NULL,
                estado VARCHAR(30) NOT NULL,
                registrado_por BIGINT NOT NULL,
                registrado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT observacion_entrega_fk FOREIGN KEY (id_entrega)
                    REFERENCES investigacion_formativa.entrega (id_entrega) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT observacion_descripcion_check CHECK (btrim(descripcion) <> ''),
                CONSTRAINT observacion_estado_check CHECK (btrim(estado) <> '')
            );
            CREATE INDEX observacion_entrega_idx ON investigacion_formativa.observacion (id_entrega);
            CREATE INDEX observacion_registrado_por_idx ON investigacion_formativa.observacion (registrado_por);
            COMMENT ON COLUMN investigacion_formativa.observacion.registrado_por IS 'Referencia pendiente a seguridad.usuario(id_usuario), sin FK externa';
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.observacion');
    }
};
