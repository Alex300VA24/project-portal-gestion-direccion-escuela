<?php

namespace App\Modules\GestionHorarios\Services;

/** Huella de las vacantes aprobadas; independiente de los ID y acciones de creación. */
final class ConsolidacionVacantesLocal
{
    public static function huella(array $datos, string $cierre, array $ajustes): string
    {
        $cursos = array_map(static fn (array $c): array => [
            'curso' => $c['id_curso_plan'], 'total' => $c['total'], 'original' => $c['original'],
            'secciones' => array_map(static fn (array $s): array => [
                'codigo' => $s['codigo'], 'capacidad' => $s['capacidad'],
                'poblacion' => $s['poblacion_estimada'], 'grupos_lab' => $s['grupos_lab'],
            ], $c['propuesta']),
        ], $datos['cursos']);
        return hash('sha256', json_encode([$datos['periodo'], $datos['plan'], $datos['version'], $cierre, $ajustes, $datos['ambientes'], $cursos], JSON_THROW_ON_ERROR));
    }

    public static function estado(?string $cierre, ?string $huella, ?string $guardada, bool $revision, bool $pendientes): string
    {
        if ($cierre === null) return 'prematricula_abierta';
        if ($revision || $huella === null) return 'revision';
        if ($guardada === null || ! hash_equals($guardada, $huella)) return 'sin_consolidar';
        return $pendientes ? 'consolidada' : 'secciones_verificadas';
    }
}
