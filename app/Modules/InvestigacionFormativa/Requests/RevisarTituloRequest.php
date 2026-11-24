<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Modules\InvestigacionFormativa\Models\Grupo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RevisarTituloRequest extends FormRequest
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
            'estado' => ['required', 'string', Rule::in(['Aceptado', 'Cambio solicitado'])],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $motivo = $this->input('motivo');

        $this->merge([
            'motivo' => is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('estado') === 'Cambio solicitado' && $this->input('motivo') === null) {
                $validator->errors()->add(
                    'motivo',
                    'Indique el motivo del cambio solicitado.',
                );
            }
        });
    }
}
