<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

final class ResolverCitaTutoriaRequest extends SeguimientoFormRequest
{
    public function rules(): array
    {
        $accion = $this->route('accion', 'programar');
        $reglas = match ($accion) {
            'programar', 'reprogramar' => [
                'fecha_hora' => ['required', 'date_format:Y-m-d\TH:i'],
                'duracion_minutos' => ['required', 'integer', 'between:10,120'],
                'modalidad' => ['required', 'in:PRESENCIAL,VIRTUAL'],
                'lugar' => ['required', 'string', 'max:250'],
                'nota' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            'cumplir' => [],
            'cancelar', 'mantener' => ['nota' => ['required', 'string', 'min:5', 'max:1000']],
            'registrar', 'corregir' => [
                'asistencia' => ['required', 'in:ASISTIO,NO_ASISTIO,NO_REALIZADA'],
                'actividad' => ['required_if:asistencia,ASISTIO', 'nullable', 'string', 'min:5', 'max:2000'],
                'acuerdos' => ['required_if:asistencia,ASISTIO', 'nullable', 'string', 'max:2000'],
                'observaciones' => ['nullable', 'string', 'max:2000'],
            ],
        };
        if ($accion === 'corregir') {
            $reglas['motivo_correccion'] = ['required', 'string', 'min:10', 'max:1000'];
        }
        $reglas['version'] = [$accion === 'programar' ? 'sometimes' : 'required', 'integer', 'min:1'];

        return $reglas;
    }
}
