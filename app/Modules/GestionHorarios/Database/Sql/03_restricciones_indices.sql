-- ============================================================
-- GESTIÓN DE HORARIOS — 03 RESTRICCIONES E ÍNDICES
-- ============================================================
-- Contenido:
--   A) Claves foráneas compuestas (declaracion, apertura)
--   B) Índices de claves foráneas
--   C) Índices únicos parciales (reglas de negocio)
--   D) Función determinista de huella de propuesta
--   E) Disparadores de integridad que una FK no puede expresar
-- ============================================================

-- ------------------------------------------------------------
-- A) Claves foráneas compuestas
-- ------------------------------------------------------------

-- Los bloques de disponibilidad deben corresponder al docente y al
-- período declarados: id_declaracion, id_periodo y id_docente deben
-- coincidir con una fila única de declaracion_disponibilidad.
ALTER TABLE gestion_horarios.disponibilidad_docente
    ADD CONSTRAINT fk_disponibilidad_declaracion
    FOREIGN KEY (id_declaracion, id_periodo, id_docente)
    REFERENCES gestion_horarios.declaracion_disponibilidad (id_declaracion, id_periodo, id_docente)
    ON DELETE RESTRICT;

-- Una asignación de una actividad de repetición queda trazada a la
-- apertura que la autorizó.
ALTER TABLE gestion_horarios.asignacion_docente
    ADD CONSTRAINT fk_asignacion_apertura
    FOREIGN KEY (id_apertura)
    REFERENCES gestion_horarios.apertura_repeticion (id_apertura)
    ON DELETE RESTRICT;

-- ------------------------------------------------------------
-- B) Índices para claves foráneas
-- ------------------------------------------------------------
CREATE INDEX ix_oferta_curso_plan ON gestion_horarios.oferta_curso (id_curso_plan);
CREATE INDEX ix_actividad_seccion ON gestion_horarios.actividad_academica (id_seccion);
CREATE INDEX ix_asignacion_actividad ON gestion_horarios.asignacion_docente (id_actividad);
CREATE INDEX ix_asignacion_docente ON gestion_horarios.asignacion_docente (id_docente);
CREATE INDEX ix_asignacion_apertura ON gestion_horarios.asignacion_docente (id_apertura);
CREATE INDEX ix_bloque_periodo ON gestion_horarios.bloque_horario (id_periodo);
CREATE INDEX ix_disponibilidad_docente ON gestion_horarios.disponibilidad_docente (id_docente);
CREATE INDEX ix_disponibilidad_declaracion ON gestion_horarios.disponibilidad_docente (id_declaracion);
CREATE INDEX ix_bloqueo_ambiente ON gestion_horarios.ambiente_bloqueo (id_ambiente);
CREATE INDEX ix_bloqueo_periodo ON gestion_horarios.ambiente_bloqueo (id_periodo);
CREATE INDEX ix_bloqueo_registrado_por ON gestion_horarios.ambiente_bloqueo (id_registrado_por);
CREATE INDEX ix_horario_periodo ON gestion_horarios.horario (id_periodo);
CREATE INDEX ix_horario_creado_por ON gestion_horarios.horario (id_creado_por);
CREATE INDEX ix_horario_aprobado_por ON gestion_horarios.horario (id_aprobado_por);
CREATE INDEX ix_detalle_actividad ON gestion_horarios.detalle_horario (id_actividad);
CREATE INDEX ix_detalle_ambiente ON gestion_horarios.detalle_horario (id_ambiente);
CREATE INDEX ix_detalle_restriccion_periodo ON gestion_horarios.detalle_restriccion (id_periodo);
CREATE INDEX ix_detalle_restriccion_docente ON gestion_horarios.detalle_restriccion (id_docente);
CREATE INDEX ix_detalle_restriccion_ambiente ON gestion_horarios.detalle_restriccion (id_ambiente);
CREATE INDEX ix_revision_revisor ON gestion_horarios.horario_revision (id_revisor);
CREATE INDEX ix_apertura_periodo ON gestion_horarios.apertura_repeticion (id_periodo);
CREATE INDEX ix_apertura_curso_plan ON gestion_horarios.apertura_repeticion (id_curso_plan);
CREATE INDEX ix_apertura_docente_propuesto ON gestion_horarios.apertura_repeticion (id_docente_propuesto);
CREATE INDEX ix_apertura_solicitado_por ON gestion_horarios.apertura_repeticion (solicitado_por);
CREATE INDEX ix_apertura_autorizado_por ON gestion_horarios.apertura_repeticion (autorizado_por);
CREATE INDEX ix_declaracion_docente ON gestion_horarios.declaracion_disponibilidad (id_docente);
CREATE INDEX ix_declaracion_registrado_por ON gestion_horarios.declaracion_disponibilidad (id_registrado_por);
CREATE INDEX ix_validacion_horario ON gestion_horarios.validacion_horario (id_horario);
CREATE INDEX ix_validacion_validado_por ON gestion_horarios.validacion_horario (id_validado_por);
CREATE INDEX ix_conflicto_validacion ON gestion_horarios.conflicto_horario (id_validacion);
CREATE INDEX ix_conflicto_detalle_a ON gestion_horarios.conflicto_horario (id_detalle_a);
CREATE INDEX ix_conflicto_detalle_b ON gestion_horarios.conflicto_horario (id_detalle_b);
CREATE INDEX ix_conflicto_docente ON gestion_horarios.conflicto_horario (id_docente);
CREATE INDEX ix_conflicto_ambiente ON gestion_horarios.conflicto_horario (id_ambiente);

