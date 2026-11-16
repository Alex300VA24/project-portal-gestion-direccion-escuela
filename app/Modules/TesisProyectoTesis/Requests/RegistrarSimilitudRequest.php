<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Foundation\Http\FormRequest;

final class RegistrarSimilitudRequest extends FormRequest
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

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['porcentaje' => 'porcentaje de similitud', 'archivo' => 'reporte de Turnitin (PDF)', 'indicaciones' => 'indicaciones'];
    }
}
