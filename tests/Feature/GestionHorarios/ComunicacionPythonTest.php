<?php

namespace Tests\Feature\GestionHorarios;

use JsonException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class ComunicacionPythonTest extends TestCase
{
    private function proceso(string $modo, float $timeout = 5): Process
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Indique HORARIOS_PYTHON_AISLADO con el intérprete del entorno de pruebas.');
        }
        $proceso = new Process([$python, __DIR__.'/python/proceso_controlado.py']);
        $proceso->setInput(json_encode(['modo' => $modo], JSON_THROW_ON_ERROR));
        $proceso->setTimeout($timeout);
        $proceso->run();

        return $proceso;
    }

    private function respuesta(Process $proceso): array
    {
        if (! $proceso->isSuccessful()) {
            throw new \RuntimeException('Error del proceso controlado.');
        }
        $resultado = json_decode($proceso->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($resultado) || ($resultado['contrato'] ?? null) !== 'HORARIOS_RESULTADO_V1' || ! in_array($resultado['estado'] ?? null, ['OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'UNKNOWN', 'MODEL_INVALID'], true)) {
            throw new \UnexpectedValueException('Respuesta controlada inválida.');
        }

        return $resultado;
    }

    public function test_cinco_estados_se_transmiten_sin_confundir_unknown(): void
    {
        foreach (['OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'UNKNOWN', 'MODEL_INVALID'] as $estado) {
            self::assertSame($estado, $this->respuesta($this->proceso($estado))['estado']);
        }
    }

    public function test_salida_incompleta_se_rechaza(): void
    {
        $this->expectException(JsonException::class);
        $this->respuesta($this->proceso('incompleto'));
    }

    public function test_contrato_invalido_se_rechaza(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->respuesta($this->proceso('invalido'));
    }

    public function test_codigo_de_error_se_rechaza(): void
    {
        $proceso = $this->proceso('error');
        self::assertSame(2, $proceso->getExitCode());
        $this->expectException(\RuntimeException::class);
        $this->respuesta($proceso);
    }

    public function test_tiempo_agotado(): void
    {
        $this->expectException(ProcessTimedOutException::class);
        $this->proceso('timeout', 0.2);
    }

    public function test_timeout_real_detiene_el_proceso_directo_antes_de_escribir(): void
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Se requiere Python aislado.');
        }
        $marca = sys_get_temp_dir().DIRECTORY_SEPARATOR.'horarios-timeout-'.bin2hex(random_bytes(12));
        $lista = $marca.'-listo';
        $proceso = new Process([$python, __DIR__.'/python/proceso_controlado.py'], null, ['HORARIOS_TIMEOUT_MARKER' => $marca, 'HORARIOS_TIMEOUT_READY' => $lista]);
        $proceso->setInput('{"modo":"timeout"}');
        $proceso->setTimeout(10);
        try {
            $proceso->start();
            $limite = microtime(true) + 5;
            while (! is_file($lista) && $proceso->isRunning() && microtime(true) < $limite) {
                usleep(50000);
            }
            self::assertFileExists($lista);
            $proceso->setTimeout(0.5);
            try {
                $proceso->wait();
                self::fail('El proceso debía superar el tiempo máximo.');
            } catch (ProcessTimedOutException) {
                self::assertFalse($proceso->isRunning());
            }
            usleep(3200000);
            self::assertFileDoesNotExist($marca);
        } finally {
            if (is_file($marca)) {
                unlink($marca);
            }
            if (is_file($lista)) {
                unlink($lista);
            }
        }
    }
}
