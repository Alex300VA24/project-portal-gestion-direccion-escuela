<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

final class DirectorioRequest extends SeguimientoFormRequest
{
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100']];
    }
}
