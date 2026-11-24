<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Requests\ModuleRequest;

final class EvaluacionRequest extends ModuleRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'notas' => ['required', 'array'],
            'notas.*' => ['required', 'numeric', 'min:0', 'max:20'],
            'comentario' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
