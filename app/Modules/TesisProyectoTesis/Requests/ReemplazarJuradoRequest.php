<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Validation\Rule;

final class ReemplazarJuradoRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('gestionarComoDirector', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cargo' => ['required', Rule::in(Parametros::CARGOS_JURADO)],
            'docente' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'cargo.required' => 'Elige el cargo del miembro que vas a reemplazar.',
            'cargo.in' => 'Elige Presidente, Secretario o Vocal.',
            'docente.required' => 'Elige al nuevo docente.',
            'docente.regex' => 'El docente elegido no es válido. Elige uno de la lista.',
            'motivo.required' => 'Escribe el motivo del reemplazo.',
            'motivo.min' => 'Explica el motivo en al menos :min caracteres.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'cargo' => 'cargo',
            'docente' => 'nuevo docente',
            'motivo' => 'motivo',
        ];
    }
}
