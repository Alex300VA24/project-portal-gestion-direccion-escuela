# Script Consolidado de Procedimientos Almacenados y Funciones PostgreSQL

Este documento recopila la implementación en PL/pgSQL de las funciones y procedimientos almacenados para los 5 módulos del sistema según sus mapas de procesos:

1. **Gestión de Calidad** (Ciclo PHVA)
2. **Proyecto de Tesis** (Etapa 1 - 9.º ciclo)
3. **Tesis y Sustentación** (Etapa 2 - 10.º ciclo)
4. **Pre Matrícula**
5. **Gestión de Matrícula No Regular**

---

## 1. Esquema `calidad` — Gestión de Calidad (Ciclo PHVA)

### 1.1 Registrar / Definir Meta
```sql
CREATE OR REPLACE PROCEDURE calidad.sp_definir_meta(
    p_indicador_id INT,
    p_periodo_id INT,
    p_valor_meta NUMERIC,
    p_usuario_id INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO calidad.meta_indicador (
        indicador_id, 
        periodo_academico_id, 
        valor_meta, 
        creado_por, 
        created_at
    )
    VALUES (
        p_indicador_id, 
        p_periodo_id, 
        p_valor_meta, 
        p_usuario_id, 
        NOW()
    )
    ON CONFLICT (indicador_id, periodo_academico_id) 
    DO UPDATE SET 
        valor_meta = EXCLUDED.valor_meta,
        updated_at = NOW();
END;
$$;
```

### 1.2 Registrar Evidencia y Medición de Indicador
```sql
CREATE OR REPLACE PROCEDURE calidad.sp_registrar_evidencia_medicion(
    p_indicador_id INT,
    p_periodo_id INT,
    p_valor_obtenido NUMERIC,
    p_archivo_id INT,
    p_usuario_id INT,
    OUT p_medicion_id INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO calidad.medicion_indicador (
        indicador_id,
        periodo_academico_id,
        valor_medido,
        estado_validacion,
        registrado_por,
        created_at
    )
    VALUES (
        p_indicador_id,
        p_periodo_id,
        p_valor_obtenido,
        'PENDIENTE',
        p_usuario_id,
        NOW()
    )
    RETURNING id INTO p_medicion_id;

    IF p_archivo_id IS NOT NULL THEN
        INSERT INTO calidad.evidencia (
            medicion_indicador_id,
            archivo_id,
            subido_por,
            created_at
        )
        VALUES (
            p_medicion_id,
            p_archivo_id,
            p_usuario_id,
            NOW()
        );
    END IF;
END;
$$;
```

### 1.3 Validar Mediciones y Evidencias (Compuerta de Validación)
```sql
CREATE OR REPLACE PROCEDURE calidad.sp_validar_datos_medicion(
    p_medicion_id INT,
    p_es_valido BOOLEAN,
    p_observacion TEXT,
    p_usuario_validador_id INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_es_valido THEN
        UPDATE calidad.medicion_indicador
        SET estado_validacion = 'VALIDADO',
            observacion_validacion = p_observacion,
            validado_por = p_usuario_validador_id,
            fecha_validacion = NOW(),
            updated_at = NOW()
        WHERE id = p_medicion_id;
    ELSE
        UPDATE calidad.medicion_indicador
        SET estado_validacion = 'OBSERVADO',
            observacion_validacion = p_observacion,
            validado_por = p_usuario_validador_id,
            fecha_validacion = NOW(),
            updated_at = NOW()
        WHERE id = p_medicion_id;
    END IF;
END;
$$;
```

### 1.4 Calcular Valor del Indicador y Brechas
```sql
CREATE OR REPLACE FUNCTION calidad.calcular_valor_indicador(
    p_indicador_id INT,
    p_periodo_id INT
)
RETURNS TABLE (
    indicador_id INT,
    periodo_academico_id INT,
    valor_meta NUMERIC,
    valor_medido NUMERIC,
    brecha NUMERIC,
    estado_evaluacion VARCHAR
) 
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT 
        i.id AS indicador_id,
        m.periodo_academico_id,
        m.valor_meta,
        mi.valor_medido,
        (mi.valor_medido - m.valor_meta) AS brecha,
        CASE 
            WHEN mi.valor_medido >= m.valor_meta THEN 'CONFORME'
            ELSE 'CRITICO'
        END AS estado_evaluacion
    FROM calidad.indicador i
    JOIN calidad.meta_indicador m ON i.id = m.indicador_id
    JOIN calidad.medicion_indicador mi ON i.id = mi.indicador_id 
        AND m.periodo_academico_id = mi.periodo_academico_id
    WHERE i.id = p_indicador_id
      AND m.periodo_academico_id = p_periodo_id
      AND mi.estado_validacion = 'VALIDADO';
END;
$$;
```

