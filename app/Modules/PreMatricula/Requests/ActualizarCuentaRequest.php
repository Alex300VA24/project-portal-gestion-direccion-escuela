<?php

namespace App\Modules\PreMatricula\Requests;

use App\Modules\PreMatricula\Services\CuentasEstudiantes;
use Illuminate\Validation\Rule;

final class ActualizarCuentaRequest extends CoordinacionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'correo_institucional' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:1024', 'regex:/\A[^\x00]{1,72}\z/'],
            'estado' => ['sometimes', 'required', Rule::in([CuentasEstudiantes::ACTIVO, CuentasEstudiantes::INACTIVO])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'Ingresa un correo electrónico válido.',
            'min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.regex' => 'La contraseña admite hasta 72 bytes y no puede contener bytes nulos.',
            'in' => 'Elige un estado válido.',
        ];
    }

    /** @return array<string, mixed> sin la contraseña vacía (no se cambia) */
    public function datos(): array
    {
        return array_filter($this->validated(), fn ($valor): bool => $valor !== null && $valor !== '');
    }
}
