<?php

namespace App\Modules\Bienestar\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FiltrarAlertaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accion' => ['required', 'string', 'in:pasar_bienestar,descartar'],
            'motivo' => ['nullable', 'string', 'required_if:accion,descartar', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'accion.required' => 'Debe seleccionar una acción: Pasar a Bienestar o Descartar.',
            'accion.in' => 'La acción seleccionada no es válida.',
            'motivo.required_if' => 'Para descartar una alerta es obligatorio consignar el motivo.',
            'motivo.min' => 'El motivo de descarte debe tener al menos 5 caracteres.',
        ];
    }
}
