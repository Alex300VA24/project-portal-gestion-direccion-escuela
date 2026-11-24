<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Requests\ModuleRequest;

/** Observación del administrador sobre una convocatoria o una práctica. */
final class ObservacionRequest extends ModuleRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:2000'],
            'id_documento' => ['nullable', 'integer'],
        ];
    }
}
