<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SolicitarAsesorRequest extends FormRequest
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

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['destinatario' => 'cargo del destinatario', 'destinatario_area' => 'área', 'detalle' => 'descripción', 'docente_sugerido' => 'docente sugerido'];
    }
}