-- ------------------------------------------------------------
-- C) Índices únicos parciales (reglas de negocio)
-- ------------------------------------------------------------

-- Debe existir una sola versión PUBLICADA vigente por período y
-- ámbito. El índice único es además el mecanismo que serializa la
-- publicación concurrente: dos transacciones no pueden publicar dos
-- versiones vigentes incompatibles; la segunda espera o recibe un
-- error de duplicidad.
CREATE UNIQUE INDEX uq_horario_publicado_vigente
    ON gestion_horarios.horario (id_periodo, ambito)
    WHERE estado = 'PUBLICADO';

-- Sólo una asignación activa (PROPUESTA o CONFIRMADA) por
-- actividad; el histórico RECHAZADA/REASIGNADA se conserva.
CREATE UNIQUE INDEX uq_asignacion_activa
    ON gestion_horarios.asignacion_docente (id_actividad)
    WHERE estado IN ('PROPUESTA', 'CONFIRMADA');

-- Una sección de repetición sólo puede incorporarse desde una
-- solicitud de apertura.
CREATE UNIQUE INDEX uq_apertura_seccion
    ON gestion_horarios.apertura_repeticion (id_seccion)
    WHERE id_seccion IS NOT NULL;

-- ------------------------------------------------------------
-- D) Huella determinista de la propuesta
-- ------------------------------------------------------------
-- Calcula md5 sobre el contenido del horario en este orden:
--   id_detalle|id_actividad|dia_semana|hora_inicio|hora_fin|
--   tipo_actividad|id_docente_activo_o_SIN_DOCENTE
-- ordenado por id_detalle y unido con salto de línea (E'\n').
-- El docente activo es la fila de asignacion_docente con estado
-- PROPUESTA o CONFIRMADA de mayor id_asignacion para la actividad.
-- Un horario sin detalles tiene huella md5('').
-- Se usa en validacion_horario.huella_propuesta y en las
-- comprobaciones de aprobación/publicación de la tabla horario.
CREATE OR REPLACE FUNCTION gestion_horarios.fn_huella_horario(p_id_horario BIGINT)
RETURNS TEXT
LANGUAGE plpgsql
STABLE
AS $function$
DECLARE
    v_huella TEXT;
