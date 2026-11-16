<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProgramarSustentacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('gestionarComoSecretaria', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+1 year'],
            'hora' => ['required', 'date_format:H:i', 'after_or_equal:07:00', 'before_or_equal:21:00'],
            'ambiente' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['fecha' => 'fecha', 'hora' => 'hora de inicio', 'ambiente' => 'aula'];
    }
}
