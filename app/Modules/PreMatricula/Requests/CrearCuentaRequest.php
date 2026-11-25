<?php

namespace App\Modules\PreMatricula\Requests;

final class CrearCuentaRequest extends CoordinacionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'correo_institucional' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'regex:/\A[^\x00]{1,72}\z/'],
            'codigo_universitario' => ['required', 'string', 'max:30', 'regex:/\A[A-Za-z0-9._-]+\z/', 'different:correo_institucional'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:150'],
            'creditos_maximos' => ['nullable', 'integer', 'min:1', 'max:32767'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'Ingresa un correo electrónico válido.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max' => 'El campo :attribute es demasiado largo.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'password.regex' => 'La contraseña admite hasta 72 bytes y no puede contener bytes nulos.',
            'codigo_universitario.regex' => 'El código universitario solo admite letras, números, punto, guion y guion bajo.',
            'different' => 'El código universitario no puede ser igual al correo.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['correo_institucional' => 'correo institucional', 'password' => 'contraseña', 'codigo_universitario' => 'código universitario', 'creditos_maximos' => 'créditos máximos'];
    }
}
