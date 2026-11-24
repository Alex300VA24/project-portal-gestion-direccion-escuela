<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ActualizarTituloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('investigacion-formativa.projects.update') === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'min:1', 'max:250'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('titulo'))) {
            $this->merge(['titulo' => trim($this->input('titulo'))]);
        }
    }
}
