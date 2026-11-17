<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Models\TipoCaso;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AtenderAlertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BienestarPermissions::check($this->user(), BienestarPermissions::GESTIONAR);
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', 'in:abrir_caso,programar_cita,descartar'],
            'id_tipo_caso' => ['required_if:accion,abrir_caso', 'nullable', Rule::exists(TipoCaso::class, 'id_tipo_caso')],
            'descripcion_caso' => ['nullable', 'string'],
            'inicio_cita' => ['required_if:accion,programar_cita', 'nullable', 'date'],
            'modalidad' => ['required_if:accion,programar_cita', 'nullable', 'in:Presencial,Virtual'],
            'motivo_descarte' => ['required_if:accion,descartar', 'nullable', 'string', 'min:5'],
        ];
    }
}
