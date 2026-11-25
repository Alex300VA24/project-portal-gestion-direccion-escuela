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

        // 1. Catálogo de Tipos de Solicitud (administrado por Bienestar)
        Schema::create('bienestar.tipo_solicitud', function (Blueprint $table) {
            $table->bigIncrements('id_tipo_solicitud');
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->string('unidad_responsable', 200);
            $table->integer('plazo_dias_habiles')->default(1);
            $table->decimal('costo_referencial', 10, 2)->default(0.00);
            $table->text('base_normativa_fuente');
            $table->string('estado_verificacion', 50)->default('pendiente_verificar'); // 'pendiente_verificar', 'verificado'
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('activo', 'bie_tipo_solicitud_activo_idx');
        });

        // 2. Requisitos por cada Tipo de Solicitud
        Schema::create('bienestar.tipo_solicitud_requisito', function (Blueprint $table) {
            $table->bigIncrements('id_requisito');
            $table->bigInteger('id_tipo_solicitud');
            $table->string('nombre', 255);
            $table->text('descripcion'); // Instrucción de qué debe subir el estudiante
            $table->boolean('es_obligatorio')->default(true);
            $table->string('formatos_permitidos', 100)->default('pdf,jpg,png');
            $table->integer('tamano_maximo_mb')->default(5);
            $table->bigInteger('id_archivo_plantilla')->nullable();
            $table->integer('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('id_tipo_solicitud', 'bie_req_tipo_solicitud_fk')
                ->references('id_tipo_solicitud')->on('bienestar.tipo_solicitud')
                ->onDelete('cascade');

            $table->foreign('id_archivo_plantilla', 'bie_req_plantilla_archivo_fk')
                ->references('id_archivo')->on('comun.archivo')
                ->nullOnDelete();

            $table->index('id_tipo_solicitud', 'bie_req_tipo_solicitud_idx');
            $table->index('orden', 'bie_req_orden_idx');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('bienestar.tipo_solicitud_requisito');
        Schema::dropIfExists('bienestar.tipo_solicitud');
    }
};
