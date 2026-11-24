<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Shared\Requests\ModuleRequest;

final class EmpresaPerfilRequest extends ModuleRequest
{
    use MensajesEnEspanol;

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

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'correo.email' => 'Escriba un correo de contacto válido, por ejemplo contacto@empresa.com.',
            'pagina_web.url' => 'La página web debe comenzar con http:// o https://.',
        ];
    }
}
