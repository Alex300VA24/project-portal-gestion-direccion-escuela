<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_academicos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();

            $table->timestamps();
        });

        Schema::create('historial_estados_academicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('estudiante_id')
                ->constrained('estudiantes', 'id')
                ->cascadeOnDelete();

            $table->foreignId('estado_academico_id')
                ->constrained('estados_academicos', 'id')
                ->restrictOnDelete();

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();

            $table->timestamps();

            $table->index(
                ['estudiante_id', 'fecha_inicio'],
                'historial_estados_estudiante_fecha_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estados_academicos');
        Schema::dropIfExists('estados_academicos');
    }
};