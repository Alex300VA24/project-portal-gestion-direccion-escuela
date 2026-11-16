<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AsignarJuradoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('gestionarComoDirector', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $codigo = ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'];

        return [
            'Presidente' => [...$codigo, 'different:Secretario', 'different:Vocal'],
            'Secretario' => [...$codigo, 'different:Vocal'],
            'Vocal' => $codigo,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['different' => 'Los tres cargos deben ser docentes distintos.'];
    }
}
