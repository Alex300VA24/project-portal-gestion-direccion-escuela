-- ============================================================
-- GESTIÓN DE HORARIOS — 05 PRUEBAS DE INTEGRIDAD
-- ============================================================
-- EJECUCIÓN (scripts 01..05 aplicados sobre una base de pruebas):
--   psql -v ON_ERROR_STOP=1 -X -d portal_gestion_direccion_pruebas \
--        -f database/gestion_horarios/05_pruebas_integridad.sql
--
-- El script completo corre dentro de UNA TRANSACCIÓN y termina con
-- ROLLBACK, por lo que no deja ningún dato de prueba. Si una
-- aserción falla, se lanza una excepción, psql detiene la ejecución
-- y devuelve código de salida distinto de cero.
--
-- Salida esperada: una línea "OK: ..." por prueba y ninguna línea
-- "FALLO:".
-- ============================================================

\set ON_ERROR_STOP on
BEGIN;

-- ------------------------------------------------------------
-- Auxiliares de prueba
-- ------------------------------------------------------------
CREATE FUNCTION pg_temp.gh_assert(p_cond BOOLEAN, p_rotulo TEXT)
RETURNS VOID
LANGUAGE plpgsql
AS $aux$
BEGIN
    IF p_cond IS DISTINCT FROM TRUE THEN
        RAISE EXCEPTION 'FALLO: %', p_rotulo;
    END IF;
    RAISE NOTICE 'OK: %', p_rotulo;
END;
$aux$;

-- Ejecuta una sentencia que DEBE rechazarse.
-- p_estado: SQLSTATE esperado (NULL = cualquiera).
-- p_subcadena: fragmento esperado en el mensaje (NULL = cualquiera).
CREATE FUNCTION pg_temp.gh_esperar_fallo(
    p_sql TEXT,
    p_estado TEXT,
    p_subcadena TEXT,
    p_rotulo TEXT
)
RETURNS VOID
LANGUAGE plpgsql
AS $aux$
DECLARE
    v_estado TEXT;
    v_mensaje TEXT;
BEGIN
    BEGIN
        EXECUTE p_sql;
    EXCEPTION WHEN OTHERS THEN
        GET STACKED DIAGNOSTICS
            v_estado = RETURNED_SQLSTATE,
            v_mensaje = MESSAGE_TEXT;

        IF p_estado IS NOT NULL AND v_estado <> p_estado THEN
            RAISE EXCEPTION 'FALLO: % -> se esperaba SQLSTATE % y llegó % (%)',
                p_rotulo, p_estado, v_estado, v_mensaje;
        END IF;

        IF p_subcadena IS NOT NULL AND position(p_subcadena IN v_mensaje) = 0 THEN
            RAISE EXCEPTION 'FALLO: % -> el mensaje no contiene "%" (mensaje: %)',
                p_rotulo, p_subcadena, v_mensaje;
        END IF;

        RAISE NOTICE 'OK: % (rechazado: % | SQLSTATE %)', p_rotulo, v_mensaje, v_estado;
        RETURN;
    END;

    RAISE EXCEPTION 'FALLO: % -> la operación fue aceptada y debió rechazarse', p_rotulo;
END;
$aux$;

-- ------------------------------------------------------------
-- Datos de prueba (se descartan con el ROLLBACK final)
-- ------------------------------------------------------------
INSERT INTO public.users (name, email, password)
VALUES ('Usuario Prueba GH', 'gh.prueba@example.test', 'no-usado');

INSERT INTO seguridad.usuario (id_usuario, codigo, estado)
SELECT id, 'GH-PRUEBA', 'ACTIVO'
FROM public.users
WHERE email = 'gh.prueba@example.test';

INSERT INTO core.periodo_academico (codigo, nombre, fecha_inicio, fecha_fin, estado)
VALUES ('2026-I', 'Primer Semestre 2026', DATE '2026-03-02', DATE '2026-07-24', 'ACTIVO');

INSERT INTO core.periodo_academico (codigo, nombre, fecha_inicio, fecha_fin, estado)
VALUES ('2026-II', 'Segundo Semestre 2026', DATE '2026-08-03', DATE '2026-12-18', 'PLANIFICADO');

INSERT INTO core.curso_plan (codigo, nombre, creditos, horas_semanales, semestre)
VALUES ('INF-101', 'Algoritmos y Estructuras de Datos', 4, 6, 3);

INSERT INTO core.docente (codigo, apellidos, nombres, estado)
VALUES ('D-001', 'Perez Ramos', 'Ana', 'ACTIVO');

INSERT INTO core.docente (codigo, apellidos, nombres, estado)
VALUES ('D-002', 'Gomez Salas', 'Luis', 'ACTIVO');

INSERT INTO core.ambiente (codigo, nombre, tipo, capacidad, piso, estado)
VALUES ('A-101', 'Aula 101', 'AULA', 40, '1', 'DISPONIBLE');

INSERT INTO core.seccion (id_periodo, id_curso_plan, codigo, tipo)
SELECT p.id_periodo, c.id_curso_plan, v.codigo, v.tipo
FROM (VALUES
    ('2026-I', 'A', 'REGULAR'),
    ('2026-I', 'B', 'REPETICION'),
    ('2026-I', 'C', 'REPETICION'),
    ('2026-II', 'A', 'REGULAR')
) AS v(periodo, codigo, tipo)
JOIN core.periodo_academico p ON p.codigo = v.periodo
JOIN core.curso_plan c ON c.codigo = 'INF-101';

