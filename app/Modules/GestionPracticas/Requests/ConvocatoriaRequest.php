<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Modules\GestionPracticas\Support\Catalogos;
use App\Shared\Requests\ModuleRequest;
use Illuminate\Validation\Rule;

final class ConvocatoriaRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'requisitos' => ['required', 'string', 'max:5000'],
            'enlace_postulacion' => ['required', 'url:http,https', 'max:500'],
            'tipo_practica' => ['required', Rule::in(array_keys(Catalogos::TIPOS_PRACTICA))],
            'modalidad' => ['required', Rule::in(array_keys(Catalogos::MODALIDADES))],
            'vacantes' => ['required', 'integer', 'min:1', 'max:500'],
            'fecha_cierre' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'enlace_postulacion.url' => 'El enlace de postulación debe comenzar con http:// o https://.',
            'tipo_practica.in' => 'Seleccione el tipo de práctica de la lista.',
            'modalidad.in' => 'Seleccione la modalidad de la lista.',
            'vacantes.integer' => 'Las vacantes deben ser un número entero.',
            'vacantes.min' => 'La convocatoria debe tener al menos :min vacante.',
            'fecha_cierre.after_or_equal' => 'La fecha de cierre no puede ser anterior a hoy.',
        ];
    }
}