### 1.5 Publicar Tablero de Indicadores y Crear Plan de Mejora
```sql
CREATE OR REPLACE FUNCTION calidad.fn_publicar_tablero_indicadores(
    p_periodo_id INT
)
RETURNS TABLE (
    proceso_nombre VARCHAR,
    indicador_nombre VARCHAR,
    meta NUMERIC,
    medido NUMERIC,
    brecha NUMERIC,
    estado VARCHAR
)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT 
        pr.nombre AS proceso_nombre,
        ind.nombre AS indicador_nombre,
        m.valor_meta AS meta,
        mi.valor_medido AS medido,
        (mi.valor_medido - m.valor_meta) AS brecha,
        CASE 
            WHEN mi.valor_medido >= m.valor_meta THEN 'CONFORME'
            ELSE 'CRITICO'
        END AS estado
    FROM calidad.indicador ind
    JOIN calidad.proceso pr ON ind.proceso_id = pr.id
    JOIN calidad.meta_indicador m ON ind.id = m.indicador_id
    JOIN calidad.medicion_indicador mi ON ind.id = mi.indicador_id 
        AND m.periodo_academico_id = mi.periodo_academico_id
    WHERE m.periodo_academico_id = p_periodo_id
      AND mi.estado_validacion = 'VALIDADO';
END;
$$;

CREATE OR REPLACE PROCEDURE calidad.sp_crear_plan_mejora(
    p_indicador_id INT,
    p_periodo_id INT,
    p_descripcion_mejora TEXT,
    p_responsable_id INT,
    OUT p_plan_mejora_id INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO calidad.plan_mejora (
        indicador_id,
        periodo_academico_id,
        descripcion,
        responsable_id,
        estado,
        created_at
    )
    VALUES (
        p_indicador_id,
        p_periodo_id,
        p_descripcion_mejora,
        p_responsable_id,
        'EN_PROCESO',
        NOW()
    )
    RETURNING id INTO p_plan_mejora_id;
END;
$$;
```

---

## 2. Esquema `tesis` — Proyecto de Tesis (Etapa 1)

### 2.1 Registrar Proyecto e Iniciar Trámite (Pasos 1 - 4)
```sql
CREATE OR REPLACE PROCEDURE tesis.sp_registrar_e_iniciar_proyecto(
    p_titulo VARCHAR,
    p_linea_investigacion_id INT,
    p_estudiante_id INT,
    p_asesor_id INT,
    OUT p_proyecto_id INT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_codigo_proyecto VARCHAR(50);
BEGIN
    v_codigo_proyecto := 'PT-' || TO_CHAR(NOW(), 'YYYYMMDD') || '-' || LPAD(CAST(FLOOR(RANDOM() * 1000) AS VARCHAR), 3, '0');

    INSERT INTO tesis.proyecto_tesis (
        codigo,
        titulo,
        linea_investigacion_id,
        estado,
        created_at
    )
    VALUES (
        v_codigo_proyecto,
        p_titulo,
        p_linea_investigacion_id,
        'REGISTRADO',
        NOW()
    )
    RETURNING id INTO p_proyecto_id;

    INSERT INTO tesis.proyecto_estudiante (
        proyecto_tesis_id,
        estudiante_id,
        created_at
    )
    VALUES (p_proyecto_id, p_estudiante_id, NOW());

    INSERT INTO tesis.asesor_proyecto (
        proyecto_tesis_id,
        docente_id,
        es_principal,
        created_at
    )
    VALUES (p_proyecto_id, p_asesor_id, TRUE, NOW());

    INSERT INTO tesis.historial_estado (
        proyecto_tesis_id,
        estado_anterior,
        estado_nuevo,
        observacion,
        created_at
    )
    VALUES (p_proyecto_id, NULL, 'REGISTRADO', 'Registro de proyecto inicial y asignación de asesor', NOW());
END;
$$;
```

### 2.2 Entrega y Evaluacion de Similitud Turnitin (Pasos 7, 8, 9)
```sql
CREATE OR REPLACE PROCEDURE tesis.sp_registrar_reporte_similitud(
    p_proyecto_id INT,
    p_archivo_id INT,
    p_porcentaje_similitud NUMERIC,
    p_observaciones TEXT,
    p_usuario_id INT,
    OUT p_aprobado BOOLEAN
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_entrega_id INT;
    v_limite_permitido NUMERIC := 25.0;
BEGIN
    INSERT INTO tesis.entrega (
        proyecto_tesis_id,
        archivo_id,
        subido_por,
        fecha_entrega,
        created_at
    )
    VALUES (p_proyecto_id, p_archivo_id, p_usuario_id, NOW(), NOW())
    RETURNING id INTO v_entrega_id;

    p_aprobado := (p_porcentaje_similitud <= v_limite_permitido);

    INSERT INTO tesis.reporte_similitud (
        proyecto_tesis_id,
        entrega_id,
        porcentaje,
        conclusiones,
        evaluado_por,
        created_at
    )
    VALUES (
        p_proyecto_id,
        v_entrega_id,
        p_porcentaje_similitud,
        p_observaciones,
        p_usuario_id,
        NOW()
    );

    IF NOT p_aprobado THEN
        UPDATE tesis.proyecto_tesis
        SET estado = 'OBSERVADO_SIMILITUD', updated_at = NOW()
        WHERE id = p_proyecto_id;
    ELSE
        UPDATE tesis.proyecto_tesis
        SET estado = 'APROBADO_SIMILITUD', updated_at = NOW()
        WHERE id = p_proyecto_id;
    END IF;

    INSERT INTO tesis.historial_estado (
        proyecto_tesis_id,
        estado_anterior,
        estado_nuevo,
        observacion,
        created_at
    )
    VALUES (
        p_proyecto_id, 
        'REGISTRADO', 
        CASE WHEN p_aprobado THEN 'APROBADO_SIMILITUD' ELSE 'OBSERVADO_SIMILITUD' END,
        'Evaluación de reporte de similitud Turnitin: ' || p_porcentaje_similitud || '%',
        NOW()
    );
END;
$$;
```

