<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Foundation\Http\FormRequest;

final class EvaluarSolicitudApoyoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BienestarPermissions::check($this->user(), BienestarPermissions::GESTIONAR);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('estado'))) {
            $this->merge(['estado' => strtolower($this->input('estado'))]);
        }
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'in:aprobada,rechazada'],
            'observacion' => ['required_if:estado,rechazada', 'nullable', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'observacion.required_if' => 'Explica al estudiante por qué se rechaza su solicitud.',
            'observacion.min' => 'La observación debe tener al menos 10 caracteres.',
        ];
    }
}
