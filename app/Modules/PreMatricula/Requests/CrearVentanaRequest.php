<?php

namespace App\Modules\PreMatricula\Requests;

use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Validation\Rule;

final class CrearVentanaRequest extends CoordinacionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_periodo' => ['required', 'integer', Rule::exists(PeriodoAcademico::class, 'id_periodo')],
            'tipo' => ['required', 'in:'.CronogramaPrematricula::TIPO_ORDINARIA.','.CronogramaPrematricula::TIPO_EXTRAORDINARIA],
            'fecha_inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'fecha_fin' => ['required', 'date_format:Y-m-d\TH:i', 'after:fecha_inicio'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['id_periodo' => 'período', 'fecha_inicio' => 'inicio', 'fecha_fin' => 'cierre'];
    }
}
