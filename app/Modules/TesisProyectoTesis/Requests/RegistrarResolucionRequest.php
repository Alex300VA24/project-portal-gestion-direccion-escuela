<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;

final class RegistrarResolucionRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $solicitud = $this->route('solicitud');

        return $solicitud !== null && $this->user()?->can('gestionarComoSecretaria', $solicitud->proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'numero' => ['required', 'string', 'max:80', 'regex:/^[\pL\pN .\/°ºN-]+$/u'],
            'fecha' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'organo_emisor' => ['required', 'string', 'max:150'],
            'dirigido_a' => ['required', 'string', 'max:150'],
            'docente' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'asunto' => ['required', 'string', 'max:500'],
            'detalle' => ['nullable', 'string', 'max:3000'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.Parametros::ARCHIVO_MAX_KB],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'fecha.before_or_equal' => 'La fecha de emisión no puede ser posterior a hoy.',
            'docente.required' => 'Elige al docente designado como asesor.',
            'docente.regex' => 'El docente elegido no es válido. Elige uno de la lista.',
            'numero.regex' => 'El número de resolución solo admite letras, números, espacios y los signos . / - °.',
            'archivo.required' => 'Adjunta la resolución escaneada en PDF.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'numero' => 'número de resolución',
            'fecha' => 'fecha de emisión',
            'organo_emisor' => 'órgano emisor',
            'dirigido_a' => 'dirigida a',
            'docente' => 'docente designado como asesor',
            'asunto' => 'asunto',
            'detalle' => 'detalle',
            'archivo' => 'resolución escaneada (PDF)',
        ];
    }
}
