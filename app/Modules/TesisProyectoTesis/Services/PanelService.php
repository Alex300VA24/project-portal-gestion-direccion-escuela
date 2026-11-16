<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\JuradoProyecto;
use App\Modules\TesisProyectoTesis\Support\Identidad;

/**
 * Cifras resumidas para la selección de rol.
 */
final class PanelService
{
    /** @return list<string> */
    public function resumenRol(string $rol, ?Identidad $id): array
    {
        if ($id === null) {
            return [];
        }

        return match ($rol) {
            'asesor' => [AsesorProyecto::query()->where('id_docente', $id->idDocente)->where('estado', 'Activo')->count().' proyectos asesorados'],
            'jurado' => [JuradoProyecto::query()->where('id_docente', $id->idDocente)->where('estado', 'Activo')->count().' proyectos asignados'],
            'alumno' => ['Tu proyecto, avances y observaciones'],
            'director' => ['Asesores, jurados e indicadores'],
            'secretaria' => ['Resoluciones, sustentaciones y actas'],
            default => [],
        };
    }
}