INSERT INTO gestion_horarios.bloque_horario (id_periodo, nombre, hora_inicio, hora_fin, orden)
SELECT id_periodo, v.nombre, v.inicio::time, v.fin::time, v.orden
FROM core.periodo_academico,
(VALUES
    ('B1', '07:00', '07:50', 1),
    ('B2', '07:50', '08:40', 2)
) AS v(nombre, inicio, fin, orden)
WHERE codigo = '2026-I';

INSERT INTO gestion_horarios.bloque_horario (id_periodo, nombre, hora_inicio, hora_fin, orden)
SELECT id_periodo, 'B1', TIME '07:00', TIME '07:50', 1
FROM core.periodo_academico
WHERE codigo = '2026-II';

-- Declaración PRESENTADA para D-001 y NO_PRESENTADA/ausente para D-002
INSERT INTO gestion_horarios.declaracion_disponibilidad
    (id_periodo, id_docente, estado, tratamiento_regular, presentado_en, id_registrado_por, observacion)
SELECT p.id_periodo, d.id_docente, 'PRESENTADA', 'ESTRICTA', now(), u.id_usuario, 'Prueba declaracion'
FROM core.periodo_academico p
JOIN core.docente d ON d.codigo = 'D-001'
JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA'
WHERE p.codigo = '2026-I';

INSERT INTO gestion_horarios.declaracion_disponibilidad
    (id_periodo, id_docente, estado, tratamiento_regular)
SELECT p.id_periodo, d.id_docente, 'PENDIENTE', 'FLEXIBLE'
FROM core.periodo_academico p
JOIN core.docente d ON d.codigo = 'D-002'
WHERE p.codigo = '2026-I';

-- Horario h1 (version 1): estado BORRADOR con dos detalles que se solapan
INSERT INTO gestion_horarios.horario (id_periodo, ambito, version, estado, id_creado_por)
SELECT p.id_periodo, 'EP_INFORMATICA', 1, 'BORRADOR', u.id_usuario
FROM core.periodo_academico p
JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA'
WHERE p.codigo = '2026-I';

INSERT INTO gestion_horarios.actividad_academica
    (id_seccion, tipo_actividad, dia_semana, hora_inicio, hora_fin)
SELECT s.id_seccion, v.tipo, v.dia, v.inicio::time, v.fin::time
FROM core.seccion s
JOIN core.periodo_academico p ON p.id_periodo = s.id_periodo
JOIN core.curso_plan c ON c.id_curso_plan = s.id_curso_plan,
(VALUES
    ('TEORIA', 'LUNES', '07:00', '08:00'),
    ('PRACTICA', 'LUNES', '07:00', '08:00')
) AS v(tipo, dia, inicio, fin)
WHERE p.codigo = '2026-I' AND c.codigo = 'INF-101' AND s.codigo = 'A';

-- Actividad de la sección de repetición B (periodo 2026-I)
INSERT INTO gestion_horarios.actividad_academica
    (id_seccion, tipo_actividad, dia_semana, hora_inicio, hora_fin)
SELECT s.id_seccion, 'TEORIA', 'MARTES', TIME '09:00', TIME '10:00'
FROM core.seccion s
JOIN core.periodo_academico p ON p.id_periodo = s.id_periodo
JOIN core.curso_plan c ON c.id_curso_plan = s.id_curso_plan
WHERE p.codigo = '2026-I' AND c.codigo = 'INF-101' AND s.codigo = 'B';

-- Actividad de la sección del periodo 2026-II (para la prueba de incompatibilidad)
INSERT INTO gestion_horarios.actividad_academica
    (id_seccion, tipo_actividad, dia_semana, hora_inicio, hora_fin)
SELECT s.id_seccion, 'TEORIA', 'LUNES', TIME '07:00', TIME '08:00'
FROM core.seccion s
JOIN core.periodo_academico p ON p.id_periodo = s.id_periodo
JOIN core.curso_plan c ON c.id_curso_plan = s.id_curso_plan
WHERE p.codigo = '2026-II' AND c.codigo = 'INF-101' AND s.codigo = 'A';

-- Dos actividades de la sección A van al horario h1
INSERT INTO gestion_horarios.detalle_horario (id_horario, id_actividad)
SELECT h.id_horario, a.id_actividad
FROM gestion_horarios.horario h
JOIN gestion_horarios.actividad_academica a
    ON a.dia_semana = 'LUNES'
   AND a.tipo_actividad IN ('TEORIA', 'PRACTICA')
JOIN core.seccion s ON s.id_seccion = a.id_seccion
JOIN core.curso_plan c ON c.id_curso_plan = s.id_curso_plan
WHERE h.version = 1 AND c.codigo = 'INF-101' AND s.codigo = 'A'
  AND s.id_periodo = h.id_periodo;

INSERT INTO gestion_horarios.asignacion_docente (id_actividad, id_docente, estado)
SELECT a.id_actividad, d.id_docente, 'CONFIRMADA'
FROM gestion_horarios.actividad_academica a
JOIN core.seccion s ON s.id_seccion = a.id_seccion
JOIN core.periodo_academico p ON p.id_periodo = s.id_periodo
JOIN core.docente d ON d.codigo = 'D-001'
WHERE a.tipo_actividad = 'TEORIA' AND a.dia_semana = 'LUNES' AND s.codigo = 'A'
  AND p.codigo = '2026-I';

