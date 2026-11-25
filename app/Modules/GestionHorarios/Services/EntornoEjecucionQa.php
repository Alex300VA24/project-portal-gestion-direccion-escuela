<?php

namespace App\Modules\GestionHorarios\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Configuración de procesos locales QA; jamás habilita acceso institucional. */
final class EntornoEjecucionQa
{
    public static function conector(): string
    {
        return base_path('app/Modules/GestionHorarios/Engine/qa/generar_horario_cpsat_qa.php');
    }

    public static function valor(string $clave): string
    {
        $valor = getenv($clave, true);
        return $valor === false ? '' : $valor;
    }

    public static function disponible(): bool
    {
        return is_file(self::conector())
            && is_file(self::valor('HORARIOS_QA_PYTHON'))
            && is_file(self::valor('HORARIOS_DEMO_ARCHIVO'));
    }

    /** El hijo recibe la misma conexión efectiva que el servidor web ya autorizado. */
    public static function variablesProceso(): array
    {
        $entorno = [];
        foreach (['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'HORARIOS_DEMO_PLAN', 'HORARIOS_QA_CONSULTAS',
            'HORARIOS_DEMO_ARCHIVO', 'HORARIOS_QA_PYTHON', 'HORARIOS_DEMO_NOMBRES_REALES'] as $clave) {
            $valor = getenv($clave, true);
            if ($valor !== false) {
                $entorno[$clave] = $valor;
            }
        }
        foreach (['APP_ENV'=>'testing', 'DB_CONNECTION'=>'pgsql', 'DB_DATABASE'=>'horarios_demo_2026_ii_demanda',
            'HORARIOS_DEMO_PLAN'=>'QA-HORARIOS', 'HORARIOS_QA_CONSULTAS'=>'1'] as $clave => $esperado) {
            if (($entorno[$clave] ?? null) !== $esperado) {
                throw new RuntimeException('Variable QA inválida: '.$clave);
            }
        }
        if (! is_file($entorno['HORARIOS_DEMO_ARCHIVO'] ?? '')) {
            throw new RuntimeException('Falta HORARIOS_DEMO_ARCHIVO.');
        }
        if (! is_file(self::conector()) || ! is_file($entorno['HORARIOS_QA_PYTHON'] ?? '')) {
            throw new RuntimeException('Conector local no configurado.');
        }
        $conexion = DB::connection();
        if ($conexion->getDriverName() !== 'pgsql' || $conexion->getConfig('host') !== '127.0.0.1'
            || $conexion->selectOne('SELECT current_database() AS nombre')->nombre !== 'horarios_demo_2026_ii_demanda') {
            throw new RuntimeException('Conexión no autorizada.');
        }
        foreach (['DB_HOST'=>'host', 'DB_PORT'=>'port', 'DB_DATABASE'=>'database', 'DB_USERNAME'=>'username', 'DB_PASSWORD'=>'password'] as $variable => $clave) {
            $valor = $conexion->getConfig($clave);
            if (! is_scalar($valor) && $valor !== null) {
                throw new RuntimeException('Configuración de conexión QA no admitida.');
            }
            $entorno[$variable] = (string) $valor;
        }
        if ($entorno['DB_DATABASE'] !== 'horarios_demo_2026_ii_demanda') {
            throw new RuntimeException('Conexión no autorizada.');
        }
        $entorno['DB_URL'] = (string) config('database.connections.pgsql.url', '');
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['SystemRoot', 'WINDIR', 'COMSPEC', 'PATH', 'PATHEXT', 'TEMP', 'TMP', 'USERPROFILE'] as $clave) {
                $valor = self::valor($clave);
                if ($valor !== '') {
                    $entorno[$clave] = $valor;
                }
            }
        }
        return $entorno;
    }
}
