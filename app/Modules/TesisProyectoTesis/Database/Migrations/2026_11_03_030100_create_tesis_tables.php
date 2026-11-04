<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE tesis.proyecto_tesis (
                id_proyecto BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                codigo VARCHAR(40) NOT NULL CONSTRAINT tes_proyecto_codigo_uq UNIQUE,
                titulo VARCHAR(300) NOT NULL,
                resumen TEXT NOT NULL,
                id_linea BIGINT NOT NULL,
                id_periodo_registro BIGINT NOT NULL,
                etapa VARCHAR(30) NOT NULL,
                estado VARCHAR(30) NOT NULL,
                registrado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT tes_proyecto_linea_fk FOREIGN KEY (id_linea) REFERENCES core.linea_investigacion(id_linea) ON DELETE RESTRICT,
                CONSTRAINT tes_proyecto_periodo_fk FOREIGN KEY (id_periodo_registro) REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT,
                CONSTRAINT tes_proyecto_etapa_ck CHECK (etapa IN ('Proyecto', 'Tesis'))
            );
            CREATE INDEX tes_proyecto_linea_idx ON tesis.proyecto_tesis(id_linea);
            CREATE INDEX tes_proyecto_periodo_idx ON tesis.proyecto_tesis(id_periodo_registro);

            CREATE TABLE tesis.proyecto_estudiante (
                id_proyecto BIGINT NOT NULL,
                id_estudiante BIGINT NOT NULL,
                CONSTRAINT tes_proyecto_estudiante_pk PRIMARY KEY (id_proyecto, id_estudiante),
                CONSTRAINT tes_pe_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE,
                CONSTRAINT tes_pe_estudiante_fk FOREIGN KEY (id_estudiante) REFERENCES core.estudiante(id_estudiante) ON DELETE RESTRICT
            );
            CREATE INDEX tes_pe_estudiante_idx ON tesis.proyecto_estudiante(id_estudiante);

            CREATE TABLE tesis.tipo_solicitud (
                id_tipo_solicitud BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL CONSTRAINT tes_tipo_solicitud_nombre_uq UNIQUE
            );

            CREATE TABLE tesis.solicitud (
                id_solicitud BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                id_tipo_solicitud BIGINT NOT NULL,
                presentada_por BIGINT NOT NULL,
                id_archivo BIGINT NOT NULL,
                estado VARCHAR(30) NOT NULL,
                presentada_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT tes_solicitud_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE RESTRICT,
                CONSTRAINT tes_solicitud_tipo_fk FOREIGN KEY (id_tipo_solicitud) REFERENCES tesis.tipo_solicitud(id_tipo_solicitud) ON DELETE RESTRICT,
                CONSTRAINT tes_solicitud_usuario_fk FOREIGN KEY (presentada_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT,
                CONSTRAINT tes_solicitud_archivo_fk FOREIGN KEY (id_archivo) REFERENCES comun.archivo(id_archivo) ON DELETE RESTRICT
            );
            CREATE INDEX tes_solicitud_proyecto_idx ON tesis.solicitud(id_proyecto);
            CREATE INDEX tes_solicitud_tipo_idx ON tesis.solicitud(id_tipo_solicitud);
            CREATE INDEX tes_solicitud_usuario_idx ON tesis.solicitud(presentada_por);
            CREATE INDEX tes_solicitud_archivo_idx ON tesis.solicitud(id_archivo);

            CREATE TABLE tesis.resolucion (
                id_resolucion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                numero VARCHAR(80) NOT NULL CONSTRAINT tes_resolucion_numero_uq UNIQUE,
                fecha DATE NOT NULL,
                asunto TEXT NOT NULL,
                id_solicitud BIGINT NOT NULL,
                id_archivo BIGINT NOT NULL,
                CONSTRAINT tes_resolucion_solicitud_fk FOREIGN KEY (id_solicitud) REFERENCES tesis.solicitud(id_solicitud) ON DELETE RESTRICT,
                CONSTRAINT tes_resolucion_archivo_fk FOREIGN KEY (id_archivo) REFERENCES comun.archivo(id_archivo) ON DELETE RESTRICT
            );
            CREATE INDEX tes_resolucion_solicitud_idx ON tesis.resolucion(id_solicitud);
            CREATE INDEX tes_resolucion_archivo_idx ON tesis.resolucion(id_archivo);

            CREATE TABLE tesis.asesor_proyecto (
                id_asesor BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                id_docente BIGINT NOT NULL,
                tipo VARCHAR(30) NOT NULL,
                id_resolucion BIGINT,
                estado VARCHAR(30) NOT NULL,
                CONSTRAINT tes_asesor_proyecto_docente_uq UNIQUE (id_proyecto, id_docente),
                CONSTRAINT tes_asesor_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE,
                CONSTRAINT tes_asesor_docente_fk FOREIGN KEY (id_docente) REFERENCES core.docente(id_docente) ON DELETE RESTRICT,
                CONSTRAINT tes_asesor_resolucion_fk FOREIGN KEY (id_resolucion) REFERENCES tesis.resolucion(id_resolucion) ON DELETE RESTRICT
            );
            CREATE INDEX tes_asesor_docente_idx ON tesis.asesor_proyecto(id_docente);
            CREATE INDEX tes_asesor_resolucion_idx ON tesis.asesor_proyecto(id_resolucion);

            CREATE TABLE tesis.jurado_proyecto (
                id_jurado BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                id_docente BIGINT NOT NULL,
                cargo VARCHAR(40) NOT NULL,
                id_resolucion BIGINT,
                estado VARCHAR(30) NOT NULL,
                CONSTRAINT tes_jurado_proyecto_docente_uq UNIQUE (id_proyecto, id_docente),
                CONSTRAINT tes_jurado_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE,
                CONSTRAINT tes_jurado_docente_fk FOREIGN KEY (id_docente) REFERENCES core.docente(id_docente) ON DELETE RESTRICT,
                CONSTRAINT tes_jurado_resolucion_fk FOREIGN KEY (id_resolucion) REFERENCES tesis.resolucion(id_resolucion) ON DELETE RESTRICT
            );
            CREATE INDEX tes_jurado_docente_idx ON tesis.jurado_proyecto(id_docente);
            CREATE INDEX tes_jurado_resolucion_idx ON tesis.jurado_proyecto(id_resolucion);

            CREATE TABLE tesis.colaborador (
                id_colaborador BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                id_persona BIGINT NOT NULL,
                institucion VARCHAR(180),
                rol_colaboracion VARCHAR(100) NOT NULL,
                CONSTRAINT tes_colaborador_proyecto_persona_uq UNIQUE (id_proyecto, id_persona),
                CONSTRAINT tes_colaborador_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE,
                CONSTRAINT tes_colaborador_persona_fk FOREIGN KEY (id_persona) REFERENCES core.persona(id_persona) ON DELETE RESTRICT
            );
            CREATE INDEX tes_colaborador_persona_idx ON tesis.colaborador(id_persona);

            CREATE TABLE tesis.actividad_cronograma (
                id_actividad BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                descripcion TEXT NOT NULL,
                fecha_inicio DATE NOT NULL,
                fecha_fin DATE NOT NULL,
                orden SMALLINT NOT NULL,
                CONSTRAINT tes_actividad_proyecto_orden_uq UNIQUE (id_proyecto, orden),
                CONSTRAINT tes_actividad_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE,
                CONSTRAINT tes_actividad_fechas_ck CHECK (fecha_fin >= fecha_inicio),
                CONSTRAINT tes_actividad_orden_ck CHECK (orden > 0)
            );

            CREATE TABLE tesis.recurso (
                id_recurso BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                tipo VARCHAR(60) NOT NULL,
                descripcion TEXT NOT NULL,
                CONSTRAINT tes_recurso_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE
            );

            CREATE TABLE tesis.partida_presupuesto (
                id_partida BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_recurso BIGINT NOT NULL,
                cantidad NUMERIC(12,2) NOT NULL,
                unidad VARCHAR(40) NOT NULL,
                costo_unitario NUMERIC(14,2) NOT NULL,
                fuente_financiamiento VARCHAR(150),
                CONSTRAINT tes_partida_recurso_fk FOREIGN KEY (id_recurso) REFERENCES tesis.recurso(id_recurso) ON DELETE CASCADE,
                CONSTRAINT tes_partida_cantidad_ck CHECK (cantidad > 0),
                CONSTRAINT tes_partida_costo_ck CHECK (costo_unitario >= 0)
            );

            CREATE TABLE tesis.entrega (
                id_entrega BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                etapa VARCHAR(40) NOT NULL,
                version INTEGER NOT NULL,
                id_archivo BIGINT NOT NULL,
                entregado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT tes_entrega_proyecto_etapa_version_uq UNIQUE (id_proyecto, etapa, version),
                CONSTRAINT tes_entrega_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE,
                CONSTRAINT tes_entrega_archivo_fk FOREIGN KEY (id_archivo) REFERENCES comun.archivo(id_archivo) ON DELETE RESTRICT,
                CONSTRAINT tes_entrega_version_ck CHECK (version > 0)
            );
            CREATE INDEX tes_entrega_archivo_idx ON tesis.entrega(id_archivo);

            CREATE TABLE tesis.reporte_similitud (
                id_reporte BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_entrega BIGINT NOT NULL CONSTRAINT tes_reporte_entrega_uq UNIQUE,
                porcentaje NUMERIC(5,2) NOT NULL,
                herramienta VARCHAR(100) NOT NULL,
                id_archivo BIGINT NOT NULL,
                generado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT tes_reporte_entrega_fk FOREIGN KEY (id_entrega) REFERENCES tesis.entrega(id_entrega) ON DELETE CASCADE,
                CONSTRAINT tes_reporte_archivo_fk FOREIGN KEY (id_archivo) REFERENCES comun.archivo(id_archivo) ON DELETE RESTRICT,
                CONSTRAINT tes_reporte_porcentaje_ck CHECK (porcentaje BETWEEN 0 AND 100)
            );
            CREATE INDEX tes_reporte_archivo_idx ON tesis.reporte_similitud(id_archivo);

            CREATE TABLE tesis.rubrica (
                id_rubrica BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre VARCHAR(150) NOT NULL,
                etapa VARCHAR(40) NOT NULL,
                version INTEGER NOT NULL,
                estado VARCHAR(30) NOT NULL,
                CONSTRAINT tes_rubrica_nombre_version_uq UNIQUE (nombre, version),
                CONSTRAINT tes_rubrica_version_ck CHECK (version > 0)
            );

            CREATE TABLE tesis.criterio_rubrica (
                id_criterio BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_rubrica BIGINT NOT NULL,
                descripcion TEXT NOT NULL,
                puntaje_maximo NUMERIC(6,2) NOT NULL,
                orden SMALLINT NOT NULL,
                CONSTRAINT tes_criterio_rubrica_orden_uq UNIQUE (id_rubrica, orden),
                CONSTRAINT tes_criterio_rubrica_fk FOREIGN KEY (id_rubrica) REFERENCES tesis.rubrica(id_rubrica) ON DELETE CASCADE,
                CONSTRAINT tes_criterio_puntaje_ck CHECK (puntaje_maximo > 0),
                CONSTRAINT tes_criterio_orden_ck CHECK (orden > 0)
            );

            CREATE TABLE tesis.revision (
                id_revision BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_entrega BIGINT NOT NULL,
                id_docente BIGINT NOT NULL,
                id_rubrica BIGINT NOT NULL,
                dictamen VARCHAR(40) NOT NULL,
                comentario TEXT,
                revisado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT tes_revision_entrega_docente_uq UNIQUE (id_entrega, id_docente),
                CONSTRAINT tes_revision_entrega_fk FOREIGN KEY (id_entrega) REFERENCES tesis.entrega(id_entrega) ON DELETE CASCADE,
                CONSTRAINT tes_revision_docente_fk FOREIGN KEY (id_docente) REFERENCES core.docente(id_docente) ON DELETE RESTRICT,
                CONSTRAINT tes_revision_rubrica_fk FOREIGN KEY (id_rubrica) REFERENCES tesis.rubrica(id_rubrica) ON DELETE RESTRICT
            );
            CREATE INDEX tes_revision_docente_idx ON tesis.revision(id_docente);
            CREATE INDEX tes_revision_rubrica_idx ON tesis.revision(id_rubrica);

            CREATE TABLE tesis.detalle_evaluacion (
                id_revision BIGINT NOT NULL,
                id_criterio BIGINT NOT NULL,
                puntaje NUMERIC(6,2) NOT NULL,
                CONSTRAINT tes_detalle_evaluacion_pk PRIMARY KEY (id_revision, id_criterio),
                CONSTRAINT tes_detalle_revision_fk FOREIGN KEY (id_revision) REFERENCES tesis.revision(id_revision) ON DELETE CASCADE,
                CONSTRAINT tes_detalle_criterio_fk FOREIGN KEY (id_criterio) REFERENCES tesis.criterio_rubrica(id_criterio) ON DELETE RESTRICT,
                CONSTRAINT tes_detalle_puntaje_ck CHECK (puntaje >= 0)
            );
            CREATE INDEX tes_detalle_criterio_idx ON tesis.detalle_evaluacion(id_criterio);

            CREATE TABLE tesis.observacion (
                id_observacion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_revision BIGINT NOT NULL,
                descripcion TEXT NOT NULL,
                estado VARCHAR(30) NOT NULL,
                subsanada_en TIMESTAMPTZ,
                CONSTRAINT tes_observacion_revision_fk FOREIGN KEY (id_revision) REFERENCES tesis.revision(id_revision) ON DELETE CASCADE
            );

            CREATE TABLE tesis.historial_estado (
                id_historial BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                estado_anterior VARCHAR(30),
                estado_nuevo VARCHAR(30) NOT NULL,
                cambiado_por BIGINT NOT NULL,
                cambiado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                observacion TEXT,
                CONSTRAINT tes_historial_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE CASCADE,
                CONSTRAINT tes_historial_usuario_fk FOREIGN KEY (cambiado_por) REFERENCES seguridad.usuario(id_usuario) ON DELETE RESTRICT
            );
            CREATE INDEX tes_historial_usuario_idx ON tesis.historial_estado(cambiado_por);

            CREATE TABLE tesis.sustentacion (
                id_sustentacion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_proyecto BIGINT NOT NULL,
                fecha_hora TIMESTAMPTZ NOT NULL,
                modalidad VARCHAR(30) NOT NULL,
                id_ambiente BIGINT,
                enlace_virtual VARCHAR(500),
                id_resolucion BIGINT,
                estado VARCHAR(30) NOT NULL,
                CONSTRAINT tes_sustentacion_proyecto_fk FOREIGN KEY (id_proyecto) REFERENCES tesis.proyecto_tesis(id_proyecto) ON DELETE RESTRICT,
                CONSTRAINT tes_sustentacion_ambiente_fk FOREIGN KEY (id_ambiente) REFERENCES core.ambiente(id_ambiente) ON DELETE RESTRICT,
                CONSTRAINT tes_sustentacion_resolucion_fk FOREIGN KEY (id_resolucion) REFERENCES tesis.resolucion(id_resolucion) ON DELETE RESTRICT,
                CONSTRAINT tes_sustentacion_modalidad_ck CHECK (modalidad IN ('Presencial', 'Virtual', 'Hibrida')),
                CONSTRAINT tes_sustentacion_ubicacion_ck CHECK (id_ambiente IS NOT NULL OR enlace_virtual IS NOT NULL)
            );
            CREATE INDEX tes_sustentacion_proyecto_idx ON tesis.sustentacion(id_proyecto);
            CREATE INDEX tes_sustentacion_ambiente_idx ON tesis.sustentacion(id_ambiente);
            CREATE INDEX tes_sustentacion_resolucion_idx ON tesis.sustentacion(id_resolucion);

            CREATE TABLE tesis.resultado_jurado (
                id_sustentacion BIGINT NOT NULL,
                id_jurado BIGINT NOT NULL,
                nota NUMERIC(5,2) NOT NULL,
                dictamen VARCHAR(40) NOT NULL,
                CONSTRAINT tes_resultado_jurado_pk PRIMARY KEY (id_sustentacion, id_jurado),
                CONSTRAINT tes_resultado_sustentacion_fk FOREIGN KEY (id_sustentacion) REFERENCES tesis.sustentacion(id_sustentacion) ON DELETE CASCADE,
                CONSTRAINT tes_resultado_jurado_fk FOREIGN KEY (id_jurado) REFERENCES tesis.jurado_proyecto(id_jurado) ON DELETE RESTRICT,
                CONSTRAINT tes_resultado_nota_ck CHECK (nota BETWEEN 0 AND 20)
            );
            CREATE INDEX tes_resultado_jurado_idx ON tesis.resultado_jurado(id_jurado);

            CREATE TABLE tesis.acta (
                id_acta BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_sustentacion BIGINT NOT NULL CONSTRAINT tes_acta_sustentacion_uq UNIQUE,
                numero VARCHAR(80) NOT NULL CONSTRAINT tes_acta_numero_uq UNIQUE,
                nota_final NUMERIC(5,2) NOT NULL,
                calificacion VARCHAR(50) NOT NULL,
                id_archivo BIGINT NOT NULL,
                emitida_en TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT tes_acta_sustentacion_fk FOREIGN KEY (id_sustentacion) REFERENCES tesis.sustentacion(id_sustentacion) ON DELETE RESTRICT,
                CONSTRAINT tes_acta_archivo_fk FOREIGN KEY (id_archivo) REFERENCES comun.archivo(id_archivo) ON DELETE RESTRICT,
                CONSTRAINT tes_acta_nota_ck CHECK (nota_final BETWEEN 0 AND 20)
            );
            CREATE INDEX tes_acta_archivo_idx ON tesis.acta(id_archivo);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['acta', 'resultado_jurado', 'sustentacion', 'historial_estado', 'observacion',
            'detalle_evaluacion', 'revision', 'criterio_rubrica', 'rubrica', 'reporte_similitud',
            'entrega', 'partida_presupuesto', 'recurso', 'actividad_cronograma', 'colaborador',
            'jurado_proyecto', 'asesor_proyecto', 'resolucion', 'solicitud', 'tipo_solicitud',
            'proyecto_estudiante', 'proyecto_tesis'] as $table) {
            Schema::dropIfExists("tesis.{$table}");
        }
    }
};
