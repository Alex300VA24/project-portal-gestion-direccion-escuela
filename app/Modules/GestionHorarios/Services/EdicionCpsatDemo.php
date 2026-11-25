<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

/** El validador Python es obligatorio para mover sesiones CP-SAT. Solo servidor local QA. */
final class EdicionCpsatDemo
{
    public static function disponible(User $usuario): bool
    {
        return PHP_SAPI === 'cli-server' && ConsultaLocalDemostracion::permitida($usuario)
            && EntornoEjecucionQa::disponible();
    }

    public function mover(User $usuario, int $version, int $actividad, string $dia, string $inicio, string $ambiente): array
    {
        if (! self::disponible($usuario)) { throw ValidationException::withMessages(['sesion'=>'Configure el conector y Python en la terminal del servidor QA.']); }
        if (! in_array($dia, HorariosVersionadosDemo::DIAS, true) || $version < 1 || $actividad < 1 || strlen($ambiente) > 100) {
            throw ValidationException::withMessages(['sesion'=>'Movimiento inválido.']);
        }
        try { $minutos=HorariosVersionadosDemo::minutos($inicio); }
        catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['sesion'=>'Hora inválida.']); }
        try {
            $proc = new Process([PHP_BINARY, EntornoEjecucionQa::conector(), 'editar', '150', (string) $version, (string) $actividad, $dia, (string) $minutos, $ambiente], base_path(), EntornoEjecucionQa::variablesProceso());
            $proc->setTimeout(90);
            $proc->run();
        }
        catch (\Throwable $e) { report($e); throw ValidationException::withMessages(['sesion'=>'No se pudo confirmar el movimiento. Abra la última versión antes de reintentar.']); }
        if (! $proc->isSuccessful()) { throw ValidationException::withMessages(['sesion'=>'El validador rechazó la entrada o cambió el horario. Revise la última versión y prevalidación.']); }
        foreach (preg_split('/\R/', $proc->getOutput()) as $linea) {
            if (! str_starts_with(trim($linea), '{')) { continue; }
            $r=json_decode($linea,true);
            if (($r['estado']??'')==='FEASIBLE' && is_bool($r['nueva_version']??null) && is_int($r['version']??null) && $r['version']===$version+($r['nueva_version']?1:0)) { return $r; }
            if (($r['estado']??'')==='MOVIMIENTO_RECHAZADO') { throw ValidationException::withMessages(['sesion'=>$r['motivo']??'Movimiento incompatible.']); }
        }
        throw ValidationException::withMessages(['sesion'=>'No se acreditó el movimiento. No se confirma ningún cambio.']);
    }
}
