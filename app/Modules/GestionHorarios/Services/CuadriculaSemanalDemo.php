<?php

namespace App\Modules\GestionHorarios\Services;

/** Presentación exclusivamente: nunca decide si un horario es factible. */
final class CuadriculaSemanalDemo
{
    public static function construir(array $actividades, array $sesiones, array $bloques): array
    {
        $dias = array_fill_keys(HorariosVersionadosDemo::DIAS, []);
        $desde = 1440; $hasta = 0;
        foreach ($bloques as [$i, $f]) {
            if ($i >= 0 && $f <= 1440 && $i < $f) { $desde = min($desde, $i); $hasta = max($hasta, $f); }
        }
        foreach ($actividades as $a) {
            $s = $sesiones[$a['id']] ?? null;
            if (!$s || !isset($dias[$s['dia']]) || $s['inicio'] < 0 || $s['fin'] > 1440 || $s['inicio'] >= $s['fin']) { continue; }
            $desde = min($desde, $s['inicio']); $hasta = max($hasta, $s['fin']);
            $dias[$s['dia']][] = ['actividad' => $a, 'sesion' => $s];
        }
        if ($desde >= $hasta) { $desde = 420; $hasta = 1200; }
        $desde = intdiv($desde, 60) * 60; $hasta = (int) ceil($hasta / 60) * 60;
        $columnas = [];
        foreach ($dias as $dia => $filas) {
            usort($filas, fn ($a, $b) => [$a['sesion']['inicio'], $a['sesion']['fin'], $a['actividad']['id']] <=> [$b['sesion']['inicio'], $b['sesion']['fin'], $b['actividad']['id']]);
            $fines = [];
            foreach ($filas as &$fila) {
                $carril = 0;
                while (isset($fines[$carril]) && $fines[$carril] > $fila['sesion']['inicio']) { $carril++; }
                $fines[$carril] = $fila['sesion']['fin'];
                $fila['carril'] = $carril;
            }
            unset($fila);
            $columnas[$dia] = ['carriles' => max(1, count($fines)), 'filas' => $filas];
        }
        return ['desde' => $desde, 'hasta' => $hasta, 'dias' => $columnas];
    }
}
