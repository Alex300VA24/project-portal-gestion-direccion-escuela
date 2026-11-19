<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de los FormRequest del módulo: mensajes de validación en español para todas las reglas que usa
 * el módulo, sin depender de lang/ global. Cada FormRequest define messages() (mensajesBase() + los suyos:
 * campos ocultos con indicación de qué hacer, reglas de negocio) y attributes() (nombres de campo en español).
 */
abstract class TesisFormRequest extends FormRequest
{
    /** @return array<string, string|array<string, string>> */
    protected function mensajesBase(): array
    {
        $mb = (int) round(Parametros::ARCHIVO_MAX_KB / 1024);

        return [
            'required' => 'Completa el campo «:attribute».',
            'required_with' => 'Completa el campo «:attribute».',
            'string' => 'El campo «:attribute» debe ser un texto.',
            'array' => 'El campo «:attribute» no tiene el formato esperado. Recarga la página e inténtalo de nuevo.',
            'integer' => 'El campo «:attribute» debe ser un número entero.',
            'numeric' => 'El campo «:attribute» debe ser un número.',
            'decimal' => 'El campo «:attribute» admite como máximo :decimal decimales.',
            'boolean' => 'El valor de «:attribute» no es válido. Recarga la página e inténtalo de nuevo.',
            'date' => 'El campo «:attribute» debe ser una fecha válida.',
            'date_format' => 'El campo «:attribute» no tiene el formato correcto (:format).',
            'after' => 'El campo «:attribute» debe ser una fecha posterior a :date.',
            'after_or_equal' => 'El campo «:attribute» debe ser igual o posterior a :date.',
            'before' => 'El campo «:attribute» debe ser una fecha anterior a :date.',
            'before_or_equal' => 'El campo «:attribute» debe ser igual o anterior a :date.',
            'in' => 'El valor elegido en «:attribute» no es válido. Elige una de las opciones de la lista.',
            'different' => '«:attribute» y «:other» deben ser personas distintas.',
            'regex' => 'El campo «:attribute» tiene caracteres no permitidos.',
            'uuid' => 'La referencia de «:attribute» no es válida. Recarga la página e inténtalo de nuevo.',
            'gt' => ['numeric' => 'El campo «:attribute» debe ser mayor que :value.'],
            'min' => [
                'string' => 'El campo «:attribute» debe tener al menos :min caracteres.',
                'numeric' => 'El campo «:attribute» debe ser al menos :min.',
                'array' => 'Agrega al menos :min elemento(s) en «:attribute».',
                'file' => 'El archivo «:attribute» debe pesar al menos :min KB.',
            ],
            'max' => [
                'string' => 'El campo «:attribute» admite como máximo :max caracteres.',
                'numeric' => 'El campo «:attribute» no puede ser mayor que :max.',
                'array' => '«:attribute» admite como máximo :max elementos.',
                'file' => "El archivo «:attribute» no debe superar los {$mb} MB.",
            ],
            'between' => [
                'numeric' => 'El campo «:attribute» debe estar entre :min y :max.',
                'string' => 'El campo «:attribute» debe tener entre :min y :max caracteres.',
                'array' => '«:attribute» debe tener entre :min y :max elementos.',
                'file' => 'El archivo «:attribute» debe pesar entre :min y :max KB.',
            ],
            'file' => 'Adjunta un archivo en «:attribute».',
            'uploaded' => "No se pudo subir «:attribute». Verifica que sea un PDF de hasta {$mb} MB e inténtalo de nuevo.",
            'mimes' => 'El archivo «:attribute» debe ser un PDF.',
            'mimetypes' => 'El archivo «:attribute» debe ser un PDF.',
            'exists' => 'El valor de «:attribute» no existe. Elige una de las opciones de la lista.',
            'distinct' => 'Hay valores repetidos en «:attribute».',
        ];
    }
}
