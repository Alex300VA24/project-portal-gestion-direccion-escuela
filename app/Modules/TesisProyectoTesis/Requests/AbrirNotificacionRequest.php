<?php

namespace App\Modules\TesisProyectoTesis\Requests;

/** Abre un aviso de la campana por su token cifrado (campo oculto). */
final class AbrirNotificacionRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['n' => ['required', 'string', 'max:2000']];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'n.required' => 'No se pudo abrir el aviso. Recarga la página y vuelve a pulsarlo en la campana.',
            'n.max' => 'No se pudo abrir el aviso. Recarga la página y vuelve a pulsarlo en la campana.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['n' => 'aviso'];
    }
}
