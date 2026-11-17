<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReporteDocenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BienestarPermissions::check($this->user(), BienestarPermissions::REPORTAR);
    }

    public function rules(): array
    {
        return [
            'codigo_estudiante' => ['required', 'string', Rule::exists(Estudiante::class, 'codigo_universitario')],
            'curso' => ['required', 'string', 'max:150'],
            'motivo_reporte' => ['required', 'string', 'max:255'],
            'descripcion_reporte' => ['required', 'string', 'min:10'],
            'prioridad' => ['required', 'in:alta,media,baja'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo_estudiante.exists' => 'El código universitario ingresado no corresponde a ningún estudiante registrado en el sistema académico.',
        ];
    }
}
