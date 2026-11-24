<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Shared\Models\Core\Seccion;
use Illuminate\Foundation\Http\FormRequest;

final class CrearGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $seccion instanceof Seccion
            && $this->user()?->can('crearGrupo', $seccion) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:1', 'max:120'],
            'titulo' => ['required', 'string', 'min:1', 'max:250'],
            'integrante_ids' => ['required', 'array', 'min:1', 'max:20'],
            'integrante_ids.*' => ['integer', 'distinct'],
            'id_linea' => ['required', 'integer', 'min:1'],
            'id_tipo_producto' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->input('nombre')) ? trim($this->input('nombre')) : $this->input('nombre'),
            'titulo' => is_string($this->input('titulo')) ? trim($this->input('titulo')) : $this->input('titulo'),
        ]);
    }
}
