<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Shared\Requests\ModuleRequest;

final class EvaluacionRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'notas' => ['required', 'array'],
            'notas.*' => ['required', 'numeric', 'min:0', 'max:20'],
            'comentario' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'notas.required' => 'Registre la nota de cada criterio de evaluación.',
            'notas.*.required' => 'Falta la nota de al menos un criterio: registre todas.',
            'notas.*.numeric' => 'Las notas deben ser números (use punto para los decimales).',
            'notas.*.min' => 'Las notas van de 0 a 20: no pueden ser menores que :min.',
            'notas.*.max' => 'Las notas van de 0 a 20: no pueden ser mayores que :max.',
        ];
    }
}
