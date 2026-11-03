-- ============================================================
-- GESTIÓN DE HORARIOS — 04 COMENTARIOS DE CATÁLOGO
-- ============================================================
-- COMMENT ON TABLE / COMMENT ON COLUMN quedan almacenados en el
-- catálogo de PostgreSQL y se consultan en pgAdmin con:
--   SELECT obj_description('gestion_horarios.apertura_repeticion'::regclass);
--   SELECT column_name, col_description(c.oid, a.attnum)
--   FROM pg_attribute a JOIN pg_class c ON c.oid = a.attrelid
--   WHERE c.relname = 'apertura_repeticion' AND a.attnum > 0;
-- ============================================================

-- ------------------------------------------------------------
-- TABLA 12: apertura_repeticion
-- ------------------------------------------------------------
COMMENT ON TABLE gestion_horarios.apertura_repeticion
    IS 'Registra la solicitud y autorización de apertura de cursos de repetición. Complementa el procedimiento Bizagi y se relaciona con periodo_academico, curso_plan, seccion, docente y usuario.';

COMMENT ON COLUMN gestion_horarios.apertura_repeticion.id_periodo
    IS 'Clave foránea a core.periodo_academico. Período al que pertenece la solicitud de repetición.';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.id_curso_plan
    IS 'Clave foránea a core.curso_plan. Curso cuya apertura de repetición se solicita.';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.id_seccion
    IS 'Referencia opcional a core.seccion. La clave compuesta (id_seccion, id_periodo, id_curso_plan) exige que la sección pertenezca al mismo período y curso que la solicitud.';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.id_docente_propuesto
    IS 'Referencia opcional a core.docente. Docente propuesto para la repetición; obligatorio cuando la solicitud pasa a INCORPORADA.';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.estado
    IS 'Ciclo de vida: SOLICITADA, AUTORIZADA, DENEGADA, INCORPORADA. Una solicitud DENEGADA no puede quedar INCORPORADA y sin autorización previa no se incorpora (disparador fn_transicion_apertura).';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.solicitado_por
    IS 'Clave foránea a seguridad.usuario. Usuario que presenta la solicitud; se preserva para trazabilidad.';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.autorizado_por
    IS 'Clave foránea opcional a seguridad.usuario. Usuario que autoriza o deniega la apertura; obligatorio en estados resueltos.';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.solicitado_en
    IS 'Fecha y hora de creación de la solicitud; inmutable una vez registrada.';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.resuelto_en
    IS 'Fecha y hora en que se autorizó o denegó la solicitud; obligatoria en los estados AUTORIZADA, DENEGADA e INCORPORADA.';
COMMENT ON COLUMN gestion_horarios.apertura_repeticion.observacion
    IS 'Justificación o comentario del solicitante y del autorizante.';

-- ------------------------------------------------------------
-- TABLA 13: declaracion_disponibilidad
-- ------------------------------------------------------------
COMMENT ON TABLE gestion_horarios.declaracion_disponibilidad
    IS 'Registra si un docente presentó formalmente su disponibilidad para un periodo académico. Complementa disponibilidad_docente, que almacena los bloques disponibles.';

COMMENT ON COLUMN gestion_horarios.declaracion_disponibilidad.id_periodo
    IS 'Clave foránea a core.periodo_academico. Período académico de la declaración.';
COMMENT ON COLUMN gestion_horarios.declaracion_disponibilidad.id_docente
    IS 'Clave foránea a core.docente. Docente que declara su disponibilidad.';
COMMENT ON COLUMN gestion_horarios.declaracion_disponibilidad.estado
    IS 'PENDIENTE (aún no presentada), PRESENTADA o NO_PRESENTADA. Único por (id_periodo, id_docente) mediante restricción UNIQUE.';
COMMENT ON COLUMN gestion_horarios.declaracion_disponibilidad.tratamiento_regular
    IS 'Política aplicable a los cursos regulares: ESTRICTA (sin flexibilidad) o FLEXIBLE. No condiciona por sí sola la disponibilidad obligatoria de una repetición, que se comprueba por actividad o sección.';
