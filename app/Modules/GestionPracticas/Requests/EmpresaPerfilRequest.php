<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Requests\ModuleRequest;

final class EmpresaPerfilRequest extends ModuleRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'direccion' => ['required', 'string', 'max:500'],
            'telefono' => ['required', 'string', 'max:30'],
            'correo' => ['required', 'email', 'max:180'],
            'pagina_web' => ['nullable', 'url:http,https', 'max:250'],
        ];
    }
}
