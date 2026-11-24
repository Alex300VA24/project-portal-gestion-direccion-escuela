-- Vistas de apoyo a seguimiento; no reemplazan el archivo del reporte generado.
-- VERSIÓN 2 (28-oct-2026): vw_cobertura_campania suma sin_respuesta y entregas_fallidas; se agregan
-- vw_i2_i4_cohorte_horizonte (filtros del dashboard) y vw_historial_seguimiento (historial por egresado).
-- Ejecutar después de 01_esquema_postgresql.sql.
-- Resultado de I1: cohortes de INGRESO; se cuenta según etapa académica vigente.
CREATE OR REPLACE VIEW vw_i1_cohorte_actual AS
SELECT c.id AS id_cohorte, c.codigo_promocion, c.anio_ingreso, c.total_ingresantes,
       COUNT(e.id) FILTER (WHERE ea.codigo IN ('EGRESADO','BACHILLER','TITULADO'))::integer AS total_egresados,
       ROUND(100.0 * COUNT(e.id) FILTER (WHERE ea.codigo IN ('EGRESADO','BACHILLER','TITULADO')) / NULLIF(c.total_ingresantes,0),2) AS porcentaje
FROM cohorte c LEFT JOIN egresado e ON e.id_cohorte=c.id
LEFT JOIN historial_estado_academico hea ON hea.id_egresado=e.id AND hea.vigente_hasta IS NULL
LEFT JOIN cat_estado_academico ea ON ea.id=hea.id_estado_academico
GROUP BY c.id,c.codigo_promocion,c.anio_ingreso,c.total_ingresantes;

CREATE OR REPLACE VIEW vw_cobertura_campania AS
SELECT c.id AS id_campania,c.nombre,
       COUNT(i.id) FILTER (WHERE i.proposito='SEGUIMIENTO')::integer AS invitados,
       COUNT(m.id)::integer AS respondieron,
       ROUND(100.0*COUNT(m.id)/NULLIF(COUNT(i.id) FILTER (WHERE i.proposito='SEGUIMIENTO'),0),2) AS cobertura_pct,
       (COUNT(i.id) FILTER (WHERE i.proposito='SEGUIMIENTO') - COUNT(m.id))::integer AS sin_respuesta,
       COUNT(i.id) FILTER (WHERE i.proposito='SEGUIMIENTO' AND i.entrega_fallida)::integer AS entregas_fallidas
FROM campania_seguimiento c LEFT JOIN invitacion_egresado i ON i.id_campania=c.id
LEFT JOIN medicion_laboral m ON m.id_invitacion=i.id
GROUP BY c.id,c.nombre;

-- I2: respuestas profesionales recibidas; ausencia de respuesta no significa desempleo.
CREATE OR REPLACE VIEW vw_i2_campania AS
SELECT i.id_campania,COUNT(m.id)::integer AS encuestados,
       COUNT(m.id) FILTER (WHERE sl.codigo='TRABAJA' AND m.trabajo_relacionado=TRUE)::integer AS trabajan_en_carrera,
       ROUND(100.0*COUNT(m.id) FILTER (WHERE sl.codigo='TRABAJA' AND m.trabajo_relacionado=TRUE)/NULLIF(COUNT(m.id),0),2) AS i2_pct
FROM invitacion_egresado i LEFT JOIN medicion_laboral m ON m.id_invitacion=i.id
LEFT JOIN cat_situacion_laboral sl ON sl.id=m.id_situacion_laboral
WHERE i.proposito='SEGUIMIENTO' GROUP BY i.id_campania;

-- I4: denominador incluye únicamente respuestas con satisfacción válida.
-- El reporte debe mostrar además el total de mediciones sin valoración.
CREATE OR REPLACE VIEW vw_i4_campania AS
SELECT i.id_campania,COUNT(m.id)::integer AS mediciones,COUNT(se.id_medicion)::integer AS valoraciones,
       COUNT(se.id_medicion) FILTER (WHERE ns.codigo IN ('SATISFECHO','MUY_SATISFECHO'))::integer AS satisfechos,
       ROUND(100.0*COUNT(se.id_medicion) FILTER (WHERE ns.codigo IN ('SATISFECHO','MUY_SATISFECHO'))/NULLIF(COUNT(se.id_medicion),0),2) AS i4_pct
FROM invitacion_egresado i JOIN medicion_laboral m ON m.id_invitacion=i.id
LEFT JOIN satisfaccion_egresado se ON se.id_medicion=m.id
LEFT JOIN cat_nivel_satisfaccion ns ON ns.id=se.id_nivel_satisfaccion
GROUP BY i.id_campania;

-- I5: excluye totalmente encuestas generales de mercado.
CREATE OR REPLACE VIEW vw_i5_campania AS
SELECT ie.id_campania,COUNT(re.id) FILTER (WHERE re.id_nivel_satisfaccion IS NOT NULL)::integer AS valoraciones,
       COUNT(re.id) FILTER (WHERE ns.codigo IN ('SATISFECHO','MUY_SATISFECHO'))::integer AS satisfechos,
       ROUND(100.0*COUNT(re.id) FILTER (WHERE ns.codigo IN ('SATISFECHO','MUY_SATISFECHO'))/NULLIF(COUNT(re.id) FILTER (WHERE re.id_nivel_satisfaccion IS NOT NULL),0),2) AS i5_pct
