<?php

namespace App\Modules\GestionHorarios\Services;

use InvalidArgumentException;

/** Simulación local: no modifica actividades ni versiones institucionales. */
final class BorradorSemana
{
    public const DIAS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

    public static function huella(array $sesiones, array $bloques): string
    {
        return hash('sha256', json_encode([$sesiones, $bloques], JSON_THROW_ON_ERROR));
    }

    public static function mover(array $sesiones, array $bloques, string $id, string $dia, string $hora): array
    {
        if (! in_array($dia, self::DIAS, true)) {
            throw new InvalidArgumentException('Día inválido.');
        }
        $calendario = new CalendarioAcademico;
        $inicio = $calendario->minutos($hora);
        if ($inicio === null) {
            throw new InvalidArgumentException('Hora inválida.');
        }
        foreach ($sesiones as $i => $s) {
            if ((string) $s['id_actividad'] !== $id) {
                continue;
            }
            $a = $calendario->minutos($s['hora_inicio']);
            $b = $calendario->minutos($s['hora_fin']);
            if ($a === null || $b === null || $b <= $a) {
                throw new InvalidArgumentException('Duración original inválida.');
            }
            $fin = $inicio + $b - $a;
            $franjas = [];
            foreach ($bloques as $bloque) {
                $desde = $calendario->minutos($bloque['hora_inicio']);
                $hasta = $calendario->minutos($bloque['hora_fin']);
                if ($desde === null || $hasta === null || $desde >= $hasta || isset($franjas[$desde])) {
                    throw new InvalidArgumentException('Bloques institucionales inconsistentes.');
                }
                $franjas[$desde] = $hasta;
            }
            ksort($franjas);
            $anterior = -1;
            foreach ($franjas as $desde => $hasta) {
                if ($desde < $anterior) {
                    throw new InvalidArgumentException('Bloques institucionales superpuestos.');
                }
                $anterior = $hasta;
            }
            $cursor = $inicio;
            while ($cursor < $fin && isset($franjas[$cursor])) {
                $cursor = $franjas[$cursor];
            }
            if ($cursor !== $fin || $fin >= 1440) {
                throw new InvalidArgumentException('La actividad debe ocupar bloques consecutivos completos; no puede cruzar un receso.');
            }
            $sesiones[$i]['dia_semana'] = $dia;
            $sesiones[$i]['hora_inicio'] = sprintf('%02d:%02d:00', intdiv($inicio, 60), $inicio % 60);
            $sesiones[$i]['hora_fin'] = sprintf('%02d:%02d:00', intdiv($fin, 60), $fin % 60);

            return $sesiones;
        }
        throw new InvalidArgumentException('Actividad no incluida en este borrador.');
    }

    /** Advierte coincidencias, sin asumir que los laboratorios simultáneos son incompatibles. */
    public static function coincidencias(array $sesiones): array
    {
        $avisos = [];
        foreach ($sesiones as $i => $a) {
            foreach (array_slice($sesiones, $i + 1) as $b) {
                if ($a['dia_semana'] === $b['dia_semana'] && $a['hora_inicio'] < $b['hora_fin'] && $b['hora_inicio'] < $a['hora_fin']) {
                    $avisos[] = ['dia' => $a['dia_semana'], 'primera' => $a['curso_nombre'].' · '.$a['tipo_actividad'], 'segunda' => $b['curso_nombre'].' · '.$b['tipo_actividad']];
                }
            }
        }

        return $avisos;
    }
}
