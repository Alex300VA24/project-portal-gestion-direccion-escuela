<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use Illuminate\Validation\Rule;

/** Filtro por etapa de la bandeja del asesor. */
final class FiltrarBandejaAsesorRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['etapa' => ['nullable', Rule::in(['Proyecto', 'Tesis'])]];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), ['etapa.in' => 'Elige la etapa Proyecto o Tesis, o «Todas».']);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['etapa' => 'etapa'];
    }
}
