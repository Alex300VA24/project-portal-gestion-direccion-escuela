<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Services\RevisionService;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Validation\Rule;

final class RevisarAvanceRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('verComoAsesor', $proyecto) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observaciones' => array_values(array_filter(
                (array) $this->input('observaciones', []),
                fn ($o) => is_array($o) && trim((string) ($o['descripcion'] ?? '')) !== '',
            )),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dictamen' => ['required', Rule::in(array_keys(RevisionService::DICTAMENES_AVANCE))],
            'comentario' => ['nullable', 'string', 'max:3000'],
            'observaciones' => ['array', 'max:30'],
            'observaciones.*.seccion' => ['required', Rule::in(Parametros::SECCIONES_DOCUMENTO)],
            'observaciones.*.descripcion' => ['required', 'string', 'min:5', 'max:2000'],
            'levantar' => ['array', 'max:50'],
            'levantar.*' => ['uuid'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'dictamen.required' => 'Elige el resultado de la revisión: Con observaciones, Sin observaciones o Proyecto culminado.',
            'dictamen.in' => 'Elige el resultado de la revisión: Con observaciones, Sin observaciones o Proyecto culminado.',
            'observaciones.*.descripcion.min' => 'Cada observación debe tener al menos :min caracteres.',
            'levantar.*.uuid' => 'Una de las observaciones marcadas para levantar no es válida. Recarga la página e inténtalo de nuevo.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'dictamen' => 'resultado de la revisión',
            'comentario' => 'comentario general',
            'observaciones' => 'observaciones',
            'observaciones.*.descripcion' => 'observación',
            'observaciones.*.seccion' => 'sección del documento',
            'levantar' => 'observaciones por levantar',
        ];
    }
}
