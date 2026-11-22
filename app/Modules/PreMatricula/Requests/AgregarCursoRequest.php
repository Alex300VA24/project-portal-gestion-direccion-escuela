<?php

namespace App\Modules\PreMatricula\Requests;

final class AgregarCursoRequest extends AlumnoRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['id_curso_plan' => ['required', 'integer', 'min:1']];
    }
}
