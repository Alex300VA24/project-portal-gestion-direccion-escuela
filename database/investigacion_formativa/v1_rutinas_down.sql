-- Orden inverso; RESTRICT implícito: no eliminar dependencias ajenas.
DROP PROCEDURE IF EXISTS investigacion_formativa.sp_registrar_puntaje_criterio(BIGINT, BIGINT, NUMERIC);
DROP FUNCTION IF EXISTS investigacion_formativa.fn_calcular_puntaje_entrega(BIGINT);
DROP TRIGGER IF EXISTS trg_validar_evaluacion_criterio ON investigacion_formativa.evaluacion_criterio;
DROP FUNCTION IF EXISTS investigacion_formativa.fn_validar_evaluacion_criterio();
DROP TRIGGER IF EXISTS trg_validar_entrega ON investigacion_formativa.entrega;
DROP FUNCTION IF EXISTS investigacion_formativa.fn_validar_entrega();
