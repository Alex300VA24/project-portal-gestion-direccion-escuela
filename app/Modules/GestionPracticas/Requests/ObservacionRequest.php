<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Shared\Requests\ModuleRequest;

/** Observación del administrador sobre una convocatoria o una práctica. */
final class ObservacionRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:2000'],
            'id_documento' => ['nullable', 'integer'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'descripcion.required' => 'Escriba las observaciones para que se sepa qué corregir.',
        ];
    }
}
