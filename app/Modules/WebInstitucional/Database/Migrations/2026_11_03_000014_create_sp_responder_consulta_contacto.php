<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE web_institucional.sp_responder_consulta_contacto(
    IN p_id_consulta bigint,
    IN p_respondido_por bigint,
    IN p_respuesta text
)
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path = pg_catalog
AS $procedure$
DECLARE
    v_consulta web_institucional.consulta_contacto%ROWTYPE;
BEGIN
    IF p_id_consulta IS NULL OR p_id_consulta <= 0
        OR p_respondido_por IS NULL OR p_respondido_por <= 0 THEN
        RAISE EXCEPTION USING ERRCODE = '22023',
            MESSAGE = 'Los identificadores deben ser positivos y no nulos.';
    END IF;

    IF p_respuesta IS NULL OR p_respuesta !~ '[^[:space:]]' THEN
        RAISE EXCEPTION USING ERRCODE = '22023',
            MESSAGE = 'La respuesta debe contener texto no vacío.';
    END IF;

    SELECT c.* INTO v_consulta
    FROM web_institucional.consulta_contacto AS c
    WHERE c.id_consulta = p_id_consulta
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION USING ERRCODE = 'P0002',
            MESSAGE = 'La consulta de contacto no existe.';
    END IF;

    IF v_consulta.estado <> 'recibida'
        OR v_consulta.respuesta IS NOT NULL
        OR v_consulta.respondido_por IS NOT NULL
        OR v_consulta.respondida_en IS NOT NULL THEN
        RAISE EXCEPTION USING ERRCODE = '55000',
            MESSAGE = 'La consulta no está recibida o ya contiene datos de respuesta.';
    END IF;

    UPDATE web_institucional.consulta_contacto
    SET estado = 'respondida',
        respuesta = p_respuesta,
        respondido_por = p_respondido_por,
        respondida_en = clock_timestamp()
    WHERE id_consulta = p_id_consulta;
END;
$procedure$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS web_institucional.sp_responder_consulta_contacto(bigint, bigint, text)');
    }
};
