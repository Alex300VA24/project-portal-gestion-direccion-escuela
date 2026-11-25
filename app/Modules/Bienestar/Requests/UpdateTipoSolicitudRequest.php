<?php

namespace App\Modules\Bienestar\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTipoSolicitudRequest extends FormRequest
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
        ];
    }
}
