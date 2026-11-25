<?php

namespace App\Modules\PreMatricula\Requests;

use App\Modules\PreMatricula\Models\DetalleSolicitud;
use Illuminate\Validation\Rule;

final class AgregarCursoRequest extends AlumnoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_curso_plan' => ['required', 'integer', 'min:1'],
            'preferencia_turno' => ['nullable', Rule::in(DetalleSolicitud::TURNOS)],
        ];
    }
}
