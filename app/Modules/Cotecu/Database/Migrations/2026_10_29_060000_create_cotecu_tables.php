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

        Schema::create('cotecu.listas_cotejo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('version', 20);
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        Schema::create('cotecu.lista_cotejo_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('lista_cotejo_id');
            $table->text('descripcion');
            $table->integer('orden');
            $table->timestamps();
            $table->foreign('lista_cotejo_id', 'cotecu_lista_item_lista_fk')
                ->references('id')
                ->on('cotecu.listas_cotejo')
                ->cascadeOnDelete();
        });

        Schema::create('cotecu.planes_curriculares', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_plan');
            $table->string('nombre', 200);
            $table->integer('version');
            $table->string('archivo_url', 500);
            $table->string('estado', 50)->default('Pendiente');
            $table->timestamps();
            $table->index('id_plan', 'cotecu_plan_id_plan_idx');
            $table->foreign('id_plan', 'cotecu_plan_id_plan_fk')
                ->references('id_plan')
                ->on('core.plan_estudios')
                ->restrictOnDelete();
        });

        Schema::create('cotecu.revisiones_plan_curricular', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('plan_curricular_id');
            $table->bigInteger('lista_cotejo_id');
            $table->bigInteger('id_revisor');
            $table->string('estado_final', 50);
            $table->text('comentario_general')->nullable();
            $table->string('resultado_url', 500)->nullable();
            $table->timestamps();
            $table->index('id_revisor', 'cotecu_revision_plan_revisor_idx');
            $table->foreign('plan_curricular_id', 'cotecu_revision_plan_documento_fk')
                ->references('id')
                ->on('cotecu.planes_curriculares')
                ->restrictOnDelete();
            $table->foreign('lista_cotejo_id', 'cotecu_revision_plan_lista_fk')
                ->references('id')
                ->on('cotecu.listas_cotejo')
                ->restrictOnDelete();
            $table->foreign('id_revisor', 'cotecu_revision_plan_revisor_fk')
                ->references('id_usuario')
                ->on('seguridad.usuario')
                ->restrictOnDelete();
        });

        Schema::create('cotecu.resultados_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('revision_id');
            $table->bigInteger('item_id');
            $table->boolean('cumple');
            $table->text('observacion_item')->nullable();
            $table->timestamps();
            $table->foreign('revision_id', 'cotecu_resultado_plan_revision_fk')
                ->references('id')
                ->on('cotecu.revisiones_plan_curricular')
                ->cascadeOnDelete();
            $table->foreign('item_id', 'cotecu_resultado_plan_item_fk')
                ->references('id')
                ->on('cotecu.lista_cotejo_items')
                ->restrictOnDelete();
        });

        Schema::create('cotecu.listas_cotejo_silabos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('version', 20);
            $table->string('estado', 20)->default('Activo');
            $table->timestamps();
        });

        Schema::create('cotecu.lista_cotejo_silabos_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('lista_cotejo_silabo_id');
            $table->text('descripcion');
            $table->integer('orden');
            $table->timestamps();
            $table->foreign('lista_cotejo_silabo_id', 'cotecu_lista_silabo_item_lista_fk')
                ->references('id')
                ->on('cotecu.listas_cotejo_silabos')
                ->cascadeOnDelete();
        });

        Schema::create('cotecu.silabos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_curso_plan');
            $table->bigInteger('id_periodo');
            $table->bigInteger('id_profesor');
            $table->integer('version');
            $table->string('archivo_url', 500);
            $table->string('estado', 50)->default('Pendiente');
            $table->timestamps();
            $table->index('id_curso_plan', 'cotecu_silabo_curso_plan_idx');
            $table->index('id_periodo', 'cotecu_silabo_periodo_idx');
            $table->index('id_profesor', 'cotecu_silabo_profesor_idx');
            $table->foreign('id_curso_plan', 'cotecu_silabo_curso_plan_fk')
                ->references('id_curso_plan')
                ->on('core.curso_plan')
                ->restrictOnDelete();
            $table->foreign('id_periodo', 'cotecu_silabo_periodo_fk')
                ->references('id_periodo')
                ->on('core.periodo_academico')
                ->restrictOnDelete();
            $table->foreign('id_profesor', 'cotecu_silabo_profesor_fk')
                ->references('id_usuario')
                ->on('seguridad.usuario')
                ->restrictOnDelete();
        });

        Schema::create('cotecu.revisiones_silabo', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('silabo_id');
            $table->bigInteger('lista_cotejo_silabo_id');
            $table->bigInteger('id_revisor');
            $table->string('estado_final', 50);
            $table->text('comentario_general')->nullable();
            $table->string('resultado_url', 500)->nullable();
            $table->timestamps();
            $table->index('id_revisor', 'cotecu_revision_silabo_revisor_idx');
            $table->foreign('silabo_id', 'cotecu_revision_silabo_documento_fk')
                ->references('id')
                ->on('cotecu.silabos')
                ->restrictOnDelete();
            $table->foreign('lista_cotejo_silabo_id', 'cotecu_revision_silabo_lista_fk')
                ->references('id')
                ->on('cotecu.listas_cotejo_silabos')
                ->restrictOnDelete();
            $table->foreign('id_revisor', 'cotecu_revision_silabo_revisor_fk')
                ->references('id_usuario')
                ->on('seguridad.usuario')
                ->restrictOnDelete();
        });

        Schema::create('cotecu.resultados_silabos_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('revision_silabo_id');
            $table->bigInteger('item_silabo_id');
            $table->boolean('cumple');
            $table->text('observacion_item')->nullable();
            $table->timestamps();
            $table->foreign('revision_silabo_id', 'cotecu_resultado_silabo_revision_fk')
                ->references('id')
                ->on('cotecu.revisiones_silabo')
                ->cascadeOnDelete();
            $table->foreign('item_silabo_id', 'cotecu_resultado_silabo_item_fk')
                ->references('id')
                ->on('cotecu.lista_cotejo_silabos_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('cotecu.resultados_silabos_items');
        Schema::dropIfExists('cotecu.revisiones_silabo');
        Schema::dropIfExists('cotecu.silabos');
        Schema::dropIfExists('cotecu.lista_cotejo_silabos_items');
        Schema::dropIfExists('cotecu.listas_cotejo_silabos');
        Schema::dropIfExists('cotecu.resultados_items');
        Schema::dropIfExists('cotecu.revisiones_plan_curricular');
        Schema::dropIfExists('cotecu.planes_curriculares');
        Schema::dropIfExists('cotecu.lista_cotejo_items');
        Schema::dropIfExists('cotecu.listas_cotejo');
    }
};