### 2.3 Asignar Jurado Dictaminador (Paso 13)
```sql
CREATE OR REPLACE PROCEDURE tesis.sp_asignar_jurado_proyecto(
    p_proyecto_id INT,
    p_presidente_id INT,
    p_secretario_id INT,
    p_vocal_id INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    DELETE FROM tesis.jurado_proyecto WHERE proyecto_tesis_id = p_proyecto_id;

    INSERT INTO tesis.jurado_proyecto (proyecto_tesis_id, docente_id, rol, created_at)
    VALUES (p_proyecto_id, p_presidente_id, 'PRESIDENTE', NOW());

    INSERT INTO tesis.jurado_proyecto (proyecto_tesis_id, docente_id, rol, created_at)
    VALUES (p_proyecto_id, p_secretario_id, 'SECRETARIO', NOW());

    INSERT INTO tesis.jurado_proyecto (proyecto_tesis_id, docente_id, rol, created_at)
    VALUES (p_proyecto_id, p_vocal_id, 'VOCAL', NOW());

    UPDATE tesis.proyecto_tesis
    SET estado = 'CON_JURADO_ASIGNADO', updated_at = NOW()
    WHERE id = p_proyecto_id;
END;
$$;
```

### 2.4 Dictamen por Jurados y Resolución (Pasos 14 a 17)
```sql
CREATE OR REPLACE PROCEDURE tesis.sp_evaluar_dictamen_jurado(
    p_proyecto_id INT,
    p_docente_jurado_id INT,
    p_aprobado BOOLEAN,
    p_observaciones TEXT,
    OUT p_estado_final VARCHAR
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_total_jurados INT;
    v_votos_favorables INT;
    v_votos_desfavorables INT;
BEGIN
    INSERT INTO tesis.revision (
        proyecto_tesis_id,
        docente_id,
        estado_evaluacion,
        observacion_general,
        created_at
    )
    VALUES (
        p_proyecto_id,
        p_docente_jurado_id,
        CASE WHEN p_aprobado THEN 'APROBADO' ELSE 'OBSERVADO' END,
        p_observaciones,
        NOW()
    );

    SELECT COUNT(*) INTO v_total_jurados 
    FROM tesis.jurado_proyecto 
    WHERE proyecto_tesis_id = p_proyecto_id;

    SELECT COUNT(*) INTO v_votos_favorables 
    FROM tesis.revision 
    WHERE proyecto_tesis_id = p_proyecto_id AND estado_evaluacion = 'APROBADO';

    SELECT COUNT(*) INTO v_votos_desfavorables 
    FROM tesis.revision 
    WHERE proyecto_tesis_id = p_proyecto_id AND estado_evaluacion = 'OBSERVADO';

    IF v_votos_desfavorables > 0 THEN
        p_estado_final := 'OBSERVADO_POR_JURADO';
        UPDATE tesis.proyecto_tesis SET estado = p_estado_final, updated_at = NOW() WHERE id = p_proyecto_id;

    ELSIF v_votos_favorables = v_total_jurados AND v_total_jurados > 0 THEN
        p_estado_final := 'PROYECTO_APROBADO';
        UPDATE tesis.proyecto_tesis SET estado = p_estado_final, updated_at = NOW() WHERE id = p_proyecto_id;

        INSERT INTO tesis.resolucion (
            proyecto_tesis_id,
            numero_resolucion,
            tipo,
            fecha_emision,
            created_at
        )
        VALUES (
            p_proyecto_id,
            'RES-PROY-' || p_proyecto_id || '-' || EXTRACT(YEAR FROM NOW()),
            'APROBACION_PROYECTO',
            CURRENT_DATE,
            NOW()
        );
    ELSE
        p_estado_final := 'EN_EVALUACION';
    END IF;

    INSERT INTO tesis.historial_estado (
        proyecto_tesis_id,
        estado_anterior,
        estado_nuevo,
        observacion,
        created_at
    )
    VALUES (p_proyecto_id, 'CON_JURADO_ASIGNADO', p_estado_final, p_observaciones, NOW());
END;
$$;
```

### 2.5 Consultar Trazabilidad de Proyecto
```sql
CREATE OR REPLACE FUNCTION tesis.fn_consultar_estado_proyecto(
    p_proyecto_id INT
)
RETURNS TABLE (
    codigo_proyecto VARCHAR,
    titulo VARCHAR,
    estudiante_nombre TEXT,
    asesor_nombre TEXT,
    estado_actual VARCHAR,
    porcentaje_similitud NUMERIC,
    jurados_evaluadores INT,
    fecha_ultimo_cambio TIMESTAMP
)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT 
        p.codigo,
        p.titulo,
        (per_est.nombres || ' ' || per_est.apellido_paterno) AS estudiante_nombre,
        (per_ase.nombres || ' ' || per_ase.apellido_paterno) AS asesor_nombre,
        p.estado AS estado_actual,
        rs.porcentaje AS porcentaje_similitud,
        CAST(COUNT(DISTINCT jp.docente_id) AS INT) AS jurados_evaluadores,
        p.updated_at AS fecha_ultimo_cambio
    FROM tesis.proyecto_tesis p
    JOIN tesis.proyecto_estudiante pe ON p.id = pe.proyecto_tesis_id
    JOIN core.estudiante est ON pe.estudiante_id = est.id
    JOIN core.persona per_est ON est.persona_id = per_est.id
    LEFT JOIN tesis.asesor_proyecto ap ON p.id = ap.proyecto_tesis_id AND ap.es_principal = TRUE
    LEFT JOIN core.docente doc ON ap.docente_id = doc.id
    LEFT JOIN core.persona per_ase ON doc.persona_id = per_ase.id
    LEFT JOIN tesis.reporte_similitud rs ON p.id = rs.proyecto_tesis_id
    LEFT JOIN tesis.jurado_proyecto jp ON p.id = jp.proyecto_tesis_id
    WHERE p.id = p_proyecto_id
    GROUP BY p.codigo, p.titulo, per_est.nombres, per_est.apellido_paterno, 
             per_ase.nombres, per_ase.apellido_paterno, p.estado, rs.porcentaje, p.updated_at;
END;
$$;
```

