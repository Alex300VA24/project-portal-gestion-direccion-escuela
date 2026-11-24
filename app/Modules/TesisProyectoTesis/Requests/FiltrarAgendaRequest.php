<?php

namespace App\Modules\TesisProyectoTesis\Requests;

/** Consulta de la agenda del aula al programar una sustentación. */
final class FiltrarAgendaRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ambiente' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'ambiente.regex' => 'El aula elegida no es válida. Elige una de la lista.',
            'fecha.date_format' => 'Elige la semana con el calendario (día/mes/año).',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['ambiente' => 'aula', 'fecha' => 'semana'];
    }
}
