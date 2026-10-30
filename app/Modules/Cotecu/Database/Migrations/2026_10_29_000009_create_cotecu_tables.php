<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ===========================================================================
        // 1. TABLAS GENERALES Y MAESTRAS (SIN ROLES)
        // ===========================================================================

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('documento_identidad', 20)->unique();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('correo_institucional', 150)->unique();
            $table->string('contrasenia_hash', 255);
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 150);
            $table->integer('ciclo_plan');
            $table->integer('creditos');
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        // ===========================================================================
        // 2. MÓDULO DE REVISIÓN DE PLANES CURRICULARES
        // ===========================================================================

        Schema::create('listas_cotejo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('version', 20);
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        Schema::create('lista_cotejo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_cotejo_id')->constrained('listas_cotejo')->onDelete('cascade');
            $table->text('descripcion');
            $table->integer('orden');
            $table->timestamps();
        });

        Schema::create('planes_curriculares', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->integer('version');
            $table->string('archivo_url', 500);
            $table->string('estado', 50)->default('Pendiente');
            $table->timestamps();
        });

        Schema::create('revisiones_plan_curricular', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_curricular_id')->constrained('planes_curriculares');
            $table->foreignId('lista_cotejo_id')->constrained('listas_cotejo');
            $table->foreignId('revisor_id')->constrained('usuarios');
            $table->string('estado_final', 50);
            $table->text('comentario_general')->nullable();
            $table->string('resultado_url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('resultados_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('revisiones_plan_curricular')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('lista_cotejo_items');
            $table->boolean('cumple');
            $table->text('observacion_item')->nullable();
            $table->timestamps();
        });

        // ===========================================================================
        // 3. MÓDULO DE REVISIÓN DE SÍLABOS
        // ===========================================================================

        Schema::create('listas_cotejo_silabos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('version', 20);
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        Schema::create('lista_cotejo_silabos_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_cotejo_silabo_id')->constrained('listas_cotejo_silabos')->onDelete('cascade');
            $table->text('descripcion');
            $table->integer('orden');
            $table->timestamps();
        });

        Schema::create('silabos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos');
            $table->foreignId('semestre_academico_id')->constrained('semestres_academicos');
            $table->foreignId('profesor_id')->constrained('usuarios');
            $table->integer('version');
            $table->string('archivo_url', 500);
            $table->string('estado', 50)->default('Pendiente');
            $table->timestamps();
        });

        Schema::create('revisiones_silabo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('silabo_id')->constrained('silabos');
            $table->foreignId('lista_cotejo_silabo_id')->constrained('listas_cotejo_silabos');
            $table->foreignId('revisor_id')->constrained('usuarios');
            $table->string('estado_final', 50);
            $table->text('comentario_general')->nullable();
            $table->string('resultado_url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('resultados_silabos_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_silabo_id')->constrained('revisiones_silabo')->onDelete('cascade');
            $table->foreignId('item_silabo_id')->constrained('lista_cotejo_silabos_items');
            $table->boolean('cumple');
            $table->text('observacion_item')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminación en orden inverso para evitar conflictos de claves foráneas
        Schema::dropIfExists('resultados_silabos_items');
        Schema::dropIfExists('revisiones_silabo');
        Schema::dropIfExists('silabos');
        Schema::dropIfExists('lista_cotejo_silabos_items');
        Schema::dropIfExists('listas_cotejo_silabos');
        
        Schema::dropIfExists('resultados_items');
        Schema::dropIfExists('revisiones_plan_curricular');
        Schema::dropIfExists('planes_curriculares');
        Schema::dropIfExists('lista_cotejo_items');
        Schema::dropIfExists('listas_cotejo');
        
        Schema::dropIfExists('semestres_academicos');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('usuarios');
    }
};
