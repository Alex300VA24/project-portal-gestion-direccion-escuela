<?php

namespace App\Modules\Bienestar\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTipoSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'unidad_responsable' => ['required', 'string', 'max:200'],
            'plazo_dias_habiles' => ['required', 'integer', 'min:1', 'max:90'],
            'base_normativa_fuente' => ['required', 'string', 'max:500'],
            'estado_verificacion' => ['required', 'string', 'in:verificado,pendiente_verificar'],
            'activo' => ['nullable', 'boolean'],
            'requisitos' => ['nullable', 'array'],
            'requisitos.*.nombre' => ['required_with:requisitos', 'string', 'max:255'],
            'requisitos.*.descripcion' => ['nullable', 'string'],
            'requisitos.*.es_obligatorio' => ['nullable', 'boolean'],
            'requisitos.*.formatos_permitidos' => ['nullable', 'string', 'max:100', 'regex:/^\s*(pdf|jpe?g|png)(\s*,\s*(pdf|jpe?g|png))*\s*$/i'],
            'requisitos.*.tamano_maximo_mb' => ['nullable', 'integer', 'min:1', 'max:50'],
            'requisitos.*.plantilla' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
        ];
    }
}
