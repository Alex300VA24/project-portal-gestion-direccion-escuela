<?php

namespace App\Shared\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['data' => ['sometimes', 'array']];
    }
}
