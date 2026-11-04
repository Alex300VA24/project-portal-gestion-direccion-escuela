<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS comun');

        Schema::table('core.seccion', function (Blueprint $table) {
            $table->unique(
                ['id_seccion', 'id_periodo', 'id_curso_plan'],
                'core_seccion_id_periodo_curso_uq',
            );
        });

        Schema::create('core.ambiente', function (Blueprint $table) {
            $table->bigIncrements('id_ambiente');
            $table->string('codigo', 30)->unique('core_ambiente_codigo_uq');
            $table->string('nombre', 150);
            $table->string('tipo', 40);
            $table->unsignedInteger('capacidad');
            $table->string('pabellon', 80)->nullable();
            $table->smallInteger('piso')->nullable();
            $table->string('estado', 20)->default('Activo');
        });

        Schema::create('core.linea_investigacion', function (Blueprint $table) {
            $table->bigIncrements('id_linea');
            $table->bigInteger('id_escuela');
            $table->string('codigo', 30)->unique('core_linea_codigo_uq');
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('Activo');
            $table->index('id_escuela', 'core_linea_escuela_idx');
            $table->foreign('id_escuela', 'core_linea_escuela_fk')
                ->references('id_escuela')->on('core.escuela_profesional')->restrictOnDelete();
        });

        Schema::create('core.docente_linea', function (Blueprint $table) {
            $table->bigInteger('id_docente');
            $table->bigInteger('id_linea');
            $table->primary(['id_docente', 'id_linea'], 'core_docente_linea_pk');
            $table->index('id_linea', 'core_docente_linea_linea_idx');
            $table->foreign('id_docente', 'core_docente_linea_docente_fk')
                ->references('id_docente')->on('core.docente')->cascadeOnDelete();
            $table->foreign('id_linea', 'core_docente_linea_linea_fk')
                ->references('id_linea')->on('core.linea_investigacion')->cascadeOnDelete();
        });

        Schema::create('seguridad.modulo_sistema', function (Blueprint $table) {
            $table->bigIncrements('id_modulo');
            $table->string('codigo', 50)->unique('seg_modulo_codigo_uq');
            $table->string('nombre', 120);
            $table->string('esquema', 63)->unique('seg_modulo_esquema_uq');
            $table->boolean('activo')->default(true);
        });

        Schema::create('comun.sector_economico', function (Blueprint $table) {
            $table->bigIncrements('id_sector');
            $table->string('codigo', 30)->unique('com_sector_codigo_uq');
            $table->string('nombre', 150)->unique('com_sector_nombre_uq');
        });

        Schema::create('comun.empresa', function (Blueprint $table) {
            $table->bigIncrements('id_empresa');
            $table->string('ruc', 11)->unique('com_empresa_ruc_uq');
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 200)->nullable();
            $table->bigInteger('id_sector')->nullable();
            $table->bigInteger('id_distrito')->nullable();
            $table->text('direccion')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 180)->nullable();
            $table->string('pagina_web', 250)->nullable();
            $table->string('estado', 20)->default('Activo');
            $table->index('id_sector', 'com_empresa_sector_idx');
            $table->index('id_distrito', 'com_empresa_distrito_idx');
            $table->foreign('id_sector', 'com_empresa_sector_fk')
                ->references('id_sector')->on('comun.sector_economico')->restrictOnDelete();
            $table->foreign('id_distrito', 'com_empresa_distrito_fk')
                ->references('id_distrito')->on('core.ubigeo_distrito')->restrictOnDelete();
        });

        Schema::create('comun.contacto_empresa', function (Blueprint $table) {
            $table->bigIncrements('id_contacto');
            $table->bigInteger('id_empresa');
            $table->bigInteger('id_persona');
            $table->string('cargo', 120)->nullable();
            $table->string('correo_laboral', 180)->nullable();
            $table->string('telefono_laboral', 30)->nullable();
            $table->string('estado', 20)->default('Activo');
            $table->unique(['id_empresa', 'id_persona'], 'com_contacto_empresa_persona_uq');
            $table->index('id_persona', 'com_contacto_persona_idx');
            $table->foreign('id_empresa', 'com_contacto_empresa_fk')
                ->references('id_empresa')->on('comun.empresa')->cascadeOnDelete();
            $table->foreign('id_persona', 'com_contacto_persona_fk')
                ->references('id_persona')->on('core.persona')->restrictOnDelete();
        });

        Schema::create('comun.archivo', function (Blueprint $table) {
            $table->bigIncrements('id_archivo');
            $table->bigInteger('id_modulo');
            $table->string('nombre_original', 255);
            $table->string('ruta_almacenamiento', 500)->unique('com_archivo_ruta_uq');
            $table->string('tipo_mime', 120);
            $table->unsignedBigInteger('tamano_bytes');
            $table->string('hash_sha256', 64)->nullable();
            $table->bigInteger('subido_por');
            $table->timestampTz('subido_en')->useCurrent();
            $table->index('id_modulo', 'com_archivo_modulo_idx');
            $table->index('subido_por', 'com_archivo_usuario_idx');
            $table->foreign('id_modulo', 'com_archivo_modulo_fk')
                ->references('id_modulo')->on('seguridad.modulo_sistema')->restrictOnDelete();
            $table->foreign('subido_por', 'com_archivo_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('comun.notificacion', function (Blueprint $table) {
            $table->bigIncrements('id_notificacion');
            $table->bigInteger('id_modulo');
            $table->bigInteger('id_usuario_destino');
            $table->string('tipo_evento', 80);
            $table->string('titulo', 180);
            $table->text('mensaje');
            $table->string('referencia_tabla', 120)->nullable();
            $table->bigInteger('referencia_id')->nullable();
            $table->string('canal', 30)->default('sistema');
            $table->timestampTz('creada_en')->useCurrent();
            $table->timestampTz('enviada_en')->nullable();
            $table->timestampTz('leida_en')->nullable();
            $table->index('id_modulo', 'com_notificacion_modulo_idx');
            $table->index('id_usuario_destino', 'com_notificacion_usuario_idx');
            $table->foreign('id_modulo', 'com_notificacion_modulo_fk')
                ->references('id_modulo')->on('seguridad.modulo_sistema')->restrictOnDelete();
            $table->foreign('id_usuario_destino', 'com_notificacion_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')->cascadeOnDelete();
        });

        Schema::create('comun.parametro_sistema', function (Blueprint $table) {
            $table->bigIncrements('id_parametro');
            $table->bigInteger('id_modulo');
            $table->string('clave', 100);
            $table->text('valor');
            $table->string('tipo_dato', 30);
            $table->text('descripcion')->nullable();
            $table->unique(['id_modulo', 'clave'], 'com_parametro_modulo_clave_uq');
            $table->foreign('id_modulo', 'com_parametro_modulo_fk')
                ->references('id_modulo')->on('seguridad.modulo_sistema')->cascadeOnDelete();
        });

        Schema::create('comun.dia_no_laborable', function (Blueprint $table) {
            $table->date('fecha')->primary('com_dia_no_laborable_pk');
            $table->string('descripcion', 180);
        });

        $this->addChecks();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('comun.dia_no_laborable');
        Schema::dropIfExists('comun.parametro_sistema');
        Schema::dropIfExists('comun.notificacion');
        Schema::dropIfExists('comun.archivo');
        Schema::dropIfExists('comun.contacto_empresa');
        Schema::dropIfExists('comun.empresa');
        Schema::dropIfExists('comun.sector_economico');
        Schema::dropIfExists('seguridad.modulo_sistema');
        Schema::dropIfExists('core.docente_linea');
        Schema::dropIfExists('core.linea_investigacion');
        Schema::dropIfExists('core.ambiente');
        Schema::table('core.seccion', function (Blueprint $table) {
            $table->dropUnique('core_seccion_id_periodo_curso_uq');
        });
        DB::statement('DROP SCHEMA IF EXISTS comun');
    }

    private function addChecks(): void
    {
        DB::statement('ALTER TABLE core.ambiente ADD CONSTRAINT core_ambiente_capacidad_ck CHECK (capacidad > 0)');
        DB::statement('ALTER TABLE comun.archivo ADD CONSTRAINT com_archivo_tamano_ck CHECK (tamano_bytes >= 0)');
        DB::statement("ALTER TABLE comun.parametro_sistema ADD CONSTRAINT com_parametro_tipo_ck CHECK (tipo_dato IN ('texto', 'numero', 'booleano', 'json', 'fecha'))");
    }
};
