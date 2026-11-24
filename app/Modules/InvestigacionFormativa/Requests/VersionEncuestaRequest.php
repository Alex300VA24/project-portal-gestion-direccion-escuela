<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Shared\Models\Core\Seccion;
use Illuminate\Foundation\Http\FormRequest;

final class VersionEncuestaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $seccion instanceof Seccion
            && $this->user()?->can('prepararEncuesta', $seccion) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