---

## 3. Esquema `tesis` — Tesis y Sustentación (Etapa 2)

### 3.1 Iniciar Tramitación de Tesis (Pasos 18 - 22)
```sql
CREATE OR REPLACE PROCEDURE tesis.sp_iniciar_tramite_tesis(
    p_proyecto_tesis_id INT,
    p_archivo_id INT,
    p_estudiante_id INT,
    OUT p_solicitud_id INT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_estado_proyecto VARCHAR(50);
BEGIN
    SELECT estado INTO v_estado_proyecto 
    FROM tesis.proyecto_tesis 
    WHERE id = p_proyecto_tesis_id;

    IF v_estado_proyecto IS NULL OR v_estado_proyecto != 'PROYECTO_APROBADO' THEN
        RAISE EXCEPTION 'El proyecto de tesis debe estar aprobado para iniciar la Etapa 2.';
    END IF;

    INSERT INTO tesis.entrega (
        proyecto_tesis_id,
        archivo_id,
        subido_por,
        fecha_entrega,
        created_at
    )
    VALUES (p_proyecto_tesis_id, p_archivo_id, p_estudiante_id, NOW(), NOW());

    INSERT INTO tesis.solicitud (
        proyecto_tesis_id,
        tipo_solicitud_id,
        estado,
        fecha_solicitud,
        created_at
    )
    VALUES (
        p_proyecto_tesis_id,
        2,
        'EN_REVISION_ASESOR',
        NOW(),
        NOW()
    )
    RETURNING id INTO p_solicitud_id;

    UPDATE tesis.proyecto_tesis 
    SET estado = 'TESIS_EN_REVISION_ASESOR', updated_at = NOW() 
    WHERE id = p_proyecto_tesis_id;

    INSERT INTO tesis.historial_estado (
        proyecto_tesis_id,
        estado_anterior,
        estado_nuevo,
        observacion,
        created_at
    )
    VALUES (p_proyecto_tesis_id, 'PROYECTO_APROBADO', 'TESIS_EN_REVISION_ASESOR', 'Inicio de tramite de borrador de tesis', NOW());
END;
$$;
```

### 3.2 Conformidad e Informe del Asesor (Pasos 23 - 26)
```sql
CREATE OR REPLACE PROCEDURE tesis.sp_evaluar_conformidad_asesor(
    p_proyecto_tesis_id INT,
    p_asesor_id INT,
    p_aprobado BOOLEAN,
    p_porcentaje_similitud NUMERIC,
    p_observaciones TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_nuevo_estado VARCHAR(50);
BEGIN
    INSERT INTO tesis.reporte_similitud (
        proyecto_tesis_id,
        porcentaje,
        conclusiones,
        evaluado_por,
        created_at
    )
    VALUES (p_proyecto_tesis_id, p_porcentaje_similitud, p_observaciones, p_asesor_id, NOW());

    IF p_aprobado AND p_porcentaje_similitud <= 25.0 THEN
        v_nuevo_estado := 'CONFORMIDAD_ASESOR_APROBADA';
    ELSE
        v_nuevo_estado := 'TESIS_OBSERVADA_ASESOR';
    END IF;

    UPDATE tesis.proyecto_tesis 
    SET estado = v_nuevo_estado, updated_at = NOW() 
    WHERE id = p_proyecto_tesis_id;

    INSERT INTO tesis.historial_estado (
        proyecto_tesis_id,
        estado_anterior,
        estado_nuevo,
        observacion,
        created_at
    )
    VALUES (p_proyecto_tesis_id, 'TESIS_EN_REVISION_ASESOR', v_nuevo_estado, p_observaciones, NOW());
END;
$$;
```

### 3.3 Programar Acto de Sustentación (Pasos 27 - 30)
```sql
CREATE OR REPLACE PROCEDURE tesis.sp_programar_sustentacion(
    p_proyecto_tesis_id INT,
    p_fecha_sustentacion TIMESTAMP,
    p_ambiente_id INT,
    p_link_virtual VARCHAR,
    OUT p_sustentacion_id INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO tesis.sustentacion (
        proyecto_tesis_id,
        fecha_hora,
        ambiente_id,
        link_virtual,
        estado,
        created_at
    )
    VALUES (
        p_proyecto_tesis_id,
        p_fecha_sustentacion,
        p_ambiente_id,
        p_link_virtual,
        'PROGRAMADA',
        NOW()
    )
    RETURNING id INTO p_sustentacion_id;

    UPDATE tesis.proyecto_tesis 
    SET estado = 'SUSTENTACION_PROGRAMADA', updated_at = NOW() 
    WHERE id = p_proyecto_tesis_id;

    INSERT INTO tesis.historial_estado (
        proyecto_tesis_id,
        estado_anterior,
        estado_nuevo,
        observacion,
        created_at
    )
    VALUES (
        p_proyecto_tesis_id, 
        'CONFORMIDAD_ASESOR_APROBADA', 
        'SUSTENTACION_PROGRAMADA', 
        'Sustentación programada para la fecha: ' || p_fecha_sustentacion, 
        NOW()
    );
END;
$$;
```

