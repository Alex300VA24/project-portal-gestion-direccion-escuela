<?php

namespace App\Modules\GestionHorarios\Requests;

use App\Shared\Requests\ModuleRequest;

final class HorarioRequest extends ModuleRequest
{
    public function authorize(): bool
    {
        return false;
    }
}
