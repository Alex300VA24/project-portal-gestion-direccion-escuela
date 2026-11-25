<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

final class CambioCitaTutoriaRequest extends SeguimientoFormRequest
{
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'tipo_cambio' => ['required', 'in:REPROGRAMACION,CANCELACION'],
            'motivo_cambio' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
