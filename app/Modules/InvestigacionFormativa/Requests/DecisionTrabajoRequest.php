<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Modules\InvestigacionFormativa\Models\Grupo;
use Illuminate\Foundation\Http\FormRequest;

final class DecisionTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grupo = $this->route('grupo');

        return $grupo instanceof Grupo
            && $this->user()?->can('revisarTitulo', $grupo) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $motivo = $this->input('motivo');

        $this->merge([
            'motivo' => is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null,
        ]);
    }
}
