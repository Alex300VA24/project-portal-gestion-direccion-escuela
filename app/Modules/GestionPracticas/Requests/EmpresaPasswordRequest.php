<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Requests\ModuleRequest;
use Illuminate\Validation\Rules\Password;

/** Cambio de contraseña de la cuenta de la empresa desde su perfil. */
final class EmpresaPasswordRequest extends ModuleRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->max(100)],
        ];
    }
}