FROM invitacion_empresa ie LEFT JOIN respuesta_empresa re ON re.id_invitacion_empresa=ie.id
LEFT JOIN cat_nivel_satisfaccion ns ON ns.id=re.id_nivel_satisfaccion
WHERE ie.contexto='DESEMPENO' AND ie.id_egresado IS NOT NULL GROUP BY ie.id_campania;

-- I3: solo evaluaciones OE oficialmente validadas.
CREATE OR REPLACE VIEW vw_i3_oe AS
SELECT e.id_cohorte,e.periodo,COUNT(*)::integer AS oe_evaluados,
       COUNT(*) FILTER (WHERE nl.codigo IN ('BUENO','MUY_BUENO'))::integer AS oe_logrados,
       ROUND(100.0*COUNT(*) FILTER (WHERE nl.codigo IN ('BUENO','MUY_BUENO'))/NULLIF(COUNT(*),0),2) AS i3_pct
FROM evaluacion_oe e JOIN cat_nivel_logro nl ON nl.id=e.id_nivel_logro
WHERE e.estado='VALIDADA' GROUP BY e.id_cohorte,e.periodo;

-- I6: cantidad absoluta de mejoras implementadas del seguimiento.
CREATE OR REPLACE VIEW vw_i6_anual AS
SELECT EXTRACT(YEAR FROM fecha_implementacion)::integer AS anio,COUNT(*)::integer AS mejoras_implementadas
FROM mejora_curricular WHERE estado='IMPLEMENTADA' AND origen_seguimiento=TRUE
GROUP BY EXTRACT(YEAR FROM fecha_implementacion);

-- Última medición por egresado (solo para dashboard ACTUAL; no para reportes históricos).
CREATE OR REPLACE VIEW vw_ultima_medicion_actual AS
SELECT DISTINCT ON (i.id_egresado) i.id_egresado,m.id AS id_medicion,m.fecha_respuesta,m.id_situacion_laboral,m.trabajo_relacionado,m.id_pais_residencia
FROM invitacion_egresado i JOIN medicion_laboral m ON m.id_invitacion=i.id
ORDER BY i.id_egresado,m.fecha_respuesta DESC,m.id DESC;

-- I2 e I4 por año de la campaña, horizonte de la campaña (1/3/5/10 años) y año de egreso de la cohorte.
-- Alimenta los filtros periodo / cohorte / horizonte del dashboard. Sin respuesta no es desempleo: solo cuenta como no respondida.
CREATE OR REPLACE VIEW vw_i2_i4_cohorte_horizonte AS
SELECT EXTRACT(YEAR FROM c.fecha_fin)::integer AS anio_campania,
       c.horizonte_anios,
       EXTRACT(YEAR FROM e.fecha_egreso)::integer AS anio_egreso,
       COUNT(i.id)::integer AS invitados,
       COUNT(m.id)::integer AS respondieron,
       COUNT(m.id) FILTER (WHERE sl.codigo='TRABAJA' AND m.trabajo_relacionado=TRUE)::integer AS trabajan_en_carrera,
       ROUND(100.0*COUNT(m.id) FILTER (WHERE sl.codigo='TRABAJA' AND m.trabajo_relacionado=TRUE)/NULLIF(COUNT(m.id),0),2) AS i2_pct,
       COUNT(se.id_medicion)::integer AS valoraciones,
       COUNT(se.id_medicion) FILTER (WHERE ns.codigo IN ('SATISFECHO','MUY_SATISFECHO'))::integer AS satisfechos,
       ROUND(100.0*COUNT(se.id_medicion) FILTER (WHERE ns.codigo IN ('SATISFECHO','MUY_SATISFECHO'))/NULLIF(COUNT(se.id_medicion),0),2) AS i4_pct
FROM campania_seguimiento c
JOIN invitacion_egresado i ON i.id_campania=c.id AND i.proposito='SEGUIMIENTO'
JOIN egresado e ON e.id=i.id_egresado
LEFT JOIN medicion_laboral m ON m.id_invitacion=i.id
LEFT JOIN cat_situacion_laboral sl ON sl.id=m.id_situacion_laboral
LEFT JOIN satisfaccion_egresado se ON se.id_medicion=m.id
LEFT JOIN cat_nivel_satisfaccion ns ON ns.id=se.id_nivel_satisfaccion
WHERE c.horizonte_anios IS NOT NULL
GROUP BY EXTRACT(YEAR FROM c.fecha_fin), c.horizonte_anios, EXTRACT(YEAR FROM e.fecha_egreso);

-- Historial de seguimiento por egresado: una fila por campaña invitada. id_medicion NULL = campaña sin respuesta.
CREATE OR REPLACE VIEW vw_historial_seguimiento AS
SELECT i.id_egresado, c.id AS id_campania, c.nombre AS campania, c.periodo, c.fecha_inicio, c.fecha_fin,
       i.estado AS estado_invitacion, i.enviado_en, i.respondido_en,
       m.id AS id_medicion, m.fecha_respuesta, sl.codigo AS situacion_laboral, m.trabajo_relacionado,
       m.nombre_empleador_cliente, ns.codigo AS satisfaccion
FROM invitacion_egresado i
JOIN campania_seguimiento c ON c.id=i.id_campania
LEFT JOIN medicion_laboral m ON m.id_invitacion=i.id
LEFT JOIN cat_situacion_laboral sl ON sl.id=m.id_situacion_laboral
LEFT JOIN satisfaccion_egresado se ON se.id_medicion=m.id
LEFT JOIN cat_nivel_satisfaccion ns ON ns.id=se.id_nivel_satisfaccion
WHERE i.proposito='SEGUIMIENTO';
