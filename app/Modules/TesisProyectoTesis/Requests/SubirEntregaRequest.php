<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Validation\Rule;

final class SubirEntregaRequest extends TesisFormRequest
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

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'tipo.required' => 'Elige si es un avance semanal o la versión final.',
            'tipo.in' => 'Elige si es un avance semanal o la versión final.',
            'numero_semana.between' => 'La semana debe estar entre :min y :max.',
            'archivo.required' => 'Adjunta el documento en PDF.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo de entrega',
            'numero_semana' => 'semana',
            'comentario' => 'comentario',
            'archivo' => 'documento (PDF)',
        ];
    }
}
