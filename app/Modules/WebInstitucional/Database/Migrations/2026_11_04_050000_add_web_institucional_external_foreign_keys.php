<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enlaza web_institucional con los esquemas compartidos (core, seguridad y
 * comun) según esquemas_y_origen_de_tablas.pdf, sección B.5.
 */
return new class extends Migration
{
    /** @var array<string, array{table: string, column: string, references: string, on: string, delete: string}> */
    private const FOREIGN_KEYS = [
        'wi_publicacion_portada_fk' => ['table' => 'publicacion', 'column' => 'id_archivo_portada', 'references' => 'comun.archivo (id_archivo)', 'on' => 'id_archivo', 'delete' => 'RESTRICT'],
        'wi_publicacion_autor_fk' => ['table' => 'publicacion', 'column' => 'autor', 'references' => 'seguridad.usuario (id_usuario)', 'on' => 'id_usuario', 'delete' => 'RESTRICT'],
        'wi_publicacion_revisor_fk' => ['table' => 'publicacion', 'column' => 'revisado_por', 'references' => 'seguridad.usuario (id_usuario)', 'on' => 'id_usuario', 'delete' => 'RESTRICT'],
        'wi_publicacion_archivo_archivo_fk' => ['table' => 'publicacion_archivo', 'column' => 'id_archivo', 'references' => 'comun.archivo (id_archivo)', 'on' => 'id_archivo', 'delete' => 'RESTRICT'],
        'wi_campana_admision_periodo_fk' => ['table' => 'campana_admision', 'column' => 'id_periodo', 'references' => 'core.periodo_academico (id_periodo)', 'on' => 'id_periodo', 'delete' => 'RESTRICT'],
        'wi_campana_admision_revisor_fk' => ['table' => 'campana_admision', 'column' => 'revisado_por', 'references' => 'seguridad.usuario (id_usuario)', 'on' => 'id_usuario', 'delete' => 'RESTRICT'],
        'wi_campana_modalidad_modalidad_fk' => ['table' => 'campana_modalidad', 'column' => 'id_modalidad', 'references' => 'core.modalidad_ingreso (id_modalidad)', 'on' => 'id_modalidad', 'delete' => 'RESTRICT'],
        'wi_documento_oficial_archivo_fk' => ['table' => 'documento_oficial', 'column' => 'id_archivo', 'references' => 'comun.archivo (id_archivo)', 'on' => 'id_archivo', 'delete' => 'RESTRICT'],
        'wi_documento_autorizador_fk' => ['table' => 'documento_oficial', 'column' => 'autorizado_por', 'references' => 'seguridad.usuario (id_usuario)', 'on' => 'id_usuario', 'delete' => 'RESTRICT'],
        'wi_plan_publicacion_plan_fk' => ['table' => 'plan_estudios_publicacion', 'column' => 'id_plan', 'references' => 'core.plan_estudios (id_plan)', 'on' => 'id_plan', 'delete' => 'RESTRICT'],
        'wi_plan_publicacion_archivo_fk' => ['table' => 'plan_estudios_publicacion', 'column' => 'id_archivo_pdf', 'references' => 'comun.archivo (id_archivo)', 'on' => 'id_archivo', 'delete' => 'RESTRICT'],
        'wi_plan_publicacion_revisor_fk' => ['table' => 'plan_estudios_publicacion', 'column' => 'revisado_por', 'references' => 'seguridad.usuario (id_usuario)', 'on' => 'id_usuario', 'delete' => 'RESTRICT'],
        'wi_perfil_docente_docente_fk' => ['table' => 'perfil_docente_web', 'column' => 'id_docente', 'references' => 'core.docente (id_docente)', 'on' => 'id_docente', 'delete' => 'RESTRICT'],
        'wi_perfil_docente_foto_fk' => ['table' => 'perfil_docente_web', 'column' => 'id_archivo_foto', 'references' => 'comun.archivo (id_archivo)', 'on' => 'id_archivo', 'delete' => 'RESTRICT'],
        'wi_perfil_docente_revisor_fk' => ['table' => 'perfil_docente_web', 'column' => 'revisado_por', 'references' => 'seguridad.usuario (id_usuario)', 'on' => 'id_usuario', 'delete' => 'RESTRICT'],
        'wi_docente_especialidad_docente_fk' => ['table' => 'docente_especialidad', 'column' => 'id_docente', 'references' => 'core.docente (id_docente)', 'on' => 'id_docente', 'delete' => 'CASCADE'],
        'wi_consulta_contacto_responsable_fk' => ['table' => 'consulta_contacto', 'column' => 'respondido_por', 'references' => 'seguridad.usuario (id_usuario)', 'on' => 'id_usuario', 'delete' => 'RESTRICT'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::FOREIGN_KEYS as $name => $fk) {
            DB::statement(sprintf(
                'ALTER TABLE web_institucional.%s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s ON DELETE %s',
                $fk['table'],
                $name,
                $fk['column'],
                $fk['references'],
                $fk['delete'],
            ));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(self::FOREIGN_KEYS, true) as $name => $fk) {
            DB::statement(sprintf(
                'ALTER TABLE web_institucional.%s DROP CONSTRAINT IF EXISTS %s',
                $fk['table'],
                $name,
            ));
        }
    }
};
