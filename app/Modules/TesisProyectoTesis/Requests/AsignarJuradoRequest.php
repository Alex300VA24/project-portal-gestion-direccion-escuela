<?php

namespace App\Modules\TesisProyectoTesis\Requests;

final class AsignarJuradoRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('gestionarComoDirector', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $codigo = ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'];

        return [
            'Presidente' => [...$codigo, 'different:Secretario', 'different:Vocal'],
            'Secretario' => [...$codigo, 'different:Vocal'],
            'Vocal' => $codigo,
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'Presidente.required' => 'Elige al presidente del jurado.',
            'Secretario.required' => 'Elige al secretario del jurado.',
            'Vocal.required' => 'Elige al vocal del jurado.',
            'different' => 'Los tres cargos deben ser docentes distintos: «:attribute» y «:other» no pueden ser la misma persona.',
            'regex' => 'El docente elegido en «:attribute» no es válido. Elige uno de la lista.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'Presidente' => 'presidente del jurado',
            'Secretario' => 'secretario del jurado',
            'Vocal' => 'vocal del jurado',
        ];
    }
}
