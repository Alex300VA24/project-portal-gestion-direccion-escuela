<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS web_institucional');

        Schema::create('web_institucional.categoria_publicacion', function (Blueprint $table) {
            $table->bigIncrements('id_categoria');
            $table->string('nombre')->unique('wi_categoria_publicacion_nombre_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_institucional.categoria_publicacion');
    }
};
