<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use Illuminate\Validation\Rule;

final class RegistrarResultadoRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $sustentacion = $this->route('sustentacion');

        return $sustentacion !== null && $this->user()?->can('verComoJurado', $sustentacion->proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resultado' => ['required', Rule::in(['Aprobatorio', 'Desaprobatorio'])],
            'mencion' => ['nullable', 'boolean'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'resultado.required' => 'Elige tu resultado: Aprobatorio o Desaprobatorio.',
            'resultado.in' => 'Elige tu resultado: Aprobatorio o Desaprobatorio.',
            'hora_fin.date_format' => 'Escribe la hora de término en formato de 24 horas (por ejemplo, 11:30).',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'resultado' => 'resultado',
            'mencion' => 'mención honrosa',
            'hora_fin' => 'hora de término',
        ];
    }
}
