<?php

namespace App\Modules\Bienestar\Requests;

use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Modules\Bienestar\Support\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;

final class StoreArticuloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(BienestarPermissions::GESTIONAR) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:200'],
            'resumen' => ['required', 'string', 'max:500'],
            'detalle' => ['required', 'string'],
            'fuente_normativa' => ['required', 'string', 'max:255'],
            'fecha_ultima_revision' => ['required', 'date'],
            'estado' => ['required', 'in:borrador,publicado'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('detalle')) {
            $this->merge([
                'detalle' => HtmlSanitizer::clean((string) $this->input('detalle')),
            ]);
        }
    }
}
