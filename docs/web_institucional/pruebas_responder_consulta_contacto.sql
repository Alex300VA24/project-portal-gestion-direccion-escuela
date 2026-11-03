-- Ejecutar únicamente en una base PostgreSQL aislada con la tabla y el procedimiento instalados.
-- No crea tablas ni depende de seguridad.usuario. Todos los datos se revierten.
BEGIN;

DO $test$
DECLARE
    v_id bigint;
    v_missing bigint;
    v_fila web_institucional.consulta_contacto%ROWTYPE;
    v_caso record;
BEGIN
    INSERT INTO web_institucional.consulta_contacto
        (nombre_remitente, correo, asunto, mensaje, estado, area_destino, derivada_en)
    VALUES ('Prueba aislada', 'prueba@example.invalid', 'Información', 'Consulta de prueba',
        'recibida', 'Admisión', CURRENT_TIMESTAMP)
    RETURNING id_consulta INTO v_id;

    -- Identificador inexistente sin asumir que un ID fijo esté libre.
    v_missing := 1;
    WHILE EXISTS (SELECT 1 FROM web_institucional.consulta_contacto WHERE id_consulta = v_missing) LOOP
        v_missing := v_missing + 1;
    END LOOP;

    FOR v_caso IN
        SELECT * FROM (VALUES
            (NULL::bigint, 7::bigint, 'Respuesta'::text, '22023'),
            (0::bigint, 7::bigint, 'Respuesta', '22023'),
            (-1::bigint, 7::bigint, 'Respuesta', '22023'),
            (v_id, NULL::bigint, 'Respuesta', '22023'),
            (v_id, 0::bigint, 'Respuesta', '22023'),
            (v_id, -1::bigint, 'Respuesta', '22023'),
            (v_id, 7::bigint, NULL::text, '22023'),
            (v_id, 7::bigint, '', '22023'),
            (v_id, 7::bigint, E' \t\n\r ', '22023'),
            (v_missing, 7::bigint, 'Respuesta', 'P0002')
        ) AS casos(id, responsable, respuesta, codigo)
    LOOP
        BEGIN
            CALL web_institucional.sp_responder_consulta_contacto(
                v_caso.id, v_caso.responsable, v_caso.respuesta);
            RAISE EXCEPTION 'Se aceptó un caso inválido: %', v_caso;
        EXCEPTION WHEN SQLSTATE '22023' OR SQLSTATE 'P0002' THEN
            IF SQLSTATE <> v_caso.codigo THEN
                RAISE EXCEPTION 'SQLSTATE inesperado: %', SQLSTATE;
            END IF;
        END;
    END LOOP;

    -- Estados ajenos al contrato y metadatos parciales no se sobrescriben.
    FOR v_caso IN
        SELECT * FROM (VALUES
            ('cerrada', NULL::text, NULL::bigint, NULL::timestamptz),
            ('respondida', NULL::text, NULL::bigint, NULL::timestamptz),
            ('recibida', 'Parcial', NULL::bigint, NULL::timestamptz),
            ('recibida', NULL::text, 7::bigint, NULL::timestamptz),
            ('recibida', NULL::text, NULL::bigint, CURRENT_TIMESTAMP)
        ) AS casos(estado, respuesta, responsable, fecha)
    LOOP
        UPDATE web_institucional.consulta_contacto
        SET estado = v_caso.estado, respuesta = v_caso.respuesta,
            respondido_por = v_caso.responsable, respondida_en = v_caso.fecha
        WHERE id_consulta = v_id;
        SELECT * INTO v_fila FROM web_institucional.consulta_contacto WHERE id_consulta = v_id;
        BEGIN
            CALL web_institucional.sp_responder_consulta_contacto(v_id, 7, 'Respuesta');
            RAISE EXCEPTION 'Se aceptó un estado o metadatos inconsistentes';
        EXCEPTION WHEN SQLSTATE '55000' THEN NULL;
        END;
        IF EXISTS (SELECT 1 FROM web_institucional.consulta_contacto AS c
            WHERE c.id_consulta = v_id AND c IS DISTINCT FROM v_fila) THEN
            RAISE EXCEPTION 'El rechazo modificó la fila';
        END IF;
    END LOOP;

    UPDATE web_institucional.consulta_contacto
    SET estado = 'recibida', respuesta = NULL, respondido_por = NULL, respondida_en = NULL
    WHERE id_consulta = v_id;
    SELECT * INTO v_fila FROM web_institucional.consulta_contacto WHERE id_consulta = v_id;

    CALL web_institucional.sp_responder_consulta_contacto(v_id, 7, 'Información solicitada.');
    IF NOT EXISTS (
        SELECT 1 FROM web_institucional.consulta_contacto AS c
        WHERE c.id_consulta = v_id AND c.estado = 'respondida'
            AND c.respuesta = 'Información solicitada.' AND c.respondido_por = 7
            AND c.respondida_en IS NOT NULL
            AND (to_jsonb(c) - ARRAY['estado', 'respuesta', 'respondido_por', 'respondida_en'])
                = (to_jsonb(v_fila) - ARRAY['estado', 'respuesta', 'respondido_por', 'respondida_en'])
    ) THEN
        RAISE EXCEPTION 'Respuesta incorrecta o modificación de otros campos';
    END IF;

    SELECT * INTO v_fila FROM web_institucional.consulta_contacto WHERE id_consulta = v_id;
    BEGIN
        CALL web_institucional.sp_responder_consulta_contacto(v_id, 8, 'Segunda respuesta');
        RAISE EXCEPTION 'Se permitió una respuesta duplicada';
    EXCEPTION WHEN SQLSTATE '55000' THEN NULL;
    END;
    IF EXISTS (SELECT 1 FROM web_institucional.consulta_contacto AS c
        WHERE c.id_consulta = v_id AND c IS DISTINCT FROM v_fila) THEN
        RAISE EXCEPTION 'La duplicación modificó la respuesta original';
    END IF;

    RAISE NOTICE 'Pruebas positivas y negativas completadas';
END;
$test$;

ROLLBACK;
