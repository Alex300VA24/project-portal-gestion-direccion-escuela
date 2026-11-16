<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Verificación con la rúbrica: la usa el asesor (ruta asesor.*) y cada jurado (ruta jurado.*).
 * Los criterios se identifican por su número de orden, no por id.
 */
final class VerificarRubricaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');
        $habilidad = str_contains((string) $this->route()?->getName(), '.jurado.') ? 'verComoJurado' : 'verComoAsesor';

        return $proyecto !== null && $this->user()?->can($habilidad, $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'marcas' => ['required', 'array', 'max:40'],
            'marcas.*' => ['required', Rule::in(['cumple', 'no_cumple', 'no_aplica'])],
            'obs' => ['array', 'max:40'],
            'obs.*' => ['nullable', 'string', 'max:2000'],
            'accion' => ['required', Rule::in(['observaciones', 'conformidad', 'referencial'])],
            'comentario' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['marcas' => 'marcas de la rúbrica', 'marcas.*' => 'marca del criterio', 'obs.*' => 'observación del criterio'];
    }
}
