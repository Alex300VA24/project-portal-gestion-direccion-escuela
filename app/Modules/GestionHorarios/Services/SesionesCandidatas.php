<?php

namespace App\Modules\GestionHorarios\Services;

/** Ensayo temporal con contrato explícito; no decide recursos ni factibilidad. */
final class SesionesCandidatas
{
    public function ensayar(array $calendario, array $actividad, ?array $declaracion, array $bloqueos): array
    {
        $duraciones = $actividad['duraciones_sesion'] ?? null;
        if (($calendario['estado'] ?? null) !== 'COMPLETO' || ! isset($calendario['periodo_id']) || ! is_array($duraciones) || ! $duraciones || ! is_array($declaracion) || ($declaracion['estado'] ?? null) !== 'PRESENTADA' || ! is_array($declaracion['franjas'] ?? null) || ! isset($actividad['id_actividad'], $actividad['id_seccion'], $actividad['id_docente'], $declaracion['id_docente'], $declaracion['id_periodo']) || (string) $declaracion['id_docente'] !== (string) $actividad['id_docente'] || (string) $declaracion['id_periodo'] !== (string) $calendario['periodo_id'] || ! is_bool($actividad['repeticion'] ?? null) || ! in_array($actividad['tipo'] ?? null, ['T', 'P', 'LAB'], true)) {
            return ['estado' => 'NO_VERIFICABLE', 'candidatos' => [], 'diagnosticos' => ['Faltan calendario, patrón, docente o declaración confirmada.']];
        }
        if (($calendario['feriados'] ?? null) !== [] || ($calendario['bloqueos'] ?? null) !== [] || $bloqueos !== []) {
            return ['estado' => 'NO_VERIFICABLE', 'candidatos' => [], 'diagnosticos' => ['Los feriados o bloqueos requieren evaluación por fecha y recurso.']];
        }
        $reloj = new CalendarioAcademico;
        $candidatos = [];
        foreach ($duraciones as $indice => $duracion) {
            if (! is_int($duracion) || $duracion <= 0) {
                return ['estado' => 'NO_VERIFICABLE', 'candidatos' => [], 'diagnosticos' => ['Duración de sesión inválida.']];
            }
            foreach ($calendario['dias'] as $dia) {
                if ($dia === 'SABADO' && ! ($actividad['repeticion'] ? $calendario['sabado_repeticion'] : $calendario['sabado_regular'])) {
                    continue;
                }
                foreach ($calendario['bloques'] as $bloque) {
                    $inicio = $reloj->minutos($bloque['hora_inicio'] ?? null);
                    $finBloque = $reloj->minutos($bloque['hora_fin'] ?? null);
                    $fin = $inicio + $duracion;
                    if ($fin > $finBloque) {
                        continue;
                    }
                    $permitida = false;
                    foreach ($declaracion['franjas'] as $franja) {
                        $a = $reloj->minutos($franja['inicio'] ?? null);
                        $b = $reloj->minutos($franja['fin'] ?? null);
                        if (($franja['dia'] ?? null) === $dia && $a !== null && $b !== null && $a <= $inicio && $fin <= $b) {
                            $permitida = true;
                        }
                    }
                    foreach ($calendario['recesos'] as $receso) {
                        if ($inicio < $reloj->minutos($receso['fin']) && $reloj->minutos($receso['inicio']) < $fin) {
                            $permitida = false;
                        }
                    }
                    if ($permitida) {
                        $candidatos[] = ['periodo_id' => $calendario['periodo_id'], 'actividad_id' => $actividad['id_actividad'], 'seccion_id' => $actividad['id_seccion'], 'docente_id' => $actividad['id_docente'], 'tipo' => $actividad['tipo'], 'sesion' => $indice + 1, 'dia' => $dia, 'inicio_minutos' => $inicio, 'fin_minutos' => $fin];
                    }
                }
            }
        }

        return ['estado' => 'PARCIAL', 'candidatos' => $candidatos, 'diagnosticos' => ['Faltan validar población, ambientes, restricciones y compatibilidad entre sesiones.']];
    }
}
