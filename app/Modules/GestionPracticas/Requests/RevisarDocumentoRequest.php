<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Requests\ModuleRequest;

final class RevisarDocumentoRequest extends ModuleRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['estado' => ['required', 'in:aprobado,observado']];
    }
}
