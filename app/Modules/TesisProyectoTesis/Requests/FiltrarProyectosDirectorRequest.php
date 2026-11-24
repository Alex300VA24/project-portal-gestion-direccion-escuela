<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Validation\Rule;

/** Filtros de «Todos los proyectos» del Director. */
final class FiltrarProyectosDirectorRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => ['nullable', Rule::in(array_keys(Estados::ETIQUETAS))],
            'buscar' => ['nullable', 'string', 'max:80'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), ['estado.in' => 'Elige un estado de la lista o «Todos».']);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['estado' => 'estado', 'buscar' => 'búsqueda'];
    }
}
