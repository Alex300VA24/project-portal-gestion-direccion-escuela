<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Services\AvanceService;
use Illuminate\Foundation\Http\FormRequest;

final class ResponderObservacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $observacion = $this->route('observacion');
        $proyecto = $observacion ? app(AvanceService::class)->proyectoDeObservacion($observacion) : null;

        return $proyecto !== null && $this->user()?->can('verComoAlumno', $proyecto) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'respuesta' => ['required', 'string', 'min:5', 'max:3000'],
            'entrega' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['respuesta' => 'respuesta', 'entrega' => 'entrega donde se corrigió'];
    }
}
