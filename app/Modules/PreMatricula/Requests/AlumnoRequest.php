<?php

namespace App\Modules\PreMatricula\Requests;

use App\Modules\PreMatricula\Policies\PrematriculaPolicy;
use Illuminate\Foundation\Http\FormRequest;

/** Acciones del alumno sobre su propia prematrícula. */
class AlumnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PrematriculaPolicy::class)->usarComoAlumno($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
