<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use Illuminate\Foundation\Http\FormRequest;

abstract class SeguimientoFormRequest extends FormRequest
{
    private int $persona;

    public function authorize(SeguimientoEstudianteService $seguimiento): bool
    {
        $this->persona = $seguimiento->personaVinculada($this->user());

        return true;
    }

    public function persona(): int
    {
        return $this->persona;
    }
}
