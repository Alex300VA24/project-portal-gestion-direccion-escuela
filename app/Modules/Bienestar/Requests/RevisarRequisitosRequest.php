<?php

namespace App\Modules\Bienestar\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RevisarRequisitosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'accion' => ['required', 'string', 'in:recibir,observar,remitir'],
            'revisiones' => ['nullable', 'array'],
            'revisiones.*.id_requisito' => ['required_with:revisiones', 'integer'],
            'revisiones.*.estado' => ['required_with:revisiones', 'in:conforme,observado'],
            'revisiones.*.comentario' => ['nullable', 'string', 'max:500'],
            'observacion_general' => ['nullable', 'string', 'max:1000'],
        ];

        // Recibir solo acusa recepción: todavía no hay revisión que validar
        if ($this->input('accion') === 'recibir') {
            return ['accion' => $rules['accion']];
        }

        if ($this->input('accion') === 'observar') {
            array_unshift($rules['revisiones.*.comentario'], 'required_if:revisiones.*.estado,observado');
        }

        if ($this->input('accion') === 'remitir') {
            $rules['confirma_folio_externo'] = ['required', 'accepted'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'accion.required' => 'Debe indicar una acción de revisión (recibir, observar o remitir).',
            'revisiones.*.estado.required_with' => 'Marca cada requisito como conforme u observado.',
            'revisiones.*.comentario.required_if' => 'Debe ingresar un comentario para cada requisito observado.',
            'confirma_folio_externo.required' => 'Para remitir a Bienestar debe confirmar bajo declaración que armó el folio físico/externo fuera del sistema.',
            'confirma_folio_externo.accepted' => 'Debe marcar la casilla de confirmación de foliado externo.',
        ];
    }
}
