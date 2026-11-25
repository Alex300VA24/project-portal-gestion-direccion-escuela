<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SolicitudTutoriaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id_periodo' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
            'clave_envio' => ['required', 'uuid'],
        ];
    }
}
