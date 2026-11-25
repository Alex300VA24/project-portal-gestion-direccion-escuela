<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

final class CatalogoCausasTutoriaRequest extends SeguimientoFormRequest
{
    protected function prepareForValidation(): void
    {
        $filas = $this->input('causas');
        if (is_array($filas) && array_is_list($filas) && count($filas) <= 30) {
            $this->merge(['causas' => array_values(array_filter($filas, fn ($fila) => ! is_array($fila)
                || ($fila['codigo'] ?? null) !== null || ($fila['nombre'] ?? null) !== null))]);
        }
    }

    public function rules(): array
    {
        return [
            'revision' => 'required|integer|min:0',
            'estado' => 'required|in:BORRADOR,VIGENTE',
            'referencia' => 'required|string|min:5|max:250',
            'motivo' => 'required|string|min:10|max:1000',
            'autoridad' => 'required_if:estado,VIGENTE|nullable|string|min:3|max:150',
            'fecha_aprobacion' => 'required_if:estado,VIGENTE|nullable|date_format:Y-m-d|before_or_equal:'.now('America/Lima')->toDateString(),
            'aprobado' => 'accepted_if:estado,VIGENTE',
            'causas' => 'required|array|list|min:1|max:30',
            'causas.*' => 'required|array:codigo,nombre,efecto,habilitada',
            'causas.*.codigo' => 'required|string|regex:/^[A-Z][A-Z0-9_]{1,39}$/D|distinct',
            'causas.*.nombre' => 'required|string|min:5|max:150',
            'causas.*.efecto' => 'required|in:FINALIZAR,REASIGNAR',
            'causas.*.habilitada' => 'required|boolean',
        ];
    }
}
