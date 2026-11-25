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

        // 1. Modificar bienestar.cita
        DB::statement('ALTER TABLE bienestar.cita ALTER COLUMN id_especialista DROP NOT NULL;');

        Schema::table('bienestar.cita', function (Blueprint $table) {
            $table->bigInteger('id_usuario_atencion')->nullable();
            $table->foreign('id_usuario_atencion', 'bie_cita_usuario_atencion_fk')
                ->references('id_usuario')->on('seguridad.usuario')
                ->nullOnDelete();
        });

        // 2. Modificar bienestar.derivacion
        Schema::table('bienestar.derivacion', function (Blueprint $table) {
            $table->boolean('consentimiento_estudiante')->default(false);
            $table->timestampTz('fecha_consentimiento')->nullable();
            $table->text('resultado_especialista')->nullable();
        });

        // 3. Modificar bienestar.seguimiento_derivacion
        DB::statement('ALTER TABLE bienestar.seguimiento_derivacion ALTER COLUMN registrado_por DROP NOT NULL;');

        Schema::table('bienestar.seguimiento_derivacion', function (Blueprint $table) {
            $table->bigInteger('registrado_por_usuario')->nullable();
            $table->foreign('registrado_por_usuario', 'bie_seguimiento_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('bienestar.seguimiento_derivacion', function (Blueprint $table) {
            $table->dropForeign('bie_seguimiento_usuario_fk');
            $table->dropColumn('registrado_por_usuario');
        });
        DB::statement('ALTER TABLE bienestar.seguimiento_derivacion ALTER COLUMN registrado_por SET NOT NULL;');

        Schema::table('bienestar.derivacion', function (Blueprint $table) {
            $table->dropColumn(['consentimiento_estudiante', 'fecha_consentimiento', 'resultado_especialista']);
        });

        Schema::table('bienestar.cita', function (Blueprint $table) {
            $table->dropForeign('bie_cita_usuario_atencion_fk');
            $table->dropColumn('id_usuario_atencion');
        });
        DB::statement('ALTER TABLE bienestar.cita ALTER COLUMN id_especialista SET NOT NULL;');
    }
};
