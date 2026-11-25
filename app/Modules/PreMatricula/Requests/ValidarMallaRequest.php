<?php

namespace App\Modules\PreMatricula\Requests;

final class ValidarMallaRequest extends CoordinacionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:5120', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['archivo.required' => 'Elige el archivo de la malla.', 'archivo.mimetypes' => 'El archivo debe ser un Excel .xlsx.'];
    }
}