BEGIN
    SELECT md5(COALESCE(string_agg(fila, E'\n' ORDER BY orden), ''))
    INTO v_huella
    FROM (
        SELECT
            d.id_detalle::text || '|' ||
            d.id_actividad::text || '|' ||
            a.dia_semana || '|' ||
            a.hora_inicio::text || '|' ||
            a.hora_fin::text || '|' ||
            a.tipo_actividad || '|' ||
            COALESCE(ad.id_docente::text, 'SIN_DOCENTE') AS fila,
            d.id_detalle::text AS orden
        FROM gestion_horarios.detalle_horario d
        JOIN gestion_horarios.actividad_academica a
            ON a.id_actividad = d.id_actividad
        LEFT JOIN LATERAL (
            SELECT ad2.id_docente
            FROM gestion_horarios.asignacion_docente ad2
            WHERE ad2.id_actividad = a.id_actividad
              AND ad2.estado IN ('PROPUESTA', 'CONFIRMADA')
            ORDER BY ad2.id_asignacion DESC
            LIMIT 1
        ) ad ON TRUE
        WHERE d.id_horario = p_id_horario
    ) AS contenido;

    RETURN v_huella;
END;
$function$;

-- ------------------------------------------------------------
-- E) Disparadores de integridad transaccional
-- ------------------------------------------------------------

-- E.1 validacion_horario: la huella se calcula siempre en servidor
-- (evidencia inmutable) y una validación no puede declararse VALIDA
-- si acumuló conflictos duros.
CREATE OR REPLACE FUNCTION gestion_horarios.fn_validacion_horario()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    IF TG_OP = 'INSERT' THEN
        NEW.huella_propuesta := gestion_horarios.fn_huella_horario(NEW.id_horario);
        NEW.validado_en := COALESCE(NEW.validado_en, now());
    ELSE
        IF NEW.id_horario IS DISTINCT FROM OLD.id_horario
            OR NEW.huella_propuesta IS DISTINCT FROM OLD.huella_propuesta
            OR NEW.validado_en IS DISTINCT FROM OLD.validado_en
            OR NEW.version_reglas IS DISTINCT FROM OLD.version_reglas
            OR NEW.id_validado_por IS DISTINCT FROM OLD.id_validado_por THEN
            RAISE EXCEPTION 'EVIDENCIA_INMUTABLE: la validación % conserva huella, fecha, reglas y responsable originales; sólo resultado y observación pueden actualizarse',
                OLD.id_validacion;
        END IF;

        IF NEW.resultado = 'VALIDA' AND OLD.resultado <> 'VALIDA' THEN
            IF EXISTS (
                SELECT 1 FROM gestion_horarios.conflicto_horario c
                WHERE c.id_validacion = OLD.id_validacion
                  AND c.severidad = 'DURA'
            ) THEN
                RAISE EXCEPTION 'CONFLICTO_DURO: la validación % tiene conflictos duros y no puede declararse VALIDA',
                    OLD.id_validacion;
            END IF;
        END IF;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER trg_validacion_horario
    BEFORE INSERT OR UPDATE ON gestion_horarios.validacion_horario
    FOR EACH ROW
    EXECUTE FUNCTION gestion_horarios.fn_validacion_horario();

-- E.2 conflicto_horario: sin huérfanos, los detalles referenciados
-- pertenecen al horario evaluado y un conflicto duro no convive con
-- una validación VALIDA.
CREATE OR REPLACE FUNCTION gestion_horarios.fn_conflicto_horario()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    v_id_horario BIGINT;
    v_resultado VARCHAR(10);
    v_horario_detalle BIGINT;
