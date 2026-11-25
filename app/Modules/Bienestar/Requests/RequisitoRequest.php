<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Foundation\Http\FormRequest;

final class RequisitoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BienestarPermissions::check($this->user(), BienestarPermissions::GESTIONAR);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'es_obligatorio' => ['nullable', 'boolean'],
            'formatos' => ['required', 'array', 'min:1'],
            'formatos.*' => ['in:pdf,jpg,png'],
            'tamano_maximo_mb' => ['required', 'integer', 'min:1', 'max:50'],
            'plantilla' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
            'quitar_plantilla' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['formatos.required' => 'Elige al menos un formato permitido.'];
    }
}