### 3.4 Calcular Nota y Emisión de Acta (Pasos 31 - 36)
```sql
CREATE OR REPLACE FUNCTION tesis.calcular_nota_sustentacion(
    p_sustentacion_id INT
)
RETURNS NUMERIC
LANGUAGE plpgsql
AS $$
DECLARE
    v_promedio NUMERIC(4,2);
BEGIN
    SELECT AVG(nota) INTO v_promedio
    FROM tesis.resultado_jurado
    WHERE sustentacion_id = p_sustentacion_id;

    RETURN COALESCE(v_promedio, 0.00);
END;
$$;

CREATE OR REPLACE PROCEDURE tesis.sp_registrar_resultado_sustentacion(
    p_sustentacion_id INT,
    OUT p_nota_final NUMERIC,
    OUT p_resultado VARCHAR
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_proyecto_id INT;
    v_numero_acta VARCHAR(50);
BEGIN
    SELECT proyecto_tesis_id INTO v_proyecto_id 
    FROM tesis.sustentacion 
    WHERE id = p_sustentacion_id;

    p_nota_final := tesis.calcular_nota_sustentacion(p_sustentacion_id);

    IF p_nota_final >= 17.00 THEN
        p_resultado := 'APROBADO_CON_EXCELENCIA';
    ELSIF p_nota_final >= 14.00 THEN
        p_resultado := 'APROBADO';
    ELSE
        p_resultado := 'DESAPROBADO';
    END IF;

    v_numero_acta := 'ACTA-' || EXTRACT(YEAR FROM NOW()) || '-' || LPAD(p_sustentacion_id::TEXT, 5, '0');

    INSERT INTO tesis.acta (
        sustentacion_id,
        numero_acta,
        nota_final,
        resultado,
        fecha_emision,
        created_at
    )
    VALUES (
        p_sustentacion_id,
        v_numero_acta,
        p_nota_final,
        p_resultado,
        NOW(),
        NOW()
    );

    UPDATE tesis.sustentacion 
    SET estado = 'FINALIZADA', updated_at = NOW() 
    WHERE id = p_sustentacion_id;

    UPDATE tesis.proyecto_tesis 
    SET estado = CASE WHEN p_resultado LIKE 'APROBADO%' THEN 'TESIS_SUSTENTADA' ELSE 'TESIS_RECHAZADA' END,
        updated_at = NOW() 
    WHERE id = v_proyecto_id;

    INSERT INTO tesis.historial_estado (
        proyecto_tesis_id,
        estado_anterior,
        estado_nuevo,
        observacion,
        created_at
    )
    VALUES (
        v_proyecto_id, 
        'SUSTENTACION_PROGRAMADA', 
        CASE WHEN p_resultado LIKE 'APROBADO%' THEN 'TESIS_SUSTENTADA' ELSE 'TESIS_RECHAZADA' END,
        'Acto de sustentación concluido con nota: ' || p_nota_final || ' (' || p_resultado || ')', 
        NOW()
    );
END;
$$;
```

### 3.5 Consulta de Expediente Completo de Tesis
```sql
CREATE OR REPLACE FUNCTION tesis.fn_obtener_expediente_completo(
    p_proyecto_tesis_id INT
)
RETURNS TABLE (
    codigo_proyecto VARCHAR,
    titulo VARCHAR,
    estudiante TEXT,
    asesor TEXT,
    estado_tesis VARCHAR,
    porcentaje_similitud NUMERIC,
    fecha_sustentacion TIMESTAMP,
    ambiente VARCHAR,
    numero_acta VARCHAR,
    nota_final NUMERIC,
    veredicto VARCHAR
)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT 
        p.codigo,
        p.titulo,
        (per_est.nombres || ' ' || per_est.apellido_paterno || ' ' || per_est.apellido_materno) AS estudiante,
        (per_ase.nombres || ' ' || per_ase.apellido_paterno) AS asesor,
        p.estado AS estado_tesis,
        rs.porcentaje AS porcentaje_similitud,
        s.fecha_hora AS fecha_sustentacion,
        COALESCE(amb.nombre, s.link_virtual, 'No asignado') AS ambiente,
        act.numero_acta,
        act.nota_final,
        act.resultado AS veredicto
    FROM tesis.proyecto_tesis p
    JOIN tesis.proyecto_estudiante pe ON p.id = pe.proyecto_tesis_id
    JOIN core.estudiante est ON pe.estudiante_id = est.id
    JOIN core.persona per_est ON est.persona_id = per_est.id
    LEFT JOIN tesis.asesor_proyecto ap ON p.id = ap.proyecto_tesis_id AND ap.es_principal = TRUE
    LEFT JOIN core.docente doc ON ap.docente_id = doc.id
    LEFT JOIN core.persona per_ase ON doc.persona_id = per_ase.id
    LEFT JOIN tesis.reporte_similitud rs ON p.id = rs.proyecto_tesis_id
    LEFT JOIN tesis.sustentacion s ON p.id = s.proyecto_tesis_id
    LEFT JOIN core.ambiente amb ON s.ambiente_id = amb.id
    LEFT JOIN tesis.acta act ON s.id = act.sustentacion_id
    WHERE p.id = p_proyecto_tesis_id;
END;
$$;
```

---

## 4. Esquema `prematricula` — Pre Matrícula