BEGIN
    IF TG_OP = 'UPDATE' AND NEW.id_validacion IS DISTINCT FROM OLD.id_validacion THEN
        RAISE EXCEPTION 'VALIDACION_INMUTABLE: el conflicto % no puede moverse a otra validación',
            OLD.id_conflicto;
    END IF;

    SELECT v.id_horario, v.resultado
    INTO v_id_horario, v_resultado
    FROM gestion_horarios.validacion_horario v
    WHERE v.id_validacion = NEW.id_validacion;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'VALIDACION_INEXISTENTE: no existe la validación %', NEW.id_validacion;
    END IF;

    IF NEW.id_detalle_a IS NOT NULL THEN
        SELECT d.id_horario INTO v_horario_detalle
        FROM gestion_horarios.detalle_horario d
        WHERE d.id_detalle = NEW.id_detalle_a;
        IF v_horario_detalle <> v_id_horario THEN
            RAISE EXCEPTION 'DETALLE_AJENO: el detalle % pertenece al horario % y no al horario evaluado %',
                NEW.id_detalle_a, v_horario_detalle, v_id_horario;
        END IF;
    END IF;

    IF NEW.id_detalle_b IS NOT NULL THEN
        SELECT d.id_horario INTO v_horario_detalle
        FROM gestion_horarios.detalle_horario d
        WHERE d.id_detalle = NEW.id_detalle_b;
        IF v_horario_detalle <> v_id_horario THEN
            RAISE EXCEPTION 'DETALLE_AJENO: el detalle % pertenece al horario % y no al horario evaluado %',
                NEW.id_detalle_b, v_horario_detalle, v_id_horario;
        END IF;
    END IF;

    IF NEW.severidad = 'DURA' AND v_resultado = 'VALIDA' THEN
        RAISE EXCEPTION 'CONFLICTO_DURO: la validación % está declarada VALIDA y no admite conflictos duros',
            NEW.id_validacion;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER trg_conflicto_horario
    BEFORE INSERT OR UPDATE ON gestion_horarios.conflicto_horario
    FOR EACH ROW
    EXECUTE FUNCTION gestion_horarios.fn_conflicto_horario();

-- E.3 horario: transiciones de estado, validación vigente para
-- aprobar/publicar, identidad de la versión inmutable e
-- inmutabilidad de la versión publicada.
CREATE OR REPLACE FUNCTION gestion_horarios.fn_transicion_horario()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    v_transiciones TEXT[];
    v_validacion_vigente BOOLEAN;
    v_tiene_validacion BOOLEAN;
