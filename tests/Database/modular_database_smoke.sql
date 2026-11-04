\set ON_ERROR_STOP on

DO $$
DECLARE
    expected RECORD;
    actual_count INTEGER;
BEGIN
    FOR expected IN
        SELECT * FROM (VALUES
            ('prematricula', 3),
            ('gestion_matriculas', 9),
            ('cotecu', 10),
            ('gestion_horarios', 15),
            ('gestion_practicas', 11),
            ('calidad', 12),
            ('tesis', 22)
        ) AS counts(schema_name, table_count)
    LOOP
        SELECT count(*) INTO actual_count
        FROM information_schema.tables
        WHERE table_schema = expected.schema_name
          AND table_type = 'BASE TABLE';

        IF actual_count <> expected.table_count THEN
            RAISE EXCEPTION 'Conteo inválido en %: esperado %, actual %',
                expected.schema_name, expected.table_count, actual_count;
        END IF;
    END LOOP;
END
$$;

DO $$
DECLARE
    invalid_count INTEGER;
BEGIN
    SELECT count(*) INTO invalid_count
    FROM pg_constraint c
    JOIN pg_class child ON child.oid = c.conrelid
    JOIN pg_namespace child_ns ON child_ns.oid = child.relnamespace
    JOIN pg_class parent ON parent.oid = c.confrelid
    JOIN pg_namespace parent_ns ON parent_ns.oid = parent.relnamespace
    WHERE c.contype = 'f'
      AND child_ns.nspname IN (
          'prematricula', 'gestion_matriculas', 'cotecu',
          'gestion_horarios', 'gestion_practicas', 'calidad', 'tesis'
      )
      AND parent_ns.nspname <> child_ns.nspname
      AND parent_ns.nspname NOT IN ('core', 'seguridad', 'comun', 'public');

    IF invalid_count <> 0 THEN
        RAISE EXCEPTION 'Existen % FK entre módulos', invalid_count;
    END IF;
END
$$;

DO $$
BEGIN
    IF gestion_matriculas.calcular_creditos_matriculados(-1, -1) <> 0 THEN
        RAISE EXCEPTION 'calcular_creditos_matriculados no devolvió cero sin filas';
    END IF;

    IF gestion_practicas.calcular_nota_final(-1) IS NOT NULL THEN
        RAISE EXCEPTION 'calcular_nota_final debía devolver NULL sin filas';
    END IF;

    IF calidad.calcular_valor_indicador(-1) IS NOT NULL THEN
        RAISE EXCEPTION 'calcular_valor_indicador debía devolver NULL sin filas';
    END IF;

    IF tesis.calcular_nota_sustentacion(-1) IS NOT NULL THEN
        RAISE EXCEPTION 'calcular_nota_sustentacion debía devolver NULL sin filas';
    END IF;
END
$$;

SELECT 'modular_database_smoke: OK' AS resultado;
