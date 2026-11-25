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

        Schema::create('seguimiento_estudiante.tutoria_revision_pendiente', function (Blueprint $table): void {
            $table->foreignId('id_estudiante')->constrained('core.estudiante', 'id_estudiante')->restrictOnDelete();
            $table->foreignId('id_periodo')->constrained('core.periodo_academico', 'id_periodo')->restrictOnDelete();
            $table->foreignId('id_asignacion')->constrained('seguimiento_estudiante.tutor_asignacion', 'id_asignacion')->restrictOnDelete();
            $table->timestampTz('registrada_en')->useCurrent();
            $table->primary(['id_estudiante', 'id_periodo']);
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::dropIfExists('seguimiento_estudiante.tutoria_revision_pendiente');
        }
    }
};