BEGIN
    IF NEW.id_periodo IS DISTINCT FROM OLD.id_periodo
        OR NEW.ambito IS DISTINCT FROM OLD.ambito
        OR NEW.version IS DISTINCT FROM OLD.version THEN
        RAISE EXCEPTION 'IDENTIDAD_INMUTABLE: período, ámbito y versión del horario % no pueden modificarse',
            OLD.id_horario;
    END IF;

    IF NEW.estado = OLD.estado THEN
        RETURN NEW;
    END IF;

    v_transiciones := CASE OLD.estado
        WHEN 'BORRADOR' THEN ARRAY['GENERADO', 'ARCHIVADO']
        WHEN 'GENERADO' THEN ARRAY['BORRADOR', 'VALIDADO', 'EN_REVISION', 'ARCHIVADO']
        WHEN 'VALIDADO' THEN ARRAY['GENERADO', 'EN_REVISION', 'APROBADO', 'ARCHIVADO']
        WHEN 'EN_REVISION' THEN ARRAY['GENERADO', 'VALIDADO', 'APROBADO', 'ARCHIVADO']
        WHEN 'APROBADO' THEN ARRAY['PUBLICADO', 'GENERADO', 'EN_REVISION', 'ARCHIVADO']
        WHEN 'PUBLICADO' THEN ARRAY['ARCHIVADO']
        WHEN 'ARCHIVADO' THEN ARRAY[]::TEXT[]
        ELSE ARRAY[]::TEXT[]
    END;

    IF NOT (NEW.estado = ANY (v_transiciones)) THEN
        RAISE EXCEPTION 'TRANSICION_INVALIDA: % -> % no está permitida para el horario %',
            OLD.estado, NEW.estado, OLD.id_horario;
    END IF;

    IF NEW.estado = 'VALIDADO' THEN
        SELECT EXISTS (
            SELECT 1 FROM gestion_horarios.validacion_horario v
            WHERE v.id_horario = NEW.id_horario
        ) INTO v_tiene_validacion;

        IF NOT v_tiene_validacion THEN
            RAISE EXCEPTION 'SIN_VALIDACION: el horario % no tiene ninguna validación registrada',
                OLD.id_horario;
        END IF;
    END IF;

    IF NEW.estado IN ('APROBADO', 'PUBLICADO') THEN
        SELECT EXISTS (
            SELECT 1 FROM gestion_horarios.validacion_horario v
            WHERE v.id_horario = NEW.id_horario
              AND v.resultado = 'VALIDA'
              AND v.huella_propuesta = gestion_horarios.fn_huella_horario(NEW.id_horario)
        ) INTO v_validacion_vigente;

        IF NOT v_validacion_vigente THEN
            RAISE EXCEPTION 'VALIDACION_NO_VIGENTE: el horario % no posee una validación VALIDA cuya huella coincida con el contenido actual',
                OLD.id_horario;
        END IF;
    END IF;

    IF NEW.estado = 'APROBADO' THEN
        IF NEW.id_aprobado_por IS NULL THEN
            RAISE EXCEPTION 'APROBACION_SIN_USUARIO: el horario % necesita el usuario que aprueba',
                OLD.id_horario;
        END IF;
        NEW.aprobado_en := COALESCE(NEW.aprobado_en, now());
    END IF;

    IF NEW.estado = 'PUBLICADO' THEN
        NEW.publicado_en := now();
        NEW.aprobado_en := COALESCE(NEW.aprobado_en, now());
    END IF;

    IF NEW.estado IN ('BORRADOR', 'GENERADO', 'VALIDADO', 'EN_REVISION') THEN
        NEW.id_aprobado_por := NULL;
        NEW.aprobado_en := NULL;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER trg_transicion_horario
    BEFORE UPDATE ON gestion_horarios.horario
    FOR EACH ROW
    EXECUTE FUNCTION gestion_horarios.fn_transicion_horario();

-- E.4 detalle_horario: el horario evaluado es una propuesta
-- editable hasta publicarse; el período del horario debe coincidir
-- con el período de la sección de la actividad programada.
CREATE OR REPLACE FUNCTION gestion_horarios.fn_validar_detalle_horario()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    v_estado VARCHAR(20);
    v_periodo_horario BIGINT;
    v_periodo_seccion BIGINT;
    v_id_horario BIGINT;
BEGIN
    IF TG_OP = 'DELETE' THEN
        v_id_horario := OLD.id_horario;
    ELSE
        v_id_horario := NEW.id_horario;
    END IF;

    SELECT h.estado, h.id_periodo
    INTO v_estado, v_periodo_horario
    FROM gestion_horarios.horario h
    WHERE h.id_horario = v_id_horario;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'HORARIO_INEXISTENTE: no existe el horario %', v_id_horario;
    END IF;

    IF v_estado = 'PUBLICADO' THEN
        RAISE EXCEPTION 'VERSION_PUBLICADA: la versión publicada del horario % no puede modificarse',
            v_id_horario;
    END IF;

    IF TG_OP <> 'DELETE' THEN
        SELECT s.id_periodo
        INTO v_periodo_seccion
        FROM gestion_horarios.actividad_academica a
        JOIN core.seccion s ON s.id_seccion = a.id_seccion
        WHERE a.id_actividad = NEW.id_actividad;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'ACTIVIDAD_INEXISTENTE: no existe la actividad %', NEW.id_actividad;
        END IF;

        IF v_periodo_horario <> v_periodo_seccion THEN
            RAISE EXCEPTION 'PERIODO_INCOMPATIBLE: el horario pertenece al período % y la sección de la actividad al período %',
                v_periodo_horario, v_periodo_seccion;
        END IF;
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER trg_validar_detalle_horario
    BEFORE INSERT OR UPDATE OR DELETE ON gestion_horarios.detalle_horario
    FOR EACH ROW
    EXECUTE FUNCTION gestion_horarios.fn_validar_detalle_horario();

