<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Requests\ModuleRequest;

final class ValidarPracticaRequest extends ModuleRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['id_docente' => ['required', 'integer']];
    }
}
