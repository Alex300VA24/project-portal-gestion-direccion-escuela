--
-- PostgreSQL database dump
--

\restrict tGHYV6JZQJctLTdIBBHeZvHmoUNAtY226ae6p4z9hx2ejsSy8ss2d6BqKa97Zuy

-- Dumped from database version 17.10
-- Dumped by pg_dump version 17.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: core; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA core;


--
-- Name: gestion_matriculas; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA gestion_matriculas;


--
-- Name: prematricula; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA prematricula;


--
-- Name: seguridad; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA seguridad;


--
-- Name: validar_curso_prerequisito_mismo_plan(); Type: FUNCTION; Schema: core; Owner: -
--

CREATE FUNCTION core.validar_curso_prerequisito_mismo_plan() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    plan_curso BIGINT;
    plan_requisito BIGINT;
BEGIN
    SELECT id_plan INTO plan_curso
    FROM core.curso_plan
    WHERE id_curso_plan = NEW.id_curso_plan;

    SELECT id_plan INTO plan_requisito
    FROM core.curso_plan
    WHERE id_curso_plan = NEW.id_curso_plan_requisito;

    IF plan_curso IS DISTINCT FROM plan_requisito THEN
        RAISE EXCEPTION 'El curso y su prerrequisito deben pertenecer al mismo plan de estudios'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: area_formacion; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.area_formacion (
    id_area bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    nombre character varying(120) NOT NULL
);


--
-- Name: area_formacion_id_area_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.area_formacion_id_area_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: area_formacion_id_area_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.area_formacion_id_area_seq OWNED BY core.area_formacion.id_area;


--
-- Name: curso; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.curso (
    id_curso bigint NOT NULL,
    codigo character varying(40) NOT NULL,
    nombre character varying(180) NOT NULL,
    creditos numeric(5,2) NOT NULL,
    horas_teoria smallint DEFAULT '0'::smallint NOT NULL,
    horas_practica smallint DEFAULT '0'::smallint NOT NULL,
    horas_laboratorio smallint DEFAULT '0'::smallint NOT NULL,
    sumilla text,
    estado character varying(30) NOT NULL,
    CONSTRAINT core_curso_horas_creditos_ck CHECK (((creditos >= (0)::numeric) AND (horas_teoria >= 0) AND (horas_practica >= 0) AND (horas_laboratorio >= 0)))
);


--
-- Name: curso_id_curso_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.curso_id_curso_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: curso_id_curso_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.curso_id_curso_seq OWNED BY core.curso.id_curso;


--
-- Name: curso_plan; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.curso_plan (
    id_curso_plan bigint NOT NULL,
    ciclo smallint NOT NULL,
    id_plan bigint NOT NULL,
    id_curso bigint NOT NULL,
    tipo character varying(30) NOT NULL,
    id_area bigint NOT NULL,
    creditos_requeridos numeric(5,2),
    CONSTRAINT core_curso_plan_ciclo_ck CHECK (((ciclo IS NULL) OR (ciclo > 0)))
);


--
-- Name: curso_plan_id_curso_plan_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.curso_plan_id_curso_plan_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: curso_plan_id_curso_plan_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.curso_plan_id_curso_plan_seq OWNED BY core.curso_plan.id_curso_plan;


--
-- Name: curso_prerequisito; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.curso_prerequisito (
    id_curso_plan bigint NOT NULL,
    id_curso_plan_requisito bigint NOT NULL,
    CONSTRAINT core_curso_prerequisito_distinto_ck CHECK ((id_curso_plan <> id_curso_plan_requisito))
);


--
-- Name: departamento_academico; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.departamento_academico (
    id_departamento bigint NOT NULL,
    id_facultad bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    nombre character varying(160) NOT NULL
);


--
-- Name: departamento_academico_id_departamento_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.departamento_academico_id_departamento_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: departamento_academico_id_departamento_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.departamento_academico_id_departamento_seq OWNED BY core.departamento_academico.id_departamento;


--
-- Name: docente; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.docente (
    id_docente bigint NOT NULL,
    id_persona bigint NOT NULL,
    codigo_docente character varying(30) NOT NULL,
    id_departamento bigint NOT NULL,
    categoria character varying(50),
    condicion character varying(50),
    dedicacion character varying(50),
    grado_academico character varying(80),
    horas_maximas smallint,
    orcid character varying(30),
    codigo_renacyt character varying(40),
    estado character varying(30) NOT NULL
);


--
-- Name: docente_id_docente_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.docente_id_docente_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: docente_id_docente_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.docente_id_docente_seq OWNED BY core.docente.id_docente;


--
-- Name: escuela_profesional; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.escuela_profesional (
    id_escuela bigint NOT NULL,
    id_facultad bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    nombre character varying(160) NOT NULL,
    estado character varying(30) NOT NULL
);


--
-- Name: escuela_profesional_id_escuela_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.escuela_profesional_id_escuela_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: escuela_profesional_id_escuela_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.escuela_profesional_id_escuela_seq OWNED BY core.escuela_profesional.id_escuela;


--
-- Name: estudiante; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.estudiante (
    id_estudiante bigint NOT NULL,
    codigo_universitario character varying(30) NOT NULL,
    estado character varying(30) NOT NULL,
    id_persona bigint NOT NULL,
    id_escuela bigint NOT NULL,
    id_plan bigint NOT NULL,
    id_periodo_ingreso bigint NOT NULL,
    id_modalidad_ingreso bigint NOT NULL,
    fecha_egreso date
);


--
-- Name: estudiante_id_estudiante_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.estudiante_id_estudiante_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: estudiante_id_estudiante_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.estudiante_id_estudiante_seq OWNED BY core.estudiante.id_estudiante;


--
-- Name: facultad; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.facultad (
    id_facultad bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    nombre character varying(160) NOT NULL
);


--
-- Name: facultad_id_facultad_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.facultad_id_facultad_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: facultad_id_facultad_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.facultad_id_facultad_seq OWNED BY core.facultad.id_facultad;


--
-- Name: modalidad_ingreso; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.modalidad_ingreso (
    id_modalidad bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    nombre character varying(120) NOT NULL,
    descripcion text,
    activo boolean DEFAULT true NOT NULL
);


--
-- Name: modalidad_ingreso_id_modalidad_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.modalidad_ingreso_id_modalidad_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modalidad_ingreso_id_modalidad_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.modalidad_ingreso_id_modalidad_seq OWNED BY core.modalidad_ingreso.id_modalidad;


--
-- Name: pais; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.pais (
    id_pais bigint NOT NULL,
    nombre character varying(120) NOT NULL
);


--
-- Name: pais_id_pais_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.pais_id_pais_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: pais_id_pais_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.pais_id_pais_seq OWNED BY core.pais.id_pais;


--
-- Name: periodo_academico; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.periodo_academico (
    id_periodo bigint NOT NULL,
    codigo character varying(20) NOT NULL,
    fecha_inicio date NOT NULL,
    fecha_fin date NOT NULL,
    estado character varying(30) NOT NULL,
    anio smallint NOT NULL,
    tipo character varying(20) NOT NULL,
    numero_semanas smallint NOT NULL,
    CONSTRAINT core_periodo_fechas_ck CHECK ((fecha_fin >= fecha_inicio))
);


