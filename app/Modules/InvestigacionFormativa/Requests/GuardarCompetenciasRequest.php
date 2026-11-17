<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Shared\Models\Core\Seccion;
use Illuminate\Foundation\Http\FormRequest;

final class GuardarCompetenciasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $seccion instanceof Seccion
            && $this->user()?->can('configurar', $seccion) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'competencia_ids' => ['required', 'array', 'min:1', 'max:100'],
            'competencia_ids.*' => ['integer', 'distinct'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
