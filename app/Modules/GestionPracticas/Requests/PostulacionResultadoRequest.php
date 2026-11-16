<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Requests\ModuleRequest;

final class PostulacionResultadoRequest extends ModuleRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['resultado' => ['required', 'in:seleccionado,no_seleccionado,desistido']];
    }
}