### 4.1 Cargar Récord Académico y Obtener Cursos Habilitados
```sql
CREATE OR REPLACE FUNCTION prematricula.fn_obtener_cursos_habilitados(
    p_estudiante_id INT,
    p_periodo_id INT
)
RETURNS TABLE (
    curso_id INT,
    codigo_curso VARCHAR,
    nombre_curso VARCHAR,
    creditos INT,
    ciclo INT,
    es_repeticion BOOLEAN
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_plan_estudios_id INT;
    v_creditos_acumulados INT;
BEGIN
    SELECT plan_estudios_id INTO v_plan_estudios_id 
    FROM core.estudiante 
    WHERE id = p_estudiante_id;

    SELECT COALESCE(SUM(c.creditos), 0) INTO v_creditos_acumulados
    FROM gestion_matriculas.nota n
    JOIN gestion_matriculas.matricula_detalle md ON n.matricula_detalle_id = md.id
    JOIN core.seccion s ON md.seccion_id = s.id
    JOIN core.curso c ON s.curso_id = c.id
    WHERE md.estudiante_id = p_estudiante_id AND n.es_aprobado = TRUE;

    RETURN QUERY
    SELECT 
        c.id AS curso_id,
        c.codigo AS codigo_curso,
        c.nombre AS nombre_curso,
        c.creditos,
        cp.ciclo,
        EXISTS (
            SELECT 1 
            FROM gestion_matriculas.nota n_des
            JOIN gestion_matriculas.matricula_detalle md_des ON n_des.matricula_detalle_id = md_des.id
            JOIN core.seccion s_des ON md_des.seccion_id = s_des.id
            WHERE md_des.estudiante_id = p_estudiante_id 
              AND s_des.curso_id = c.id 
              AND n_des.es_aprobado = FALSE
        ) AS es_repeticion
    FROM core.curso_plan cp
    JOIN core.curso c ON cp.curso_id = c.id
    WHERE cp.plan_estudios_id = v_plan_estudios_id
      AND c.id NOT IN (
          SELECT s_ap.curso_id 
          FROM gestion_matriculas.nota n_ap
          JOIN gestion_matriculas.matricula_detalle md_ap ON n_ap.matricula_detalle_id = md_ap.id
          JOIN core.seccion s_ap ON md_ap.seccion_id = s_ap.id
          WHERE md_ap.estudiante_id = p_estudiante_id AND n_ap.es_aprobado = TRUE
      )
      AND NOT EXISTS (
          SELECT 1 
          FROM core.curso_prerequisito req
          WHERE req.curso_id = c.id
            AND req.prerequisito_id NOT IN (
                SELECT s_pr.curso_id 
                FROM gestion_matriculas.nota n_pr
                JOIN gestion_matriculas.matricula_detalle md_pr ON n_pr.matricula_detalle_id = md_pr.id
                JOIN core.seccion s_pr ON md_pr.seccion_id = s_pr.id
                WHERE md_pr.estudiante_id = p_estudiante_id AND n_pr.es_aprobado = TRUE
            )
      );
END;
$$;
```

### 4.2 Validar Créditos y Enviar Pre Matrícula
```sql
CREATE OR REPLACE PROCEDURE prematricula.sp_enviar_prematricula(
    p_estudiante_id INT,
    p_periodo_id INT,
    p_cursos_ids INT[],
    OUT p_solicitud_id INT,
    OUT p_es_valido BOOLEAN,
    OUT p_mensaje TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_total_creditos INT := 0;
    v_tiene_tercera_matricula BOOLEAN := FALSE;
    v_max_creditos INT := 22;
    v_curso_id INT;
    v_veces_cursado INT;
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM prematricula.cronograma_prematricula
        WHERE periodo_academico_id = p_periodo_id AND estado = 'ACTIVO'
    ) THEN
        p_es_valido := FALSE;
        p_mensaje := 'El periodo de pre matricula no se encuentra activo.';
        RETURN;
    END IF;

    FOREACH v_curso_id IN ARRAY p_cursos_ids LOOP
        SELECT creditos INTO v_total_creditos 
        FROM core.curso WHERE id = v_curso_id;
        
        SELECT COUNT(*) INTO v_veces_cursado
        FROM gestion_matriculas.matricula_detalle md
        JOIN core.seccion s ON md.seccion_id = s.id
        WHERE md.estudiante_id = p_estudiante_id AND s.curso_id = v_curso_id;

        IF v_veces_cursado >= 2 THEN
            v_tiene_tercera_matricula := TRUE;
        END IF;
    END LOOP;

    IF v_tiene_tercera_matricula THEN
        v_max_creditos := 12;
    ELSE
        v_max_creditos := 22;
    END IF;

    IF v_total_creditos > v_max_creditos THEN
        p_es_valido := FALSE;
        p_mensaje := 'Supera el límite de créditos permitido (' || v_max_creditos || ' créditos max).';
        RETURN;
    END IF;

    INSERT INTO prematricula.solicitud_prematricula (
        estudiante_id,
        periodo_academico_id,
        total_creditos,
        estado,
        fecha_solicitud,
        created_at
    )
    VALUES (
        p_estudiante_id,
        p_periodo_id,
        v_total_creditos,
        'REGISTRADO',
        NOW(),
        NOW()
    )
    RETURNING id INTO p_solicitud_id;

    FOREACH v_curso_id IN ARRAY p_cursos_ids LOOP
        INSERT INTO prematricula.detalle_solicitud (
            solicitud_prematricula_id,
            curso_id,
            created_at
        )
        VALUES (p_solicitud_id, v_curso_id, NOW());
    END LOOP;

    p_es_valido := TRUE;
    p_mensaje := 'Pre matrícula enviada exitosamente.';
END;
$$;
```

