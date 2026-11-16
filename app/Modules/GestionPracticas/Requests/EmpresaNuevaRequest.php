<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Requests\ModuleRequest;

final class EmpresaNuevaRequest extends ModuleRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'ruc' => ['required', 'digits:11'],
            'razon_social' => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'pagina_web' => ['nullable', 'url:http,https', 'max:250'],
            'contacto_dni' => ['required', 'digits:8'],
            'contacto_nombres' => ['required', 'string', 'max:120'],
            'contacto_apellido_paterno' => ['required', 'string', 'max:80'],
            'correo_acceso' => ['required', 'email', 'max:180'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
