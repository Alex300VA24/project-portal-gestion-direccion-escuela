<?php

namespace App\Modules\PreMatricula\Requests;

use App\Modules\PreMatricula\Models\DetalleSolicitud;
use Illuminate\Validation\Rule;

final class CambiarTurnoRequest extends AlumnoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['preferencia_turno' => ['required', Rule::in(DetalleSolicitud::TURNOS)]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['preferencia_turno.in' => 'Elige Mañana, Tarde o Sin preferencia.', 'preferencia_turno.required' => 'Elige una preferencia de turno.'];
    }
}
