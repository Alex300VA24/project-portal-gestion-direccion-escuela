<?php

namespace App\Modules\SeguimientoEgresado\Requests;

/** Mensajes de validación en español para los formularios del módulo (la aplicación usa el idioma inglés por defecto). */
trait MensajesDeValidacion
{
    /** @return array<string, string> */
    protected static function mensajesBase(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'email' => 'Ingresa un :attribute con formato válido (ejemplo: nombre@correo.com).',
            'regex' => 'El formato de :attribute no es válido.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'in' => 'El valor de :attribute no es válido.',
            'exists' => 'El valor de :attribute no es válido.',
            'boolean' => 'El campo :attribute debe ser sí o no.',
            'confirmed' => 'La confirmación de :attribute no coincide.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'date_format' => 'El campo :attribute debe tener el formato año-mes-día.',
            'after' => 'El campo :attribute no puede ser una fecha anterior a 1950.',
            'before_or_equal' => 'El campo :attribute no puede ser una fecha futura.',
            'after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'distinct' => 'Hay opciones repetidas en :attribute.',
            'array' => 'El campo :attribute no tiene el formato esperado.',
        ];
    }
}