-- Cuatro solicitudes de apertura identificadas por su observacion
INSERT INTO gestion_horarios.apertura_repeticion
    (id_periodo, id_curso_plan, id_seccion, solicitado_por, observacion)
SELECT p.id_periodo, c.id_curso_plan, NULL, u.id_usuario, v.obs
FROM core.periodo_academico p
JOIN core.curso_plan c ON c.codigo = 'INF-101'
JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA',
(VALUES ('AP-A'), ('AP-B')) AS v(obs)
WHERE p.codigo = '2026-I';

INSERT INTO gestion_horarios.apertura_repeticion
    (id_periodo, id_curso_plan, id_seccion, solicitado_por, observacion)
SELECT p.id_periodo, c.id_curso_plan, s.id_seccion, u.id_usuario, v.obs
FROM core.periodo_academico p
JOIN core.curso_plan c ON c.codigo = 'INF-101'
JOIN core.seccion s ON s.id_periodo = p.id_periodo AND s.id_curso_plan = c.id_curso_plan
JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA',
(VALUES ('AP-C'), ('AP-D')) AS v(obs)
WHERE p.codigo = '2026-I' AND s.codigo IN ('B', 'C')
  AND (v.obs = 'AP-C' AND s.codigo = 'B' OR v.obs = 'AP-D' AND s.codigo = 'C');

-- ============================================================
-- PRUEBAS ESTRUCTURALES
-- ============================================================
SELECT pg_temp.gh_assert(
    (SELECT count(*) FROM information_schema.tables
      WHERE table_schema = 'gestion_horarios' AND table_type = 'BASE TABLE') = 15,
    'E1. Existen exactamente 15 tablas en gestion_horarios'
);

SELECT pg_temp.gh_assert(
    (SELECT count(*) FROM information_schema.table_constraints
      WHERE table_schema = 'gestion_horarios' AND constraint_type = 'PRIMARY KEY') = 15,
    'E2. Las 15 tablas tienen clave primaria'
);

SELECT pg_temp.gh_assert(
    (
        SELECT count(*)
        FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace tn ON tn.oid = t.relnamespace
        JOIN pg_class f ON f.oid = c.confrelid
        JOIN pg_namespace fn ON fn.oid = f.relnamespace
        WHERE c.contype = 'f'
          AND tn.nspname = 'gestion_horarios'
          AND fn.nspname NOT IN ('gestion_horarios', 'core', 'seguridad', 'comun', 'public')
    ) = 0,
    'E3. Ninguna clave foránea apunta a esquemas de otros módulos'
);

DO $pruebas$
DECLARE
    v_total INTEGER;
BEGIN
    SELECT count(*) INTO v_total
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    JOIN pg_namespace tn ON tn.oid = t.relnamespace
    WHERE c.contype = 'f' AND tn.nspname = 'gestion_horarios';

    IF v_total < 30 THEN
        RAISE EXCEPTION 'FALLO: E4. Se esperaban al menos 30 claves foráneas y hay %', v_total;
    END IF;

    RAISE NOTICE 'OK: E4. Inventario de claves foráneas del módulo: % definidas', v_total;
END;
$pruebas$;

SELECT pg_temp.gh_assert(
    (
        SELECT count(DISTINCT c.conname)
        FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE n.nspname = 'gestion_horarios'
          AND c.conname IN (
            'uq_oferta_curso',
            'uq_bloque_periodo_inicio',
            'uq_disponibilidad',
            'uq_detalle_horario_actividad',
            'uq_horario_version',
            'uq_restriccion_codigo',
            'uq_horario_revision_numero',
            'uq_declaracion_periodo_docente'
          )
    ) = 8,
    'E5. Se conservan las restricciones UNIQUE originales y las de la declaración'
);

SELECT pg_temp.gh_assert(
    (
        SELECT count(*)
        FROM (VALUES
            ('apertura_repeticion'),
            ('declaracion_disponibilidad'),
            ('validacion_horario'),
            ('conflicto_horario')
        ) AS v(tabla)
        WHERE obj_description(('gestion_horarios.' || v.tabla)::regclass) IS NOT NULL
    ) = 4,
    'E6. Las 4 tablas nuevas tienen COMMENT ON TABLE en el catálogo'
);

SELECT pg_temp.gh_assert(
    (
        SELECT count(*)
        FROM pg_attribute a
        JOIN pg_class t ON t.oid = a.attrelid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE n.nspname = 'gestion_horarios'
          AND a.attnum > 0 AND NOT a.attisdropped
          AND col_description(t.oid, a.attnum) IS NOT NULL
          AND (
                (t.relname = 'apertura_repeticion' AND a.attname IN ('estado', 'autorizado_por', 'resuelto_en', 'id_seccion'))
             OR (t.relname = 'declaracion_disponibilidad' AND a.attname IN ('estado', 'tratamiento_regular'))
             OR (t.relname = 'validacion_horario' AND a.attname IN ('huella_propuesta', 'version_reglas', 'id_validacion'))
             OR (t.relname = 'conflicto_horario' AND a.attname IN ('id_validacion', 'tipo', 'severidad', 'id_detalle_a'))
          )
    ) = 13,
    'E7. Las columnas clave de las 4 tablas nuevas están documentadas en el catálogo'
);

