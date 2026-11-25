<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

final class RecepcionSecretariaRequest extends SeguimientoFormRequest
{
    public function rules(): array
    {
        if ($this->routeIs('seguimiento-estudiante.secretaria.lotes.guardar')) {
            return [
                'ambito' => ['required', 'string', 'regex:/^[1-9][0-9]{0,18}-[1-9][0-9]{0,18}$/D'],
                'tipo' => ['required', 'in:matriculas,notas,asistencia'],
                'referencia' => ['required', 'string', 'max:150'],
                'fecha_corte' => ['required', 'date_format:Y-m-d', 'after_or_equal:0001-01-01', 'before_or_equal:'.now('America/Lima')->toDateString()],
                'archivo' => ['required', 'file', 'max:2048'],
            ];
        }

        return [
            'tipo' => ['required', 'in:matriculas,notas,asistencia'],
            'archivo' => ['required', 'file', 'max:2048'],
        ];
    }
}
