<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Models\Beneficio;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSolicitudApoyoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(BienestarPermissions::SOLICITAR) ?? false;
    }

    public function rules(): array
    {
        return [
            'id_beneficio' => ['required', Rule::exists(Beneficio::class, 'id_beneficio')->where('activo', true)],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_beneficio.required' => 'Debe seleccionar un programa o beneficio de bienestar.',
            'id_beneficio.exists' => 'El beneficio seleccionado no se encuentra activo o disponible.',
            'motivo.min' => 'El motivo de su solicitud debe contener al menos 10 caracteres explicativos.',
        ];
    }
}