-- ============================================================
-- PRUEBAS DE RELACIONES
-- ============================================================
SELECT pg_temp.gh_assert(
    (
        SELECT count(*) FROM gestion_horarios.apertura_repeticion
        WHERE observacion IN ('AP-A', 'AP-B', 'AP-C', 'AP-D')
    ) = 4,
    'R1. Las aperturas con período y curso válidos se registran'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.apertura_repeticion
         (id_periodo, id_curso_plan, id_seccion, solicitado_por, observacion)
       SELECT p.id_periodo, c.id_curso_plan, s.id_seccion, u.id_usuario, 'AP-ROTURA'
       FROM core.periodo_academico p
       JOIN core.curso_plan c ON c.codigo = 'INF-101'
       JOIN core.seccion s ON s.codigo = 'A'
       JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA'
       WHERE p.codigo = '2026-I'
         AND s.id_periodo = (SELECT id_periodo FROM core.periodo_academico WHERE codigo = '2026-II')$q$,
    '23503',
    'fk_apertura_seccion_periodo_curso',
    'R2. No se admite una sección de otro período o curso en la apertura'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.declaracion_disponibilidad
         (id_periodo, id_docente, estado, presentado_en)
       SELECT p.id_periodo, d.id_docente, 'PRESENTADA', now()
       FROM core.periodo_academico p
       JOIN core.docente d ON d.codigo = 'D-001'
       WHERE p.codigo = '2026-I'$q$,
    '23505',
    'uq_declaracion_periodo_docente',
    'R3. No se admiten declaraciones duplicadas de un docente en un período'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.validacion_horario (id_horario, resultado, version_reglas)
       VALUES (999999, 'INVALIDA', 'v1')$q$,
    '23503',
    'fk_validacion_horario',
    'R4. Una validación debe pertenecer a un horario existente'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.conflicto_horario (id_validacion, tipo, severidad, descripcion)
       VALUES (999999, 'CRUCE_DOCENTE', 'DURA', 'Conflicto sin validacion')$q$,
    'P0001',
    'VALIDACION_INEXISTENTE',
    'R5. Un conflicto debe pertenecer a una validación existente'
);

-- Horario h2 (version 2) con detalle propio, para la prueba R6
INSERT INTO gestion_horarios.horario (id_periodo, ambito, version, estado, id_creado_por)
SELECT p.id_periodo, 'EP_INFORMATICA', 2, 'BORRADOR', u.id_usuario
FROM core.periodo_academico p
JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA'
WHERE p.codigo = '2026-I';

INSERT INTO gestion_horarios.detalle_horario (id_horario, id_actividad)
SELECT h.id_horario, a.id_actividad
FROM gestion_horarios.horario h
JOIN gestion_horarios.actividad_academica a ON a.tipo_actividad = 'TEORIA' AND a.dia_semana = 'MARTES'
JOIN core.seccion s ON s.id_seccion = a.id_seccion
WHERE h.version = 2 AND s.codigo = 'B';

INSERT INTO gestion_horarios.validacion_horario (id_horario, resultado, version_reglas)
SELECT h.id_horario, 'INVALIDA', 'v1.0.0'
FROM gestion_horarios.horario h
WHERE h.version = 1;

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.conflicto_horario
         (id_validacion, tipo, severidad, id_detalle_a, descripcion)
       SELECT v.id_validacion, 'CRUCE_DOCENTE', 'DURA', d.id_detalle,
              'Detalle tomado de otra version'
       FROM gestion_horarios.validacion_horario v
       JOIN gestion_horarios.horario h ON h.id_horario = v.id_horario AND h.version = 1
       JOIN gestion_horarios.horario h2 ON h2.version = 2
       JOIN gestion_horarios.detalle_horario d ON d.id_horario = h2.id_horario$q$,
    'P0001',
    'DETALLE_AJENO',
    'R6. Un conflicto no puede referenciar detalles de una versión distinta a la validada'
);

-- ============================================================
-- PRUEBAS DE REGLAS DE NEGOCIO
-- ============================================================
-- N1. Una apertura denegada no puede incorporarse
UPDATE gestion_horarios.apertura_repeticion
SET estado = 'DENEGADA',
    autorizado_por = (SELECT id_usuario FROM seguridad.usuario WHERE codigo = 'GH-PRUEBA'),
    resuelto_en = now()
WHERE observacion = 'AP-A';

SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.apertura_repeticion
       SET estado = 'INCORPORADA'
       WHERE observacion = 'AP-A'$q$,
    'P0001',
    'APERTURA_DENEGADA',
    'N1. Una solicitud DENEGADA no puede quedar INCORPORADA'
);

-- N2. Una repetición no puede incorporarse sin autorización previa
SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.apertura_repeticion
       SET estado = 'INCORPORADA',
           id_seccion = NULL
       WHERE observacion = 'AP-B'$q$,
    'P0001',
    'APERTURA_SIN_AUTORIZACION',
    'N2. SIN_AUTORIZACION: no se incorpora una repetición sin autorización'
);

