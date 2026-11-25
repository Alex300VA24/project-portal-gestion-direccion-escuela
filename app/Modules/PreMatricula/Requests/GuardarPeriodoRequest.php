<?php

namespace App\Modules\PreMatricula\Requests;

use App\Modules\PreMatricula\Services\PeriodosAcademicos;
use Illuminate\Validation\Rule;

final class GuardarPeriodoRequest extends CoordinacionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $parcial = $this->isMethod('PUT') ? 'sometimes' : 'required';

        return [
            'codigo' => [$parcial, 'string', 'max:20'],
            'fecha_inicio' => [$parcial, 'date_format:Y-m-d'],
            'fecha_fin' => [$parcial, 'date_format:Y-m-d'],
            'estado' => [$parcial, Rule::in(PeriodosAcademicos::ESTADOS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return PeriodosAcademicos::MENSAJES;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['fecha_inicio' => 'inicio de clases', 'fecha_fin' => 'fin de clases'];
    }
}
