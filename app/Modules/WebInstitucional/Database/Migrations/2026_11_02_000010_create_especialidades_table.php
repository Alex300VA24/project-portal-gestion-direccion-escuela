<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_institucional.especialidad', function (Blueprint $table) {
            $table->bigIncrements('id_especialidad');
            $table->string('nombre')->unique('wi_especialidad_nombre_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.especialidad');
    }
};
