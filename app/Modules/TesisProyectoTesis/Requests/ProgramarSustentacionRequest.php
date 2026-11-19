<?php

namespace App\Modules\TesisProyectoTesis\Requests;

final class ProgramarSustentacionRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('gestionarComoSecretaria', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+1 year'],
            'hora' => ['required', 'date_format:H:i', 'after_or_equal:07:00', 'before_or_equal:21:00'],
            'ambiente' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'fecha.after_or_equal' => 'La fecha de la sustentación no puede ser anterior a hoy.',
            'fecha.before' => 'Programa la sustentación dentro del próximo año.',
            'fecha.date_format' => 'Elige la fecha con el calendario (día/mes/año).',
            'hora.date_format' => 'Escribe la hora de inicio en formato de 24 horas (por ejemplo, 15:00).',
            'hora.after_or_equal' => 'La hora de inicio debe ser desde las 07:00.',
            'hora.before_or_equal' => 'La hora de inicio debe ser hasta las 21:00.',
            'ambiente.required' => 'Elige el aula de la sustentación.',
            'ambiente.regex' => 'El aula elegida no es válida. Elige una de la lista.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha',
            'hora' => 'hora de inicio',
            'ambiente' => 'aula',
        ];
    }
}
