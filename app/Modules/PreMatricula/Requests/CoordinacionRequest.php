<?php

namespace App\Modules\PreMatricula\Requests;

use App\Modules\PreMatricula\Policies\PrematriculaPolicy;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Acciones y consultas de Dirección y Secretaría. */
class CoordinacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PrematriculaPolicy::class)->coordinar($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periodo' => ['nullable', 'integer', Rule::exists(PeriodoAcademico::class, 'id_periodo')],
            'ciclo' => ['nullable', 'integer', 'min:1', 'max:14'],
            'estado' => ['nullable', 'in:ENVIADA,BORRADOR,CORRECCION'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ];
    }
}
