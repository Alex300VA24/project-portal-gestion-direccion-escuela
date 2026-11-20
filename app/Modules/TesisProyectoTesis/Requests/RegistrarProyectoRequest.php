<?php

namespace App\Modules\TesisProyectoTesis\Requests;

use App\Modules\TesisProyectoTesis\Services\CatalogoService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Support\DuracionCronograma;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registro del proyecto (formato F1 de la Escuela). La duración no la envía el formulario: se calcula del
 * cronograma. Recursos y presupuesto viajan en una sola tabla (tipo, descripción, código del clasificador,
 * unidad, cantidad y valor unitario).
 */
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

        // El código del clasificador se elige como «2.3.1.5.1 · Concepto»: se guarda solo el código.
        $recursos = array_map(function (array $r) {
            $codigo = trim((string) ($r['codigo'] ?? ''));
            $codigo = trim(explode('·', $codigo)[0]);
            if (in_array($codigo, ['-', '—', Parametros::SIN_CODIGO], true)) {
                $codigo = Parametros::SIN_CODIGO;
            }

            return [...$r, 'codigo' => $codigo];
        }, $limpiar('recursos', 'descripcion'));

        $this->merge([
            'colaboradores' => $limpiar('colaboradores', 'nombres'),
            'actividades' => $limpiar('actividades', 'descripcion'),
            'recursos' => $recursos,
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
            'diseno_otro' => ['nullable', 'required_if:diseno,'.Parametros::DISENO_OTRO, 'string', 'max:100'],
            'lugar_ejecucion' => ['required', 'string', 'max:200'],
            'institucion' => ['required', 'string', 'max:200'],
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
            'actividades.*.horas' => ['required', 'integer', 'between:1,60'],

            'recursos' => ['array', 'max:30'],
            'recursos.*.tipo' => ['required', Rule::in(Parametros::TIPOS_RECURSO_F1)],
            'recursos.*.descripcion' => ['required', 'string', 'max:200'],
            'recursos.*.codigo' => ['nullable', 'string', 'max:20', 'regex:/^(–|\d+(\.\d+)*)$/u'],
            'recursos.*.unidad' => ['required', 'string', 'max:40'],
            'recursos.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'recursos.*.costo' => ['required', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /** La duración se calcula del cronograma y no puede superar el máximo. */
    public function after(): array
    {
        return [function (Validator $v): void {
            if ($v->errors()->hasAny(['actividades', 'actividades.*'])) {
                return;
            }
            $meses = DuracionCronograma::meses((array) $this->input('actividades', []));
            if ($meses !== null && $meses > Parametros::DURACION_MAX_MESES) {
                $v->errors()->add('actividades', "El cronograma dura {$meses} meses; el máximo es ".Parametros::DURACION_MAX_MESES.'. Revisa las fechas de inicio y término.');
            }
        }];
    }

    /** Datos validados con la duración calculada y el diseño «Otro» resuelto. */
    public function datosF1(): array
    {
        $d = $this->validated();
        $d['duracion_meses'] = DuracionCronograma::meses($d['actividades']);
        if ($d['diseno'] === Parametros::DISENO_OTRO) {
            $d['diseno'] = Parametros::DISENO_OTRO.': '.trim((string) $d['diseno_otro']);
        }
        unset($d['diseno_otro']);

        return $d;
    }

    /** @return array<string, string|array<string, string>> */
    public function messages(): array
    {
        return array_merge($this->mensajesBase(), [
            'actividades.required' => 'Agrega al menos una actividad al cronograma.',
            'actividades.min' => 'Agrega al menos una actividad al cronograma.',
            'actividades.*.fecha_fin.after_or_equal' => 'El término de cada actividad debe ser igual o posterior a su inicio.',
            'actividades.*.horas.required' => 'Indica la dedicación de cada actividad, en horas por semana.',
            'diseno_otro.required_if' => 'Describe el diseño de contrastación cuando eliges «Otro».',
            'recursos.*.codigo.regex' => 'El código del clasificador debe tener números separados por puntos (por ejemplo, 2.3.1.5.1) o «–» si no tiene código.',
            'recursos.*.unidad.required' => 'Indica la unidad de medida de cada recurso (por ejemplo, Unidad, Millar o Mes).',
            'recursos.*.costo.required' => 'Indica el valor unitario en S/ de cada recurso (0 si es un bien propio).',
            'recursos.*.tipo.in' => 'El tipo de recurso debe ser Bienes o Servicios.',
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
            'diseno_otro' => 'otro diseño',
            'lugar_ejecucion' => 'lugar de ejecución',
            'institucion' => 'institución',
            'financiamiento' => 'financiamiento',
            'coautor_codigo' => 'código del coautor',
            'documento' => 'documento F1 (PDF)',
            'actividades' => 'cronograma',
            'actividades.*.descripcion' => 'actividad del cronograma',
            'actividades.*.fecha_inicio' => 'inicio de la actividad',
            'actividades.*.fecha_fin' => 'término de la actividad',
            'actividades.*.horas' => 'dedicación (horas por semana)',
            'recursos' => 'recursos y presupuesto',
            'recursos.*.tipo' => 'tipo de recurso',
            'recursos.*.descripcion' => 'descripción del recurso',
            'recursos.*.codigo' => 'código del clasificador de gastos',
            'recursos.*.unidad' => 'unidad de medida',
            'recursos.*.cantidad' => 'cantidad',
            'recursos.*.costo' => 'valor unitario (S/)',
            'colaboradores.*.nombres' => 'nombre del colaborador',
            'colaboradores.*.institucion' => 'institución del colaborador',
            'colaboradores.*.rol' => 'rol del colaborador',
        ];
    }
}
