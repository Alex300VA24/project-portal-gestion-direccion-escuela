<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Modules\InvestigacionFormativa\Models\Grupo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class GuardarEvaluacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grupo = $this->route('grupo');

        return $grupo instanceof Grupo
            && $this->user()?->can('evaluar', $grupo) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'criterios' => ['required', 'array', 'min:1', 'max:30'],
            'criterios.*.id_criterio' => ['required', 'integer', 'min:1'],
            'criterios.*.nivel' => ['required', 'integer', 'between:1,4'],
            'comentario' => ['nullable', 'string', 'max:4000'],
            'sin_observaciones' => ['required', 'boolean'],
            'observaciones' => ['nullable', 'array', 'max:20'],
            'observaciones.*' => ['string', 'max:2000'],
            'version' => ['required', 'integer', 'min:0'],
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

        if (isset($datos['observaciones']) && is_array($datos['observaciones'])) {
            $observaciones = [];

            foreach ($datos['observaciones'] as $observacion) {
                if (! is_string($observacion)) {
                    $observaciones[] = $observacion;

                    continue;
                }

                $observacion = trim($observacion);
                $observaciones[] = $observacion;
            }

            $datos['observaciones'] = $observaciones;
        }

        $this->replace($datos);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $criterios = $this->input('criterios');

            if (is_array($criterios)) {
                $ids = [];

                foreach ($criterios as $indice => $criterio) {
                    $id = is_array($criterio) ? ($criterio['id_criterio'] ?? null) : null;

                    if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                        continue;
                    }

                    $id = (int) $id;

                    if (in_array($id, $ids, true)) {
                        $validator->errors()->add(
                            "criterios.{$indice}.id_criterio",
                            'Cada criterio debe evaluarse una sola vez.',
                        );
                    }

                    $ids[] = $id;
                }
            }

            $observaciones = $this->input('observaciones', []);
            $observaciones = is_array($observaciones) ? $observaciones : [];
            $tieneObservaciones = false;

            foreach ($observaciones as $observacion) {
                if (is_string($observacion) && $observacion !== '') {
                    $tieneObservaciones = true;
                    break;
                }
            }

            if ($this->boolean('sin_observaciones')) {
                if ($tieneObservaciones) {
                    $validator->errors()->add(
                        'observaciones',
                        'No registre observaciones si marcó que la entrega no tiene observaciones.',
                    );
                }
            } elseif (! $tieneObservaciones) {
                $validator->errors()->add(
                    'observaciones',
                    'Registre al menos una observación o marque que la entrega no tiene observaciones.',
                );
            }
        });
    }
}
