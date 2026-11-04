<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puestos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre')->unique();

            $table->timestamps();
        });

        Schema::create('personas_puestos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('persona_id')
                ->constrained('personas', 'id')
                ->cascadeOnDelete();

            $table->foreignId('puesto_id')
                ->constrained('puestos', 'id')
                ->restrictOnDelete();

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();

            $table->timestamps();

            $table->index(
                ['persona_id', 'fecha_inicio'],
                'personas_puestos_persona_fecha_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas_puestos');
        Schema::dropIfExists('puestos');
    }
};