<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Búsqueda del coautor por código (JSON). La ruta ya exige sesión y rol alumno; un error de validación
 * responde con {encontrado: false, mensaje} para que el formulario muestre el texto en español.
 */
final class BuscarCoautorRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/']];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'codigo.required' => 'Escribe el código universitario del coautor.',
            'codigo.regex' => 'El código solo admite números, letras y guiones.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['codigo' => 'código universitario'];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['encontrado' => false, 'mensaje' => $validator->errors()->first()], 422));
    }
}