COMMENT ON COLUMN gestion_horarios.declaracion_disponibilidad.presentado_en
    IS 'Fecha y hora de presentación formal; obligatoria cuando el estado es PRESENTADA.';
COMMENT ON COLUMN gestion_horarios.declaracion_disponibilidad.id_registrado_por
    IS 'Referencia opcional a seguridad.usuario. Usuario que registró la declaración en el sistema.';
COMMENT ON COLUMN gestion_horarios.declaracion_disponibilidad.observacion
    IS 'Observaciones administrativas sobre la declaración.';

-- ------------------------------------------------------------
-- TABLA 14: validacion_horario
-- ------------------------------------------------------------
COMMENT ON TABLE gestion_horarios.validacion_horario
    IS 'Registra cada validación realizada sobre una versión de horario, su resultado y las reglas aplicadas. Permite verificar si una propuesta es apta para revisión.';

COMMENT ON COLUMN gestion_horarios.validacion_horario.id_validacion
    IS 'Clave primaria de la ejecución de validación; es el destino de conflicto_horario.id_validacion y se conserva como evidencia histórica.';
COMMENT ON COLUMN gestion_horarios.validacion_horario.id_horario
    IS 'Clave foránea a gestion_horarios.horario. Versión de horario evaluada; una versión puede tener muchas validaciones históricas.';
COMMENT ON COLUMN gestion_horarios.validacion_horario.resultado
    IS 'VALIDA o INVALIDA. No puede quedar VALIDA si la validación acumula conflictos con severidad DURA.';
COMMENT ON COLUMN gestion_horarios.validacion_horario.validado_en
    IS 'Fecha y hora de ejecución de la validación; inmutable porque constituye evidencia histórica.';
COMMENT ON COLUMN gestion_horarios.validacion_horario.id_validado_por
    IS 'Referencia opcional a seguridad.usuario. Usuario o proceso que ejecutó la validación.';
COMMENT ON COLUMN gestion_horarios.validacion_horario.version_reglas
    IS 'Identificador de la versión del motor de reglas aplicada (por ejemplo v1.0.0); obligatorio para poder reproducir la evaluación.';
COMMENT ON COLUMN gestion_horarios.validacion_horario.huella_propuesta
    IS 'Huella md5 determinista del contenido del horario al validarlo, calculada en servidor por gestion_horarios.fn_huella_horario a partir de detalle_horario, actividad_academica y la asignación docente activa. Si el contenido cambia, la huella deja de coincidir y la validación deja de ser vigente para aprobar o publicar.';
COMMENT ON COLUMN gestion_horarios.validacion_horario.observacion
    IS 'Detalle libre del resultado de la validación.';

-- ------------------------------------------------------------
-- TABLA 15: conflicto_horario
-- ------------------------------------------------------------
COMMENT ON TABLE gestion_horarios.conflicto_horario
    IS 'Registra los conflictos detectados durante una validación de horario. Se relaciona con validacion_horario y opcionalmente con actividades, docentes y ambientes afectados.';

COMMENT ON COLUMN gestion_horarios.conflicto_horario.id_validacion
    IS 'Clave foránea a validacion_horario. Identifica la ejecución que detectó este conflicto. Es obligatoria: no se admiten conflictos huérfanos.';
COMMENT ON COLUMN gestion_horarios.conflicto_horario.tipo
    IS 'Naturaleza del conflicto: CRUCE_DOCENTE, CRUCE_AMBIENTE, CAPACIDAD_INSUFICIENTE, AMBIENTE_INCOMPATIBLE, FUERA_DISPONIBILIDAD, AMBIENTE_BLOQUEADO, HORAS_INCOMPLETAS, PERIODO_INCOMPATIBLE, APERTURA_NO_AUTORIZADA u OTRO.';
COMMENT ON COLUMN gestion_horarios.conflicto_horario.severidad
    IS 'DURA impide declarar VALIDA la validación y, por tanto, aprobar la versión; BLANDA sólo genera observación.';
COMMENT ON COLUMN gestion_horarios.conflicto_horario.id_detalle_a
    IS 'Referencia opcional a gestion_horarios.detalle_horario. Uno de los detalles programados implicados; debe pertenecer al horario de la validación.';
