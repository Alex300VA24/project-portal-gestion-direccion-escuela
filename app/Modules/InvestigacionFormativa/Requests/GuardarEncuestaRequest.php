<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Shared\Models\Core\Seccion;
use Illuminate\Foundation\Http\FormRequest;

final class GuardarEncuestaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $seccion instanceof Seccion
            && $this->user()?->can('gestionarEncuesta', $seccion) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'preguntas' => ['required', 'array', 'min:1', 'max:50'],
            'preguntas.*' => ['required', 'string', 'max:1000'],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $datos = $this->all();

        if (isset($datos['preguntas']) && is_array($datos['preguntas'])) {
            $datos['preguntas'] = array_map(
                static fn (mixed $pregunta): mixed => is_string($pregunta) ? trim($pregunta) : $pregunta,
                $datos['preguntas'],
            );
        }

        $this->replace($datos);
    }
}
