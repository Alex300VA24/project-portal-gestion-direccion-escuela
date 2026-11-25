<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Support\Catalogos;
use App\Shared\Requests\ModuleRequest;
use Illuminate\Validation\Rule;

final class DocumentoRequest extends ModuleRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(array_keys(Catalogos::TIPOS_DOCUMENTO))],
            'archivo' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
