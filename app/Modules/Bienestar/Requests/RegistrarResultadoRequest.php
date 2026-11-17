<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Foundation\Http\FormRequest;

final class RegistrarResultadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BienestarPermissions::check($this->user(), BienestarPermissions::GESTIONAR);
    }

    public function rules(): array
    {
        return [
            'resultado_especialista' => ['required', 'string', 'min:5'],
            'estado_derivacion' => ['required', 'in:Pendiente,Atendida,Cancelada'],
        ];
    }
}
