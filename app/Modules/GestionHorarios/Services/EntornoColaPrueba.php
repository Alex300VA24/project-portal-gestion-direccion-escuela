<?php

namespace App\Modules\GestionHorarios\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EntornoColaPrueba
{
    public static function verificar(): void
    {
        $db = DB::connection();
        $cfg = $db->getConfig();
        if (! app()->environment('testing') || $db->getDriverName() !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55440 || ($cfg['database'] ?? null) !== 'horarios_cola_test' || ($cfg['username'] ?? null) !== 'horarios_test') {
            throw new RuntimeException('Entorno de cola de prueba no autorizado.');
        }
        $real = $db->selectOne("SELECT current_database() AS base, current_user AS usuario, current_setting('server_version_num') AS version");
        if ($real->base !== 'horarios_cola_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000) {
            throw new RuntimeException('Base de cola de prueba no autorizada.');
        }
    }
}
