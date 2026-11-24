<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Shared\Models\Core\Seccion;
use Illuminate\Foundation\Http\FormRequest;

final class HabilitarSeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $seccion instanceof Seccion
            && $this->user()?->can('habilitar', $seccion) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'habilitada' => ['required', 'boolean'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