-- N3. Incorporación sin disponibilidad obligatoria del docente
UPDATE gestion_horarios.apertura_repeticion
SET estado = 'AUTORIZADA',
    autorizado_por = (SELECT id_usuario FROM seguridad.usuario WHERE codigo = 'GH-PRUEBA'),
    resuelto_en = now(),
    id_docente_propuesto = (SELECT id_docente FROM core.docente WHERE codigo = 'D-002')
WHERE observacion = 'AP-C';

SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.apertura_repeticion
       SET estado = 'INCORPORADA'
       WHERE observacion = 'AP-C'$q$,
    'P0001',
    'DISPONIBILIDAD_NO_PRESENTADA',
    'N3. SIN_DISPONIBILIDAD: D-002 no presentó disponibilidad y bloquea la incorporación'
);

-- N4. Incorporación correcta con disponibilidad presentada
UPDATE gestion_horarios.apertura_repeticion
SET estado = 'AUTORIZADA',
    autorizado_por = (SELECT id_usuario FROM seguridad.usuario WHERE codigo = 'GH-PRUEBA'),
    resuelto_en = now(),
    id_docente_propuesto = (SELECT id_docente FROM core.docente WHERE codigo = 'D-001')
WHERE observacion = 'AP-D';

UPDATE gestion_horarios.apertura_repeticion
SET estado = 'INCORPORADA'
WHERE observacion = 'AP-D';

SELECT pg_temp.gh_assert(
    (SELECT estado FROM gestion_horarios.apertura_repeticion WHERE observacion = 'AP-D') = 'INCORPORADA',
    'N4. Una apertura AUTORIZADA con disponibilidad PRESENTADA queda INCORPORADA'
);

-- N5. Una propuesta sin validación VÁLIDA no puede aprobarse
INSERT INTO gestion_horarios.horario (id_periodo, ambito, version, estado, id_creado_por)
SELECT p.id_periodo, 'EP_INFORMATICA', 3, 'BORRADOR', u.id_usuario
FROM core.periodo_academico p
JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA'
WHERE p.codigo = '2026-I';

INSERT INTO gestion_horarios.detalle_horario (id_horario, id_actividad)
SELECT h.id_horario, a.id_actividad
FROM gestion_horarios.horario h
JOIN gestion_horarios.actividad_academica a
    ON a.dia_semana = 'LUNES' AND a.tipo_actividad = 'TEORIA'
JOIN core.seccion s ON s.id_seccion = a.id_seccion
WHERE h.version = 3 AND s.codigo = 'A' AND s.id_periodo = h.id_periodo;

UPDATE gestion_horarios.horario SET estado = 'GENERADO' WHERE version = 3;

INSERT INTO gestion_horarios.validacion_horario (id_horario, resultado, version_reglas)
SELECT id_horario, 'INVALIDA', 'v1.0.0' FROM gestion_horarios.horario WHERE version = 3;

UPDATE gestion_horarios.horario SET estado = 'VALIDADO' WHERE version = 3;

SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.horario
       SET estado = 'APROBADO',
           id_aprobado_por = (SELECT id_usuario FROM seguridad.usuario WHERE codigo = 'GH-PRUEBA')
       WHERE version = 3$q$,
    'P0001',
    'VALIDACION_NO_VIGENTE',
    'N5. Una propuesta inválida no puede aprobarse'
);

-- N6. Si cambia el contenido, la validación anterior deja de autorizar
UPDATE gestion_horarios.validacion_horario
SET resultado = 'VALIDA', observacion = 'Sin conflictos'
WHERE id_horario = (SELECT id_horario FROM gestion_horarios.horario WHERE version = 3);

UPDATE gestion_horarios.horario
SET estado = 'APROBADO',
    id_aprobado_por = (SELECT id_usuario FROM seguridad.usuario WHERE codigo = 'GH-PRUEBA')
WHERE version = 3;

-- La versión aprobada sigue siendo editable mientras no se publique
INSERT INTO gestion_horarios.detalle_horario (id_horario, id_actividad)
SELECT h.id_horario, a.id_actividad
FROM gestion_horarios.horario h
JOIN gestion_horarios.actividad_academica a
    ON a.dia_semana = 'LUNES' AND a.tipo_actividad = 'PRACTICA'
JOIN core.seccion s ON s.id_seccion = a.id_seccion
WHERE h.version = 3 AND s.codigo = 'A' AND s.id_periodo = h.id_periodo;

SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.horario SET estado = 'PUBLICADO' WHERE version = 3$q$,
    'P0001',
    'VALIDACION_NO_VIGENTE',
    'N6. La validación anterior no autoriza publicar una versión modificada'
);

-- N7. Una propuesta no aprobada no puede publicarse
INSERT INTO gestion_horarios.horario (id_periodo, ambito, version, estado, id_creado_por)
SELECT p.id_periodo, 'EP_INFORMATICA', 4, 'BORRADOR', u.id_usuario
FROM core.periodo_academico p
JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA'
WHERE p.codigo = '2026-I';

SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.horario SET estado = 'PUBLICADO' WHERE version = 4$q$,
    'P0001',
    'TRANSICION_INVALIDA',
    'N7. Una propuesta no aprobada no puede publicarse'
);

