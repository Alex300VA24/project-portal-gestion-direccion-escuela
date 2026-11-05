<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'web_institucional')
            ->delete();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('seguridad.modulo_sistema')->insertOrIgnore([
            'codigo' => 'web_institucional',
            'nombre' => 'Web institucional',
            'esquema' => 'web_institucional',
            'activo' => true,
        ]);
    }
};
