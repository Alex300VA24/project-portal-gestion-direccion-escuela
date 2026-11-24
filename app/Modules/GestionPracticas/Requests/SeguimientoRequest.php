<?php

namespace App\Modules\GestionPracticas\Requests;

use App\Modules\GestionPracticas\Requests\Concerns\MensajesEnEspanol;
use App\Modules\GestionPracticas\Support\Catalogos;
use App\Shared\Requests\ModuleRequest;
use Illuminate\Validation\Rule;

final class SeguimientoRequest extends ModuleRequest
{
    use MensajesEnEspanol;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'tipo' => ['required', Rule::in(array_keys(Catalogos::TIPOS_SEGUIMIENTO))],
            'descripcion' => ['required', 'string', 'max:3000'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesPropios(): array
    {
        return [
            'fecha.before_or_equal' => 'La fecha del seguimiento no puede ser posterior a hoy.',
            'tipo.in' => 'Seleccione el tipo de seguimiento de la lista.',
            'descripcion.required' => 'Describa lo realizado en este seguimiento.',
        ];
    }
}
