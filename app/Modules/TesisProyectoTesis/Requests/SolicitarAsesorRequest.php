<?php

namespace App\Modules\TesisProyectoTesis\Requests;

final class SolicitarAsesorRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('gestionarComoDirector', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'destinatario' => ['required', 'string', 'max:120'],
            'destinatario_area' => ['required', 'string', 'max:120'],
            'asunto' => ['required', 'string', 'max:200'],
            'detalle' => ['required', 'string', 'min:20', 'max:3000'],
            'docente_sugerido' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'detalle.min' => 'Describe la solicitud en al menos :min caracteres.',
            'docente_sugerido.regex' => 'El docente sugerido no es válido. Elige uno de la lista o «Sin sugerencia».',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'destinatario' => 'dirigido a (cargo)',
            'destinatario_area' => 'área',
            'asunto' => 'asunto',
            'detalle' => 'descripción',
            'docente_sugerido' => 'docente sugerido',
        ];
    }
}
