<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Foundation\Http\FormRequest;

final class CasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BienestarPermissions::check($this->user(), BienestarPermissions::GESTIONAR);
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'bienestar.panel.casos.citas' => [
                'inicio_cita' => ['required', 'date', 'after:'.now('America/Lima')->format('Y-m-d H:i')],
                'modalidad' => ['required', 'in:Presencial,Virtual'],
            ],
            'bienestar.panel.citas.registrar' => [
                'estado' => ['required', 'in:Realizada,Cancelada,No_Asistio'],
                'notas' => ['required_if:estado,Realizada', 'nullable', 'string', 'min:5', 'max:2000'],
            ],
            default => ['observacion' => ['required', 'string', 'min:10', 'max:1000']],
        };
    }

    public function messages(): array
    {
        return [
            'inicio_cita.after' => 'La cita debe programarse en una fecha y hora futuras.',
            'notas.required_if' => 'Escribe las notas de la atención realizada.',
            'observacion.min' => 'Describe el cierre con al menos 10 caracteres.',
        ];
    }
}
