<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegistrarResultadoRequest extends FormRequest
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
}