### 4.3 Consolidar Demanda y Calcular Proyección Histórica
```sql
CREATE OR REPLACE FUNCTION prematricula.fn_consolidar_demanda_y_proyeccion(
    p_periodo_id INT
)
RETURNS TABLE (
    curso_id INT,
    codigo_curso VARCHAR,
    nombre_curso VARCHAR,
    demanda_prematricula INT,
    proyeccion_historica INT,
    demanda_estimada_total INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    WITH demanda_real AS (
        SELECT 
            ds.curso_id,
            COUNT(DISTINCT sp.estudiante_id) AS cantidad_demanda
        FROM prematricula.solicitud_prematricula sp
        JOIN prematricula.detalle_solicitud ds ON sp.id = ds.solicitud_prematricula_id
        WHERE sp.periodo_academico_id = p_periodo_id
        GROUP BY ds.curso_id
    ),
    promedio_historico AS (
        SELECT 
            sec.curso_id,
            COALESCE(ROUND(AVG(conteo.cant)), 0) AS promedio_alumnos
        FROM (
            SELECT s.curso_id, m.periodo_academico_id, COUNT(md.id) AS cant
            FROM gestion_matriculas.matricula_detalle md
            JOIN gestion_matriculas.matricula m ON md.matricula_id = m.id
            JOIN core.seccion s ON md.seccion_id = s.id
            GROUP BY s.curso_id, m.periodo_academico_id
        ) conteo
        GROUP BY sec.curso_id
    )
    SELECT 
        c.id AS curso_id,
        c.codigo AS codigo_curso,
        c.nombre AS nombre_curso,
        COALESCE(dr.cantidad_demanda, 0)::INT AS demanda_prematricula,
        COALESCE(ph.promedio_alumnos, 0)::INT AS proyeccion_historica,
        GREATEST(COALESCE(dr.cantidad_demanda, 0), COALESCE(ph.promedio_alumnos, 0))::INT AS demanda_estimada_total
    FROM core.curso c
    LEFT JOIN demanda_real dr ON c.id = dr.curso_id
    LEFT JOIN promedio_historico ph ON c.id = ph.curso_id
    WHERE dr.cantidad_demanda IS NOT NULL OR ph.promedio_alumnos IS NOT NULL;
END;
$$;
```

### 4.4 Determinar Secciones Necesarias
```sql
CREATE OR REPLACE FUNCTION prematricula.fn_determinar_secciones_necesarias(
    p_periodo_id INT,
    p_capacidad_max_seccion INT DEFAULT 40
)
RETURNS TABLE (
    curso_id INT,
    codigo_curso VARCHAR,
    nombre_curso VARCHAR,
    demanda_total INT,
    secciones_sugeridas INT,
    alumnos_por_seccion NUMERIC
)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT 
        dp.curso_id,
        dp.codigo_curso,
        dp.nombre_curso,
        dp.demanda_estimada_total AS demanda_total,
        CEIL(dp.demanda_estimada_total::NUMERIC / p_capacidad_max_seccion)::INT AS secciones_sugeridas,
        ROUND(dp.demanda_estimada_total::NUMERIC / GREATEST(CEIL(dp.demanda_estimada_total::NUMERIC / p_capacidad_max_seccion), 1), 2) AS alumnos_por_seccion
    FROM prematricula.fn_consolidar_demanda_y_proyeccion(p_periodo_id) dp
    ORDER BY dp.demanda_estimada_total DESC;
END;
$$;
```

---

## 5. Esquema `gestion_matriculas` — Matrícula No Regular

### 5.1 Solicitar Matrícula No Regular
```sql
CREATE OR REPLACE PROCEDURE gestion_matriculas.sp_registrar_solicitud_no_regular(
    p_estudiante_id INT,
    p_periodo_id INT,
    p_motivo TEXT,
    p_seccion_id INT,
    p_archivo_id INT,
    OUT p_solicitud_id INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO gestion_matriculas.reserva_matricula (
        estudiante_id,
        periodo_academico_id,
        motivo,
        estado,
        fecha_solicitud,
        created_at
    )
    VALUES (
        p_estudiante_id,
        p_periodo_id,
        p_motivo,
        'ENVIADA',
        NOW(),
        NOW()
    )
    RETURNING id INTO p_solicitud_id;

    IF p_archivo_id IS NOT NULL THEN
        INSERT INTO comun.archivo (
            id,
            nombre_original,
            mime_type,
            created_at
        )
        VALUES (
            p_archivo_id,
            'Adjunto_Matricula_No_Regular_' || p_solicitud_id,
            'application/pdf',
            NOW()
        )
        ON CONFLICT (id) DO NOTHING;
    END IF;

    INSERT INTO comun.notificacion (
        usuario_id,
        titulo,
        mensaje,
        estado,
        created_at
    )
    SELECT 
        u.id,
        'Nueva Solicitud de Matrícula No Regular',
        'El estudiante ' || p_estudiante_id || ' ha enviado una solicitud de matrícula no regular.',
        'PENDIENTE',
        NOW()
    FROM seguridad.usuario u
    WHERE u.rol = 'ADMINISTRACION';
END;
$$;
```

### 5.2 Evaluar Documentación (Administración)
```sql
CREATE OR REPLACE PROCEDURE gestion_matriculas.sp_evaluar_documentacion_no_regular(
    p_solicitud_id INT,
    p_documentacion_correcta BOOLEAN,
    p_observaciones TEXT,
    p_administrador_id INT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_estudiante_id INT;
BEGIN
    SELECT estudiante_id INTO v_estudiante_id 
    FROM gestion_matriculas.reserva_matricula 
    WHERE id = p_solicitud_id;

    IF NOT p_documentacion_correcta THEN
        UPDATE gestion_matriculas.reserva_matricula
        SET estado = 'OBSERVADA_DOCUMENTACION',
            observacion = p_observaciones,
            updated_at = NOW()
        WHERE id = p_solicitud_id;

        INSERT INTO comun.notificacion (
            usuario_id,
            titulo,
            mensaje,
            estado,
            created_at
        )
        VALUES (
            v_estudiante_id,
            'Observación en Solicitud de Matrícula No Regular',
            'Su solicitud requiere corrección de documentos: ' || p_observaciones,
            'PENDIENTE',
            NOW()
        );
    ELSE
        UPDATE gestion_matriculas.reserva_matricula
        SET estado = 'ENVIADA_A_DOCENTE',
            updated_at = NOW()
        WHERE id = p_solicitud_id;
    END IF;
END;
$$;
```

