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

        Schema::create('bienestar.articulo_informativo', function (Blueprint $table) {
            $table->bigIncrements('id_articulo');
            $table->string('titulo', 255);
            $table->text('resumen');
            $table->text('detalle');
            $table->string('fuente_normativa', 255);
            $table->date('fecha_ultima_revision');
            $table->string('estado', 30)->default('borrador'); // 'borrador', 'publicado'
            $table->bigInteger('autor_id');
            $table->timestamps();

            $table->foreign('autor_id', 'bie_articulo_autor_fk')
                ->references('id_usuario')->on('seguridad.usuario')
                ->restrictOnDelete();

            $table->index('estado', 'bie_articulo_estado_idx');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('bienestar.articulo_informativo');
    }
};
