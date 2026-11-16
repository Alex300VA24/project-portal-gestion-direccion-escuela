<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReemplazarJuradoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proyecto = $this->route('proyecto');

        return $proyecto !== null && $this->user()?->can('gestionarComoDirector', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cargo' => ['required', Rule::in(Parametros::CARGOS_JURADO)],
            'docente' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
