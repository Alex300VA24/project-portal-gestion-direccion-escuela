<?php

namespace App\Modules\PreMatricula\Requests;

final class ValidarResultadosRequest extends CoordinacionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:5120', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['archivo.mimetypes' => 'El archivo debe ser un Excel .xlsx.'];
    }
}
