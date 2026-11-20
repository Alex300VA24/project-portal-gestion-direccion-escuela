<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Modules\GestionHorarios\Engine\ReconciliarIntentosPrueba;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/** Instrumentación de prueba: solver real con pausa en callback de solución. */
final class InterrupcionOrtoolsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 190;

    public function __construct(public readonly string $id, public readonly string $marca, public readonly string $token) {}

    public function handle(): void
    {
        $db = DB::connection();
        (new ReconciliarIntentosPrueba($db))->consultar($this->id);
        $intento = (string) Str::uuid();
        $tomado = $db->selectOne("update prueba.solicitud_cola set estado='EJECUTANDO', intento=?, generacion=generacion+1, lease_hasta=clock_timestamp()+interval '210 seconds' where id=? and estado='EN_COLA' returning id", [$intento, $this->id]);
        if (! $tomado) {
            return;
        }
        $proceso = new Process([getenv('HORARIOS_PYTHON_AISLADO'), __DIR__.'/solver_interrumpido_27b6.py', $this->marca, $this->token]);
        $proceso->setTimeout(60);
        $proceso->run();
    }
}
