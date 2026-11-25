<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Exclusivo para revisión visual de datos sintéticos. NO incorporar al PR institucional. */
final class ConsultaLocalDemostracion
{
    public static function entornoAislado(): bool
    {
        if (PHP_SAPI !== 'cli-server' || ! app()->environment('testing')
            || EntornoEjecucionQa::valor('HORARIOS_QA_CONSULTAS') !== '1'
            || getenv('DB_CONNECTION') !== 'pgsql'
            || getenv('DB_DATABASE') !== 'horarios_demo_2026_ii_demanda'
            || request()->server('REMOTE_ADDR') !== '127.0.0.1'
            || request()->server('HTTP_HOST') !== '127.0.0.1:8001'
            || (string) request()->server('SERVER_PORT') !== '8001') {
            return false;
        }

        try {
            $db = DB::connection();
            if ($db->getDriverName() !== 'pgsql' || $db->getConfig('host') !== '127.0.0.1'
                || $db->selectOne('SELECT current_database() AS nombre')->nombre !== 'horarios_demo_2026_ii_demanda') {
                return false;
            }
            $planes = $db->table('core.plan_estudios')->get(['codigo', 'id_escuela']);
            $periodos = $db->table('core.periodo_academico')->pluck('codigo');
            if ($planes->count() !== 1 || $planes->first()->codigo !== 'QA-HORARIOS'
                || $periodos->count() !== 1 || $periodos->first() !== '2026-II') {
                return false;
            }
            // El seeder de demostración anonimiza todos los docentes mediante documentos QA.
            return ! $db->table('core.docente as d')->leftJoin('core.persona as p', 'p.id_persona', '=', 'd.id_persona')
                ->where(fn ($q) => $q->whereNull('p.id_persona')->orWhereNull('p.tipo_documento')
                    ->orWhere('p.tipo_documento', '!=', 'QA')->orWhere('d.codigo_docente', 'not like', 'QA-%'))
                ->exists();
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    public static function permitida(?User $usuario): bool
    {
        if (!$usuario || $usuario->email !== 'director.qa@horarios.test' || !self::entornoAislado()) return false;
        try {
            return $usuario->hasRole('director_escuela', 'web')
                && $usuario->can('gestion-horarios.view')
                && (new AutorizacionGestionAcademica)->permite($usuario)
                && (new AmbitoConsultaAdministrativa)->escuelas($usuario) === [(int) DB::table('core.plan_estudios')->value('id_escuela')];
        } catch (Throwable $e) {
            report($e);
            return false;
        }
    }

    /** Solo lectura de instantáneas publicadas; nunca usar para escrituras QA. */
    public static function lecturaPublicada(?User $usuario): bool
    {
        if (self::permitida($usuario)) return true;
        if (!$usuario || !$usuario->hasAnyRole(['alumno', 'profesor']) || !self::entornoAislado()) return false;
        return LectoresPublicacionQa::autorizado((string)$usuario->email, (string)EntornoEjecucionQa::valor('HORARIOS_QA_LECTORES'));
    }

    public static function exigir(User $usuario): void
    {
        if (! self::permitida($usuario)) {
            (new AmbitoConsultaAdministrativa)->denegarRecursoGlobal($usuario);
        }
    }
}
