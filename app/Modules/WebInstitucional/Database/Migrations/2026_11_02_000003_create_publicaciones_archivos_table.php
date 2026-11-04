<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.publicacion_archivo', function (Blueprint $table) {
            $table->bigInteger('id_publicacion');
            $table->bigInteger('id_archivo')->index('wi_publicacion_archivo_archivo_index');
            $table->integer('orden');

            $table->primary(['id_publicacion', 'id_archivo'], 'wi_publicacion_archivo_pk');
            $table->foreign('id_publicacion', 'wi_publicacion_archivo_publicacion_fk')
                ->references('id_publicacion')->on('web_institucional.publicacion');
        });

        DB::statement('ALTER TABLE web_institucional.publicacion_archivo ADD CONSTRAINT wi_publicacion_archivo_orden_check CHECK (orden >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.publicacion_archivo');
    }
};
