<?php

namespace Tests\Support\GestionHorarios\Jobs;

use Tests\Support\GestionHorarios\Engine\CatalogoEscenariosPrueba;
use Tests\Support\GestionHorarios\Engine\EjecutorPythonPruebas;
use Tests\Support\GestionHorarios\Engine\ValidarResultadoFicticio;
use App\Modules\GestionHorarios\Engine\VerificarEvidenciaMotor;
use Tests\Support\GestionHorarios\Services\EntornoColaPrueba;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EjecutarCpsatPrueba implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public readonly string $id) {}

    public function handle(): void
    {
        EntornoColaPrueba::verificar();
        $tabla = DB::table('gestion_horarios.ejecucion_cpsat_prueba');
        $tomado = DB::selectOne("UPDATE gestion_horarios.ejecucion_cpsat_prueba SET estado = 'EJECUTANDO', iniciada_en = clock_timestamp() WHERE id = ? AND estado = 'EN_COLA' RETURNING iniciada_en", [$this->id]);
        if (! $tomado) {
            return;
        }
        try {
            $ejecucion = (clone $tabla)->where('id', $this->id)->first();
            $escenario = (new CatalogoEscenariosPrueba)->entrada($ejecucion->escenario);
            if (! hash_equals(trim($ejecucion->huella), $escenario['huella_sha256'])) {
                throw new \DomainException('Huella de prueba inválida.');
            }
            $python = env('HORARIOS_PYTHON_AISLADO');
            if (! is_string($python) || ! is_file($python)) {
                throw new \RuntimeException('Intérprete de prueba no disponible.');
            }
            $entrada = $escenario['entrada'];
            $respuesta = (new EjecutorPythonPruebas($python))->ejecutar($entrada, 5);
            (new VerificarEvidenciaMotor)->validar($entrada, trim($ejecucion->huella), $respuesta);
            $validacion = (new ValidarResultadoFicticio)->validar($entrada, $respuesta);
            (clone $tabla)->where('id', $this->id)->where('estado', 'EJECUTANDO')->where('iniciada_en', $tomado->iniciada_en)->where('huella', $ejecucion->huella)->where('escenario', $ejecucion->escenario)->update(['estado' => 'FINALIZADO', 'solver' => $respuesta['estado'], 'validada' => $validacion['solucion_validada'], 'finalizada_en' => now()]);
        } catch (Throwable $e) {
            // No se registra el mensaje ni la traza, que pueden contener rutas internas.
            (clone $tabla)->where('id', $this->id)->where('estado', 'EJECUTANDO')->where('iniciada_en', $tomado->iniciada_en)->update(['estado' => 'FALLIDO', 'error_codigo' => $e instanceof \DomainException ? 'ENTRADA_O_RESULTADO_INVALIDO' : 'EJECUCION_NO_DISPONIBLE', 'finalizada_en' => now()]);
        }
    }
}
