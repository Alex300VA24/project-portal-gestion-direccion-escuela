<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Esquema seguimiento_egresado (40 tablas) según
 * esquemas_y_origen_de_tablas.pdf, sección B.12.
 */
return new class extends Migration
{
    private const TABLES = [
        'cat_area_profesional',
        'cat_categoria_habilidad',
        'cat_habilidad',
        'cat_estado_academico',
        'cat_estado_formacion',
        'cat_tipo_formacion',
        'cat_modalidad_laboral',
        'cat_modalidad_trabajo',
        'cat_situacion_laboral',
        'cat_nivel_logro',
        'cat_nivel_satisfaccion',
        'cat_canal_envio',
        'cohorte',
        'competencia',
        'objetivo_educacional',
        'egresado',
        'grado_egresado',
        'historial_estado_academico',
        'contacto_egresado',
        'formacion_posterior',
        'importacion_padron',
        'importacion_fila',
        'campana_seguimiento',
        'campana_cohorte',
        'campana_canal',
        'invitacion_egresado',
        'medicion_laboral',
        'medicion_habilidad',
        'evaluacion_competencia',
        'evaluacion_oe',
        'satisfaccion_egresado',
        'invitacion_empresa',
        'respuesta_empresa',
        'respuesta_empresa_habilidad',
        'indicador_egresado',
        'reporte_generado',
        'reporte_indicador',
        'mejora_curricular',
        'plan_accion',
        'plan_accion_evidencia',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared($this->catalogs());
        DB::unprepared($this->egresados());
        DB::unprepared($this->campanas());
        DB::unprepared($this->resultados());
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP TABLE IF EXISTS seguimiento_egresado.{$table}");
        }
    }

    private function catalogs(): string
    {
        return <<<'SQL'
            CREATE SCHEMA IF NOT EXISTS seguimiento_egresado;

            CREATE TABLE seguimiento_egresado.cat_area_profesional (
                id_area BIGSERIAL PRIMARY KEY,
                nombre  VARCHAR(120) NOT NULL,
                CONSTRAINT sg_cat_area_profesional_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_egresado.cat_categoria_habilidad (
                id_categoria BIGSERIAL PRIMARY KEY,
                nombre       VARCHAR(120) NOT NULL,
                CONSTRAINT sg_cat_categoria_habilidad_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_egresado.cat_habilidad (
                id_habilidad BIGSERIAL PRIMARY KEY,
                id_categoria BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_categoria_habilidad (id_categoria) ON DELETE RESTRICT,
                nombre       VARCHAR(150) NOT NULL,
                CONSTRAINT sg_cat_habilidad_nombre_uq UNIQUE (nombre)
            );
            CREATE INDEX sg_cat_habilidad_categoria_idx ON seguimiento_egresado.cat_habilidad (id_categoria);

            CREATE TABLE seguimiento_egresado.cat_estado_academico (
                id_estado BIGSERIAL PRIMARY KEY,
                nombre    VARCHAR(80) NOT NULL,
                CONSTRAINT sg_cat_estado_academico_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_egresado.cat_estado_formacion (
                id_estado BIGSERIAL PRIMARY KEY,
                nombre    VARCHAR(80) NOT NULL,
                CONSTRAINT sg_cat_estado_formacion_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_egresado.cat_tipo_formacion (
                id_tipo BIGSERIAL PRIMARY KEY,
                nombre  VARCHAR(80) NOT NULL,
                CONSTRAINT sg_cat_tipo_formacion_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_egresado.cat_modalidad_laboral (
                id_modalidad BIGSERIAL PRIMARY KEY,
                nombre       VARCHAR(80) NOT NULL,
                CONSTRAINT sg_cat_modalidad_laboral_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_egresado.cat_modalidad_trabajo (
                id_modalidad BIGSERIAL PRIMARY KEY,
                nombre       VARCHAR(80) NOT NULL,
                CONSTRAINT sg_cat_modalidad_trabajo_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_egresado.cat_situacion_laboral (
                id_situacion BIGSERIAL PRIMARY KEY,
                nombre       VARCHAR(80) NOT NULL,
                CONSTRAINT sg_cat_situacion_laboral_nombre_uq UNIQUE (nombre)
            );

            CREATE TABLE seguimiento_egresado.cat_nivel_logro (
                id_nivel BIGSERIAL PRIMARY KEY,
                nombre   VARCHAR(60) NOT NULL,
                valor    SMALLINT NOT NULL,
                CONSTRAINT sg_cat_nivel_logro_nombre_uq UNIQUE (nombre),
                CONSTRAINT sg_cat_nivel_logro_valor_uq UNIQUE (valor)
            );

            CREATE TABLE seguimiento_egresado.cat_nivel_satisfaccion (
                id_nivel BIGSERIAL PRIMARY KEY,
                nombre   VARCHAR(60) NOT NULL,
                valor    SMALLINT NOT NULL,
                CONSTRAINT sg_cat_nivel_satisfaccion_nombre_uq UNIQUE (nombre),
                CONSTRAINT sg_cat_nivel_satisfaccion_valor_uq UNIQUE (valor)
            );

            CREATE TABLE seguimiento_egresado.cat_canal_envio (
                id_canal BIGSERIAL PRIMARY KEY,
                nombre   VARCHAR(60) NOT NULL,
                CONSTRAINT sg_cat_canal_envio_nombre_uq UNIQUE (nombre)
            );
            SQL;
    }

    private function egresados(): string
    {
        return <<<'SQL'
            CREATE TABLE seguimiento_egresado.cohorte (
                id_cohorte  BIGSERIAL PRIMARY KEY,
                id_plan     BIGINT NOT NULL REFERENCES core.plan_estudios (id_plan) ON DELETE RESTRICT,
                anio        SMALLINT NOT NULL,
                semestre    VARCHAR(10) NOT NULL,
                descripcion TEXT,
                CONSTRAINT sg_cohorte_plan_periodo_uq UNIQUE (id_plan, anio, semestre)
            );

            CREATE TABLE seguimiento_egresado.competencia (
                id_competencia BIGSERIAL PRIMARY KEY,
                id_plan        BIGINT NOT NULL REFERENCES core.plan_estudios (id_plan) ON DELETE RESTRICT,
                codigo         VARCHAR(30) NOT NULL,
                descripcion    TEXT NOT NULL,
                CONSTRAINT sg_competencia_plan_codigo_uq UNIQUE (id_plan, codigo)
            );

            CREATE TABLE seguimiento_egresado.objetivo_educacional (
                id_objetivo BIGSERIAL PRIMARY KEY,
                id_plan     BIGINT NOT NULL REFERENCES core.plan_estudios (id_plan) ON DELETE RESTRICT,
                codigo      VARCHAR(30) NOT NULL,
                descripcion TEXT NOT NULL,
                CONSTRAINT sg_objetivo_educacional_plan_codigo_uq UNIQUE (id_plan, codigo)
            );

            CREATE TABLE seguimiento_egresado.egresado (
                id_egresado   BIGSERIAL PRIMARY KEY,
                id_estudiante BIGINT NOT NULL REFERENCES core.estudiante (id_estudiante) ON DELETE RESTRICT,
                id_cohorte    BIGINT NOT NULL REFERENCES seguimiento_egresado.cohorte (id_cohorte) ON DELETE RESTRICT,
                fecha_egreso  DATE NOT NULL,
                registrado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT sg_egresado_estudiante_uq UNIQUE (id_estudiante)
            );
            CREATE INDEX sg_egresado_cohorte_idx ON seguimiento_egresado.egresado (id_cohorte);

            CREATE TABLE seguimiento_egresado.grado_egresado (
                id_grado        BIGSERIAL PRIMARY KEY,
                id_egresado     BIGINT NOT NULL REFERENCES seguimiento_egresado.egresado (id_egresado) ON DELETE CASCADE,
                tipo            VARCHAR(40) NOT NULL,
                fecha_obtencion DATE,
                resolucion      VARCHAR(100)
            );
            CREATE INDEX sg_grado_egresado_egresado_idx ON seguimiento_egresado.grado_egresado (id_egresado);

            CREATE TABLE seguimiento_egresado.historial_estado_academico (
                id_historial BIGSERIAL PRIMARY KEY,
                id_egresado  BIGINT NOT NULL REFERENCES seguimiento_egresado.egresado (id_egresado) ON DELETE CASCADE,
                id_estado    BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_estado_academico (id_estado) ON DELETE RESTRICT,
                fecha        DATE NOT NULL,
                observacion  TEXT
            );
            CREATE INDEX sg_historial_estado_egresado_idx ON seguimiento_egresado.historial_estado_academico (id_egresado);
            CREATE INDEX sg_historial_estado_estado_idx ON seguimiento_egresado.historial_estado_academico (id_estado);

            CREATE TABLE seguimiento_egresado.contacto_egresado (
                id_contacto  BIGSERIAL PRIMARY KEY,
                id_egresado  BIGINT NOT NULL REFERENCES seguimiento_egresado.egresado (id_egresado) ON DELETE CASCADE,
                tipo         VARCHAR(30) NOT NULL,
                valor        VARCHAR(190) NOT NULL,
                es_principal BOOLEAN NOT NULL DEFAULT FALSE,
                verificado   BOOLEAN NOT NULL DEFAULT FALSE
            );
            CREATE INDEX sg_contacto_egresado_egresado_idx ON seguimiento_egresado.contacto_egresado (id_egresado);

            CREATE TABLE seguimiento_egresado.formacion_posterior (
                id_formacion BIGSERIAL PRIMARY KEY,
                id_egresado  BIGINT NOT NULL REFERENCES seguimiento_egresado.egresado (id_egresado) ON DELETE CASCADE,
                id_tipo      BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_tipo_formacion (id_tipo) ON DELETE RESTRICT,
                id_estado    BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_estado_formacion (id_estado) ON DELETE RESTRICT,
                institucion  VARCHAR(200) NOT NULL,
                programa     VARCHAR(200),
                id_pais      BIGINT REFERENCES core.pais (id_pais) ON DELETE RESTRICT,
                fecha_inicio DATE,
                fecha_fin    DATE,
                CONSTRAINT sg_formacion_posterior_fechas_ck CHECK (fecha_fin IS NULL OR fecha_inicio IS NULL OR fecha_fin >= fecha_inicio)
            );
            CREATE INDEX sg_formacion_posterior_egresado_idx ON seguimiento_egresado.formacion_posterior (id_egresado);
            CREATE INDEX sg_formacion_posterior_tipo_idx ON seguimiento_egresado.formacion_posterior (id_tipo);
            CREATE INDEX sg_formacion_posterior_estado_idx ON seguimiento_egresado.formacion_posterior (id_estado);
            CREATE INDEX sg_formacion_posterior_pais_idx ON seguimiento_egresado.formacion_posterior (id_pais);

            CREATE TABLE seguimiento_egresado.importacion_padron (
                id_importacion BIGSERIAL PRIMARY KEY,
                id_archivo     BIGINT NOT NULL REFERENCES comun.archivo (id_archivo) ON DELETE RESTRICT,
                importado_por  BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
                estado         VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
                importado_en   TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX sg_importacion_padron_archivo_idx ON seguimiento_egresado.importacion_padron (id_archivo);
            CREATE INDEX sg_importacion_padron_usuario_idx ON seguimiento_egresado.importacion_padron (importado_por);

            CREATE TABLE seguimiento_egresado.importacion_fila (
                id_fila        BIGSERIAL PRIMARY KEY,
                id_importacion BIGINT NOT NULL REFERENCES seguimiento_egresado.importacion_padron (id_importacion) ON DELETE CASCADE,
                numero_fila    INTEGER NOT NULL,
                datos          JSONB,
                estado         VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
                mensaje_error  TEXT,
                id_egresado    BIGINT REFERENCES seguimiento_egresado.egresado (id_egresado) ON DELETE SET NULL,
                CONSTRAINT sg_importacion_fila_numero_uq UNIQUE (id_importacion, numero_fila)
            );
            CREATE INDEX sg_importacion_fila_egresado_idx ON seguimiento_egresado.importacion_fila (id_egresado);
            SQL;
    }

    private function campanas(): string
    {
        return <<<'SQL'
            CREATE TABLE seguimiento_egresado.campana_seguimiento (
                id_campana   BIGSERIAL PRIMARY KEY,
                nombre       VARCHAR(180) NOT NULL,
                dirigida_a   VARCHAR(30) NOT NULL DEFAULT 'egresado',
                fecha_inicio DATE NOT NULL,
                fecha_fin    DATE NOT NULL,
                estado       VARCHAR(30) NOT NULL DEFAULT 'Borrador',
                creada_por   BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
                CONSTRAINT sg_campana_fechas_ck CHECK (fecha_fin >= fecha_inicio)
            );
            CREATE INDEX sg_campana_creador_idx ON seguimiento_egresado.campana_seguimiento (creada_por);

            CREATE TABLE seguimiento_egresado.campana_cohorte (
                id_campana BIGINT NOT NULL REFERENCES seguimiento_egresado.campana_seguimiento (id_campana) ON DELETE CASCADE,
                id_cohorte BIGINT NOT NULL REFERENCES seguimiento_egresado.cohorte (id_cohorte) ON DELETE RESTRICT,
                CONSTRAINT sg_campana_cohorte_pk PRIMARY KEY (id_campana, id_cohorte)
            );
            CREATE INDEX sg_campana_cohorte_cohorte_idx ON seguimiento_egresado.campana_cohorte (id_cohorte);

            CREATE TABLE seguimiento_egresado.campana_canal (
                id_campana BIGINT NOT NULL REFERENCES seguimiento_egresado.campana_seguimiento (id_campana) ON DELETE CASCADE,
                id_canal   BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_canal_envio (id_canal) ON DELETE RESTRICT,
                CONSTRAINT sg_campana_canal_pk PRIMARY KEY (id_campana, id_canal)
            );
            CREATE INDEX sg_campana_canal_canal_idx ON seguimiento_egresado.campana_canal (id_canal);

            CREATE TABLE seguimiento_egresado.invitacion_egresado (
                id_invitacion BIGSERIAL PRIMARY KEY,
                id_campana    BIGINT NOT NULL REFERENCES seguimiento_egresado.campana_seguimiento (id_campana) ON DELETE CASCADE,
                id_egresado   BIGINT NOT NULL REFERENCES seguimiento_egresado.egresado (id_egresado) ON DELETE CASCADE,
                id_canal      BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_canal_envio (id_canal) ON DELETE RESTRICT,
                token_hash    VARCHAR(255) NOT NULL,
                enviada_en    TIMESTAMPTZ,
                respondida_en TIMESTAMPTZ,
                CONSTRAINT sg_invitacion_egresado_token_uq UNIQUE (token_hash),
                CONSTRAINT sg_invitacion_egresado_campana_egresado_uq UNIQUE (id_campana, id_egresado)
            );
            CREATE INDEX sg_invitacion_egresado_egresado_idx ON seguimiento_egresado.invitacion_egresado (id_egresado);
            CREATE INDEX sg_invitacion_egresado_canal_idx ON seguimiento_egresado.invitacion_egresado (id_canal);

            CREATE TABLE seguimiento_egresado.invitacion_empresa (
                id_invitacion BIGSERIAL PRIMARY KEY,
                id_campana    BIGINT NOT NULL REFERENCES seguimiento_egresado.campana_seguimiento (id_campana) ON DELETE CASCADE,
                id_empresa    BIGINT NOT NULL REFERENCES comun.empresa (id_empresa) ON DELETE RESTRICT,
                correo_destino VARCHAR(190) NOT NULL,
                token_hash    VARCHAR(255) NOT NULL,
                enviada_en    TIMESTAMPTZ,
                respondida_en TIMESTAMPTZ,
                CONSTRAINT sg_invitacion_empresa_token_uq UNIQUE (token_hash),
                CONSTRAINT sg_invitacion_empresa_campana_empresa_uq UNIQUE (id_campana, id_empresa)
            );
            CREATE INDEX sg_invitacion_empresa_empresa_idx ON seguimiento_egresado.invitacion_empresa (id_empresa);
            SQL;
    }

    private function resultados(): string
    {
        return <<<'SQL'
            CREATE TABLE seguimiento_egresado.medicion_laboral (
                id_medicion          BIGSERIAL PRIMARY KEY,
                id_invitacion        BIGINT NOT NULL REFERENCES seguimiento_egresado.invitacion_egresado (id_invitacion) ON DELETE CASCADE,
                id_situacion         BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_situacion_laboral (id_situacion) ON DELETE RESTRICT,
                id_modalidad_laboral BIGINT REFERENCES seguimiento_egresado.cat_modalidad_laboral (id_modalidad) ON DELETE RESTRICT,
                id_modalidad_trabajo BIGINT REFERENCES seguimiento_egresado.cat_modalidad_trabajo (id_modalidad) ON DELETE RESTRICT,
                id_empresa           BIGINT REFERENCES comun.empresa (id_empresa) ON DELETE RESTRICT,
                id_sector            BIGINT REFERENCES comun.sector_economico (id_sector) ON DELETE RESTRICT,
                id_area              BIGINT REFERENCES seguimiento_egresado.cat_area_profesional (id_area) ON DELETE RESTRICT,
                cargo                VARCHAR(150),
                meses_primer_empleo  SMALLINT,
                relacionado_carrera  BOOLEAN,
                CONSTRAINT sg_medicion_laboral_invitacion_uq UNIQUE (id_invitacion),
                CONSTRAINT sg_medicion_laboral_meses_ck CHECK (meses_primer_empleo IS NULL OR meses_primer_empleo >= 0)
            );
            CREATE INDEX sg_medicion_laboral_situacion_idx ON seguimiento_egresado.medicion_laboral (id_situacion);
            CREATE INDEX sg_medicion_laboral_mod_laboral_idx ON seguimiento_egresado.medicion_laboral (id_modalidad_laboral);
            CREATE INDEX sg_medicion_laboral_mod_trabajo_idx ON seguimiento_egresado.medicion_laboral (id_modalidad_trabajo);
            CREATE INDEX sg_medicion_laboral_empresa_idx ON seguimiento_egresado.medicion_laboral (id_empresa);
            CREATE INDEX sg_medicion_laboral_sector_idx ON seguimiento_egresado.medicion_laboral (id_sector);
            CREATE INDEX sg_medicion_laboral_area_idx ON seguimiento_egresado.medicion_laboral (id_area);

            CREATE TABLE seguimiento_egresado.medicion_habilidad (
                id_invitacion BIGINT NOT NULL REFERENCES seguimiento_egresado.invitacion_egresado (id_invitacion) ON DELETE CASCADE,
                id_habilidad  BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_habilidad (id_habilidad) ON DELETE RESTRICT,
                id_nivel      BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_nivel_logro (id_nivel) ON DELETE RESTRICT,
                CONSTRAINT sg_medicion_habilidad_pk PRIMARY KEY (id_invitacion, id_habilidad)
            );
            CREATE INDEX sg_medicion_habilidad_habilidad_idx ON seguimiento_egresado.medicion_habilidad (id_habilidad);
            CREATE INDEX sg_medicion_habilidad_nivel_idx ON seguimiento_egresado.medicion_habilidad (id_nivel);

            CREATE TABLE seguimiento_egresado.evaluacion_competencia (
                id_invitacion  BIGINT NOT NULL REFERENCES seguimiento_egresado.invitacion_egresado (id_invitacion) ON DELETE CASCADE,
                id_competencia BIGINT NOT NULL REFERENCES seguimiento_egresado.competencia (id_competencia) ON DELETE RESTRICT,
                id_nivel       BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_nivel_logro (id_nivel) ON DELETE RESTRICT,
                CONSTRAINT sg_evaluacion_competencia_pk PRIMARY KEY (id_invitacion, id_competencia)
            );
            CREATE INDEX sg_evaluacion_competencia_competencia_idx ON seguimiento_egresado.evaluacion_competencia (id_competencia);
            CREATE INDEX sg_evaluacion_competencia_nivel_idx ON seguimiento_egresado.evaluacion_competencia (id_nivel);

            CREATE TABLE seguimiento_egresado.evaluacion_oe (
                id_invitacion BIGINT NOT NULL REFERENCES seguimiento_egresado.invitacion_egresado (id_invitacion) ON DELETE CASCADE,
                id_objetivo   BIGINT NOT NULL REFERENCES seguimiento_egresado.objetivo_educacional (id_objetivo) ON DELETE RESTRICT,
                id_nivel      BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_nivel_logro (id_nivel) ON DELETE RESTRICT,
                CONSTRAINT sg_evaluacion_oe_pk PRIMARY KEY (id_invitacion, id_objetivo)
            );
            CREATE INDEX sg_evaluacion_oe_objetivo_idx ON seguimiento_egresado.evaluacion_oe (id_objetivo);
            CREATE INDEX sg_evaluacion_oe_nivel_idx ON seguimiento_egresado.evaluacion_oe (id_nivel);

            CREATE TABLE seguimiento_egresado.satisfaccion_egresado (
                id_invitacion BIGINT PRIMARY KEY REFERENCES seguimiento_egresado.invitacion_egresado (id_invitacion) ON DELETE CASCADE,
                id_nivel      BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_nivel_satisfaccion (id_nivel) ON DELETE RESTRICT,
                comentario    TEXT
            );
            CREATE INDEX sg_satisfaccion_egresado_nivel_idx ON seguimiento_egresado.satisfaccion_egresado (id_nivel);

            CREATE TABLE seguimiento_egresado.respuesta_empresa (
                id_respuesta          BIGSERIAL PRIMARY KEY,
                id_invitacion         BIGINT NOT NULL REFERENCES seguimiento_egresado.invitacion_empresa (id_invitacion) ON DELETE CASCADE,
                id_sector             BIGINT REFERENCES comun.sector_economico (id_sector) ON DELETE RESTRICT,
                egresados_contratados SMALLINT,
                id_nivel_satisfaccion BIGINT REFERENCES seguimiento_egresado.cat_nivel_satisfaccion (id_nivel) ON DELETE RESTRICT,
                comentario            TEXT,
                respondida_en         TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT sg_respuesta_empresa_invitacion_uq UNIQUE (id_invitacion),
                CONSTRAINT sg_respuesta_empresa_contratados_ck CHECK (egresados_contratados IS NULL OR egresados_contratados >= 0)
            );
            CREATE INDEX sg_respuesta_empresa_sector_idx ON seguimiento_egresado.respuesta_empresa (id_sector);
            CREATE INDEX sg_respuesta_empresa_nivel_idx ON seguimiento_egresado.respuesta_empresa (id_nivel_satisfaccion);

            CREATE TABLE seguimiento_egresado.respuesta_empresa_habilidad (
                id_respuesta BIGINT NOT NULL REFERENCES seguimiento_egresado.respuesta_empresa (id_respuesta) ON DELETE CASCADE,
                id_habilidad BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_habilidad (id_habilidad) ON DELETE RESTRICT,
                id_nivel     BIGINT NOT NULL REFERENCES seguimiento_egresado.cat_nivel_logro (id_nivel) ON DELETE RESTRICT,
                CONSTRAINT sg_respuesta_empresa_habilidad_pk PRIMARY KEY (id_respuesta, id_habilidad)
            );
            CREATE INDEX sg_respuesta_empresa_habilidad_habilidad_idx ON seguimiento_egresado.respuesta_empresa_habilidad (id_habilidad);
            CREATE INDEX sg_respuesta_empresa_habilidad_nivel_idx ON seguimiento_egresado.respuesta_empresa_habilidad (id_nivel);

            CREATE TABLE seguimiento_egresado.indicador_egresado (
                id_indicador BIGSERIAL PRIMARY KEY,
                codigo       VARCHAR(10) NOT NULL,
                nombre       VARCHAR(180) NOT NULL,
                formula      TEXT,
                CONSTRAINT sg_indicador_egresado_codigo_uq UNIQUE (codigo)
            );

            CREATE TABLE seguimiento_egresado.reporte_generado (
                id_reporte     BIGSERIAL PRIMARY KEY,
                codigo_formato VARCHAR(30) NOT NULL,
                id_campana     BIGINT REFERENCES seguimiento_egresado.campana_seguimiento (id_campana) ON DELETE SET NULL,
                parametros     JSONB,
                id_archivo     BIGINT REFERENCES comun.archivo (id_archivo) ON DELETE SET NULL,
                generado_por   BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
                generado_en    TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX sg_reporte_generado_campana_idx ON seguimiento_egresado.reporte_generado (id_campana);
            CREATE INDEX sg_reporte_generado_archivo_idx ON seguimiento_egresado.reporte_generado (id_archivo);
            CREATE INDEX sg_reporte_generado_usuario_idx ON seguimiento_egresado.reporte_generado (generado_por);

            CREATE TABLE seguimiento_egresado.reporte_indicador (
                id_reporte   BIGINT NOT NULL REFERENCES seguimiento_egresado.reporte_generado (id_reporte) ON DELETE CASCADE,
                id_indicador BIGINT NOT NULL REFERENCES seguimiento_egresado.indicador_egresado (id_indicador) ON DELETE RESTRICT,
                valor        NUMERIC(14,4),
                CONSTRAINT sg_reporte_indicador_pk PRIMARY KEY (id_reporte, id_indicador)
            );
            CREATE INDEX sg_reporte_indicador_indicador_idx ON seguimiento_egresado.reporte_indicador (id_indicador);

            CREATE TABLE seguimiento_egresado.mejora_curricular (
                id_mejora     BIGSERIAL PRIMARY KEY,
                id_plan       BIGINT NOT NULL REFERENCES core.plan_estudios (id_plan) ON DELETE RESTRICT,
                id_campana    BIGINT REFERENCES seguimiento_egresado.campana_seguimiento (id_campana) ON DELETE SET NULL,
                descripcion   TEXT NOT NULL,
                prioridad     VARCHAR(20) NOT NULL DEFAULT 'Media',
                estado        VARCHAR(30) NOT NULL DEFAULT 'Propuesta',
                registrada_en TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX sg_mejora_curricular_plan_idx ON seguimiento_egresado.mejora_curricular (id_plan);
            CREATE INDEX sg_mejora_curricular_campana_idx ON seguimiento_egresado.mejora_curricular (id_campana);

            CREATE TABLE seguimiento_egresado.plan_accion (
                id_plan_accion BIGSERIAL PRIMARY KEY,
                id_mejora      BIGINT NOT NULL REFERENCES seguimiento_egresado.mejora_curricular (id_mejora) ON DELETE CASCADE,
                actividad      TEXT NOT NULL,
                id_responsable BIGINT NOT NULL REFERENCES seguridad.usuario (id_usuario) ON DELETE RESTRICT,
                fecha_limite   DATE,
                estado         VARCHAR(30) NOT NULL DEFAULT 'Pendiente'
            );
            CREATE INDEX sg_plan_accion_mejora_idx ON seguimiento_egresado.plan_accion (id_mejora);
            CREATE INDEX sg_plan_accion_responsable_idx ON seguimiento_egresado.plan_accion (id_responsable);

            CREATE TABLE seguimiento_egresado.plan_accion_evidencia (
                id_plan_accion BIGINT NOT NULL REFERENCES seguimiento_egresado.plan_accion (id_plan_accion) ON DELETE CASCADE,
                id_archivo     BIGINT NOT NULL REFERENCES comun.archivo (id_archivo) ON DELETE RESTRICT,
                CONSTRAINT sg_plan_accion_evidencia_pk PRIMARY KEY (id_plan_accion, id_archivo)
            );
            CREATE INDEX sg_plan_accion_evidencia_archivo_idx ON seguimiento_egresado.plan_accion_evidencia (id_archivo);
            SQL;
    }
};
