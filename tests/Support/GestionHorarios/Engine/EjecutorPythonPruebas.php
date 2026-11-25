<?php

namespace Tests\Support\GestionHorarios\Engine;

use DomainException;
use JsonException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/** Ejecución aislada para pruebas; no registra resultados ni está conectada a rutas. */
final class EjecutorPythonPruebas
{
    public function __construct(private readonly string $interprete, private readonly ?array $instrumentacionPrueba = null) {}

    /** Ejecuta exactamente la entrada registrada, sin sustituir su presupuesto. */
    public function ejecutarExacto(array $entrada, ?callable $renovar = null, ?callable $iniciado = null): array
    {
        $segundos = $entrada['tiempo_limite'] ?? null;
        if (! is_int($segundos) || $segundos < 1 || $segundos > 150) {
            throw new DomainException('La entrada no declara un presupuesto de búsqueda válido.');
        }
        $respuesta = $this->ejecutar($entrada, $segundos, $renovar, $iniciado);
        if (in_array($respuesta['estado'], ['OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'UNKNOWN'], true) && ($respuesta['tiempo_limite_s'] ?? null) !== $segundos) {
            throw new ErrorEjecucionPythonPrueba('PARAMETROS_INCOMPATIBLES');
        }

        return $respuesta;
    }

    public function ejecutar(array $entrada, int $segundos = 150, ?callable $renovar = null, ?callable $iniciado = null): array
    {
        if (! is_file($this->interprete)) {
            throw new ErrorEjecucionPythonPrueba('INTERPRETE_NO_DISPONIBLE');
        }
        if ($segundos < 1 || $segundos > 150 || ($entrada['contrato'] ?? null) !== 'HORARIOS_MOTOR_V1' || ! in_array($entrada['operacion'] ?? 'GENERAR_HORARIO', ['GENERAR_HORARIO'], true)) {
            throw new DomainException('Configuración o contrato de prueba inválido.');
        }
        foreach (['configuracion', 'secciones', 'actividades', 'ambientes', 'disponibilidades', 'bloqueos', 'sesiones'] as $campo) {
            if (! isset($entrada[$campo]) || ! is_array($entrada[$campo])) {
                throw new DomainException('Faltan datos académicos de prueba.');
            }
        }
        $entrada['tiempo_limite'] = $segundos;
        $json = json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 2_000_000) {
            throw new DomainException('La entrada de prueba excede el tamaño permitido.');
        }
        $directorio = app_path('Modules/GestionHorarios/Engine/python');
        $comando = [$this->interprete, '-B', '-m', 'scripts.motor_aplicacion'];
        if ($this->instrumentacionPrueba !== null) {
            $cfg = config('database.connections.'.config('database.default'));
            if (! app()->environment('testing') || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55471 || ($cfg['database'] ?? null) !== 'horarios_27b2_test' || ($cfg['username'] ?? null) !== 'horarios_test' || count($this->instrumentacionPrueba) !== 2) {
                throw new DomainException('Instrumentación de prueba no autorizada.');
            }
            $comando = [$this->interprete, '-B', base_path('tests/Feature/GestionHorarios/soporte/academico_instrumentado_27b8.py'), $this->instrumentacionPrueba[0], $this->instrumentacionPrueba[1], $directorio];
        }
        $proceso = new Process($comando, $directorio, ['PYTHONPATH' => $directorio, 'PYTHONDONTWRITEBYTECODE' => '1']);
        $proceso->setInput($json);
        $proceso->setTimeout($segundos + 30);
        try {
            if ($renovar === null && $iniciado === null) {
                $proceso->run();
            } else {
                $proceso->start();
                if ($iniciado !== null) {
                    $iniciado($proceso->getPid());
                }
                while ($proceso->isRunning()) {
                    if ($renovar !== null && ! $renovar()) {
                        $proceso->stop(0);
                        throw new ErrorEjecucionPythonPrueba('LEASE_PERDIDO');
                    }
                    $proceso->checkTimeout();
                    usleep(1000000);
                }
                $proceso->wait();
            }
        } catch (ProcessTimedOutException $e) {
            throw new ErrorEjecucionPythonPrueba('TIMEOUT_PROCESO', $e);
        }
        if (! $proceso->isSuccessful()) {
            throw new ErrorEjecucionPythonPrueba('ERROR_PROCESO');
        }

        return $this->validarRespuesta($proceso->getOutput());
    }

    public function validarRespuesta(string $salida): array
    {
        if (strlen($salida) > 5_000_000) {
            throw new ErrorEjecucionPythonPrueba('ERROR_COMUNICACION');
        }
        try {
            $resultado = json_decode($salida, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ErrorEjecucionPythonPrueba('ERROR_COMUNICACION', $e);
        }
        if (! is_array($resultado) || ($resultado['contrato'] ?? null) !== 'HORARIOS_RESULTADO_V1' || ! in_array($resultado['estado'] ?? null, ['OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'UNKNOWN', 'MODEL_INVALID', 'INPUT_INVALID'], true) || ! is_array($resultado['sesiones'] ?? null)) {
            throw new ErrorEjecucionPythonPrueba('ERROR_COMUNICACION');
        }
        if (in_array($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], true) && (($resultado['validacion_operativa']['valida'] ?? null) !== true || ($resultado['validacion_operativa']['completa'] ?? null) !== true)) {
            throw new ErrorEjecucionPythonPrueba('RESULTADO_OPERATIVO_INVALIDO');
        }

        return $resultado;
    }
}
