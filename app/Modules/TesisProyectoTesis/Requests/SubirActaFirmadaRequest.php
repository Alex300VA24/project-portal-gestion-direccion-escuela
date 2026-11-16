<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Foundation\Http\FormRequest;

final class SubirActaFirmadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $acta = $this->route('acta');

        return $acta !== null && $this->user()?->can('gestionarComoSecretaria', $acta->proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['archivo' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.Parametros::ARCHIVO_MAX_KB]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['archivo' => 'documento firmado (PDF)'];
    }
}
