<?php

namespace App\Modules\GestionPracticas\Services;

use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Models\Seguimiento;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class SeguimientoService
{
    /** @param array{fecha: string, tipo: string, descripcion: string, observaciones?: ?string} $datos */
    public function registrar(Practica $practica, Actor $supervisor, array $datos): Seguimiento
    {
        if ((int) $practica->id_docente_supervisor !== $supervisor->requiereDocente()) {
            throw new AuthorizationException('Solo el docente supervisor asignado a esta práctica puede registrar su seguimiento.');
        }

        if ($practica->estado !== Practica::EN_CURSO) {
            throw ValidationException::withMessages(['estado' => 'Solo se registra seguimiento mientras la práctica está en curso.']);
        }

        return $practica->seguimientos()->create([
            'fecha' => $datos['fecha'],
            'tipo' => $datos['tipo'],
            'descripcion' => $datos['descripcion'],
            'observaciones' => $datos['observaciones'] ?? null,
            'registrado_por' => $supervisor->requiereDocente(),
        ])->refresh();
    }
}
