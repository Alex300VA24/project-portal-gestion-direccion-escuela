<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Shared\Models\Core\Seccion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class ResponderEncuestaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $seccion instanceof Seccion
            && $this->user()?->can('responderEncuesta', $seccion) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'respuestas' => ['required', 'array', 'min:1', 'max:50'],
            'respuestas.*' => ['required', 'array'],
            'respuestas.*.id_pregunta' => ['required', 'integer', 'min:1'],
            'respuestas.*.valor' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $datos = $this->all();
        $comentario = $datos['comentario'] ?? null;

        if (is_string($comentario)) {
            $comentario = trim($comentario);
            $datos['comentario'] = $comentario === '' ? null : $comentario;
        }

        $this->replace($datos);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $respuestas = $this->input('respuestas');
            $respuestas = is_array($respuestas) ? $respuestas : [];
            $ids = [];

            foreach ($respuestas as $indice => $respuesta) {
                $id = is_array($respuesta) ? ($respuesta['id_pregunta'] ?? null) : null;

                if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                    continue;
                }

                $id = (int) $id;

                if (in_array($id, $ids, true)) {
                    $validator->errors()->add(
                        "respuestas.{$indice}.id_pregunta",
                        'No puede responder la misma pregunta más de una vez.',
                    );
                }

                $ids[] = $id;
            }
        });
    }
}