### 5.3 Evaluar Recomendación (Docente)
```sql
CREATE OR REPLACE PROCEDURE gestion_matriculas.sp_evaluar_recomendacion_docente(
    p_solicitud_id INT,
    p_docente_id INT,
    p_recomendacion_favorable BOOLEAN,
    p_informe_academico TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_estudiante_id INT;
BEGIN
    SELECT estudiante_id INTO v_estudiante_id 
    FROM gestion_matriculas.reserva_matricula 
    WHERE id = p_solicitud_id;

    IF p_recomendacion_favorable THEN
        UPDATE gestion_matriculas.reserva_matricula
        SET estado = 'RECOMENDACION_FAVORABLE',
            observacion = p_informe_academico,
            updated_at = NOW()
        WHERE id = p_solicitud_id;
    ELSE
        UPDATE gestion_matriculas.reserva_matricula
        SET estado = 'RECHAZADA_POR_DOCENTE',
            observacion = p_informe_academico,
            updated_at = NOW()
        WHERE id = p_solicitud_id;

        INSERT INTO comun.notificacion (
            usuario_id,
            titulo,
            mensaje,
            estado,
            created_at
        )
        VALUES (
            v_estudiante_id,
            'Recomendación Académica Desfavorable',
            'El docente ha emitido opinión desfavorable sobre su matrícula no regular: ' || p_informe_academico,
            'PENDIENTE',
            NOW()
        );
    END IF;
END;
$$;
```

### 5.4 Procesar Cupos, Autorización y Registro Final (Administración)
```sql
CREATE OR REPLACE PROCEDURE gestion_matriculas.sp_procesar_matricula_no_regular_final(
    p_solicitud_id INT,
    p_seccion_id INT,
    p_administrador_id INT,
    OUT p_matriculada BOOLEAN,
    OUT p_mensaje TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_estudiante_id INT;
    v_periodo_id INT;
    v_cupo_maximo INT;
    v_cupos_ocupados INT;
    v_matricula_id INT;
BEGIN
    SELECT estudiante_id, periodo_academico_id INTO v_estudiante_id, v_periodo_id 
    FROM gestion_matriculas.reserva_matricula 
    WHERE id = p_solicitud_id;

    SELECT capacidad INTO v_cupo_maximo 
    FROM core.seccion 
    WHERE id = p_seccion_id;

    SELECT COUNT(*) INTO v_cupos_ocupados 
    FROM gestion_matriculas.matricula_detalle 
    WHERE seccion_id = p_seccion_id;

    IF (v_cupo_maximo - v_cupos_ocupados) <= 0 THEN
        UPDATE gestion_matriculas.reserva_matricula
        SET estado = 'RECHAZADA_SIN_CUPOS', updated_at = NOW()
        WHERE id = p_solicitud_id;

        INSERT INTO comun.notificacion (
            usuario_id,
            titulo,
            mensaje,
            estado,
            created_at
        )
        VALUES (
            v_estudiante_id,
            'Falta de Cupos para Matrícula No Regular',
            'No fue posible matricularlo debido a que la sección seleccionada no cuenta con cupos disponibles.',
            'PENDIENTE',
            NOW()
        );

        p_matriculada := FALSE;
        p_mensaje := 'No hay cupos disponibles en la sección indicada.';
        RETURN;
    END IF;

    INSERT INTO gestion_matriculas.matricula (
        estudiante_id,
        periodo_academico_id,
        tipo_matricula,
        estado,
        fecha_matricula,
        created_at
    )
    VALUES (
        v_estudiante_id,
        v_periodo_id,
        'NO_REGULAR',
        'COMPLETADA',
        NOW(),
        NOW()
    )
    RETURNING id INTO v_matricula_id;

    INSERT INTO gestion_matriculas.matricula_detalle (
        matricula_id,
        estudiante_id,
        seccion_id,
        created_at
    )
    VALUES (
        v_matricula_id,
        v_estudiante_id,
        p_seccion_id,
        NOW()
    );

    UPDATE gestion_matriculas.reserva_matricula
    SET estado = 'APROBADA_Y_MATRICULADA', updated_at = NOW()
    WHERE id = p_solicitud_id;

    INSERT INTO comun.notificacion (
        usuario_id,
        titulo,
        mensaje,
        estado,
        created_at
    )
    VALUES (
        v_estudiante_id,
        'Matrícula No Regular Aprobada',
        'Su matrícula no regular ha sido procesada y registrada exitosamente.',
        'PENDIENTE',
        NOW()
    );

    p_matriculada := TRUE;
    p_mensaje := 'Matrícula no regular registrada exitosamente.';
END;
$$;
```

### 5.5 Consultar Trazabilidad de Solicitudes No Regulares
```sql
CREATE OR REPLACE FUNCTION gestion_matriculas.fn_consultar_estado_no_regular(
    p_estudiante_id INT
)
RETURNS TABLE (
    solicitud_id INT,
    fecha_solicitud TIMESTAMP,
    motivo TEXT,
    estado_actual VARCHAR,
    observaciones TEXT,
    fecha_actualizacion TIMESTAMP
)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT 
        rm.id AS solicitud_id,
        rm.fecha_solicitud,
        rm.motivo,
        rm.estado AS estado_actual,
        rm.observacion AS observaciones,
        rm.updated_at AS fecha_actualizacion
    FROM gestion_matriculas.reserva_matricula rm
    WHERE rm.estudiante_id = p_estudiante_id
    ORDER BY rm.fecha_solicitud DESC;
END;
$$;
```