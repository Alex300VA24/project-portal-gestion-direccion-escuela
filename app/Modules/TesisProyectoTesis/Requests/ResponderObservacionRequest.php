<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;

use App\Modules\TesisProyectoTesis\Services\AvanceService;

final class ResponderObservacionRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $observacion = $this->route('observacion');
        $proyecto = $observacion ? app(AvanceService::class)->proyectoDeObservacion($observacion) : null;

        return $proyecto !== null && $this->user()?->can('verComoAlumno', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'respuesta' => ['required', 'string', 'min:5', 'max:3000'],
            'entrega' => ['nullable', 'uuid'],
            'archivo' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.Parametros::ARCHIVO_MAX_KB],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'respuesta.required' => 'Escribe cómo corregiste la observación.',
            'respuesta.min' => 'Explica la corrección en al menos :min caracteres.',
            'entrega.uuid' => 'La versión elegida en «Corregida en» no es válida. Elige una de la lista o déjala vacía.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'respuesta' => 'respuesta',
            'entrega' => 'corregida en',
            'archivo' => 'documento corregido (PDF)',
        ];
    }
}
