<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\AsignacionTutoriaService;

final class PlanGeneralTutoriaRequest extends SeguimientoFormRequest
{
    public function authorize(SeguimientoEstudianteService $seguimiento): bool
    {
        parent::authorize($seguimiento);

        $escuela = $this->route('escuela');
        $periodo = $this->route('periodo');
        abort_unless((string) (int) $escuela === $escuela && (string) (int) $periodo === $periodo, 404);
        abort_unless(app(AsignacionTutoriaService::class)->ambitos($this->persona())
            ->where('ambito.id_escuela', (int) $escuela)
            ->where('ambito.id_periodo', (int) $periodo)
            ->exists(), 404);

        return true;
    }

    public function rules(): array
    {
        if ($this->routeIs('seguimiento-estudiante.plan-general.retirar')) {
            return [
                'version' => 'required|integer|min:1',
                'reason' => 'required|string|min:10|max:1000',
            ];
        }

        return [
            'version' => 'required|integer|min:0',
            'request_id' => 'required|uuid',
            'title' => 'required|string|min:10|max:200',
            'approval_reference' => 'required|string|min:3|max:200',
            'approved_by' => 'required|string|min:5|max:200',
            'approved_on' => 'required|date_format:Y-m-d|before_or_equal:'.now('America/Lima')->toDateString(),
            'valid_from' => 'required|date_format:Y-m-d|after_or_equal:approved_on',
            'valid_until' => 'required|date_format:Y-m-d|after_or_equal:valid_from',
            'approval_confirmed' => 'required|accepted',
            'pdf' => 'required|file|mimetypes:application/pdf|max:5120',
        ];
    }
}
