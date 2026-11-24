<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;

final class RegistrarSimilitudRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('verComoAsesor', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'porcentaje' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.Parametros::ARCHIVO_MAX_KB],
            'indicaciones' => ['nullable', 'string', 'max:2000'],
            'conformidad' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'porcentaje.between' => 'El porcentaje de similitud debe estar entre 0 y 100.',
            'porcentaje.decimal' => 'El porcentaje admite como máximo 2 decimales (por ejemplo, 12.50).',
            'archivo.required' => 'Adjunta el reporte de Turnitin en PDF.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'porcentaje' => 'porcentaje de similitud',
            'archivo' => 'reporte de Turnitin (PDF)',
            'indicaciones' => 'indicaciones',
            'conformidad' => 'conformidad',
        ];
    }
}
