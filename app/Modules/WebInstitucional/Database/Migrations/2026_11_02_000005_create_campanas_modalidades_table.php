<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.campana_modalidad', function (Blueprint $table) {
            $table->bigInteger('id_campana');
            $table->bigInteger('id_modalidad')->index('wi_campana_modalidad_modalidad_index');
            $table->integer('vacantes');
            $table->text('requisitos')->nullable();

            $table->primary(['id_campana', 'id_modalidad'], 'wi_campana_modalidad_pk');
            $table->foreign('id_campana', 'wi_campana_modalidad_campana_fk')
                ->references('id_campana')->on('web_institucional.campana_admision');
        });

        DB::statement('ALTER TABLE web_institucional.campana_modalidad ADD CONSTRAINT wi_campana_modalidad_vacantes_check CHECK (vacantes >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.campana_modalidad');
    }
};
