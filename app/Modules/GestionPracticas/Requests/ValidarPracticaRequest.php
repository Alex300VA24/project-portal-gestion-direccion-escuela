<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Shared\Requests\ModuleRequest;

final class ValidarPracticaRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['id_docente' => ['required', 'integer']];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'id_docente.required' => 'Seleccione el docente supervisor que se asignará a la práctica.',
            'id_docente.integer' => 'Seleccione un docente de la lista.',
        ];
    }
}
