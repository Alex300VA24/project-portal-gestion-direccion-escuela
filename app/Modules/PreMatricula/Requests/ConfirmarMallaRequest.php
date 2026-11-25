<?php

namespace App\Modules\PreMatricula\Requests;

final class ConfirmarMallaRequest extends CoordinacionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sha256' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'huella' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }
}
