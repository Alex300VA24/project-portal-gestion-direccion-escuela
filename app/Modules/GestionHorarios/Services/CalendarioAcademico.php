<?php

namespace App\Modules\GestionHorarios\Services;

/** Análisis de lectura; la configuración explícita aún no tiene proveedor institucional. */
final class CalendarioAcademico
{
    public function evaluar(?array $periodo, array $bloques, array $actividades, ?array $configuracion = null, array $bloqueos = []): array
    {
        if (! $periodo) {
            return ['estado' => 'INEXISTENTE', 'bloques' => [], 'dias' => null, 'recesos' => null, 'bloqueos' => [], 'patrones_definidos' => 0, 'patrones_pendientes' => count($actividades), 'candidatos' => [], 'diagnosticos' => ['PERIODO_INEXISTENTE']];
        }
        $errores = [];
        $inicio = $periodo['fecha_inicio'] ?? null;
        $fin = $periodo['fecha_fin'] ?? null;
        if (! $this->fecha($inicio) || ! $this->fecha($fin) || $inicio > $fin) {
            $errores[] = 'FECHAS_INVALIDAS';
        }
        $ordenados = [];
        foreach ($bloques as $bloque) {
            $a = $this->minutos($bloque['hora_inicio'] ?? null);
            $b = $this->minutos($bloque['hora_fin'] ?? null);
            if ($a === null || $b === null || $a >= $b || (isset($bloque['id_periodo']) && (string) $bloque['id_periodo'] !== (string) ($periodo['id_periodo'] ?? ''))) {
                $errores[] = 'BLOQUE_INVALIDO';

                continue;
            }
            $ordenados[] = ['inicio' => $a, 'fin' => $b, 'registro' => $bloque];
        }
        usort($ordenados, fn ($a, $b) => [$a['inicio'], $a['fin']] <=> [$b['inicio'], $b['fin']]);
        $finAnterior = -1;
        $anteriores = [];
        foreach ($ordenados as $bloque) {
            $clave = $bloque['inicio'].':'.$bloque['fin'];
            if (isset($anteriores[$clave])) {
                $errores[] = 'BLOQUE_DUPLICADO';
            } elseif ($bloque['inicio'] < $finAnterior) {
                $errores[] = 'BLOQUES_SUPERPUESTOS';
            }
            $anteriores[$clave] = true;
            $finAnterior = max($finAnterior, $bloque['fin']);
        }
        $diasValidos = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
        $completa = is_array($configuracion) && ($configuracion['validacion'] ?? null) === 'CONFIRMADA'
            && isset($configuracion['dias'], $configuracion['recesos'], $configuracion['feriados'], $configuracion['sabado_regular'], $configuracion['sabado_repeticion'])
            && is_array($configuracion['dias']) && is_array($configuracion['recesos']) && is_array($configuracion['feriados'])
            && is_bool($configuracion['sabado_regular']) && is_bool($configuracion['sabado_repeticion']);
        if ($completa && (! $configuracion['dias'] || count(array_unique($configuracion['dias'])) !== count($configuracion['dias']) || array_diff($configuracion['dias'], $diasValidos))) {
            $errores[] = 'DIAS_INVALIDOS';
        }
        if ($completa) {
            foreach ($configuracion['recesos'] as $receso) {
                $a = $this->minutos($receso['inicio'] ?? null);
                $b = $this->minutos($receso['fin'] ?? null);
                if ($a === null || $b === null || $a >= $b) {
                    $errores[] = 'RECESO_INVALIDO';
                }
            }
            foreach ($configuracion['feriados'] as $fecha) {
                if (! $this->fecha($fecha) || $fecha < $inicio || $fecha > $fin) {
                    $errores[] = 'FERIADO_INVALIDO';
                }
            }
        }
        $pendientes = count($actividades);
        $estado = $errores ? 'INCONSISTENTE' : ($completa && $ordenados ? 'COMPLETO' : (! $ordenados && ! $completa ? 'INEXISTENTE' : 'PARCIAL'));
        if (! $completa) {
            $errores[] = 'CALENDARIO_SEMANAL_NO_CONFIRMADO';
        }
        if (! $ordenados) {
            $errores[] = 'SIN_BLOQUES';
        }

        return ['estado' => $estado, 'periodo_id' => $periodo['id_periodo'] ?? null, 'bloques' => array_column($ordenados, 'registro'), 'dias' => $completa ? $configuracion['dias'] : null, 'recesos' => $completa ? $configuracion['recesos'] : null, 'feriados' => $completa ? $configuracion['feriados'] : null, 'sabado_regular' => $completa ? $configuracion['sabado_regular'] : null, 'sabado_repeticion' => $completa ? $configuracion['sabado_repeticion'] : null, 'bloqueos' => $bloqueos, 'patrones_definidos' => 0, 'patrones_pendientes' => $pendientes, 'candidatos' => [], 'diagnosticos' => array_values(array_unique($errores))];
    }

    public function minutos(mixed $hora): ?int
    {
        if (! is_string($hora) || ! preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $hora, $m) || (int) $m[1] > 23 || (int) $m[2] > 59 || (isset($m[3]) && (int) $m[3] !== 0)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    private function fecha(mixed $fecha): bool
    {
        return is_string($fecha) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
