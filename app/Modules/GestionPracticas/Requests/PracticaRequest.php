<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Requests\ModuleRequest;

/** Datos de la práctica que registra el estudiante (la carta de aceptación es obligatoria solo al registrar). */
final class PracticaRequest extends ModuleRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'area' => ['required', 'string', 'max:150'],
            'funciones' => ['required', 'string', 'max:5000'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'horas_totales' => ['required', 'integer', 'min:1', 'max:3000'],
            'id_periodo' => ['nullable', 'integer', function (string $atributo, mixed $valor, \Closure $fallar): void {
                if ($valor !== null && ! PeriodoAcademico::query()->whereKey($valor)->exists()) {
                    $fallar('El período académico no existe.');
                }
            }],
            'carta' => [$this->isMethod('POST') ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
