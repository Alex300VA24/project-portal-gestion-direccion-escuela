<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

final class MaterialNivelacionRequest extends SeguimientoFormRequest
{
    public function rules(): array
    {
        if ($this->routeIs('seguimiento-estudiante.nivelacion.visibilidad-material')) {
            return ['visible' => ['required', 'boolean']];
        }

        return [
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'titulo' => ['required', 'string', 'max:150'],
            'visible' => ['sometimes', 'boolean'],
        ];
    }
}
