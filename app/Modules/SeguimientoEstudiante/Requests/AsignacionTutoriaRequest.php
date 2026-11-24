<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

final class AsignacionTutoriaRequest extends SeguimientoFormRequest
{
    public function rules(): array
    {
        if ($this->routeIs('seguimiento-estudiante.asignaciones.finalizar')) {
            return [
                'id_estudiante' => 'required|integer|min:1',
                'id_asignacion' => 'required|integer|min:1',
                'version_causa' => 'required|uuid',
                'causa' => 'required|string|max:40',
                'motivo' => 'required|string|min:10|max:1000',
                'reemplazo' => 'nullable|integer|min:1',
            ];
        }

        return [
            'id_estudiante' => ['required', 'integer', 'min:1'],
            'id_docente' => ['required', 'integer', 'min:1'],
            'id_asignacion' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
