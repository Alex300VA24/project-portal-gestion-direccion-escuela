<?php

namespace Tests\Support\GestionHorarios\Services;

use Tests\Support\GestionHorarios\Engine\CatalogoEscenariosPrueba;
use Tests\Support\GestionHorarios\Jobs\EjecutarCpsatPrueba;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

/** Exclusivo de pruebas aisladas; no está expuesto a usuarios. */
final class EjecucionesCpsatPrueba
{
    public function encolar(string $escenario): string
    {
        EntornoColaPrueba::verificar();
        if (config('queue.connections.database.driver') !== 'database' || (int) config('queue.connections.database.retry_after') <= 45 || ! in_array(config('queue.connections.database.connection'), [null, config('database.default')], true)) {
            throw new RuntimeException('La cola de prueba aislada no está configurada.');
        }
        $datos = (new CatalogoEscenariosPrueba)->entrada($escenario);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($id, $escenario, $datos) {
            DB::table('gestion_horarios.ejecucion_cpsat_prueba')->insert(['id' => $id, 'escenario' => $escenario, 'huella' => $datos['huella_sha256'], 'estado' => 'PENDIENTE']);
            Queue::connection('database')->pushOn('gestion-horarios-prueba', new EjecutarCpsatPrueba($id));
            DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->update(['estado' => 'EN_COLA']);
        });

        return $id;
    }

    public function cancelarPendiente(string $id): bool
    {
        EntornoColaPrueba::verificar();

        return DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('id', $id)->whereIn('estado', ['PENDIENTE', 'EN_COLA'])->update(['estado' => 'CANCELADO', 'finalizada_en' => now()]) === 1;
    }

    /** Es una señal operativa, no prueba que el proceso hijo haya terminado. */
    public function posiblementeAbandonadas(): array
    {
        EntornoColaPrueba::verificar();

        return DB::table('gestion_horarios.ejecucion_cpsat_prueba')->where('estado', 'EJECUTANDO')->whereNotNull('iniciada_en')->where('iniciada_en', '<', now()->subMinutes(2))->orderBy('iniciada_en')->pluck('id')->all();
    }
}
