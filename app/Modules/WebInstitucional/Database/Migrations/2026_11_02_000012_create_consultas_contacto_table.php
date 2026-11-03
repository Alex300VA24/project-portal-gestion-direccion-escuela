<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.consulta_contacto', function (Blueprint $table) {
            $table->bigIncrements('id_consulta');
            $table->string('nombre_remitente');
            $table->string('correo');
            $table->string('telefono', 50)->nullable();
            $table->string('asunto');
            $table->text('mensaje');
            $table->string('estado', 50);
            $table->timestampTz('recibida_en')->useCurrent();
            $table->bigInteger('respondido_por')->nullable()->index('wi_consulta_contacto_responsable_index');
            // P4: respuesta y derivación, sin duplicar usuarios de seguridad.
            $table->text('respuesta')->nullable();
            $table->timestampTz('respondida_en')->nullable();
            $table->string('area_destino', 120)->nullable();
            $table->timestampTz('derivada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.consulta_contacto');
    }
};
