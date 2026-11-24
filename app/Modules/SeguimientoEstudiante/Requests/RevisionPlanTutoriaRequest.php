<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

final class RevisionPlanTutoriaRequest extends SeguimientoFormRequest
{
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'version_presentada' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:RECIBIDO,OBSERVADO'],
            'comentario' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
