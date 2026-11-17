<?php

namespace App\Modules\InvestigacionFormativa\Requests;

use App\Modules\InvestigacionFormativa\Support\PuntajeRubrica;
use App\Shared\Models\Core\Seccion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class GuardarRubricaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $seccion instanceof Seccion
            && $this->user()?->can('configurar', $seccion) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:1', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:4000'],
            'criterios' => ['required', 'array', 'min:1', 'max:30'],
            'criterios.*.descripcion' => ['required', 'string', 'min:1', 'max:2000'],
            'criterios.*.niveles' => ['required', 'array', 'size:4'],
            'criterios.*.niveles.*.nivel' => ['required', 'integer', 'between:1,4'],
            'criterios.*.niveles.*.descripcion' => ['required', 'string', 'min:1', 'max:2000'],
            'criterios.*.niveles.*.puntaje' => ['required', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $datos = $this->all();

        if (isset($datos['nombre']) && is_string($datos['nombre'])) {
            $datos['nombre'] = trim($datos['nombre']);
        }

        if (array_key_exists('descripcion', $datos)) {
            if (! is_string($datos['descripcion']) || trim($datos['descripcion']) === '') {
                $datos['descripcion'] = null;
            } else {
                $datos['descripcion'] = trim($datos['descripcion']);
            }
        }

        if (isset($datos['criterios']) && is_array($datos['criterios'])) {
            foreach ($datos['criterios'] as $indice => $criterio) {
                if (isset($criterio['descripcion']) && is_string($criterio['descripcion'])) {
                    $datos['criterios'][$indice]['descripcion'] = trim($criterio['descripcion']);
                }

                if (isset($criterio['niveles']) && is_array($criterio['niveles'])) {
                    foreach ($criterio['niveles'] as $posicion => $nivel) {
                        if (isset($nivel['descripcion']) && is_string($nivel['descripcion'])) {
                            $datos['criterios'][$indice]['niveles'][$posicion]['descripcion'] = trim(
                                $nivel['descripcion'],
                            );
                        }
                    }
                }
            }
        }

        $this->replace($datos);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $criterios = $this->input('criterios');

            if (! is_array($criterios)) {
                return;
            }

            foreach ($criterios as $indice => $criterio) {
                $niveles = is_array($criterio['niveles'] ?? null) ? $criterio['niveles'] : [];

                if ($niveles === []) {
                    continue;
                }

                $nivelesDeclarados = array_map(
                    static fn (mixed $nivel): int => (int) ($nivel['nivel'] ?? 0),
                    $niveles,
                );
                sort($nivelesDeclarados);

                if ($nivelesDeclarados !== [1, 2, 3, 4]) {
                    $validator->errors()->add(
                        "criterios.{$indice}.niveles",
                        'Cada criterio debe declarar exactamente los niveles 1, 2, 3 y 4, sin repetición.',
                    );

                    continue;
                }

                $puntajes = PuntajeRubrica::centavosPorNivel($niveles);

                if ($puntajes === null) {
                    $validator->errors()->add(
                        "criterios.{$indice}.niveles",
                        'Los puntajes deben ser números válidos con hasta dos decimales.',
                    );

                    continue;
                }

                if ($puntajes[1] > $puntajes[2]
                    || $puntajes[2] > $puntajes[3]
                    || $puntajes[3] > $puntajes[4]) {
                    $validator->errors()->add(
                        "criterios.{$indice}.niveles",
                        'Los puntajes deben ser no decrecientes desde el nivel 1 al nivel 4.',
                    );

                    continue;
                }

                if ($puntajes[4] <= 0) {
                    $validator->errors()->add(
                        "criterios.{$indice}.niveles",
                        'El puntaje máximo del criterio debe ser mayor a cero.',
                    );
                }
            }
        });
    }
}
