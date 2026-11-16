<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubirEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('verComoAlumno', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['Avance', 'Final'])],
            'numero_semana' => ['required', 'integer', 'between:1,'.(Parametros::SEMANAS_CICLO + 2)],
            'comentario' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.Parametros::ARCHIVO_MAX_KB],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['tipo' => 'tipo de entrega', 'numero_semana' => 'semana', 'archivo' => 'archivo (PDF)'];
    }
}