-- E.5 apertura_repeticion: trazabilidad de la autorización y
-- política de disponibilidad obligatoria antes de incorporar.
CREATE OR REPLACE FUNCTION gestion_horarios.fn_transicion_apertura()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    v_estado_declaracion VARCHAR(20);
BEGIN
    IF TG_OP = 'INSERT' THEN
        NEW.solicitado_en := COALESCE(NEW.solicitado_en, now());
    ELSE
        IF NEW.id_periodo IS DISTINCT FROM OLD.id_periodo
            OR NEW.id_curso_plan IS DISTINCT FROM OLD.id_curso_plan
            OR NEW.solicitado_por IS DISTINCT FROM OLD.solicitado_por
            OR NEW.solicitado_en IS DISTINCT FROM OLD.solicitado_en THEN
            RAISE EXCEPTION 'SOLICITUD_INMUTABLE: período, curso, solicitante y fecha de la solicitud % no pueden modificarse',
                OLD.id_apertura;
        END IF;

        IF OLD.estado = 'DENEGADA' AND NEW.estado <> 'DENEGADA' THEN
            RAISE EXCEPTION 'APERTURA_DENEGADA: la solicitud % fue denegada y no puede quedar %',
                OLD.id_apertura, NEW.estado;
        END IF;

        IF OLD.estado = 'INCORPORADA' AND NEW.estado <> 'INCORPORADA' THEN
            RAISE EXCEPTION 'APERTURA_INCORPORADA: la solicitud % ya está incorporada y su estado no puede cambiar',
                OLD.id_apertura;
        END IF;

        IF OLD.estado = 'SOLICITADA' AND NEW.estado = 'INCORPORADA' THEN
            RAISE EXCEPTION 'APERTURA_SIN_AUTORIZACION: la solicitud % no puede incorporarse sin autorización previa',
                OLD.id_apertura;
        END IF;

        IF OLD.estado IN ('AUTORIZADA', 'DENEGADA') AND NEW.estado = 'SOLICITADA' THEN
            RAISE EXCEPTION 'APERTURA_RESUELTA: la solicitud % ya fue resuelta; cree una nueva solicitud si corresponde',
                OLD.id_apertura;
        END IF;
    END IF;

    IF NEW.estado IN ('AUTORIZADA', 'DENEGADA', 'INCORPORADA') THEN
        IF NEW.autorizado_por IS NULL THEN
            RAISE EXCEPTION 'AUTORIZACION_SIN_USUARIO: la solicitud % en estado % necesita el usuario que resuelve',
                NEW.id_apertura, NEW.estado;
        END IF;
        NEW.resuelto_en := COALESCE(NEW.resuelto_en, now());
    END IF;

    IF NEW.estado = 'INCORPORADA' THEN
        IF NEW.id_seccion IS NULL OR NEW.id_docente_propuesto IS NULL THEN
            RAISE EXCEPTION 'INCORPORACION_INCOMPLETA: la solicitud % requiere sección y docente propuesto para quedar INCORPORADA',
                NEW.id_apertura;
        END IF;

        SELECT d.estado
        INTO v_estado_declaracion
        FROM gestion_horarios.declaracion_disponibilidad d
        WHERE d.id_periodo = NEW.id_periodo
          AND d.id_docente = NEW.id_docente_propuesto;

        IF NOT FOUND OR v_estado_declaracion <> 'PRESENTADA' THEN
            RAISE EXCEPTION 'DISPONIBILIDAD_NO_PRESENTADA: el docente % no presentó disponibilidad para el período %; política de disponibilidad obligatoria de la repetición incumplida',
                NEW.id_docente_propuesto, NEW.id_periodo;
        END IF;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER trg_transicion_apertura
    BEFORE INSERT OR UPDATE ON gestion_horarios.apertura_repeticion
    FOR EACH ROW
    EXECUTE FUNCTION gestion_horarios.fn_transicion_apertura();
