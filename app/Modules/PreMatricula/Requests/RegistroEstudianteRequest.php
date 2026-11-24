<?php

namespace App\Modules\PreMatricula\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Registro público del estudiante, como RegisterStudentRequest de master. */
final class RegistroEstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'codigo_universitario' => ['required', 'string', 'digits:10'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:150'],
            'correo_institucional' => ['required', 'string', 'email', 'max:255', 'regex:/^[A-Za-z0-9._%+-]+@unitru\.edu\.pe$/i'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'codigo_universitario.required' => 'El código universitario es obligatorio.',
            'codigo_universitario.digits' => 'El código universitario debe tener exactamente 10 dígitos.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'correo_institucional.required' => 'El correo institucional es obligatorio.',
            'correo_institucional.email' => 'Ingresa un correo electrónico válido.',
            'correo_institucional.regex' => 'Debes utilizar un correo con dominio @unitru.edu.pe.',
        ];
    }
}
