<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Services\CatalogoService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Closure;
use Illuminate\Validation\Rule;

final class RegistrarProyectoRequest extends TesisFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('alumno') === true
            && app(IdentidadService::class)->actual($this->user())?->idEstudiante !== null;
    }

    protected function prepareForValidation(): void
    {
        // Filas vacías del formulario dinámico no se validan ni se guardan.
        $limpiar = fn (string $clave, string $campo) => array_values(array_filter(
            (array) $this->input($clave, []),
            fn ($fila) => is_array($fila) && trim((string) ($fila[$campo] ?? '')) !== '',
        ));

        $this->merge([
            'colaboradores' => $limpiar('colaboradores', 'nombres'),
            'actividades' => $limpiar('actividades', 'descripcion'),
            'recursos' => $limpiar('recursos', 'descripcion'),
            'partidas' => $limpiar('partidas', 'descripcion'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxPalabras = function (string $atributo, mixed $valor, Closure $fail): void {
            // Un valor que no es texto ya lo rechaza la regla «string»: aquí no se convierte (evita un error 500).
            if (is_string($valor) && count(preg_split('/\s+/u', trim($valor), -1, PREG_SPLIT_NO_EMPTY)) > Parametros::TITULO_MAX_PALABRAS) {
                $fail('El título no debe superar '.Parametros::TITULO_MAX_PALABRAS.' palabras.');
            }
        };

        return [
            'titulo' => ['required', 'string', 'min:10', 'max:300', $maxPalabras],
            'resumen' => ['required', 'string', 'min:20', 'max:3000'],
            'linea' => ['required', 'string', Rule::in(app(CatalogoService::class)->codigosLineas())],
            'enfoque' => ['required', Rule::in(Parametros::ENFOQUES)],
            'tipo_investigacion' => ['required', Rule::in(Parametros::TIPOS_INVESTIGACION)],
            'diseno' => ['required', Rule::in(Parametros::DISENOS)],
            'lugar_ejecucion' => ['required', 'string', 'max:200'],
            'institucion' => ['required', 'string', 'max:200'],
            'duracion_meses' => ['required', 'integer', 'between:1,36'],
            'financiamiento' => ['required', Rule::in(Parametros::FINANCIAMIENTOS)],
            'coautor_codigo' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'documento' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.Parametros::ARCHIVO_MAX_KB],

            'colaboradores' => ['array', 'max:10'],
            'colaboradores.*.nombres' => ['required', 'string', 'max:200'],
            'colaboradores.*.institucion' => ['nullable', 'string', 'max:180'],
            'colaboradores.*.rol' => ['nullable', 'string', 'max:100'],

            'actividades' => ['required', 'array', 'min:1', 'max:20'],
            'actividades.*.descripcion' => ['required', 'string', 'max:300'],
            'actividades.*.fecha_inicio' => ['required', 'date'],
            'actividades.*.fecha_fin' => ['required', 'date', 'after_or_equal:actividades.*.fecha_inicio'],
            'actividades.*.horas' => ['nullable', 'integer', 'between:1,60'],

            'recursos' => ['array', 'max:20'],
            'recursos.*.tipo' => ['required', Rule::in(Parametros::TIPOS_RECURSO)],
            'recursos.*.descripcion' => ['required', 'string', 'max:300'],
            'recursos.*.cantidad' => ['nullable', 'numeric', 'gt:0', 'max:99999'],

            'partidas' => ['array', 'max:30'],
            'partidas.*.codigo' => ['nullable', 'string', 'max:20', 'regex:/^[0-9. ]+$/'],
            'partidas.*.descripcion' => ['required', 'string', 'max:200'],
            'partidas.*.monto' => ['required', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'actividades.required' => 'Agrega al menos una actividad al cronograma.',
            'actividades.min' => 'Agrega al menos una actividad al cronograma.',
            'actividades.*.fecha_fin.after_or_equal' => 'El término de cada actividad debe ser igual o posterior a su inicio.',
            'documento.required' => 'Adjunta el documento F1 en PDF.',
            'coautor_codigo.regex' => 'El código del coautor solo admite números, letras y guiones.',
            'linea.in' => 'Elige una línea de investigación de la lista.',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'titulo' => 'título',
            'resumen' => 'resumen',
            'linea' => 'línea de investigación',
            'enfoque' => 'enfoque',
            'tipo_investigacion' => 'tipo por el fin que persigue',
            'diseno' => 'diseño de contrastación',
            'lugar_ejecucion' => 'lugar de ejecución',
            'institucion' => 'institución',
            'duracion_meses' => 'duración en meses',
            'financiamiento' => 'financiamiento',
            'coautor_codigo' => 'código del coautor',
            'documento' => 'documento F1 (PDF)',
            'actividades' => 'cronograma',
            'actividades.*.descripcion' => 'actividad del cronograma',
            'actividades.*.fecha_inicio' => 'inicio de la actividad',
            'actividades.*.fecha_fin' => 'término de la actividad',
            'actividades.*.horas' => 'dedicación (horas por semana)',
            'partidas.*.descripcion' => 'concepto de la partida',
            'partidas.*.monto' => 'monto de la partida',
            'partidas.*.codigo' => 'código de partida',
            'recursos.*.descripcion' => 'descripción del recurso',
            'recursos.*.tipo' => 'tipo de recurso',
            'recursos.*.cantidad' => 'cantidad',
            'colaboradores.*.nombres' => 'nombre del colaborador',
            'colaboradores.*.institucion' => 'institución del colaborador',
            'colaboradores.*.rol' => 'rol del colaborador',
        ];
    }
}