-- N8. Sólo una versión publicada vigente por período y ámbito
INSERT INTO gestion_horarios.horario (id_periodo, ambito, version, estado, id_creado_por)
SELECT p.id_periodo, 'EP_INFORMATICA', v.version, 'BORRADOR', u.id_usuario
FROM core.periodo_academico p
JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA',
(VALUES (5), (6)) AS v(version)
WHERE p.codigo = '2026-I';

INSERT INTO gestion_horarios.detalle_horario (id_horario, id_actividad)
SELECT h.id_horario, a.id_actividad
FROM gestion_horarios.horario h
JOIN gestion_horarios.actividad_academica a
    ON a.dia_semana = 'LUNES' AND a.tipo_actividad = 'TEORIA'
JOIN core.seccion s ON s.id_seccion = a.id_seccion
WHERE h.version IN (5, 6) AND s.codigo = 'A' AND s.id_periodo = h.id_periodo;

UPDATE gestion_horarios.horario SET estado = 'GENERADO' WHERE version IN (5, 6);

INSERT INTO gestion_horarios.validacion_horario (id_horario, resultado, version_reglas)
SELECT id_horario, 'INVALIDA', 'v1.0.0' FROM gestion_horarios.horario WHERE version IN (5, 6);

UPDATE gestion_horarios.horario SET estado = 'VALIDADO' WHERE version IN (5, 6);

UPDATE gestion_horarios.validacion_horario
SET resultado = 'VALIDA'
WHERE id_horario IN (SELECT id_horario FROM gestion_horarios.horario WHERE version IN (5, 6));

UPDATE gestion_horarios.horario
SET estado = 'APROBADO',
    id_aprobado_por = (SELECT id_usuario FROM seguridad.usuario WHERE codigo = 'GH-PRUEBA')
WHERE version IN (5, 6);

UPDATE gestion_horarios.horario SET estado = 'PUBLICADO' WHERE version = 5;

SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.horario SET estado = 'PUBLICADO' WHERE version = 6$q$,
    '23505',
    'uq_horario_publicado_vigente',
    'N8. Dos versiones no pueden quedar publicadas y vigentes para el mismo período y ámbito'
);

-- N9. La versión publicada no puede modificarse libremente
SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.horario SET version = 6 WHERE version = 5$q$,
    'P0001',
    'IDENTIDAD_INMUTABLE',
    'N9a. La identidad (período, ámbito, versión) de la publicada es inmutable'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.detalle_horario (id_horario, id_actividad)
       SELECT h.id_horario, a.id_actividad
       FROM gestion_horarios.horario h
       JOIN gestion_horarios.actividad_academica a
           ON a.dia_semana = 'LUNES' AND a.tipo_actividad = 'PRACTICA'
       JOIN core.seccion s ON s.id_seccion = a.id_seccion
       WHERE h.version = 5 AND s.codigo = 'A' AND s.id_periodo = h.id_periodo$q$,
    'P0001',
    'VERSION_PUBLICADA',
    'N9b. No se puede insertar detalle en la versión publicada'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.detalle_horario
       SET id_actividad = (SELECT a.id_actividad
                             FROM gestion_horarios.actividad_academica a
                             JOIN core.seccion s ON s.id_seccion = a.id_seccion
                            WHERE s.codigo = 'B')
       WHERE id_horario = (SELECT id_horario FROM gestion_horarios.horario WHERE version = 5)$q$,
    'P0001',
    'VERSION_PUBLICADA',
    'N9c. No se puede modificar el detalle de la versión publicada'
);

-- Tras archivar, la publicación queda libre para otra versión vigente
UPDATE gestion_horarios.horario SET estado = 'ARCHIVADO' WHERE version = 5;

UPDATE gestion_horarios.horario SET estado = 'PUBLICADO' WHERE version = 6;

SELECT pg_temp.gh_assert(
    (SELECT count(*) FROM gestion_horarios.horario
      WHERE ambito = 'EP_INFORMATICA' AND estado = 'PUBLICADO') = 1,
    'N9d. Archivada la versión anterior, sigue existiendo exactamente una publicada vigente'
);

-- N10. El período del horario debe coincidir con el de la sección
SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.detalle_horario (id_horario, id_actividad)
       SELECT h.id_horario, a.id_actividad
       FROM gestion_horarios.horario h
       JOIN gestion_horarios.actividad_academica a ON a.tipo_actividad = 'TEORIA' AND a.dia_semana = 'LUNES'
       JOIN core.seccion s ON s.id_seccion = a.id_seccion
       WHERE h.version = 1 AND s.codigo = 'A'
         AND s.id_periodo = (SELECT id_periodo FROM core.periodo_academico WHERE codigo = '2026-II')$q$,
    'P0001',
    'PERIODO_INCOMPATIBLE',
    'N10. No se programa una sección de otro período en el horario'
);

-- N11. Un conflicto duro impide declarar VALIDA la validación
INSERT INTO gestion_horarios.conflicto_horario
    (id_validacion, tipo, severidad, id_detalle_a, id_docente, descripcion)
SELECT v.id_validacion, 'CRUCE_DOCENTE', 'DURA', d.id_detalle, doc.id_docente,
       'D-001 tiene dos actividades el LUNES a la misma hora'
FROM gestion_horarios.validacion_horario v
JOIN gestion_horarios.horario h ON h.id_horario = v.id_horario AND h.version = 1
JOIN gestion_horarios.detalle_horario d
    ON d.id_horario = h.id_horario
