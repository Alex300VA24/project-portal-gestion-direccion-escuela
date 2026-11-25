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

        DB::statement('ALTER TABLE bienestar.especialista ALTER COLUMN id_usuario DROP NOT NULL;');

        Schema::table('bienestar.especialista', function (Blueprint $table) {
            $table->string('nombres', 120)->nullable();
            $table->string('apellidos', 120)->nullable();
            $table->string('contacto', 150)->nullable();
            $table->string('institucion', 150)->nullable()->default('Dirección de Bienestar UNT');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('bienestar.especialista', function (Blueprint $table) {
            $table->dropColumn(['nombres', 'apellidos', 'contacto', 'institucion']);
        });

        DB::statement('ALTER TABLE bienestar.especialista ALTER COLUMN id_usuario SET NOT NULL;');
    }
};
