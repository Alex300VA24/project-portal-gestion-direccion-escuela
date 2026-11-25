<?php

namespace Tests\Feature\GestionHorarios\soporte;

use Tests\Support\GestionHorarios\Engine\EjecutorPythonPruebas;
use Tests\Support\GestionHorarios\Engine\ErrorEjecucionPythonPrueba;
use Tests\Support\GestionHorarios\Engine\ProteccionPropuestaPrueba;
use Tests\Support\GestionHorarios\Engine\ReconciliarIntentosPrueba;
use Tests\Support\GestionHorarios\Engine\TrazabilidadEjecucionPrueba;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Job accesible únicamente a la suite aislada; no está registrado en rutas. */
final class EjecutarPreparacionColaFicticia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 190;

    public function __construct(public readonly string $id) {}

    public function handle(): void
    {
        $db = DB::connection();
        $cfg = $db->getConfig();
        if (! app()->environment('testing') || $db->getDriverName() !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55471 || ($cfg['database'] ?? null) !== 'horarios_27b2_test' || ($cfg['username'] ?? null) !== 'horarios_test') {
            throw new RuntimeException('Job ficticio fuera del entorno aislado.');
        }
        $real = $db->selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version");
        if ($real->base !== 'horarios_27b2_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000) {
            throw new RuntimeException('Identidad PostgreSQL no válida.');
        }
        $intento = (string) Str::uuid();
        $fila = $db->transaction(function () use ($db, $intento) {
            $fila = $db->selectOne("UPDATE prueba.solicitud_cola SET estado='EJECUTANDO', intento=?, generacion=generacion+1, lease_hasta=clock_timestamp()+interval '210 seconds' WHERE id=? AND estado='EN_COLA' RETURNING *", [$intento, $this->id]);
            if ($fila) {
                $db->table('prueba.intento_cola')->insert(['id' => $intento, 'solicitud_id' => $this->id, 'generacion' => $fila->generacion, 'estado' => 'EJECUTANDO', 'worker_pid' => getmypid(), 'entrada_sha256' => $fila->entrada_sha256, 'academica_sha256' => $fila->academica_sha256, 'reclamado_en' => $db->raw('clock_timestamp()'), 'lease_hasta' => $fila->lease_hasta]);
            }

            return $fila;
        });
        if (! $fila) {
            return;
        }
        try {
            require_once __DIR__.'/ProveedorComplementosPostgresFicticio.php';
            $preparada = json_decode($fila->preparada, true, 512, JSON_THROW_ON_ERROR);
            if (! hash_equals($fila->entrada_sha256, $preparada['huella_sha256']) || ! hash_equals($fila->academica_sha256, $preparada['huella_academica_sha256'])) {
                throw new RuntimeException('Solicitud incompatible.');
            }
            $python = getenv('HORARIOS_PYTHON_AISLADO');
            if (! is_string($python)) {
                throw new RuntimeException('Intérprete no disponible.');
            }
            $db->table('prueba.intento_cola')->where('id', $intento)->update(['presupuesto' => $preparada['entrada']['tiempo_limite']]);
            $reconciliador = new ReconciliarIntentosPrueba($db);
            $marca = getenv('HORARIOS_27B8_MARCA');
            $token = getenv('HORARIOS_27B8_TOKEN');
            $instrumentacion = is_string($marca) && $marca !== '' && is_string($token) && $token !== '' ? [$marca, $token] : null;
            $lanzadorPid = null;
            $resultado = (new EjecutorPythonPruebas($python, $instrumentacion))->ejecutarExacto($preparada['entrada'], fn () => $reconciliador->renovar($this->id, $intento, (int) $fila->generacion), function ($pid) use ($db, $intento, &$lanzadorPid): void {
                $lanzadorPid = $pid;
                $db->table('prueba.intento_cola')->where('id', $intento)->where('estado', 'EJECUTANDO')->update(['python_iniciado_en' => $db->raw('clock_timestamp()'), 'lanzador_pid' => $pid]);
            });
            if (! in_array($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], true)) {
                $db->transaction(function () use ($db, $intento, $fila, $resultado): void {
                    $codigo = 'SOLVER_'.$resultado['estado'];
                    if ($db->table('prueba.solicitud_cola')->where('id', $this->id)->where('estado', 'EJECUTANDO')->where('intento', $intento)->where('generacion', $fila->generacion)->whereRaw('lease_hasta > clock_timestamp()')->update(['estado' => 'RECHAZADO', 'solver' => $resultado['estado'], 'diagnostico' => $codigo])) {
                        $db->table('prueba.intento_cola')->where('id', $intento)->where('estado', 'EJECUTANDO')->update(['estado' => 'RECHAZADO', 'solver' => $resultado['estado'], 'diagnostico' => $codigo, 'python_finalizado_en' => $db->raw('clock_timestamp()'), 'finalizado_en' => $db->raw('clock_timestamp()')]);
                    }
                });

                return;
            }
            $traza = (new TrazabilidadEjecucionPrueba)->crear($this->id, $intento, (int) $fila->generacion, $preparada, $resultado, getmypid(), (int) $lanzadorPid);
            $db->table('prueba.intento_cola')->where('id', $intento)->where('estado', 'EJECUTANDO')->update(['python_finalizado_en' => $db->raw('clock_timestamp()'), 'solver' => $resultado['estado'], 'resultado' => json_encode($resultado, JSON_THROW_ON_ERROR), 'evidencia' => json_encode($traza, JSON_THROW_ON_ERROR)]);
            // Pausa exclusivamente en el Job de prueba, después de que Python ha terminado.
            if (getenv('HORARIOS_27B7_PAUSA_TRAS_PYTHON') === '1') {
                $liberar = getenv('HORARIOS_27B7_LIBERAR');
                $limite = microtime(true) + 30;
                while ((! is_string($liberar) || ! is_file($liberar)) && microtime(true) < $limite) {
                    usleep(100000);
                }
            }
            $proteccion = new ProteccionPropuestaPrueba($db, new ProveedorComplementosPostgresFicticio($db, $preparada['entrada']['tiempo_limite']));
            $aceptacion = $proteccion->aceptar($preparada, $resultado, function () use ($db, $intento, $fila, $resultado, $preparada): bool {
                $db->table('prueba.solicitud_cola')->where('id', $this->id)->lockForUpdate()->first();
                $evidencia = $db->table('prueba.intento_cola')->where('id', $intento)->where('solicitud_id', $this->id)->lockForUpdate()->first();
                if (! $evidencia || ! $evidencia->evidencia || ! $evidencia->resultado || ! (new TrazabilidadEjecucionPrueba)->coincide(json_decode($evidencia->evidencia, true, 512, JSON_THROW_ON_ERROR), $this->id, $intento, (int) $fila->generacion, $preparada, $resultado, (int) $evidencia->worker_pid, (int) $evidencia->lanzador_pid) || (new TrazabilidadEjecucionPrueba)->hashResultado(json_decode($evidencia->resultado, true, 512, JSON_THROW_ON_ERROR)) !== (new TrazabilidadEjecucionPrueba)->hashResultado($resultado)) {
                    return false;
                }
                $actualizado = $db->table('prueba.solicitud_cola')->where('id', $this->id)->where('estado', 'EJECUTANDO')->where('intento', $intento)->where('generacion', $fila->generacion)->whereRaw('lease_hasta > clock_timestamp()')->where('entrada_sha256', $fila->entrada_sha256)->update([
                    'estado' => 'FINALIZADO', 'solver' => $resultado['estado'],
                    'tiempo_aplicado' => $resultado['tiempo_limite_s'],
                    'tiempo_busqueda' => $resultado['tiempo_busqueda_s'],
                    'resultado' => json_encode($resultado, JSON_THROW_ON_ERROR),
                ]) === 1;
                if ($actualizado) {
                    $db->table('prueba.intento_cola')->where('id', $intento)->where('generacion', $fila->generacion)->where('estado', 'EJECUTANDO')->update(['estado' => 'FINALIZADO', 'validacion' => 'VALIDADA_PHP_PARCIAL', 'finalizado_en' => $db->raw('clock_timestamp()')]);
                }

                return $actualizado;
            });
            if (! in_array($aceptacion['estado'], ['ACEPTADA_PROTOCOLO_FICTICIO', 'DUPLICADA'], true)) {
                $db->transaction(function () use ($db, $intento, $fila, $aceptacion): void {
                    if ($db->table('prueba.solicitud_cola')->where('id', $this->id)->where('estado', 'EJECUTANDO')->where('intento', $intento)->where('generacion', $fila->generacion)->update(['estado' => 'RECHAZADO', 'diagnostico' => $aceptacion['estado']])) {
                        $db->table('prueba.intento_cola')->where('id', $intento)->where('estado', 'EJECUTANDO')->update(['estado' => 'RECHAZADO', 'diagnostico' => $aceptacion['estado'], 'finalizado_en' => $db->raw('clock_timestamp()')]);
                    }
                });
            }
            // Punto de interrupción exclusivo de la suite, después del commit.
            if (in_array($aceptacion['estado'], ['ACEPTADA_PROTOCOLO_FICTICIO', 'DUPLICADA'], true) && getenv('HORARIOS_27B7_PAUSA_TRAS_COMMIT') === '1') {
                sleep(30);
            }
        } catch (Throwable $e) {
            $codigo = $e instanceof ErrorEjecucionPythonPrueba ? $e->diagnostico : ($e instanceof \DomainException ? 'ENTRADA_INVALIDA' : 'ERROR_INTERNO');
            $db->transaction(function () use ($db, $intento, $fila, $codigo): void {
                if ($db->table('prueba.solicitud_cola')->where('id', $this->id)->where('estado', 'EJECUTANDO')->where('intento', $intento)->where('generacion', $fila->generacion)->update(['estado' => 'FALLIDO', 'diagnostico' => $codigo])) {
                    $db->table('prueba.intento_cola')->where('id', $intento)->where('estado', 'EJECUTANDO')->update(['estado' => 'FALLIDO', 'diagnostico' => $codigo, 'finalizado_en' => $db->raw('clock_timestamp()')]);
                }
            });
        }
    }
}
