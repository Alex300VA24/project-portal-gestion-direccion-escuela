<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.docente_especialidad', function (Blueprint $table) {
            $table->bigInteger('id_docente');
            $table->bigInteger('id_especialidad')->index('wi_docente_especialidad_especialidad_index');

            $table->primary(['id_docente', 'id_especialidad'], 'wi_docente_especialidad_pk');
            $table->foreign('id_especialidad', 'wi_docente_especialidad_especialidad_fk')
                ->references('id_especialidad')->on('web_institucional.especialidad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.docente_especialidad');
    }
};
