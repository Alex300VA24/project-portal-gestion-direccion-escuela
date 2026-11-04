<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiantes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('persona_id')
                ->unique()
                ->constrained('personas', 'id');

            $table->string('codigo_matricula')->unique();
            $table->date('fecha_admision');
            $table->string('semestre_admision', 10);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiantes');
    }
};