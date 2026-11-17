<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Foundation\Http\FormRequest;

final class EvaluarSolicitudApoyoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BienestarPermissions::check($this->user(), BienestarPermissions::GESTIONAR);
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'in:Aprobada,Rechazada'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
