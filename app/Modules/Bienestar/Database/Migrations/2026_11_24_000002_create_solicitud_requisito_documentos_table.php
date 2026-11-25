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

        Schema::create('bienestar.solicitud_requisito_documento', function (Blueprint $table) {
            $table->bigIncrements('id_solicitud_doc');
            $table->bigInteger('id_solicitud');
            $table->bigInteger('id_requisito');
            $table->bigInteger('id_archivo');
            $table->string('estado_revision', 30)->default('pendiente'); // 'pendiente', 'conforme', 'observado'
            $table->text('comentario_revision')->nullable(); // Comentario de Secretaría al observar
            $table->bigInteger('revisado_por')->nullable();
            $table->timestampTz('revisado_en')->nullable();
            $table->timestampTz('subido_en')->useCurrent();
            $table->timestampTz('actualizado_en')->useCurrent();

            $table->foreign('id_solicitud', 'bie_srd_solicitud_fk')
                ->references('id_solicitud')->on('bienestar.solicitud_apoyo')
                ->onDelete('cascade');

            $table->foreign('id_requisito', 'bie_srd_requisito_fk')
                ->references('id_requisito')->on('bienestar.tipo_solicitud_requisito')
                ->onDelete('restrict');

            $table->foreign('id_archivo', 'bie_srd_archivo_fk')
                ->references('id_archivo')->on('comun.archivo')
                ->onDelete('restrict');

            $table->foreign('revisado_por', 'bie_srd_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')
                ->nullOnDelete();

            $table->index('id_solicitud', 'bie_srd_solicitud_idx');
            $table->index('id_requisito', 'bie_srd_requisito_idx');
            $table->index('estado_revision', 'bie_srd_estado_idx');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('bienestar.solicitud_requisito_documento');
    }
};
