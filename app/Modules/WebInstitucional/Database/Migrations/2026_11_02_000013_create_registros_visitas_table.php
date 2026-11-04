<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.registro_visita', function (Blueprint $table) {
            $table->bigIncrements('id_visita');
            $table->text('ruta');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('visitado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.registro_visita');
    }
};