JOIN gestion_horarios.actividad_academica a ON a.id_actividad = d.id_actividad AND a.tipo_actividad = 'TEORIA'
JOIN core.docente doc ON doc.codigo = 'D-001';

SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.validacion_horario
       SET resultado = 'VALIDA'
       WHERE id_horario = (SELECT id_horario FROM gestion_horarios.horario WHERE version = 1)$q$,
    'P0001',
    'CONFLICTO_DURO',
    'N11a. Una validación con conflictos duros no puede declararse VALIDA'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.conflicto_horario
         (id_validacion, tipo, severidad, descripcion)
       SELECT v.id_validacion, 'CAPACIDAD_INSUFICIENTE', 'DURA', 'Aula pequeña'
       FROM gestion_horarios.validacion_horario v
       JOIN gestion_horarios.horario h ON h.id_horario = v.id_horario AND h.version = 5$q$,
    'P0001',
    'CONFLICTO_DURO',
    'N11b. No se admiten conflictos duros sobre una validación VALIDA'
);

INSERT INTO gestion_horarios.conflicto_horario
    (id_validacion, tipo, severidad, id_ambiente, descripcion)
SELECT v.id_validacion, 'HORAS_INCOMPLETAS', 'BLANDA', amb.id_ambiente,
       'Observacion blanda sobre carga horaria'
FROM gestion_horarios.validacion_horario v
JOIN gestion_horarios.horario h ON h.id_horario = v.id_horario AND h.version = 5
JOIN core.ambiente amb ON amb.codigo = 'A-101';

SELECT pg_temp.gh_assert(
    (SELECT count(*) FROM gestion_horarios.conflicto_horario) >= 2,
    'N11c. Los conflictos (duros y blandos) quedan registrados como evidencia'
);

-- N12. Los bloques de disponibilidad corresponden a declaración, docente y período
SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.disponibilidad_docente
         (id_periodo, id_docente, dia_semana, id_bloque)
       SELECT p.id_periodo, d.id_docente, 'LUNES', b.id_bloque
       FROM core.periodo_academico p
       JOIN core.docente d ON d.codigo = 'D-001'
       JOIN core.periodo_academico p2 ON p2.codigo = '2026-I'
       JOIN gestion_horarios.bloque_horario b ON b.id_periodo = p2.id_periodo
       WHERE p.codigo = '2026-II'$q$,
    '23503',
    'fk_disponibilidad_bloque_periodo',
    'N12a. No se admite un bloque de horario de otro período'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.disponibilidad_docente
         (id_periodo, id_docente, id_declaracion, dia_semana, id_bloque)
       SELECT d1.id_periodo, doc1.id_docente, d2.id_declaracion, 'LUNES', b.id_bloque
       FROM gestion_horarios.declaracion_disponibilidad d1
       JOIN gestion_horarios.declaracion_disponibilidad d2 ON d2.id_periodo = d1.id_periodo
       JOIN core.docente doc1 ON doc1.id_docente = d1.id_docente AND doc1.codigo = 'D-001'
       JOIN gestion_horarios.bloque_horario b ON b.id_periodo = d1.id_periodo
       WHERE doc1.codigo = 'D-001'
         AND EXISTS (
             SELECT 1 FROM core.docente dx
             WHERE dx.id_docente = d2.id_docente AND dx.codigo = 'D-002'
         )$q$,
    '23503',
    'fk_disponibilidad_declaracion',
    'N12b. No se admite una declaración de otro docente'
);

INSERT INTO gestion_horarios.disponibilidad_docente
    (id_periodo, id_docente, id_declaracion, dia_semana, id_bloque)
SELECT dec.id_periodo, dec.id_docente, dec.id_declaracion, 'LUNES', b.id_bloque
FROM gestion_horarios.declaracion_disponibilidad dec
JOIN core.docente d ON d.id_docente = dec.id_docente AND d.codigo = 'D-001'
JOIN gestion_horarios.bloque_horario b ON b.id_periodo = dec.id_periodo
WHERE dec.estado = 'PRESENTADA' AND b.nombre = 'B1';

SELECT pg_temp.gh_assert(
    (SELECT count(*) FROM gestion_horarios.disponibilidad_docente) = 1,
    'N12c. Los bloques se registran contra la declaración correcta de docente y período'
);

-- N13. Restricciones de datos
SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.actividad_academica
         (id_seccion, tipo_actividad, dia_semana, hora_inicio, hora_fin)
       SELECT s.id_seccion, 'TEORIA', 'MARTES', TIME '10:00', TIME '09:00'
       FROM core.seccion s
       JOIN core.periodo_academico p ON p.id_periodo = s.id_periodo
       WHERE p.codigo = '2026-I' AND s.codigo = 'A'$q$,
    '23514',
    'ck_actividad_horas',
    'N13a. hora_inicio debe ser anterior a hora_fin en actividad_academica'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.bloque_horario (id_periodo, nombre, hora_inicio, hora_fin)
       SELECT id_periodo, 'ROTO', TIME '12:00', TIME '12:00'
       FROM core.periodo_academico WHERE codigo = '2026-I'$q$,
    '23514',
    'ck_bloque_horas',
    'N13b. hora_inicio debe ser anterior a hora_fin en bloque_horario'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO core.ambiente (codigo, nombre, tipo, capacidad)
       VALUES ('A-000', 'Aula invalida', 'AULA', 0)$q$,
    '23514',
    'ck_ambiente_capacidad',
    'N13c. La capacidad del ambiente debe ser positiva'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.horario (id_periodo, ambito, version, estado, id_creado_por)
       SELECT p.id_periodo, 'OTRO_AMBITO', 0, 'BORRADOR', u.id_usuario
       FROM core.periodo_academico p
       JOIN seguridad.usuario u ON u.codigo = 'GH-PRUEBA'
       WHERE p.codigo = '2026-I'$q$,
    '23514',
    'ck_horario_version',
    'N13d. La versión del horario debe ser positiva'
);

SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.conflicto_horario
         (id_validacion, tipo, severidad, descripcion)
       SELECT v.id_validacion, 'OTRO', 'CRITICA', 'Severidad inventada'
       FROM gestion_horarios.validacion_horario v
       JOIN gestion_horarios.horario h ON h.id_horario = v.id_horario AND h.version = 1$q$,
    '23514',
    'ck_conflicto_severidad',
    'N13e. La severidad debe ser DURA o BLANDA'
);

-- N14. La evidencia de la validación es inmutable
SELECT pg_temp.gh_esperar_fallo(
    $q$UPDATE gestion_horarios.validacion_horario
       SET huella_propuesta = repeat('a', 32)
       WHERE id_horario = (SELECT id_horario FROM gestion_horarios.horario WHERE version = 1)$q$,
    'P0001',
    'EVIDENCIA_INMUTABLE',
    'N14. La huella de una validación registrada no puede alterarse'
);

-- N15. No existen conflictos huérfanos (el disparador y la restricción NOT NULL/FK los rechazan)
SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.conflicto_horario (id_validacion, tipo, severidad, descripcion)
       VALUES (NULL, 'OTRO', 'BLANDA', 'Conflicto huerfano')$q$,
    'P0001',
    'VALIDACION_INEXISTENTE',
    'N15. Un conflicto sin validación se rechaza'
);

-- N16. Se conserva la restricción UNIQUE de detalle_horario
SELECT pg_temp.gh_esperar_fallo(
    $q$INSERT INTO gestion_horarios.detalle_horario (id_horario, id_actividad)
       SELECT d.id_horario, d.id_actividad
       FROM gestion_horarios.detalle_horario d
       JOIN gestion_horarios.horario h ON h.id_horario = d.id_horario
       WHERE h.version = 1
       LIMIT 1$q$,
    '23505',
    'uq_detalle_horario_actividad',
    'N16. No se duplica una actividad dentro de la misma versión de horario'
);

-- ============================================================
-- PRUEBA DE REGRESIÓN
-- ============================================================
DO $pruebas$
DECLARE
    v_tabla TEXT;
    v_filas BIGINT;
BEGIN
    FOREACH v_tabla IN ARRAY ARRAY[
        'oferta_curso', 'actividad_academica', 'asignacion_docente',
        'bloque_horario', 'disponibilidad_docente', 'ambiente_bloqueo',
        'horario', 'detalle_horario', 'restriccion', 'detalle_restriccion',
        'horario_revision'
    ] LOOP
        EXECUTE format('SELECT count(*) FROM gestion_horarios.%I', v_tabla) INTO v_filas;
    END LOOP;

    RAISE NOTICE 'OK: G1. Las 11 tablas originales siguen consultables (consulta simple sin error)';
END;
$pruebas$;

SELECT pg_temp.gh_assert(
    (
        SELECT count(*) FROM information_schema.tables
        WHERE table_schema IN (
            'pre_matricula', 'bienestar', 'investigacion_formativa',
            'seguimiento_estudiante', 'web_institucional', 'calidad',
            'cotecu', 'gestion_practicas', 'tesis_proyecto_tesis',
            'gestion_matriculas', 'seguimiento_egresado'
        )
    ) = 0,
    'G2. Ningún esquema de otro módulo contiene tablas nuevas (estructuras intactas)'
);

SELECT pg_temp.gh_assert(
    (
        SELECT count(*) FROM information_schema.columns
        WHERE table_schema = 'public' AND table_name = 'users'
          AND column_name IN ('id', 'name', 'email', 'password')
    ) = 4,
    'G3. public.users conserva sus columnas de autenticación'
);

SELECT pg_temp.gh_assert(
    (
        SELECT count(*) FROM information_schema.schemata
        WHERE schema_name IN ('gestion_horarios', 'core', 'seguridad')
    ) = 3,
    'G4. Los esquemas gestion_horarios, core y seguridad existen'
);

SELECT pg_temp.gh_assert(
    (
        SELECT count(*)
        FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace tn ON tn.oid = t.relnamespace
        WHERE c.contype = 'f'
          AND tn.nspname IN ('pre_matricula', 'bienestar', 'gestion_matriculas',
                             'tesis_proyecto_tesis', 'seguimiento_estudiante')
    ) = 0,
    'G5. Los módulos ajenos no adquirieron dependencias con gestion_horarios'
);

-- ============================================================
-- FIN
-- ============================================================
SELECT 'Todas las pruebas de integridad de gestion_horarios superadas (rollback de datos de prueba)' AS resultado;

ROLLBACK;
