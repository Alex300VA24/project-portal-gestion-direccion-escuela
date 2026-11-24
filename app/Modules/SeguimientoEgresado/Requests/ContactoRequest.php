<?php

namespace App\Modules\SeguimientoEgresado\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Contacto del egresado: columnas de contacto_egresado (correo y celular obligatorios; teléfono fijo y dirección opcionales). */
class ContactoRequest extends FormRequest
{
    use MensajesDeValidacion;

    public function rules(): array
    {
        return self::reglas();
    }

    public static function reglas(): array
    {
        return [
            'correo_personal' => ['required', ...Formatos::correo()],
            'celular_whatsapp' => ['required', 'string', 'max:20', Formatos::celular()],
            'telefono_fijo' => ['nullable', 'string', 'max:20', Formatos::telefonoFijo()],
            'direccion' => ['nullable', 'string', 'min:5', 'max:250', Formatos::texto()],
        ];
    }

    public function messages(): array
    {
        return self::mensajesBase();
    }

    public function attributes(): array
    {
        return [
            'correo_personal' => 'correo personal',
            'celular_whatsapp' => 'celular o WhatsApp',
            'telefono_fijo' => 'teléfono fijo',
            'direccion' => 'dirección',
        ];
    }
}
