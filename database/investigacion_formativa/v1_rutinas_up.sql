-- Versión 1: conservar este archivo para reproducir la migración original.
CREATE FUNCTION investigacion_formativa.fn_validar_entrega()
RETURNS TRIGGER
LANGUAGE plpgsql
SET search_path = pg_catalog
AS $$
DECLARE
    v_seccion_proyecto BIGINT;
    v_seccion_entregable BIGINT;
BEGIN
    SELECT g.id_seccion INTO v_seccion_proyecto
    FROM investigacion_formativa.proyecto AS p
    JOIN investigacion_formativa.grupo AS g ON g.id_grupo = p.id_grupo
    WHERE p.id_proyecto = NEW.id_proyecto
    FOR SHARE OF p, g;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El proyecto % no existe', NEW.id_proyecto USING ERRCODE = '23503';
    END IF;

    SELECT e.id_seccion INTO v_seccion_entregable
    FROM investigacion_formativa.entregable AS e
    WHERE e.id_entregable = NEW.id_entregable
    FOR SHARE OF e;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El entregable % no existe', NEW.id_entregable USING ERRCODE = '23503';
    END IF;

    IF v_seccion_proyecto IS DISTINCT FROM v_seccion_entregable THEN
        RAISE EXCEPTION 'El proyecto y el entregable deben pertenecer a la misma sección'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_validar_entrega
BEFORE INSERT OR UPDATE ON investigacion_formativa.entrega
FOR EACH ROW EXECUTE FUNCTION investigacion_formativa.fn_validar_entrega();

CREATE FUNCTION investigacion_formativa.fn_validar_evaluacion_criterio()
RETURNS TRIGGER
LANGUAGE plpgsql
SET search_path = pg_catalog
AS $$
DECLARE
    v_rubrica_entregable BIGINT;
    v_rubrica_criterio BIGINT;
    v_puntaje_maximo NUMERIC;
BEGIN
    SELECT en.id_rubrica INTO v_rubrica_entregable
    FROM investigacion_formativa.evaluacion AS ev
    JOIN investigacion_formativa.entrega AS et ON et.id_entrega = ev.id_entrega
    JOIN investigacion_formativa.entregable AS en ON en.id_entregable = et.id_entregable
    WHERE ev.id_evaluacion = NEW.id_evaluacion
    FOR SHARE OF ev, et, en;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'La evaluación % no existe', NEW.id_evaluacion USING ERRCODE = '23503';
    END IF;

    SELECT c.id_rubrica, c.puntaje_maximo INTO v_rubrica_criterio, v_puntaje_maximo
    FROM investigacion_formativa.criterio AS c
    WHERE c.id_criterio = NEW.id_criterio
    FOR SHARE OF c;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El criterio % no existe', NEW.id_criterio USING ERRCODE = '23503';
    END IF;

    IF v_rubrica_criterio IS DISTINCT FROM v_rubrica_entregable THEN
        RAISE EXCEPTION 'El criterio no pertenece a la rúbrica del entregable evaluado'
            USING ERRCODE = '23514';
    END IF;

    IF NEW.puntaje IS NULL OR NEW.puntaje < 0 OR NEW.puntaje > v_puntaje_maximo
        OR NEW.puntaje = 'NaN'::NUMERIC THEN
        RAISE EXCEPTION 'El puntaje debe estar entre 0 y %', v_puntaje_maximo
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_validar_evaluacion_criterio
BEFORE INSERT OR UPDATE ON investigacion_formativa.evaluacion_criterio
FOR EACH ROW EXECUTE FUNCTION investigacion_formativa.fn_validar_evaluacion_criterio();

-- NULL distingue una entrega inexistente o sin evaluación de una evaluación sin puntajes (0).
CREATE FUNCTION investigacion_formativa.fn_calcular_puntaje_entrega(p_id_entrega BIGINT)
RETURNS NUMERIC
LANGUAGE sql
STABLE
SET search_path = pg_catalog
AS $$
    SELECT COALESCE((
        SELECT SUM(ec.puntaje)
        FROM investigacion_formativa.evaluacion_criterio AS ec
        WHERE ec.id_evaluacion = ev.id_evaluacion
    ), 0::NUMERIC)
    FROM investigacion_formativa.evaluacion AS ev
    WHERE ev.id_entrega = p_id_entrega;
$$;

CREATE PROCEDURE investigacion_formativa.sp_registrar_puntaje_criterio(
    p_id_evaluacion BIGINT,
    p_id_criterio BIGINT,
    p_puntaje NUMERIC
)
LANGUAGE plpgsql
SET search_path = pg_catalog
AS $$
BEGIN
    -- El trigger valida tanto INSERT como UPDATE, también en escrituras directas.
    INSERT INTO investigacion_formativa.evaluacion_criterio (id_evaluacion, id_criterio, puntaje)
    VALUES (p_id_evaluacion, p_id_criterio, p_puntaje)
    ON CONFLICT (id_evaluacion, id_criterio)
    DO UPDATE SET puntaje = EXCLUDED.puntaje;
END;
$$;
