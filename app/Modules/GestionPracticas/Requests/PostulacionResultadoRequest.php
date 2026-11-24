<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Shared\Requests\ModuleRequest;

final class PostulacionResultadoRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['resultado' => ['required', 'in:seleccionado,no_seleccionado,desistido']];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'resultado.required' => 'Indique el resultado de su postulación.',
            'resultado.in' => 'Elija una de las opciones: fui seleccionado, no fui seleccionado o desistí.',
        ];
    }
}