--
-- Name: periodo_academico_id_periodo_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.periodo_academico_id_periodo_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: periodo_academico_id_periodo_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.periodo_academico_id_periodo_seq OWNED BY core.periodo_academico.id_periodo;


--
-- Name: persona; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.persona (
    id_persona bigint NOT NULL,
    tipo_documento character varying(20) NOT NULL,
    numero_documento character varying(30) NOT NULL,
    nombres character varying(120) NOT NULL,
    apellido_paterno character varying(80) NOT NULL,
    apellido_materno character varying(80),
    sexo character varying(20),
    fecha_nacimiento date,
    correo_personal character varying(190),
    telefono character varying(30),
    id_pais_nacionalidad bigint,
    id_distrito_domicilio bigint,
    direccion text,
    creado_en timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: persona_id_persona_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.persona_id_persona_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: persona_id_persona_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.persona_id_persona_seq OWNED BY core.persona.id_persona;


--
-- Name: plan_estudios; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.plan_estudios (
    id_plan bigint NOT NULL,
    id_escuela bigint NOT NULL,
    codigo character varying(40) NOT NULL,
    nombre character varying(160) NOT NULL,
    version character varying(30) NOT NULL,
    anio_aprobacion smallint NOT NULL,
    resolucion_aprobacion character varying(100),
    fecha_vigencia_inicio date NOT NULL,
    fecha_vigencia_fin date,
    es_predeterminado boolean DEFAULT false NOT NULL,
    estado character varying(30) NOT NULL
);


--
-- Name: plan_estudios_id_plan_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.plan_estudios_id_plan_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: plan_estudios_id_plan_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.plan_estudios_id_plan_seq OWNED BY core.plan_estudios.id_plan;


--
-- Name: seccion; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.seccion (
    id_seccion bigint NOT NULL,
    id_periodo bigint NOT NULL,
    id_curso_plan bigint NOT NULL,
    codigo character varying(30) NOT NULL,
    capacidad integer,
    estado character varying(30) NOT NULL,
    id_docente_responsable bigint,
    CONSTRAINT core_seccion_capacidad_ck CHECK (((capacidad IS NULL) OR (capacidad >= 0)))
);


--
-- Name: seccion_id_seccion_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.seccion_id_seccion_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: seccion_id_seccion_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.seccion_id_seccion_seq OWNED BY core.seccion.id_seccion;


--
-- Name: ubigeo_departamento; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.ubigeo_departamento (
    id_departamento bigint NOT NULL,
    nombre character varying(120) NOT NULL
);


--
-- Name: ubigeo_departamento_id_departamento_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.ubigeo_departamento_id_departamento_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ubigeo_departamento_id_departamento_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.ubigeo_departamento_id_departamento_seq OWNED BY core.ubigeo_departamento.id_departamento;


--
-- Name: ubigeo_distrito; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.ubigeo_distrito (
    id_distrito bigint NOT NULL,
    id_provincia bigint NOT NULL,
    nombre character varying(120) NOT NULL
);


--
-- Name: ubigeo_distrito_id_distrito_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.ubigeo_distrito_id_distrito_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ubigeo_distrito_id_distrito_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.ubigeo_distrito_id_distrito_seq OWNED BY core.ubigeo_distrito.id_distrito;


--
-- Name: ubigeo_provincia; Type: TABLE; Schema: core; Owner: -
--

CREATE TABLE core.ubigeo_provincia (
    id_provincia bigint NOT NULL,
    id_departamento bigint NOT NULL,
    nombre character varying(120) NOT NULL
);


--
-- Name: ubigeo_provincia_id_provincia_seq; Type: SEQUENCE; Schema: core; Owner: -
--

CREATE SEQUENCE core.ubigeo_provincia_id_provincia_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ubigeo_provincia_id_provincia_seq; Type: SEQUENCE OWNED BY; Schema: core; Owner: -
--

ALTER SEQUENCE core.ubigeo_provincia_id_provincia_seq OWNED BY core.ubigeo_provincia.id_provincia;


--
-- Name: acta_notas; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.acta_notas (
    id_acta bigint NOT NULL,
    id_seccion bigint NOT NULL,
    estado character varying(30) NOT NULL,
    cerrada_por bigint,
    cerrada_en timestamp(0) with time zone
);


--
-- Name: acta_notas_id_acta_seq; Type: SEQUENCE; Schema: gestion_matriculas; Owner: -
--

CREATE SEQUENCE gestion_matriculas.acta_notas_id_acta_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: acta_notas_id_acta_seq; Type: SEQUENCE OWNED BY; Schema: gestion_matriculas; Owner: -
--

ALTER SEQUENCE gestion_matriculas.acta_notas_id_acta_seq OWNED BY gestion_matriculas.acta_notas.id_acta;


--
-- Name: asistencia; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.asistencia (
    id_detalle bigint NOT NULL,
    fecha date NOT NULL,
    estado character varying(30) NOT NULL
);


--
-- Name: componente_evaluacion; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.componente_evaluacion (
    id_componente bigint NOT NULL,
    id_seccion bigint NOT NULL,
    nombre character varying(100) NOT NULL,
    peso numeric(5,2) NOT NULL,
    orden smallint NOT NULL,
    CONSTRAINT mat_comp_peso_orden_ck CHECK (((peso > (0)::numeric) AND (peso <= (100)::numeric) AND (orden > 0)))
);


--
-- Name: componente_evaluacion_id_componente_seq; Type: SEQUENCE; Schema: gestion_matriculas; Owner: -
--

CREATE SEQUENCE gestion_matriculas.componente_evaluacion_id_componente_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: componente_evaluacion_id_componente_seq; Type: SEQUENCE OWNED BY; Schema: gestion_matriculas; Owner: -
--

ALTER SEQUENCE gestion_matriculas.componente_evaluacion_id_componente_seq OWNED BY gestion_matriculas.componente_evaluacion.id_componente;


--
-- Name: cronograma_matricula; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.cronograma_matricula (
    id_cronograma bigint NOT NULL,
    id_periodo bigint NOT NULL,
    tipo character varying(30) NOT NULL,
    fecha_inicio timestamp(0) with time zone NOT NULL,
    fecha_fin timestamp(0) with time zone NOT NULL,
    CONSTRAINT mat_crono_fechas_ck CHECK ((fecha_fin > fecha_inicio))
);


--
-- Name: cronograma_matricula_id_cronograma_seq; Type: SEQUENCE; Schema: gestion_matriculas; Owner: -
--

CREATE SEQUENCE gestion_matriculas.cronograma_matricula_id_cronograma_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cronograma_matricula_id_cronograma_seq; Type: SEQUENCE OWNED BY; Schema: gestion_matriculas; Owner: -
--

ALTER SEQUENCE gestion_matriculas.cronograma_matricula_id_cronograma_seq OWNED BY gestion_matriculas.cronograma_matricula.id_cronograma;


--
-- Name: limite_creditos; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.limite_creditos (
    id_limite bigint NOT NULL,
    id_estudiante bigint NOT NULL,
    id_periodo bigint NOT NULL,
    creditos_maximos numeric(5,2) NOT NULL,
    motivo text,
    CONSTRAINT mat_lim_creditos_ck CHECK ((creditos_maximos >= (0)::numeric))
);


--
-- Name: limite_creditos_id_limite_seq; Type: SEQUENCE; Schema: gestion_matriculas; Owner: -
--

CREATE SEQUENCE gestion_matriculas.limite_creditos_id_limite_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: limite_creditos_id_limite_seq; Type: SEQUENCE OWNED BY; Schema: gestion_matriculas; Owner: -
--

ALTER SEQUENCE gestion_matriculas.limite_creditos_id_limite_seq OWNED BY gestion_matriculas.limite_creditos.id_limite;


--
-- Name: matricula; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.matricula (
    id_matricula bigint NOT NULL,
    id_estudiante bigint NOT NULL,
    id_periodo bigint NOT NULL,
    id_cronograma bigint NOT NULL,
    id_solicitud_prematricula bigint,
    estado character varying(30) NOT NULL,
    registrado_por bigint NOT NULL,
    registrada_en timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: matricula_detalle; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.matricula_detalle (
    id_detalle bigint NOT NULL,
    id_matricula bigint NOT NULL,
    id_seccion bigint NOT NULL,
    estado character varying(30) NOT NULL,
    nota_final numeric(5,2),
    retirado_en timestamp(0) with time zone,
    CONSTRAINT mat_det_nota_ck CHECK (((nota_final IS NULL) OR ((nota_final >= (0)::numeric) AND (nota_final <= (20)::numeric))))
);


--
-- Name: matricula_detalle_id_detalle_seq; Type: SEQUENCE; Schema: gestion_matriculas; Owner: -
--

CREATE SEQUENCE gestion_matriculas.matricula_detalle_id_detalle_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matricula_detalle_id_detalle_seq; Type: SEQUENCE OWNED BY; Schema: gestion_matriculas; Owner: -
--

ALTER SEQUENCE gestion_matriculas.matricula_detalle_id_detalle_seq OWNED BY gestion_matriculas.matricula_detalle.id_detalle;


--
-- Name: matricula_id_matricula_seq; Type: SEQUENCE; Schema: gestion_matriculas; Owner: -
--

CREATE SEQUENCE gestion_matriculas.matricula_id_matricula_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matricula_id_matricula_seq; Type: SEQUENCE OWNED BY; Schema: gestion_matriculas; Owner: -
--

ALTER SEQUENCE gestion_matriculas.matricula_id_matricula_seq OWNED BY gestion_matriculas.matricula.id_matricula;


--
-- Name: nota; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.nota (
    id_detalle bigint NOT NULL,
    id_componente bigint NOT NULL,
    valor numeric(5,2) NOT NULL,
    registrado_por bigint NOT NULL,
    registrado_en timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT mat_nota_valor_ck CHECK (((valor >= (0)::numeric) AND (valor <= (20)::numeric)))
);


--
-- Name: reserva_matricula; Type: TABLE; Schema: gestion_matriculas; Owner: -
--

CREATE TABLE gestion_matriculas.reserva_matricula (
    id_reserva bigint NOT NULL,
    id_estudiante bigint NOT NULL,
    id_periodo bigint NOT NULL,
    motivo text NOT NULL,
    resolucion character varying(100),
    registrada_en timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: reserva_matricula_id_reserva_seq; Type: SEQUENCE; Schema: gestion_matriculas; Owner: -
--

CREATE SEQUENCE gestion_matriculas.reserva_matricula_id_reserva_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reserva_matricula_id_reserva_seq; Type: SEQUENCE OWNED BY; Schema: gestion_matriculas; Owner: -
--

ALTER SEQUENCE gestion_matriculas.reserva_matricula_id_reserva_seq OWNED BY gestion_matriculas.reserva_matricula.id_reserva;


--
-- Name: v_historial_academico; Type: VIEW; Schema: gestion_matriculas; Owner: -
--

CREATE VIEW gestion_matriculas.v_historial_academico AS
 SELECT m.id_estudiante,
    s.id_periodo,
    cp.id_curso,
    cp.id_curso_plan,
    c.codigo AS codigo_curso,
    c.creditos,
    d.nota_final,
    d.estado,
        CASE
            WHEN ((d.estado)::text <> 'Matriculado'::text) THEN d.estado
            WHEN (d.nota_final IS NULL) THEN 'En curso'::character varying
            WHEN (d.nota_final >= 10.5) THEN 'Aprobado'::character varying
            ELSE 'Desaprobado'::character varying
        END AS condicion
   FROM ((((gestion_matriculas.matricula m
     JOIN gestion_matriculas.matricula_detalle d ON ((d.id_matricula = m.id_matricula)))
     JOIN core.seccion s ON ((s.id_seccion = d.id_seccion)))
     JOIN core.curso_plan cp ON ((cp.id_curso_plan = s.id_curso_plan)))
     JOIN core.curso c ON ((c.id_curso = cp.id_curso)))
  WHERE ((m.estado)::text <> 'Anulada'::text);


--
-- Name: cronograma_prematricula; Type: TABLE; Schema: prematricula; Owner: -
--

CREATE TABLE prematricula.cronograma_prematricula (
    id_cronograma bigint NOT NULL,
    id_periodo bigint NOT NULL,
    fecha_inicio timestamp(0) with time zone NOT NULL,
    fecha_fin timestamp(0) with time zone NOT NULL,
    estado character varying(30) NOT NULL,
    CONSTRAINT pre_crono_fechas_ck CHECK ((fecha_fin > fecha_inicio))
);


--
-- Name: cronograma_prematricula_id_cronograma_seq; Type: SEQUENCE; Schema: prematricula; Owner: -
--

CREATE SEQUENCE prematricula.cronograma_prematricula_id_cronograma_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cronograma_prematricula_id_cronograma_seq; Type: SEQUENCE OWNED BY; Schema: prematricula; Owner: -
--

ALTER SEQUENCE prematricula.cronograma_prematricula_id_cronograma_seq OWNED BY prematricula.cronograma_prematricula.id_cronograma;


--
-- Name: detalle_solicitud; Type: TABLE; Schema: prematricula; Owner: -
--

CREATE TABLE prematricula.detalle_solicitud (
    id_detalle bigint NOT NULL,
    id_solicitud bigint NOT NULL,
    id_curso_plan bigint NOT NULL,
    id_seccion_preferida bigint,
    estado character varying(30) NOT NULL,
    motivo_rechazo text
);


--
-- Name: detalle_solicitud_id_detalle_seq; Type: SEQUENCE; Schema: prematricula; Owner: -
--

CREATE SEQUENCE prematricula.detalle_solicitud_id_detalle_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: detalle_solicitud_id_detalle_seq; Type: SEQUENCE OWNED BY; Schema: prematricula; Owner: -
--

ALTER SEQUENCE prematricula.detalle_solicitud_id_detalle_seq OWNED BY prematricula.detalle_solicitud.id_detalle;


--
-- Name: solicitud_prematricula; Type: TABLE; Schema: prematricula; Owner: -
--

CREATE TABLE prematricula.solicitud_prematricula (
    id_solicitud bigint NOT NULL,
    id_estudiante bigint NOT NULL,
    id_periodo bigint NOT NULL,
    estado character varying(30) NOT NULL,
    observacion text,
    enviada_en timestamp(0) with time zone,
    revisado_por bigint,
    revisado_en timestamp(0) with time zone
);


--
-- Name: solicitud_prematricula_id_solicitud_seq; Type: SEQUENCE; Schema: prematricula; Owner: -
--

CREATE SEQUENCE prematricula.solicitud_prematricula_id_solicitud_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: solicitud_prematricula_id_solicitud_seq; Type: SEQUENCE OWNED BY; Schema: prematricula; Owner: -
--

ALTER SEQUENCE prematricula.solicitud_prematricula_id_solicitud_seq OWNED BY prematricula.solicitud_prematricula.id_solicitud;


--
-- Name: usuario; Type: TABLE; Schema: seguridad; Owner: -
--

CREATE TABLE seguridad.usuario (
    id_usuario bigint NOT NULL,
    correo_institucional character varying(190) NOT NULL,
    contrasena_hash character varying(255) NOT NULL,
    estado character varying(30) NOT NULL,
    remember_token character varying(100),
    creado_en timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    id_persona bigint NOT NULL,
    debe_cambiar_contrasena boolean DEFAULT true NOT NULL,
    ultimo_acceso timestamp(0) with time zone
);


--
-- Name: usuario_id_usuario_seq; Type: SEQUENCE; Schema: seguridad; Owner: -
--

CREATE SEQUENCE seguridad.usuario_id_usuario_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuario_id_usuario_seq; Type: SEQUENCE OWNED BY; Schema: seguridad; Owner: -
--

ALTER SEQUENCE seguridad.usuario_id_usuario_seq OWNED BY seguridad.usuario.id_usuario;


--
-- Name: area_formacion id_area; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.area_formacion ALTER COLUMN id_area SET DEFAULT nextval('core.area_formacion_id_area_seq'::regclass);


--
-- Name: curso id_curso; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso ALTER COLUMN id_curso SET DEFAULT nextval('core.curso_id_curso_seq'::regclass);


--
-- Name: curso_plan id_curso_plan; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_plan ALTER COLUMN id_curso_plan SET DEFAULT nextval('core.curso_plan_id_curso_plan_seq'::regclass);


--
-- Name: departamento_academico id_departamento; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.departamento_academico ALTER COLUMN id_departamento SET DEFAULT nextval('core.departamento_academico_id_departamento_seq'::regclass);


--
-- Name: docente id_docente; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.docente ALTER COLUMN id_docente SET DEFAULT nextval('core.docente_id_docente_seq'::regclass);


--
-- Name: escuela_profesional id_escuela; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.escuela_profesional ALTER COLUMN id_escuela SET DEFAULT nextval('core.escuela_profesional_id_escuela_seq'::regclass);


--
-- Name: estudiante id_estudiante; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante ALTER COLUMN id_estudiante SET DEFAULT nextval('core.estudiante_id_estudiante_seq'::regclass);


--
-- Name: facultad id_facultad; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.facultad ALTER COLUMN id_facultad SET DEFAULT nextval('core.facultad_id_facultad_seq'::regclass);


--
-- Name: modalidad_ingreso id_modalidad; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.modalidad_ingreso ALTER COLUMN id_modalidad SET DEFAULT nextval('core.modalidad_ingreso_id_modalidad_seq'::regclass);


--
-- Name: pais id_pais; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.pais ALTER COLUMN id_pais SET DEFAULT nextval('core.pais_id_pais_seq'::regclass);


--
-- Name: periodo_academico id_periodo; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.periodo_academico ALTER COLUMN id_periodo SET DEFAULT nextval('core.periodo_academico_id_periodo_seq'::regclass);


--
-- Name: persona id_persona; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.persona ALTER COLUMN id_persona SET DEFAULT nextval('core.persona_id_persona_seq'::regclass);


--
-- Name: plan_estudios id_plan; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.plan_estudios ALTER COLUMN id_plan SET DEFAULT nextval('core.plan_estudios_id_plan_seq'::regclass);


--
-- Name: seccion id_seccion; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.seccion ALTER COLUMN id_seccion SET DEFAULT nextval('core.seccion_id_seccion_seq'::regclass);


--
-- Name: ubigeo_departamento id_departamento; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_departamento ALTER COLUMN id_departamento SET DEFAULT nextval('core.ubigeo_departamento_id_departamento_seq'::regclass);


--
-- Name: ubigeo_distrito id_distrito; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_distrito ALTER COLUMN id_distrito SET DEFAULT nextval('core.ubigeo_distrito_id_distrito_seq'::regclass);


--
-- Name: ubigeo_provincia id_provincia; Type: DEFAULT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_provincia ALTER COLUMN id_provincia SET DEFAULT nextval('core.ubigeo_provincia_id_provincia_seq'::regclass);


--
-- Name: acta_notas id_acta; Type: DEFAULT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.acta_notas ALTER COLUMN id_acta SET DEFAULT nextval('gestion_matriculas.acta_notas_id_acta_seq'::regclass);


--
-- Name: componente_evaluacion id_componente; Type: DEFAULT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.componente_evaluacion ALTER COLUMN id_componente SET DEFAULT nextval('gestion_matriculas.componente_evaluacion_id_componente_seq'::regclass);


--
-- Name: cronograma_matricula id_cronograma; Type: DEFAULT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.cronograma_matricula ALTER COLUMN id_cronograma SET DEFAULT nextval('gestion_matriculas.cronograma_matricula_id_cronograma_seq'::regclass);


--
-- Name: limite_creditos id_limite; Type: DEFAULT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.limite_creditos ALTER COLUMN id_limite SET DEFAULT nextval('gestion_matriculas.limite_creditos_id_limite_seq'::regclass);


--
-- Name: matricula id_matricula; Type: DEFAULT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula ALTER COLUMN id_matricula SET DEFAULT nextval('gestion_matriculas.matricula_id_matricula_seq'::regclass);


--
-- Name: matricula_detalle id_detalle; Type: DEFAULT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula_detalle ALTER COLUMN id_detalle SET DEFAULT nextval('gestion_matriculas.matricula_detalle_id_detalle_seq'::regclass);


--
-- Name: reserva_matricula id_reserva; Type: DEFAULT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.reserva_matricula ALTER COLUMN id_reserva SET DEFAULT nextval('gestion_matriculas.reserva_matricula_id_reserva_seq'::regclass);


--
-- Name: cronograma_prematricula id_cronograma; Type: DEFAULT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.cronograma_prematricula ALTER COLUMN id_cronograma SET DEFAULT nextval('prematricula.cronograma_prematricula_id_cronograma_seq'::regclass);


--
-- Name: detalle_solicitud id_detalle; Type: DEFAULT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.detalle_solicitud ALTER COLUMN id_detalle SET DEFAULT nextval('prematricula.detalle_solicitud_id_detalle_seq'::regclass);


--
-- Name: solicitud_prematricula id_solicitud; Type: DEFAULT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.solicitud_prematricula ALTER COLUMN id_solicitud SET DEFAULT nextval('prematricula.solicitud_prematricula_id_solicitud_seq'::regclass);


--
-- Name: usuario id_usuario; Type: DEFAULT; Schema: seguridad; Owner: -
--

ALTER TABLE ONLY seguridad.usuario ALTER COLUMN id_usuario SET DEFAULT nextval('seguridad.usuario_id_usuario_seq'::regclass);


--
-- Name: area_formacion area_formacion_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.area_formacion
    ADD CONSTRAINT area_formacion_pkey PRIMARY KEY (id_area);


--
-- Name: area_formacion core_area_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.area_formacion
    ADD CONSTRAINT core_area_codigo_uq UNIQUE (codigo);


--
-- Name: curso core_curso_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso
    ADD CONSTRAINT core_curso_codigo_uq UNIQUE (codigo);


--
-- Name: curso_plan core_curso_plan_plan_curso_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_plan
    ADD CONSTRAINT core_curso_plan_plan_curso_uq UNIQUE (id_plan, id_curso);


--
-- Name: departamento_academico core_dep_acad_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.departamento_academico
    ADD CONSTRAINT core_dep_acad_codigo_uq UNIQUE (codigo);


--
-- Name: docente core_docente_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.docente
    ADD CONSTRAINT core_docente_codigo_uq UNIQUE (codigo_docente);


--
-- Name: docente core_docente_orcid_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.docente
    ADD CONSTRAINT core_docente_orcid_uq UNIQUE (orcid);


--
-- Name: docente core_docente_persona_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.docente
    ADD CONSTRAINT core_docente_persona_uq UNIQUE (id_persona);


--
-- Name: escuela_profesional core_escuela_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.escuela_profesional
    ADD CONSTRAINT core_escuela_codigo_uq UNIQUE (codigo);


--
-- Name: estudiante core_estudiante_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante
    ADD CONSTRAINT core_estudiante_codigo_uq UNIQUE (codigo_universitario);


--
-- Name: estudiante core_estudiante_persona_escuela_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante
    ADD CONSTRAINT core_estudiante_persona_escuela_uq UNIQUE (id_persona, id_escuela);


--
-- Name: facultad core_facultad_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.facultad
    ADD CONSTRAINT core_facultad_codigo_uq UNIQUE (codigo);


--
-- Name: facultad core_facultad_nombre_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.facultad
    ADD CONSTRAINT core_facultad_nombre_uq UNIQUE (nombre);


--
-- Name: modalidad_ingreso core_modalidad_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.modalidad_ingreso
    ADD CONSTRAINT core_modalidad_codigo_uq UNIQUE (codigo);


--
-- Name: pais core_pais_nombre_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.pais
    ADD CONSTRAINT core_pais_nombre_uq UNIQUE (nombre);


--
-- Name: periodo_academico core_periodo_anio_tipo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.periodo_academico
    ADD CONSTRAINT core_periodo_anio_tipo_uq UNIQUE (anio, tipo);


--
-- Name: periodo_academico core_periodo_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.periodo_academico
    ADD CONSTRAINT core_periodo_codigo_uq UNIQUE (codigo);


--
-- Name: persona core_persona_documento_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.persona
    ADD CONSTRAINT core_persona_documento_uq UNIQUE (tipo_documento, numero_documento);


--
-- Name: plan_estudios core_plan_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.plan_estudios
    ADD CONSTRAINT core_plan_codigo_uq UNIQUE (codigo);


--
-- Name: plan_estudios core_plan_escuela_version_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.plan_estudios
    ADD CONSTRAINT core_plan_escuela_version_uq UNIQUE (id_escuela, version);


--
-- Name: seccion core_seccion_periodo_curso_codigo_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.seccion
    ADD CONSTRAINT core_seccion_periodo_curso_codigo_uq UNIQUE (id_periodo, id_curso_plan, codigo);


--
-- Name: ubigeo_departamento core_ubigeo_dep_nombre_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_departamento
    ADD CONSTRAINT core_ubigeo_dep_nombre_uq UNIQUE (nombre);


--
-- Name: ubigeo_distrito core_ubigeo_dist_prov_nombre_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_distrito
    ADD CONSTRAINT core_ubigeo_dist_prov_nombre_uq UNIQUE (id_provincia, nombre);


--
-- Name: ubigeo_provincia core_ubigeo_prov_dep_nombre_uq; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_provincia
    ADD CONSTRAINT core_ubigeo_prov_dep_nombre_uq UNIQUE (id_departamento, nombre);


--
-- Name: curso curso_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso
    ADD CONSTRAINT curso_pkey PRIMARY KEY (id_curso);


--
-- Name: curso_plan curso_plan_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_plan
    ADD CONSTRAINT curso_plan_pkey PRIMARY KEY (id_curso_plan);


--
-- Name: curso_prerequisito curso_prerequisito_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_prerequisito
    ADD CONSTRAINT curso_prerequisito_pkey PRIMARY KEY (id_curso_plan, id_curso_plan_requisito);


--
-- Name: departamento_academico departamento_academico_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.departamento_academico
    ADD CONSTRAINT departamento_academico_pkey PRIMARY KEY (id_departamento);


--
-- Name: docente docente_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.docente
    ADD CONSTRAINT docente_pkey PRIMARY KEY (id_docente);


--
-- Name: escuela_profesional escuela_profesional_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.escuela_profesional
    ADD CONSTRAINT escuela_profesional_pkey PRIMARY KEY (id_escuela);


--
-- Name: estudiante estudiante_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante
    ADD CONSTRAINT estudiante_pkey PRIMARY KEY (id_estudiante);


--
-- Name: facultad facultad_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.facultad
    ADD CONSTRAINT facultad_pkey PRIMARY KEY (id_facultad);


--
-- Name: modalidad_ingreso modalidad_ingreso_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.modalidad_ingreso
    ADD CONSTRAINT modalidad_ingreso_pkey PRIMARY KEY (id_modalidad);


--
-- Name: pais pais_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.pais
    ADD CONSTRAINT pais_pkey PRIMARY KEY (id_pais);


--
-- Name: periodo_academico periodo_academico_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.periodo_academico
    ADD CONSTRAINT periodo_academico_pkey PRIMARY KEY (id_periodo);


--
-- Name: persona persona_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.persona
    ADD CONSTRAINT persona_pkey PRIMARY KEY (id_persona);


--
-- Name: plan_estudios plan_estudios_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.plan_estudios
    ADD CONSTRAINT plan_estudios_pkey PRIMARY KEY (id_plan);


--
-- Name: seccion seccion_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.seccion
    ADD CONSTRAINT seccion_pkey PRIMARY KEY (id_seccion);


--
-- Name: ubigeo_departamento ubigeo_departamento_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_departamento
    ADD CONSTRAINT ubigeo_departamento_pkey PRIMARY KEY (id_departamento);


--
-- Name: ubigeo_distrito ubigeo_distrito_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_distrito
    ADD CONSTRAINT ubigeo_distrito_pkey PRIMARY KEY (id_distrito);


--
-- Name: ubigeo_provincia ubigeo_provincia_pkey; Type: CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_provincia
    ADD CONSTRAINT ubigeo_provincia_pkey PRIMARY KEY (id_provincia);


--
-- Name: acta_notas acta_notas_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.acta_notas
    ADD CONSTRAINT acta_notas_pkey PRIMARY KEY (id_acta);


--
-- Name: asistencia asistencia_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.asistencia
    ADD CONSTRAINT asistencia_pkey PRIMARY KEY (id_detalle, fecha);


--
-- Name: componente_evaluacion componente_evaluacion_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.componente_evaluacion
    ADD CONSTRAINT componente_evaluacion_pkey PRIMARY KEY (id_componente);


--
-- Name: cronograma_matricula cronograma_matricula_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.cronograma_matricula
    ADD CONSTRAINT cronograma_matricula_pkey PRIMARY KEY (id_cronograma);


--
-- Name: limite_creditos limite_creditos_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.limite_creditos
    ADD CONSTRAINT limite_creditos_pkey PRIMARY KEY (id_limite);


--
-- Name: acta_notas mat_acta_seccion_uq; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.acta_notas
    ADD CONSTRAINT mat_acta_seccion_uq UNIQUE (id_seccion);


--
-- Name: componente_evaluacion mat_comp_seccion_nombre_uq; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.componente_evaluacion
    ADD CONSTRAINT mat_comp_seccion_nombre_uq UNIQUE (id_seccion, nombre);


--
-- Name: cronograma_matricula mat_crono_periodo_tipo_uq; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.cronograma_matricula
    ADD CONSTRAINT mat_crono_periodo_tipo_uq UNIQUE (id_periodo, tipo);


--
-- Name: matricula_detalle mat_det_matricula_seccion_uq; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula_detalle
    ADD CONSTRAINT mat_det_matricula_seccion_uq UNIQUE (id_matricula, id_seccion);


--
-- Name: limite_creditos mat_lim_est_periodo_uq; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.limite_creditos
    ADD CONSTRAINT mat_lim_est_periodo_uq UNIQUE (id_estudiante, id_periodo);


--
-- Name: matricula mat_matricula_est_periodo_uq; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula
    ADD CONSTRAINT mat_matricula_est_periodo_uq UNIQUE (id_estudiante, id_periodo);


--
-- Name: reserva_matricula mat_reserva_est_periodo_uq; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.reserva_matricula
    ADD CONSTRAINT mat_reserva_est_periodo_uq UNIQUE (id_estudiante, id_periodo);


--
-- Name: matricula_detalle matricula_detalle_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula_detalle
    ADD CONSTRAINT matricula_detalle_pkey PRIMARY KEY (id_detalle);


--
-- Name: matricula matricula_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula
    ADD CONSTRAINT matricula_pkey PRIMARY KEY (id_matricula);


--
-- Name: nota nota_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.nota
    ADD CONSTRAINT nota_pkey PRIMARY KEY (id_detalle, id_componente);


--
-- Name: reserva_matricula reserva_matricula_pkey; Type: CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.reserva_matricula
    ADD CONSTRAINT reserva_matricula_pkey PRIMARY KEY (id_reserva);


--
-- Name: cronograma_prematricula cronograma_prematricula_pkey; Type: CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.cronograma_prematricula
    ADD CONSTRAINT cronograma_prematricula_pkey PRIMARY KEY (id_cronograma);


--
-- Name: detalle_solicitud detalle_solicitud_pkey; Type: CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.detalle_solicitud
    ADD CONSTRAINT detalle_solicitud_pkey PRIMARY KEY (id_detalle);


--
-- Name: cronograma_prematricula pre_crono_periodo_uq; Type: CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.cronograma_prematricula
    ADD CONSTRAINT pre_crono_periodo_uq UNIQUE (id_periodo);


--
-- Name: detalle_solicitud pre_det_sol_curso_uq; Type: CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.detalle_solicitud
    ADD CONSTRAINT pre_det_sol_curso_uq UNIQUE (id_solicitud, id_curso_plan);


--
-- Name: solicitud_prematricula pre_sol_est_periodo_uq; Type: CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.solicitud_prematricula
    ADD CONSTRAINT pre_sol_est_periodo_uq UNIQUE (id_estudiante, id_periodo);


--
-- Name: solicitud_prematricula solicitud_prematricula_pkey; Type: CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.solicitud_prematricula
    ADD CONSTRAINT solicitud_prematricula_pkey PRIMARY KEY (id_solicitud);


--
-- Name: usuario seg_usuario_correo_uq; Type: CONSTRAINT; Schema: seguridad; Owner: -
--

ALTER TABLE ONLY seguridad.usuario
    ADD CONSTRAINT seg_usuario_correo_uq UNIQUE (correo_institucional);


--
-- Name: usuario seg_usuario_persona_uq; Type: CONSTRAINT; Schema: seguridad; Owner: -
--

ALTER TABLE ONLY seguridad.usuario
    ADD CONSTRAINT seg_usuario_persona_uq UNIQUE (id_persona);


--
-- Name: usuario usuario_pkey; Type: CONSTRAINT; Schema: seguridad; Owner: -
--

ALTER TABLE ONLY seguridad.usuario
    ADD CONSTRAINT usuario_pkey PRIMARY KEY (id_usuario);


--
-- Name: core_curso_plan_area_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_curso_plan_area_idx ON core.curso_plan USING btree (id_area);


--
-- Name: core_curso_plan_curso_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_curso_plan_curso_idx ON core.curso_plan USING btree (id_curso);


--
-- Name: core_curso_prerequisito_req_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_curso_prerequisito_req_idx ON core.curso_prerequisito USING btree (id_curso_plan_requisito);


--
-- Name: core_estudiante_modalidad_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_estudiante_modalidad_idx ON core.estudiante USING btree (id_modalidad_ingreso);


--
-- Name: core_estudiante_periodo_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_estudiante_periodo_idx ON core.estudiante USING btree (id_periodo_ingreso);


--
-- Name: core_estudiante_plan_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_estudiante_plan_idx ON core.estudiante USING btree (id_plan);


--
-- Name: core_persona_distrito_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_persona_distrito_idx ON core.persona USING btree (id_distrito_domicilio);


--
-- Name: core_persona_pais_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_persona_pais_idx ON core.persona USING btree (id_pais_nacionalidad);


--
-- Name: core_seccion_curso_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_seccion_curso_idx ON core.seccion USING btree (id_curso_plan);


--
-- Name: core_seccion_docente_idx; Type: INDEX; Schema: core; Owner: -
--

CREATE INDEX core_seccion_docente_idx ON core.seccion USING btree (id_docente_responsable);


--
-- Name: mat_acta_usuario_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_acta_usuario_idx ON gestion_matriculas.acta_notas USING btree (cerrada_por);


--
-- Name: mat_det_seccion_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_det_seccion_idx ON gestion_matriculas.matricula_detalle USING btree (id_seccion);


--
-- Name: mat_lim_periodo_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_lim_periodo_idx ON gestion_matriculas.limite_creditos USING btree (id_periodo);


--
-- Name: mat_matricula_crono_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_matricula_crono_idx ON gestion_matriculas.matricula USING btree (id_cronograma);


--
-- Name: mat_matricula_periodo_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_matricula_periodo_idx ON gestion_matriculas.matricula USING btree (id_periodo);


--
-- Name: mat_matricula_pre_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_matricula_pre_idx ON gestion_matriculas.matricula USING btree (id_solicitud_prematricula);


--
-- Name: mat_matricula_usuario_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_matricula_usuario_idx ON gestion_matriculas.matricula USING btree (registrado_por);


--
-- Name: mat_nota_componente_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_nota_componente_idx ON gestion_matriculas.nota USING btree (id_componente);


--
-- Name: mat_nota_usuario_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_nota_usuario_idx ON gestion_matriculas.nota USING btree (registrado_por);


--
-- Name: mat_reserva_periodo_idx; Type: INDEX; Schema: gestion_matriculas; Owner: -
--

CREATE INDEX mat_reserva_periodo_idx ON gestion_matriculas.reserva_matricula USING btree (id_periodo);


--
-- Name: pre_det_curso_idx; Type: INDEX; Schema: prematricula; Owner: -
--

CREATE INDEX pre_det_curso_idx ON prematricula.detalle_solicitud USING btree (id_curso_plan);


--
-- Name: pre_det_sec_idx; Type: INDEX; Schema: prematricula; Owner: -
--

CREATE INDEX pre_det_sec_idx ON prematricula.detalle_solicitud USING btree (id_seccion_preferida);


--
-- Name: pre_sol_periodo_idx; Type: INDEX; Schema: prematricula; Owner: -
--

CREATE INDEX pre_sol_periodo_idx ON prematricula.solicitud_prematricula USING btree (id_periodo);


--
-- Name: pre_sol_revisor_idx; Type: INDEX; Schema: prematricula; Owner: -
--

CREATE INDEX pre_sol_revisor_idx ON prematricula.solicitud_prematricula USING btree (revisado_por);


--
-- Name: curso_prerequisito core_curso_prerequisito_mismo_plan_trg; Type: TRIGGER; Schema: core; Owner: -
--

CREATE TRIGGER core_curso_prerequisito_mismo_plan_trg BEFORE INSERT OR UPDATE ON core.curso_prerequisito FOR EACH ROW EXECUTE FUNCTION core.validar_curso_prerequisito_mismo_plan();


--
-- Name: curso_plan core_curso_plan_area_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_plan
    ADD CONSTRAINT core_curso_plan_area_fk FOREIGN KEY (id_area) REFERENCES core.area_formacion(id_area) ON DELETE RESTRICT;


--
-- Name: curso_plan core_curso_plan_curso_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_plan
    ADD CONSTRAINT core_curso_plan_curso_fk FOREIGN KEY (id_curso) REFERENCES core.curso(id_curso) ON DELETE RESTRICT;


--
-- Name: curso_plan core_curso_plan_plan_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_plan
    ADD CONSTRAINT core_curso_plan_plan_fk FOREIGN KEY (id_plan) REFERENCES core.plan_estudios(id_plan) ON DELETE RESTRICT;


--
-- Name: curso_prerequisito core_curso_prerequisito_curso_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_prerequisito
    ADD CONSTRAINT core_curso_prerequisito_curso_fk FOREIGN KEY (id_curso_plan) REFERENCES core.curso_plan(id_curso_plan) ON DELETE RESTRICT;


--
-- Name: curso_prerequisito core_curso_prerequisito_req_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.curso_prerequisito
    ADD CONSTRAINT core_curso_prerequisito_req_fk FOREIGN KEY (id_curso_plan_requisito) REFERENCES core.curso_plan(id_curso_plan) ON DELETE RESTRICT;


--
-- Name: departamento_academico core_dep_acad_facultad_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.departamento_academico
    ADD CONSTRAINT core_dep_acad_facultad_fk FOREIGN KEY (id_facultad) REFERENCES core.facultad(id_facultad) ON DELETE RESTRICT;


--
-- Name: docente core_docente_departamento_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.docente
    ADD CONSTRAINT core_docente_departamento_fk FOREIGN KEY (id_departamento) REFERENCES core.departamento_academico(id_departamento) ON DELETE RESTRICT;


--
-- Name: docente core_docente_persona_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.docente
    ADD CONSTRAINT core_docente_persona_fk FOREIGN KEY (id_persona) REFERENCES core.persona(id_persona) ON DELETE RESTRICT;


--
-- Name: escuela_profesional core_escuela_facultad_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.escuela_profesional
    ADD CONSTRAINT core_escuela_facultad_fk FOREIGN KEY (id_facultad) REFERENCES core.facultad(id_facultad) ON DELETE RESTRICT;


--
-- Name: estudiante core_estudiante_escuela_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante
    ADD CONSTRAINT core_estudiante_escuela_fk FOREIGN KEY (id_escuela) REFERENCES core.escuela_profesional(id_escuela) ON DELETE RESTRICT;


--
-- Name: estudiante core_estudiante_modalidad_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante
    ADD CONSTRAINT core_estudiante_modalidad_fk FOREIGN KEY (id_modalidad_ingreso) REFERENCES core.modalidad_ingreso(id_modalidad) ON DELETE RESTRICT;


--
-- Name: estudiante core_estudiante_periodo_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante
    ADD CONSTRAINT core_estudiante_periodo_fk FOREIGN KEY (id_periodo_ingreso) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT;


--
-- Name: estudiante core_estudiante_persona_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante
    ADD CONSTRAINT core_estudiante_persona_fk FOREIGN KEY (id_persona) REFERENCES core.persona(id_persona) ON DELETE RESTRICT;


--
-- Name: estudiante core_estudiante_plan_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.estudiante
    ADD CONSTRAINT core_estudiante_plan_fk FOREIGN KEY (id_plan) REFERENCES core.plan_estudios(id_plan) ON DELETE RESTRICT;


--
-- Name: persona core_persona_distrito_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.persona
    ADD CONSTRAINT core_persona_distrito_fk FOREIGN KEY (id_distrito_domicilio) REFERENCES core.ubigeo_distrito(id_distrito) ON DELETE RESTRICT;


--
-- Name: persona core_persona_pais_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.persona
    ADD CONSTRAINT core_persona_pais_fk FOREIGN KEY (id_pais_nacionalidad) REFERENCES core.pais(id_pais) ON DELETE RESTRICT;


--
-- Name: plan_estudios core_plan_escuela_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.plan_estudios
    ADD CONSTRAINT core_plan_escuela_fk FOREIGN KEY (id_escuela) REFERENCES core.escuela_profesional(id_escuela) ON DELETE RESTRICT;


--
-- Name: seccion core_seccion_curso_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.seccion
    ADD CONSTRAINT core_seccion_curso_fk FOREIGN KEY (id_curso_plan) REFERENCES core.curso_plan(id_curso_plan) ON DELETE RESTRICT;


--
-- Name: seccion core_seccion_docente_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.seccion
    ADD CONSTRAINT core_seccion_docente_fk FOREIGN KEY (id_docente_responsable) REFERENCES core.docente(id_docente) ON DELETE RESTRICT;


--
-- Name: seccion core_seccion_periodo_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.seccion
    ADD CONSTRAINT core_seccion_periodo_fk FOREIGN KEY (id_periodo) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT;


--
-- Name: ubigeo_distrito core_ubigeo_dist_prov_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_distrito
    ADD CONSTRAINT core_ubigeo_dist_prov_fk FOREIGN KEY (id_provincia) REFERENCES core.ubigeo_provincia(id_provincia) ON DELETE RESTRICT;


--
-- Name: ubigeo_provincia core_ubigeo_prov_dep_fk; Type: FK CONSTRAINT; Schema: core; Owner: -
--

ALTER TABLE ONLY core.ubigeo_provincia
    ADD CONSTRAINT core_ubigeo_prov_dep_fk FOREIGN KEY (id_departamento) REFERENCES core.ubigeo_departamento(id_departamento) ON DELETE RESTRICT;


--
-- Name: acta_notas mat_acta_seccion_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.acta_notas
    ADD CONSTRAINT mat_acta_seccion_fk FOREIGN KEY (id_seccion) REFERENCES core.seccion(id_seccion) ON DELETE RESTRICT;


--
-- Name: acta_notas mat_acta_usuario_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.acta_notas
    ADD CONSTRAINT mat_acta_usuario_fk FOREIGN KEY (cerrada_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT;


--
-- Name: asistencia mat_asistencia_detalle_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.asistencia
    ADD CONSTRAINT mat_asistencia_detalle_fk FOREIGN KEY (id_detalle) REFERENCES gestion_matriculas.matricula_detalle(id_detalle) ON DELETE RESTRICT;


--
-- Name: componente_evaluacion mat_comp_seccion_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.componente_evaluacion
    ADD CONSTRAINT mat_comp_seccion_fk FOREIGN KEY (id_seccion) REFERENCES core.seccion(id_seccion) ON DELETE RESTRICT;


--
-- Name: cronograma_matricula mat_crono_periodo_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.cronograma_matricula
    ADD CONSTRAINT mat_crono_periodo_fk FOREIGN KEY (id_periodo) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT;


--
-- Name: matricula_detalle mat_det_matricula_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula_detalle
    ADD CONSTRAINT mat_det_matricula_fk FOREIGN KEY (id_matricula) REFERENCES gestion_matriculas.matricula(id_matricula) ON DELETE RESTRICT;


--
-- Name: matricula_detalle mat_det_seccion_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula_detalle
    ADD CONSTRAINT mat_det_seccion_fk FOREIGN KEY (id_seccion) REFERENCES core.seccion(id_seccion) ON DELETE RESTRICT;


--
-- Name: limite_creditos mat_lim_estudiante_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.limite_creditos
    ADD CONSTRAINT mat_lim_estudiante_fk FOREIGN KEY (id_estudiante) REFERENCES core.estudiante(id_estudiante) ON DELETE RESTRICT;


--
-- Name: limite_creditos mat_lim_periodo_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.limite_creditos
    ADD CONSTRAINT mat_lim_periodo_fk FOREIGN KEY (id_periodo) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT;


--
-- Name: matricula mat_matricula_crono_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula
    ADD CONSTRAINT mat_matricula_crono_fk FOREIGN KEY (id_cronograma) REFERENCES gestion_matriculas.cronograma_matricula(id_cronograma) ON DELETE RESTRICT;


--
-- Name: matricula mat_matricula_estudiante_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula
    ADD CONSTRAINT mat_matricula_estudiante_fk FOREIGN KEY (id_estudiante) REFERENCES core.estudiante(id_estudiante) ON DELETE RESTRICT;


--
-- Name: matricula mat_matricula_periodo_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula
    ADD CONSTRAINT mat_matricula_periodo_fk FOREIGN KEY (id_periodo) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT;


--
-- Name: matricula mat_matricula_usuario_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.matricula
    ADD CONSTRAINT mat_matricula_usuario_fk FOREIGN KEY (registrado_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT;


--
-- Name: nota mat_nota_componente_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.nota
    ADD CONSTRAINT mat_nota_componente_fk FOREIGN KEY (id_componente) REFERENCES gestion_matriculas.componente_evaluacion(id_componente) ON DELETE RESTRICT;


--
-- Name: nota mat_nota_detalle_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.nota
    ADD CONSTRAINT mat_nota_detalle_fk FOREIGN KEY (id_detalle) REFERENCES gestion_matriculas.matricula_detalle(id_detalle) ON DELETE RESTRICT;


--
-- Name: nota mat_nota_usuario_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.nota
    ADD CONSTRAINT mat_nota_usuario_fk FOREIGN KEY (registrado_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT;


--
-- Name: reserva_matricula mat_reserva_estudiante_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.reserva_matricula
    ADD CONSTRAINT mat_reserva_estudiante_fk FOREIGN KEY (id_estudiante) REFERENCES core.estudiante(id_estudiante) ON DELETE RESTRICT;


--
-- Name: reserva_matricula mat_reserva_periodo_fk; Type: FK CONSTRAINT; Schema: gestion_matriculas; Owner: -
--

ALTER TABLE ONLY gestion_matriculas.reserva_matricula
    ADD CONSTRAINT mat_reserva_periodo_fk FOREIGN KEY (id_periodo) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT;


--
-- Name: cronograma_prematricula pre_cr_per_fk; Type: FK CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.cronograma_prematricula
    ADD CONSTRAINT pre_cr_per_fk FOREIGN KEY (id_periodo) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT;


--
-- Name: detalle_solicitud pre_de_cur_fk; Type: FK CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.detalle_solicitud
    ADD CONSTRAINT pre_de_cur_fk FOREIGN KEY (id_curso_plan) REFERENCES core.curso_plan(id_curso_plan) ON DELETE RESTRICT;


--
-- Name: detalle_solicitud pre_de_sec_fk; Type: FK CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.detalle_solicitud
    ADD CONSTRAINT pre_de_sec_fk FOREIGN KEY (id_seccion_preferida) REFERENCES core.seccion(id_seccion) ON DELETE RESTRICT;


--
-- Name: detalle_solicitud pre_de_sol_fk; Type: FK CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.detalle_solicitud
    ADD CONSTRAINT pre_de_sol_fk FOREIGN KEY (id_solicitud) REFERENCES prematricula.solicitud_prematricula(id_solicitud) ON DELETE RESTRICT;


--
-- Name: solicitud_prematricula pre_so_est_fk; Type: FK CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.solicitud_prematricula
    ADD CONSTRAINT pre_so_est_fk FOREIGN KEY (id_estudiante) REFERENCES core.estudiante(id_estudiante) ON DELETE RESTRICT;


--
-- Name: solicitud_prematricula pre_so_per_fk; Type: FK CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.solicitud_prematricula
    ADD CONSTRAINT pre_so_per_fk FOREIGN KEY (id_periodo) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT;


--
-- Name: solicitud_prematricula pre_so_rev_fk; Type: FK CONSTRAINT; Schema: prematricula; Owner: -
--

ALTER TABLE ONLY prematricula.solicitud_prematricula
    ADD CONSTRAINT pre_so_rev_fk FOREIGN KEY (revisado_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT;


--
-- Name: usuario seg_usuario_persona_fk; Type: FK CONSTRAINT; Schema: seguridad; Owner: -
--

ALTER TABLE ONLY seguridad.usuario
    ADD CONSTRAINT seg_usuario_persona_fk FOREIGN KEY (id_persona) REFERENCES core.persona(id_persona) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict tGHYV6JZQJctLTdIBBHeZvHmoUNAtY226ae6p4z9hx2ejsSy8ss2d6BqKa97Zuy

