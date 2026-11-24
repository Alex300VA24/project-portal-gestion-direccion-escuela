<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Shared\Requests\ModuleRequest;

final class EmpresaNuevaRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            // Solo RUC de empresa: 11 dígitos y comienzan con 20 (los que comienzan con 10 son de personas naturales).
            'ruc' => ['bail', 'required', 'digits:11', 'starts_with:20'],
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

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'ruc.required' => 'Escriba el RUC de la empresa.',
            'ruc.digits' => 'El RUC debe tener exactamente 11 dígitos numéricos, sin espacios ni guiones.',
            'ruc.starts_with' => 'Solo se aceptan RUC de empresa: deben comenzar con 20 (los que comienzan con 10 son de personas naturales).',
            'contacto_dni.digits' => 'El DNI del representante debe tener exactamente 8 dígitos numéricos.',
            'password.min' => 'La contraseña inicial debe tener al menos :min caracteres.',
            'correo_acceso.email' => 'Escriba un correo de acceso válido, por ejemplo representante@empresa.com.',
            'pagina_web.url' => 'La página web debe comenzar con http:// o https://.',
        ];
    }
}
