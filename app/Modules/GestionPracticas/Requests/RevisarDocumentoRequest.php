<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Shared\Requests\ModuleRequest;

final class RevisarDocumentoRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['estado' => ['required', 'in:aprobado,observado']];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'estado.required' => 'Indique si el documento queda aprobado u observado.',
            'estado.in' => 'La revisión del documento debe ser «aprobado» u «observado».',
        ];
    }
}
