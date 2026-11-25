<?php

namespace App\Modules\PreMatricula\Requests;

use App\Modules\PreMatricula\Services\PlanEstudios;
use Illuminate\Validation\Rule;

final class ActualizarCursoRequest extends CoordinacionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:150'],
            'creditos' => ['sometimes', 'integer', 'between:1,32767'],
            'ciclo' => ['sometimes', 'integer', 'between:1,32767'],
            'horas_teoria' => ['sometimes', 'integer', 'between:0,999'],
            'horas_practica' => ['sometimes', 'integer', 'between:0,999'],
            'horas_laboratorio' => ['sometimes', 'integer', 'between:0,999'],
            'prerrequisitos' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'estado' => ['sometimes', Rule::in([PlanEstudios::ACTIVO, PlanEstudios::INACTIVO])],
            'confirmar_correccion' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'integer' => 'El campo :attribute debe ser un número entero.',
            'between' => 'El campo :attribute debe estar entre :min y :max.',
            'max' => 'El campo :attribute es demasiado largo.',
            'in' => 'Elige un estado válido.',
        ];
    }

    /** @return array<string, mixed> datos listos para el servicio */
    public function datos(): array
    {
        $datos = $this->validated();
        if (array_key_exists('prerrequisitos', $datos)) {
            $texto = trim((string) $datos['prerrequisitos']);
            $datos['prerrequisitos'] = $texto === '' ? [] : array_map(trim(...), explode(';', $texto));
        }

        return $datos;
    }
}
