<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.documento_oficial', function (Blueprint $table) {
            $table->bigIncrements('id_documento');
            $table->string('tipo');
            $table->string('titulo');
            $table->string('numero')->nullable();
            $table->date('fecha_emision');
            $table->bigInteger('id_archivo')->index('wi_documento_oficial_archivo_index');
            $table->boolean('publicado')->default(false);
            // P7: vigencia, trazabilidad entre versiones y autorización.
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->bigInteger('id_documento_anterior')->nullable()->index('wi_documento_anterior_index');
            $table->bigInteger('autorizado_por')->nullable()->index('wi_documento_autorizador_index');
            $table->timestampTz('autorizado_en')->nullable();
            $table->foreign('id_documento_anterior', 'wi_documento_anterior_fk')
                ->references('id_documento')->on('web_institucional.documento_oficial');
        });

        DB::statement('ALTER TABLE web_institucional.documento_oficial ADD CONSTRAINT wi_documento_vigencia_check CHECK (vigente_desde IS NULL OR vigente_hasta IS NULL OR vigente_hasta >= vigente_desde)');
        DB::statement('ALTER TABLE web_institucional.documento_oficial ADD CONSTRAINT wi_documento_version_check CHECK (id_documento_anterior IS NULL OR id_documento_anterior <> id_documento)');
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.documento_oficial');
    }
};
