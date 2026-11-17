<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Especialista;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDerivacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BienestarPermissions::check($this->user(), BienestarPermissions::GESTIONAR);
    }

    public function rules(): array
    {
        return [
            'id_caso' => ['required', Rule::exists(CasoBienestar::class, 'id_caso')],
            'id_especialista_destino' => ['nullable', Rule::exists(Especialista::class, 'id_especialista')],
            'entidad_externa' => ['nullable', 'string', 'max:180'],
            'motivo' => ['required', 'string', 'min:5'],
            'consentimiento_estudiante' => ['required', 'boolean', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consentimiento_estudiante.accepted' => 'Es obligatorio contar con el consentimiento expreso del estudiante para proceder con la derivación externa.',
        ];
    }
}
