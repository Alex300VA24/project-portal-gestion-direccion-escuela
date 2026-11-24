<?php

namespace App\Modules\SeguimientoEstudiante\Requests;

use App\Modules\SeguimientoEstudiante\Services\Tutoria\PlanTutoriaService;

final class PlanTutoriaRequest extends SeguimientoFormRequest
{
    protected function prepareForValidation(): void
    {
        $actividades = $this->input('activities', []);
        if (is_array($actividades)) {
            foreach ($actividades as &$actividad) {
                if (is_array($actividad) && ($actividad['target'] ?? null) === '') {
                    $actividad['target'] = null;
                }
            }
        }
        $this->merge(['activities' => $actividades]);
    }

    public function rules(): array
    {
        $presentar = $this->input('action') === 'PRESENTAR';
        $reglas = [
            'version' => 'required|integer|min:0',
            'request_id' => 'required|uuid',
            'action' => 'required|in:BORRADOR,PRESENTAR',
            'fields' => 'required|array:foundation,needs,objectives,evaluation,improvement',
            'activities' => ($presentar ? 'required|min:1|' : 'present|').'array|list|max:30',
            'activities.*' => 'required|array:id,title,timing,mode,target,unit,evidence',
            'activities.*.id' => 'required|uuid|distinct',
            'activities.*.title' => ($presentar ? 'required|min:5|' : 'nullable|').'string|max:200',
            'activities.*.timing' => ($presentar ? 'required|min:3|' : 'nullable|').'string|max:100',
            'activities.*.mode' => 'required|in:INDIVIDUAL,GRUPAL,GESTION',
            'activities.*.target' => ($presentar ? 'required|' : 'nullable|').'integer|min:1|max:10000',
            'activities.*.unit' => 'required|in:SESIONES,ESTUDIANTES,ACTIVIDADES',
            'activities.*.evidence' => 'nullable|string|max:500',
        ];
        foreach (array_keys(PlanTutoriaService::FIELDS) as $campo) {
            $reglas['fields.'.$campo] = ($presentar ? 'required|min:10|' : 'nullable|').'string|max:4000';
        }

        return $reglas;
    }

    public function attributes(): array
    {
        $atributos = [
            'activities' => 'cronograma',
            'activities.*.title' => 'nombre de la actividad',
            'activities.*.timing' => 'mes o semana',
            'activities.*.target' => 'meta de la actividad',
            'activities.*.evidence' => 'evidencia esperada',
        ];
        foreach (PlanTutoriaService::FIELDS as $campo => $etiqueta) {
            $atributos['fields.'.$campo] = mb_strtolower($etiqueta);
        }

        return $atributos;
    }

    public function messages(): array
    {
        return [
            'required' => 'Completa :attribute para presentar el plan.',
            'min.string' => ':attribute debe tener al menos :min caracteres.',
            'max.string' => ':attribute admite hasta :max caracteres.',
            'activities.min' => 'Añade al menos una actividad al cronograma.',
        ];
    }

    public function plan(): array
    {
        $datos = $this->validated();
        $fields = [];
        foreach (array_keys(PlanTutoriaService::FIELDS) as $campo) {
            $fields[$campo] = trim($datos['fields'][$campo] ?? '');
        }
        $activities = array_map(fn (array $fila) => [
            'id' => $fila['id'],
            'title' => trim($fila['title'] ?? ''),
            'timing' => trim($fila['timing'] ?? ''),
            'mode' => $fila['mode'],
            'target' => isset($fila['target']) ? (int) $fila['target'] : null,
            'unit' => $fila['unit'],
            'evidence' => trim($fila['evidence'] ?? ''),
        ], $datos['activities']);

        return compact('fields', 'activities') + [
            'version' => (int) $datos['version'],
            'request_id' => $datos['request_id'],
            'action' => $datos['action'],
        ];
    }
}