COMMENT ON COLUMN gestion_horarios.conflicto_horario.id_detalle_b
    IS 'Referencia opcional a gestion_horarios.detalle_horario. Segundo detalle implicado; opcional porque un conflicto también puede afectar a un docente o ambiente sin dos actividades.';
COMMENT ON COLUMN gestion_horarios.conflicto_horario.id_docente
    IS 'Referencia opcional a core.docente. Docente afectado por el conflicto.';
COMMENT ON COLUMN gestion_horarios.conflicto_horario.id_ambiente
    IS 'Referencia opcional a core.ambiente. Ambiente afectado por el conflicto.';
COMMENT ON COLUMN gestion_horarios.conflicto_horario.descripcion
    IS 'Descripción legible del conflicto detectado; se conserva como evidencia histórica aunque la propuesta se regenere.';

-- ------------------------------------------------------------
-- Complemento sobre tablas y columnas originales (11)
-- ------------------------------------------------------------
COMMENT ON TABLE gestion_horarios.oferta_curso
    IS 'Oferta de un curso en un período (REGULAR o REPETICION); tabla original del modelo modular.';
COMMENT ON TABLE gestion_horarios.actividad_academica
    IS 'Sesión programable de una sección: tipo, día y rango horario; tabla original del modelo modular.';
COMMENT ON TABLE gestion_horarios.asignacion_docente
    IS 'Docente asignado a una actividad con historial de estados; admite trazabilidad a la apertura de repetición que lo autorizó.';
COMMENT ON TABLE gestion_horarios.bloque_horario
    IS 'Bloque de la grilla horaria de un período; tabla original del modelo modular.';
COMMENT ON TABLE gestion_horarios.disponibilidad_docente
    IS 'Bloques de tiempo disponibles de un docente; la declaración formal vive en declaracion_disponibilidad y se enlaza mediante id_declaracion.';
COMMENT ON TABLE gestion_horarios.ambiente_bloqueo
    IS 'Bloqueos de aulas por fechas u horarios; alimenta la regla AMBIENTE_BLOQUEADO de la validación.';
COMMENT ON TABLE gestion_horarios.horario
    IS 'Versión de un horario por período y ámbito, con ciclo de vida BORRADOR, GENERADO, VALIDADO, EN_REVISION, APROBADO, PUBLICADO y ARCHIVADO.';
COMMENT ON TABLE gestion_horarios.detalle_horario
    IS 'Incorporación de una actividad a una versión de horario; UNIQUE (id_horario, id_actividad) es necesaria pero no suficiente para evitar cruces.';
COMMENT ON TABLE gestion_horarios.restriccion
    IS 'Catálogo de reglas evaluables con severidad DURA o BLANDA; sus tipos coinciden con los de conflicto_horario.';
COMMENT ON TABLE gestion_horarios.detalle_restriccion
    IS 'Parámetros y ámbito de aplicación de una restricción.';
COMMENT ON TABLE gestion_horarios.horario_revision
    IS 'Revisión formal de una versión de horario; conserva evidencia histórica.';

COMMENT ON COLUMN gestion_horarios.horario.estado
    IS 'Ciclo de vida de la versión. Sólo una versión por (id_periodo, ambito) puede estar PUBLICADA (índice único parcial uq_horario_publicado_vigente).';
COMMENT ON COLUMN gestion_horarios.horario.version
    IS 'Número de versión dentro del período y ámbito; inmutable tras su creación.';
COMMENT ON COLUMN gestion_horarios.horario.id_aprobado_por
    IS 'Clave foránea a seguridad.usuario. Usuario que aprobó la versión; obligatorio en APROBADO y PUBLICADO.';
COMMENT ON COLUMN gestion_horarios.detalle_horario.id_actividad
    IS 'Clave foránea a gestion_horarios.actividad_academica. La sección de la actividad debe pertenecer al período del horario.';
COMMENT ON COLUMN gestion_horarios.disponibilidad_docente.id_declaracion
    IS 'Clave foránea compuesta a declaracion_disponibilidad junto con id_periodo e id_docente: los bloques deben corresponder a la declaración de ese docente en ese período.';
